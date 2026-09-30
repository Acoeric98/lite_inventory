<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/app.php';
$path = sys_get_temp_dir() . '/lite_inventory_test_' . bin2hex(random_bytes(4)) . '.sqlite';
$pdo = db(['database' => $path]);
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
foreach (['users','inventories','inventory_access','items'] as $table) {
    if (!in_array($table, $tables, true)) throw new RuntimeException("Missing table: $table");
}
$pdo->exec("INSERT INTO users(username,password_hash,role) VALUES('admin','hash',3)");
$pdo->exec("INSERT INTO inventories(name,location) VALUES('Teszt','Budapest')");
$pdo->exec("INSERT INTO items(inventory_id,sku,name,quantity) VALUES(1,'SKU-1','Doboz',5)");
if ((int)$pdo->query('SELECT quantity FROM items')->fetchColumn() !== 5) throw new RuntimeException('Item insert failed');
unset($pdo); @unlink($path); @unlink($path . '-wal'); @unlink($path . '-shm');
echo "Smoke test OK\n";
