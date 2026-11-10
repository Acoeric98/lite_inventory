<?php
declare(strict_types=1);
session_start(['name' => (require dirname(__DIR__) . '/config.php')['session_name'], 'cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
$config = require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/src/app.php';
$pdo = db($config);
// Refresh roles from the database so changes apply to existing sessions.
if (user()) {
    $stmt = $pdo->prepare('SELECT id, username, role, theme_mode, custom_theme FROM users WHERE id=? AND deleted_at IS NULL');
    $stmt->execute([user()['id']]);
    $_SESSION['user'] = $stmt->fetch() ?: null;
}
$page = (string)($_GET['page'] ?? 'stock');
if (!in_array($page, ['stock', 'admin', 'tickets'], true)) { http_response_code(404); exit('Az oldal nem található.'); }
if ($page !== 'stock' && !user()) redirect();
if ($page === 'admin' && !is_supervisor()) { http_response_code(403); exit('Ehhez az oldalhoz nincs jogosultságod.'); }
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
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND deleted_at IS NULL');
            $stmt->execute([trim((string)$_POST['username'])]); $found = $stmt->fetch();
            if (!$found || !password_verify((string)$_POST['password'], $found['password_hash'])) throw new InvalidArgumentException('Hibás felhasználónév vagy jelszó.');
            session_regenerate_id(true); unset($found['password_hash']); $_SESSION['user'] = $found; redirect();
        }
        if ($action === 'logout') { $_SESSION = []; session_destroy(); redirect(); }
        if (!user()) redirect();
        if ($action === 'save_theme') {
            $mode = (string)($_POST['theme_mode'] ?? '');
            if (!in_array($mode, ['light','dark','system','custom'], true)) throw new InvalidArgumentException('Hibás megjelenítési mód.');
            $colors = null;
            if ($mode === 'custom') {
                $colors = validate_theme_colors(is_array($_POST['colors'] ?? null) ? $_POST['colors'] : []);
            }
            // Switching modes preserves the user's existing custom palette.
            if ($colors !== null) $pdo->prepare('UPDATE users SET theme_mode=?,custom_theme=? WHERE id=?')->execute([$mode,json_encode($colors),user()['id']]);
            else $pdo->prepare('UPDATE users SET theme_mode=? WHERE id=?')->execute([$mode,user()['id']]);
            flash('Megjelenítés elmentve.');
            redirect('index.php?page='.$page.(!empty($_GET['ticket']) ? '&ticket='.(int)$_GET['ticket'] : '').(!empty($_GET['inventory']) ? '&inventory='.(int)$_GET['inventory'] : ''));
        }
        require __DIR__ . '/../src/management_actions.php';
        if ($action === 'create_inventory' && is_admin()) {
            $stmt = $pdo->prepare('INSERT INTO inventories(name,location) VALUES(?,?)');
            $stmt->execute([valid_text((string)$_POST['name']), trim((string)$_POST['location'])]); flash('Raktár létrehozva.');
        } elseif ($action === 'delete_inventory' && is_admin()) {
            $stmt = $pdo->prepare('DELETE FROM inventories WHERE id=?'); $stmt->execute([(int)$_POST['inventory_id']]); flash('Raktár és a hozzá tartozó adatok törölve.');
        } elseif ($action === 'save_item' && can_write()) {
            $inventoryId = (int)$_POST['inventory_id']; require_inventory_access($pdo, $inventoryId);
            $qty = filter_var($_POST['quantity'], FILTER_VALIDATE_INT); if ($qty === false || $qty < 0) throw new InvalidArgumentException('A mennyiség nem lehet negatív.');
            $location = trim((string)($_POST['location'] ?? ''));
            $location = valid_text($location === '' ? 'allokálatlan' : $location);
            $stmt = $pdo->prepare('INSERT INTO items(inventory_id,sku,name,quantity,unit,note,updated_by,location) VALUES(?,?,?,?,?,?,?,?) ON CONFLICT(inventory_id,sku) DO UPDATE SET name=excluded.name,quantity=excluded.quantity,unit=excluded.unit,location=excluded.location,note=excluded.note,updated_by=excluded.updated_by,updated_at=CURRENT_TIMESTAMP');
            $stmt->execute([$inventoryId, valid_text((string)$_POST['sku'], 60), valid_text((string)$_POST['name']),$qty,valid_text((string)$_POST['unit'],20),trim((string)$_POST['note']),user()['id'],$location]); flash('Készletadat mentve.');
        } elseif ($action === 'delete_item' && is_admin()) {
            $inventoryId=(int)$_POST['inventory_id']; require_inventory_access($pdo,$inventoryId); $pdo->prepare('DELETE FROM items WHERE id=? AND inventory_id=?')->execute([(int)$_POST['item_id'],$inventoryId]); flash('Tétel törölve.');
        } else throw new InvalidArgumentException('Nincs jogosultságod ehhez a művelethez.');
    } catch (Throwable $error) { flash($error instanceof PDOException && str_contains($error->getMessage(),'UNIQUE') ? 'Ez a név vagy azonosító már létezik.' : $error->getMessage(), 'error'); }
    if ($page !== 'stock') redirect('index.php?page='.$page.($page === 'tickets' && !empty($_POST['ticket_id']) ? '&ticket='.(int)$_POST['ticket_id'] : ''));
    redirect('index.php' . (!empty($_POST['inventory_id']) ? '?inventory='.(int)$_POST['inventory_id'] : ''));
}

$selected = (int)($_GET['inventory'] ?? 0);
$inventories=[]; $items=[]; $users=[]; $access=[];
if (user() && $page === 'stock') {
    if (is_admin()) $inventories=$pdo->query('SELECT * FROM inventories ORDER BY name')->fetchAll();
    else { $s=$pdo->prepare('SELECT i.* FROM inventories i JOIN inventory_access a ON a.inventory_id=i.id WHERE a.user_id=? ORDER BY i.name'); $s->execute([user()['id']]); $inventories=$s->fetchAll(); }
    if (!$selected && $inventories) $selected=(int)$inventories[0]['id'];
    if ($selected) { require_inventory_access($pdo,$selected); $s=$pdo->prepare('SELECT items.*,users.username FROM items LEFT JOIN users ON users.id=items.updated_by WHERE inventory_id=? ORDER BY items.name'); $s->execute([$selected]); $items=$s->fetchAll(); }

}
require __DIR__ . '/../src/management_data.php';
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$themeMode = user()['theme_mode'] ?? 'system';
if (!in_array($themeMode, ['light','dark','system','custom'], true)) $themeMode = 'system';
$themeColors = saved_theme_colors(user()['custom_theme'] ?? null);
require __DIR__ . '/view.php';
