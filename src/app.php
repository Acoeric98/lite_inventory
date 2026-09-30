<?php
declare(strict_types=1);

const ROLE_VIEWER = 0;
const ROLE_EDITOR = 1;
const ROLE_SUPERVISOR = 2;
const ROLE_ADMIN = 3;

function role_labels(): array
{
    return [0 => 'Csak olvasás', 1 => 'Írás és olvasás', 2 => 'Supervisor', 3 => 'Admin'];
}

function db(array $config): PDO
{
    $directory = dirname($config['database']);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Az adatbázis könyvtára nem hozható létre.');
    }
    $pdo = new PDO('sqlite:' . $config['database'], null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 username TEXT NOT NULL UNIQUE COLLATE NOCASE,
 password_hash TEXT NOT NULL,
 role INTEGER NOT NULL DEFAULT 0 CHECK(role BETWEEN 0 AND 3),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS inventories (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL UNIQUE COLLATE NOCASE,
 location TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS inventory_access (
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 inventory_id INTEGER NOT NULL REFERENCES inventories(id) ON DELETE CASCADE,
 PRIMARY KEY (user_id, inventory_id)
);
CREATE TABLE IF NOT EXISTS items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 inventory_id INTEGER NOT NULL REFERENCES inventories(id) ON DELETE CASCADE,
 sku TEXT NOT NULL,
 name TEXT NOT NULL,
 quantity INTEGER NOT NULL DEFAULT 0 CHECK(quantity >= 0),
 unit TEXT NOT NULL DEFAULT 'db',
 note TEXT NOT NULL DEFAULT '',
 updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(inventory_id, sku)
);
CREATE INDEX IF NOT EXISTS idx_items_inventory ON items(inventory_id);
SQL);
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $url = 'index.php'): never { header('Location: ' . $url); exit; }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function require_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf'])) {
        http_response_code(419); exit('Lejárt vagy hibás munkamenet. Frissítsd az oldalt.');
    }
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (user()['role'] ?? -1) === ROLE_ADMIN; }
function is_supervisor(): bool { return (user()['role'] ?? -1) >= ROLE_SUPERVISOR; }
function can_write(): bool { return (user()['role'] ?? -1) >= ROLE_EDITOR; }

function can_access_inventory(PDO $pdo, int $inventoryId): bool
{
    if (is_admin()) return true;
    $stmt = $pdo->prepare('SELECT 1 FROM inventory_access WHERE user_id = ? AND inventory_id = ?');
    $stmt->execute([user()['id'], $inventoryId]);
    return (bool)$stmt->fetchColumn();
}

function require_inventory_access(PDO $pdo, int $inventoryId): void
{
    if (!$inventoryId || !can_access_inventory($pdo, $inventoryId)) {
        http_response_code(403); exit('Ehhez a raktárhoz nincs jogosultságod.');
    }
}

function valid_text(string $value, int $max = 120): string
{
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('A kötelező mező hibás vagy túl hosszú.');
    return $value;
}
