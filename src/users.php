<?php
declare(strict_types=1);

function update_managed_user(PDO $pdo, int $target, string $action, array $input): void
{
    // Serialize hierarchy changes and last-admin checks across concurrent requests.
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND deleted_at IS NULL');
        $stmt->execute([$target]); $member = $stmt->fetch();
        if (!$member) throw new InvalidArgumentException('A felhasználó nem található.');
        if ($action === 'save_manager') {
            $raw = $input['manager_id'] ?? '';
            $manager = $raw === '' ? null : filter_var($raw, FILTER_VALIDATE_INT);
            if ($manager === false || ($manager !== null && $manager < 1)) throw new InvalidArgumentException('Hibás felettes.');
            $seen = [$target => true];
            $cursor = $manager;
            while ($cursor !== null) {
                if (isset($seen[$cursor])) throw new InvalidArgumentException('A felettesi kapcsolat nem lehet körkörös.');
                $seen[$cursor] = true;
                $stmt->execute([$cursor]); $parent = $stmt->fetch();
                if (!$parent || (int)$parent['role'] < ROLE_SUPERVISOR) throw new InvalidArgumentException('Csak aktív supervisor vagy admin lehet felettes.');
                $cursor = $parent['manager_id'] === null ? null : (int)$parent['manager_id'];
            }
            $pdo->prepare('UPDATE users SET manager_id=? WHERE id=?')->execute([$manager,$target]);
        } else {
            if ($target === (int)user()['id']) throw new InvalidArgumentException('A saját fiókodat nem törölheted és a saját szerepkörödet nem módosíthatod.');
            $role = $action === 'delete_user' ? null : filter_var($input['role'] ?? null, FILTER_VALIDATE_INT);
            if ($action !== 'delete_user' && ($role === false || $role < 0 || $role > 3)) throw new InvalidArgumentException('Hibás szerepkör.');
            if ((int)$member['role'] === ROLE_ADMIN && $role !== ROLE_ADMIN && (int)$pdo->query('SELECT COUNT(*) FROM users WHERE role=3 AND deleted_at IS NULL')->fetchColumn() <= 1) {
                throw new InvalidArgumentException('Az utolsó admin nem távolítható el.');
            }
            if ($action === 'delete_user' || $role < ROLE_SUPERVISOR) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE manager_id=? AND deleted_at IS NULL');
                $stmt->execute([$target]);
                if ((int)$stmt->fetchColumn() > 0) throw new InvalidArgumentException('Előbb rendeld át a közvetlen beosztottakat más feletteshez, vagy töröld a felettesi kapcsolatukat.');
            }
            if ($action === 'delete_user') {
                $pdo->prepare("UPDATE users SET deleted_at=CURRENT_TIMESTAMP,password_hash='',manager_id=NULL WHERE id=?")->execute([$target]);
                $pdo->prepare('DELETE FROM inventory_access WHERE user_id=?')->execute([$target]);
            } else {
                $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role,$target]);
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
}

function reporting_chain(array $member, array $users): string
{
    $names = [$member['username']];
    $seen = [(int)$member['id'] => true];
    $manager = $member['manager_id'];
    while ($manager !== null && isset($users[$manager]) && !isset($seen[$manager])) {
        $seen[$manager] = true;
        $names[] = $users[$manager]['username'];
        $manager = $users[$manager]['manager_id'];
    }
    return implode(' → ', array_reverse($names));
}
