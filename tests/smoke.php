<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';
$path = sys_get_temp_dir() . '/lite_inventory_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$pdo = db(['database' => $path]);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['users','inventories','inventory_access','items','tickets','ticket_comments'] as $table) {
    if (!in_array($table, $tables, true)) throw new RuntimeException("Missing table: $table");
}
$pdo->exec("INSERT INTO users(username,password_hash,role) VALUES('admin','hash',3)");
$pdo->exec("INSERT INTO inventories(name,location) VALUES('Teszt','Budapest')");
$pdo->exec("INSERT INTO items(inventory_id,sku,name,quantity) VALUES(1,'SKU-1','Doboz',5)");
if ((int)$pdo->query('SELECT quantity FROM items')->fetchColumn() !== 5) throw new RuntimeException('Item insert failed');
if ($pdo->query('SELECT location FROM items')->fetchColumn() !== 'allokálatlan') throw new RuntimeException('Missing default location');
$pdo->exec("UPDATE items SET location='A-02 polc' WHERE sku='SKU-1'");
migrate($pdo);
if ($pdo->query('SELECT location FROM items')->fetchColumn() !== 'A-02 polc') throw new RuntimeException('Migration changed assigned location');
// Simulate a database created before item locations were introduced.
$pdo->exec('ALTER TABLE items DROP COLUMN location');
migrate($pdo);
if ($pdo->query('SELECT location FROM items')->fetchColumn() !== 'allokálatlan') throw new RuntimeException('Existing item was not migrated');
if ((int)$pdo->query('SELECT quantity FROM items')->fetchColumn() !== 5) throw new RuntimeException('Migration changed stock');
migrate($pdo);
if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Database integrity check failed');
if ($pdo->query('SELECT theme_mode FROM users')->fetchColumn() !== 'system') throw new RuntimeException('Wrong default theme');
$pdo->exec('ALTER TABLE users DROP COLUMN theme_mode; ALTER TABLE users DROP COLUMN custom_theme');
migrate($pdo);
if ($pdo->query('SELECT theme_mode FROM users')->fetchColumn() !== 'system') throw new RuntimeException('Existing user theme migration failed');
$pdo->exec("UPDATE users SET theme_mode='custom',custom_theme='{}'");
migrate($pdo);
if ($pdo->query('SELECT theme_mode FROM users')->fetchColumn() !== 'custom') throw new RuntimeException('Theme was overwritten');
if (theme_foreground('#000000') !== '#ffffff' || theme_foreground('#ffffff') !== '#000000') throw new RuntimeException('Invalid contrast color');
$pdo->exec('DROP INDEX idx_users_manager; ALTER TABLE users DROP COLUMN manager_id; ALTER TABLE users DROP COLUMN deleted_at');
migrate($pdo);
if ($pdo->query('SELECT manager_id FROM users')->fetchColumn() !== null) throw new RuntimeException('Existing user manager default failed');
if ($pdo->query('SELECT deleted_at FROM users')->fetchColumn() !== null) throw new RuntimeException('Existing user deletion default failed');
if ($pdo->query('PRAGMA foreign_key_check')->fetchAll() !== []) throw new RuntimeException('Foreign key check failed');
unset($pdo); @unlink($path); @unlink($path . '-wal'); @unlink($path . '-shm');
echo "Smoke test OK\n";
