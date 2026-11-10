"""Integration checks; run with PHP_BIN=/path/to/php python3 tests/management_http.py."""
import http.cookiejar
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]


class Client:
    def __init__(self, base):
        self.base = base
        self.page = ''
        self.opener = urllib.request.build_opener(
            urllib.request.ProxyHandler({}),
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path='', data=None, expected=200):
        try:
            response = self.opener.open(self.base + path, None if data is None else urllib.parse.urlencode(data).encode())
        except urllib.error.HTTPError as error:
            response = error
        self.page = response.read().decode()
        assert response.code == expected, (path, response.code, self.page[:200])
        return self.page

    def post(self, path='', expected=200, **fields):
        fields['csrf'] = re.search(r'name="csrf" value="([^"]+)"', self.page).group(1)
        return self.request(path, fields, expected)

    def login(self, username, password):
        self.request()
        self.post(action='login', username=username, password=password)


def run():
    with tempfile.TemporaryDirectory() as tmp:
        database = str(Path(tmp) / 'test.sqlite')
        env = dict(os.environ, INVENTORY_DB=database)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}/index.php'
        with open(Path(tmp) / 'server.log', 'w+') as log:
            server = subprocess.Popen([os.environ.get('PHP_BIN', 'php'), '-S', f'127.0.0.1:{port}', '-t', 'public'], cwd=ROOT, env=env, stdout=log, stderr=log)
            try:
                admin = Client(base)
                for attempt in range(50):
                    try:
                        admin.request()
                        break
                    except OSError:
                        if server.poll() is not None:
                            log.seek(0)
                            raise RuntimeError(log.read())
                        time.sleep(.1)
                password = secrets.token_urlsafe(24)
                admin.post(action='setup', username='admin', password=password)
                admin.login('admin', password)
                assert 'Adminisztráció' in admin.page
                assert 'Új felhasználó' not in admin.page
                admin.request('?page=admin')
                assert 'Új felhasználó' in admin.page
                for username, role in [('supervisor', 2), ('viewer', 0), ('editor', 1), ('other', 0)]:
                    admin.post('?page=admin', action='create_user', username=username, password=password, role=role)
                with sqlite3.connect(database) as db:
                    ids = dict(db.execute('SELECT username,id FROM users'))
                    db.execute("INSERT INTO inventories(name) VALUES('Engedélyezett'),('Tiltott')")
                    db.execute('INSERT INTO inventory_access(user_id,inventory_id) VALUES(?,1)', (ids['supervisor'],))
                clients = {name: Client(base) for name in ['viewer','editor','supervisor','other']}
                for name, client in clients.items():
                    client.login(name, password)
                for name in ['viewer', 'editor']:
                    client = clients[name]
                    assert 'Adminisztráció' not in client.page
                    client.request('?page=admin', expected=403)
                    client.request('?page=tickets')
                    client.post('?page=tickets', action='save_role', user_id=ids['viewer'], role=3, expected=403)
                supervisor = clients['supervisor']
                supervisor.request('?page=admin')
                assert 'Engedélyezett' in supervisor.page and 'Tiltott' not in supervisor.page
                supervisor.post('?page=admin', action='save_access', user_id=ids['viewer'], inventory_id=2, allowed='on', expected=403)
                supervisor.request('?page=admin')
                supervisor.post('?page=admin', action='save_role', user_id=ids['viewer'], role=3, expected=403)
                supervisor.request('?page=admin')
                supervisor.post('?page=admin', action='create_user', username='escalation', password=password, role=3)
                assert 'Ezt a jogosultságot nem adhatod meg' in supervisor.page
                supervisor.post('?page=admin', action='save_access', user_id=ids['viewer'], inventory_id=1, allowed='on')
                reporter = clients['viewer']
                reporter.request('?page=tickets')
                for email, category in [('invalid', 'bug'), ('a@example.com', 'invalid')]:
                    reporter.post('?page=tickets', action='create_ticket', email=email, category=category, subject='Elutasítandó', body='Leírás')
                    with sqlite3.connect(database) as db:
                        assert db.execute('SELECT COUNT(*) FROM tickets').fetchone()[0] == 0
                for category in ['bug','feature','question']:
                    reporter.post('?page=tickets', action='create_ticket', email='reporter@example.com', category=category, subject='Teszt <script>', body='Leírás <b>')
                    assert '&lt;script&gt;' in reporter.page and '&lt;b&gt;' in reporter.page
                    assert '<script>' not in reporter.page
                reporter.request('?page=tickets&ticket=1')
                reporter.post('?page=tickets&ticket=1', action='comment_ticket', ticket_id=1, body='Beküldő válasza')
                other = clients['other']
                other.request('?page=tickets')
                assert 'Teszt &lt;script&gt;' not in other.page
                other.request('?page=tickets&ticket=1', expected=404)
                other.request('?page=tickets')
                other.post('?page=tickets', action='comment_ticket', ticket_id=1, body='Tiltott', expected=404)
                supervisor.request('?page=tickets&ticket=1')
                assert 'Beküldő válasza' in supervisor.page and 'reporter@example.com' in supervisor.page
                assert '3 aktív bejelentés' in supervisor.page
                supervisor.post('?page=tickets&ticket=1', action='comment_ticket', ticket_id=1, body='Supervisor válasza')
                reporter.request('?page=tickets&ticket=1')
                assert 'Supervisor válasza' in reporter.page
                reporter.post('?page=tickets&ticket=1', action='ticket_status', ticket_id=1, status='closed', expected=403)
                supervisor.post('?page=tickets&ticket=1', action='ticket_status', ticket_id=1, status='closed')
                assert '2 aktív bejelentés' in supervisor.page
                reporter.request('?page=tickets&ticket=1')
                reporter.post('?page=tickets&ticket=1', action='comment_ticket', ticket_id=1, body='Lezárás után')
                assert 'lezárt bejelentéshez nem lehet hozzászólni' in reporter.page
                supervisor.post('?page=tickets&ticket=1', action='ticket_status', ticket_id=1, status='open')
                assert '3 aktív bejelentés' in supervisor.page
                admin.request('?page=admin')
                admin.post('?page=admin', action='save_role', user_id=ids['supervisor'], role=0)
                supervisor.request('?page=admin', expected=403)
                supervisor.request('?page=tickets&ticket=1', expected=404)
                admin.request('?page=tickets&ticket=1')
                admin.request('?page=tickets&ticket=1', {'action':'comment_ticket','ticket_id':'1','body':'CSRF nélkül'}, expected=419)
                with sqlite3.connect(database) as db:
                    assert db.execute('SELECT COUNT(*) FROM ticket_comments').fetchone()[0] == 2
                    assert db.execute('SELECT role FROM users WHERE id=?', (ids['viewer'],)).fetchone()[0] == 0
                    assert db.execute('SELECT COUNT(*) FROM inventory_access WHERE user_id=? AND inventory_id=2', (ids['viewer'],)).fetchone()[0] == 0
                assert Client(base).request('?page=admin').find('Belépés a raktárba') >= 0
                print('Management HTTP checks OK: roles, session refresh, access scope, tickets, email, categories, comments, status, notification, escaping, CSRF')
            finally:
                server.terminate()
                server.wait()


if __name__ == '__main__':
    run()
