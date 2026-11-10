<?php
declare(strict_types=1);

$adminActions = ['create_user', 'save_access', 'save_role'];
$ticketActions = ['create_ticket', 'comment_ticket', 'ticket_status'];
if (in_array($action, $adminActions, true)) {
    if ($page !== 'admin' || !is_supervisor()) { http_response_code(403); exit('Nincs jogosultságod ehhez a művelethez.'); }
    if ($action === 'create_user') {
        $role = filter_var($_POST['role'] ?? null, FILTER_VALIDATE_INT);
        if ($role === false || $role < 0 || $role > (is_admin() ? 3 : 1)) throw new InvalidArgumentException('Ezt a jogosultságot nem adhatod meg.');
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 10) throw new InvalidArgumentException('A jelszó legalább 10 karakter legyen.');
        $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,role) VALUES(?,?,?)');
        $stmt->execute([valid_text((string)($_POST['username'] ?? ''), 50), password_hash($password, PASSWORD_DEFAULT), $role]);
        flash('Felhasználó létrehozva.');
    } else {
        $target = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id=?'); $stmt->execute([$target]); $targetRole = $stmt->fetchColumn();
        if ($targetRole === false || $target === (int)user()['id'] || (!is_admin() && (int)$targetRole > ROLE_EDITOR)) {
            throw new InvalidArgumentException('Ezt a felhasználót nem módosíthatod.');
        }
        if ($action === 'save_role') {
            if (!is_admin()) { http_response_code(403); exit('Csak admin módosíthat szerepkört.'); }
            $role = filter_var($_POST['role'] ?? null, FILTER_VALIDATE_INT);
            if ($role === false || $role < 0 || $role > 3) throw new InvalidArgumentException('Hibás szerepkör.');
            $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $target]);
            flash('Szerepkör módosítva.');
        } else {
            $inventoryId = (int)($_POST['inventory_id'] ?? 0);
            require_inventory_access($pdo, $inventoryId);
            $stmt = $pdo->prepare('SELECT 1 FROM inventories WHERE id=?'); $stmt->execute([$inventoryId]);
            if (!$stmt->fetchColumn()) throw new InvalidArgumentException('A raktár nem található.');
            if ((int)$targetRole === ROLE_ADMIN) throw new InvalidArgumentException('Az admin minden raktárhoz hozzáfér.');
            if (isset($_POST['allowed'])) $pdo->prepare('INSERT OR IGNORE INTO inventory_access(user_id,inventory_id) VALUES(?,?)')->execute([$target,$inventoryId]);
            else $pdo->prepare('DELETE FROM inventory_access WHERE user_id=? AND inventory_id=?')->execute([$target,$inventoryId]);
            flash('Raktár-hozzáférés frissítve.');
        }
    }
    redirect('index.php?page=admin');
}
if (in_array($action, $ticketActions, true)) {
    if ($page !== 'tickets') { http_response_code(403); exit('Hibás művelet.'); }
    if ($action === 'create_ticket') {
        $email = trim((string)($_POST['email'] ?? ''));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Adj meg szintaktikailag helyes email-címet.');
        $category = (string)($_POST['category'] ?? '');
        if (!array_key_exists($category, ticket_categories())) throw new InvalidArgumentException('Válassz bejelentéstípust.');
        $subject = valid_text((string)($_POST['subject'] ?? ''), 160);
        $body = valid_text((string)($_POST['body'] ?? ''), 10000);
        $pdo->prepare('INSERT INTO tickets(created_by,email,category,subject,body) VALUES(?,?,?,?,?)')->execute([user()['id'],$email,$category,$subject,$body]);
        $ticketId = (int)$pdo->lastInsertId();
        flash('Bejelentés elküldve. A válaszokat itt találod; emailt nem küldünk.');
    } else {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $ticket = require_ticket($pdo, $ticketId);
        if ($action === 'comment_ticket') {
            if ($ticket['status'] !== 'open') throw new InvalidArgumentException('A lezárt bejelentéshez nem lehet hozzászólni.');
            $body = valid_text((string)($_POST['body'] ?? ''), 10000);
            $pdo->prepare('INSERT INTO ticket_comments(ticket_id,user_id,body) VALUES(?,?,?)')->execute([$ticketId,user()['id'],$body]);
            flash('Hozzászólás elküldve.');
        } else {
            if (!is_supervisor()) { http_response_code(403); exit('Csak admin vagy supervisor kezelheti az állapotot.'); }
            $status = (string)($_POST['status'] ?? '');
            if (!in_array($status, ['open','closed'], true)) throw new InvalidArgumentException('Hibás állapot.');
            $pdo->prepare('UPDATE tickets SET status=? WHERE id=?')->execute([$status,$ticketId]);
            flash('Bejelentés állapota módosítva.');
        }
    }
    redirect('index.php?page=tickets&ticket='.$ticketId);
}
