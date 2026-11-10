<?php
declare(strict_types=1);
$activeTickets = 0;
if (is_supervisor()) $activeTickets = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status='open'")->fetchColumn();
if ($page === 'admin') {
    if (is_admin()) $inventories = $pdo->query('SELECT * FROM inventories ORDER BY name')->fetchAll();
    else {
        $stmt = $pdo->prepare('SELECT i.* FROM inventories i JOIN inventory_access a ON a.inventory_id=i.id WHERE a.user_id=? ORDER BY i.name');
        $stmt->execute([user()['id']]); $inventories = $stmt->fetchAll();
    }
    $users = $pdo->query('SELECT id,username,role,manager_id FROM users WHERE deleted_at IS NULL ORDER BY username')->fetchAll();
    $userMap = array_column($users, null, 'id');
    $directReports = [];
    foreach ($users as $member) {
        if ($member['manager_id'] !== null) $directReports[(int)$member['manager_id']][] = $member['username'];
    }
    $access = [];
    foreach ($pdo->query('SELECT user_id,inventory_id FROM inventory_access') as $row) $access[(int)$row['user_id']][(int)$row['inventory_id']] = true;
}
if ($page === 'tickets') {
    $stmt = $pdo->prepare('SELECT tickets.*,users.username FROM tickets JOIN users ON users.id=tickets.created_by '.(is_supervisor() ? '' : 'WHERE created_by=? ')."ORDER BY CASE status WHEN 'open' THEN 0 ELSE 1 END, tickets.id DESC");
    $stmt->execute(is_supervisor() ? [] : [user()['id']]); $tickets = $stmt->fetchAll();
    $ticket = null; $comments = [];
    if (isset($_GET['ticket'])) {
        $ticket = require_ticket($pdo, (int)$_GET['ticket']);
        $stmt = $pdo->prepare('SELECT ticket_comments.*,users.username FROM ticket_comments JOIN users ON users.id=ticket_comments.user_id WHERE ticket_id=? ORDER BY ticket_comments.id');
        $stmt->execute([$ticket['id']]); $comments = $stmt->fetchAll();
    }
}
