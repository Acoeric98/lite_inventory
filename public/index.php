<?php
declare(strict_types=1);
session_start(['name' => (require dirname(__DIR__) . '/config.php')['session_name'], 'cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$config = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/src/app.php';
$pdo = db($config);
$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'dashboard');
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        if ($action === 'setup' && $userCount === 0) {
            $username = valid_text((string)$_POST['username'], 50);
            if (strlen((string)$_POST['password']) < 10) throw new InvalidArgumentException('A jelszó legalább 10 karakter legyen.');
            $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,3)');
            $stmt->execute([$username, password_hash((string)$_POST['password'], PASSWORD_DEFAULT)]);
            flash('Az admin fiók elkészült. Most jelentkezz be.'); redirect();
        }
        if ($action === 'login') {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([trim((string)$_POST['username'])]); $found = $stmt->fetch();
            if (!$found || !password_verify((string)$_POST['password'], $found['password_hash'])) throw new InvalidArgumentException('Hibás felhasználónév vagy jelszó.');
            session_regenerate_id(true); unset($found['password_hash']); $_SESSION['user'] = $found; redirect();
        }
        if ($action === 'logout') { $_SESSION = []; session_destroy(); redirect(); }
        if (!user()) redirect();
        if ($action === 'create_inventory' && is_admin()) {
            $stmt = $pdo->prepare('INSERT INTO inventories(name,location) VALUES(?,?)');
            $stmt->execute([valid_text((string)$_POST['name']), trim((string)$_POST['location'])]); flash('Raktár létrehozva.');
        } elseif ($action === 'delete_inventory' && is_admin()) {
            $stmt = $pdo->prepare('DELETE FROM inventories WHERE id=?'); $stmt->execute([(int)$_POST['inventory_id']]); flash('Raktár és a hozzá tartozó adatok törölve.');
        } elseif ($action === 'create_user' && is_supervisor()) {
            $role = (int)$_POST['role']; $max = is_admin() ? ROLE_ADMIN : ROLE_EDITOR;
            if ($role < ROLE_VIEWER || $role > $max) throw new InvalidArgumentException('Ezt a jogosultságot nem adhatod meg.');
            if (strlen((string)$_POST['password']) < 10) throw new InvalidArgumentException('A jelszó legalább 10 karakter legyen.');
            $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)');
            $stmt->execute([valid_text((string)$_POST['username'], 50), password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $role]); flash('Felhasználó létrehozva.');
        } elseif ($action === 'save_access' && is_supervisor()) {
            $target = (int)$_POST['user_id']; $inventoryId = (int)$_POST['inventory_id'];
            require_inventory_access($pdo, $inventoryId);
            if ($target === (int)user()['id']) throw new InvalidArgumentException('A saját hozzáférésedet nem módosíthatod.');
            if (isset($_POST['allowed'])) $pdo->prepare('INSERT OR IGNORE INTO inventory_access(user_id,inventory_id) VALUES(?,?)')->execute([$target,$inventoryId]);
            else $pdo->prepare('DELETE FROM inventory_access WHERE user_id=? AND inventory_id=?')->execute([$target,$inventoryId]);
            flash('Raktár-hozzáférés frissítve.');
        } elseif ($action === 'save_item' && can_write()) {
            $inventoryId = (int)$_POST['inventory_id']; require_inventory_access($pdo, $inventoryId);
            $qty = filter_var($_POST['quantity'], FILTER_VALIDATE_INT); if ($qty === false || $qty < 0) throw new InvalidArgumentException('A mennyiség nem lehet negatív.');
            $stmt = $pdo->prepare('INSERT INTO items(inventory_id,sku,name,quantity,unit,note,updated_by) VALUES(?,?,?,?,?,?,?) ON CONFLICT(inventory_id,sku) DO UPDATE SET name=excluded.name,quantity=excluded.quantity,unit=excluded.unit,note=excluded.note,updated_by=excluded.updated_by,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute([$inventoryId, valid_text((string)$_POST['sku'], 60), valid_text((string)$_POST['name']),$qty,valid_text((string)$_POST['unit'],20),trim((string)$_POST['note']),user()['id']]); flash('Készletadat mentve.');
        } elseif ($action === 'delete_item' && is_admin()) {
            $inventoryId=(int)$_POST['inventory_id']; require_inventory_access($pdo,$inventoryId); $pdo->prepare('DELETE FROM items WHERE id=? AND inventory_id=?')->execute([(int)$_POST['item_id'],$inventoryId]); flash('Tétel törölve.');
        } else throw new InvalidArgumentException('Nincs jogosultságod ehhez a művelethez.');
    } catch (Throwable $error) { flash($error instanceof PDOException && str_contains($error->getMessage(),'UNIQUE') ? 'Ez a név vagy azonosító már létezik.' : $error->getMessage(), 'error'); }
    redirect('index.php' . (!empty($_POST['inventory_id']) ? '?inventory='.(int)$_POST['inventory_id'] : ''));
}

$selected = (int)($_GET['inventory'] ?? 0);
$inventories=[]; $items=[]; $users=[]; $access=[];
if (user()) {
    if (is_admin()) $inventories=$pdo->query('SELECT * FROM inventories ORDER BY name')->fetchAll();
    else { $s=$pdo->prepare('SELECT i.* FROM inventories i JOIN inventory_access a ON a.inventory_id=i.id WHERE a.user_id=? ORDER BY i.name'); $s->execute([user()['id']]); $inventories=$s->fetchAll(); }
    if (!$selected && $inventories) $selected=(int)$inventories[0]['id'];
    if ($selected) { require_inventory_access($pdo,$selected); $s=$pdo->prepare('SELECT items.*,users.username FROM items LEFT JOIN users ON users.id=items.updated_by WHERE inventory_id=? ORDER BY items.name'); $s->execute([$selected]); $items=$s->fetchAll(); }
    if (is_supervisor()) {
        $users=$pdo->query('SELECT id,username,role FROM users ORDER BY username')->fetchAll();
        foreach ($pdo->query('SELECT user_id,inventory_id FROM inventory_access') as $row) {
            $access[(int)$row['user_id']][(int)$row['inventory_id']] = true;
        }
    }
}
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
require __DIR__ . '/view.php';
