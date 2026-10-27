<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_name('to_em_casa_session');
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function createUser(PDO $pdo, string $username, string $name, string $password, string $role = 'vendedor', bool $active = true): array
{
    $username = trim($username);
    $name = trim($name);

    if ($username === '' || $name === '') {
        throw new InvalidArgumentException('Usuário e nome são obrigatórios.');
    }

    if (strlen($password) < 8) {
        throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
    }

    if (!in_array($role, ['admin', 'vendedor'], true)) {
        throw new InvalidArgumentException('Perfil inválido.');
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        throw new RuntimeException('Este usuário já existe.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, name, password_hash, role, active) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $name, $passwordHash, $role, $active ? 1 : 0]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'username' => $username,
        'name' => $name,
        'role' => $role,
        'active' => $active,
    ];
}

function listUsers(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, username, name, role, active, created_at FROM users ORDER BY username ASC');
    return $stmt->fetchAll();
}

function getUserById(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT id, username, name, role, active, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function updateUser(PDO $pdo, int $id, string $username, string $name, string $role = 'vendedor', ?string $password = null, bool $active = true): array
{
    $target = getUserById($pdo, $id);
    if (!$target) {
        throw new RuntimeException('Usuário não encontrado.');
    }

    $username = trim($username);
    $name = trim($name);

    if ($username === '' || $name === '') {
        throw new InvalidArgumentException('Usuário e nome são obrigatórios.');
    }

    if (!in_array($role, ['admin', 'vendedor'], true)) {
        throw new InvalidArgumentException('Perfil inválido.');
    }

    $sql = 'UPDATE users SET username = ?, name = ?, role = ?, active = ?';
    $params = [$username, $name, $role, $active ? 1 : 0];

    if ($password !== null && trim($password) !== '') {
        if (strlen(trim($password)) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        $sql .= ', password_hash = ?';
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    $sql .= ' WHERE id = ?';
    $params[] = $id;

    $existing = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1');
    $existing->execute([$username, $id]);
    if ($existing->fetch()) {
        throw new RuntimeException('Já existe outro usuário com este nome de usuário.');
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'id' => $id,
        'username' => $username,
        'name' => $name,
        'role' => $role,
        'active' => $active,
    ];
}

function toggleUserStatus(PDO $pdo, int $id): bool
{
    $user = getUserById($pdo, $id);
    if (!$user) {
        throw new RuntimeException('Usuário não encontrado.');
    }

    $newStatus = (int)!((bool)$user['active']);
    $stmt = $pdo->prepare('UPDATE users SET active = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);

    return (bool)$newStatus;
}

function logAudit(PDO $pdo, string $eventType, string $entityType, ?int $entityId, string $action, string $details, ?int $userId = null): void
{
    if (!function_exists('tableExists') || !tableExists($pdo, 'audit_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (event_type, entity_type, entity_id, action, details, user_id) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$eventType, $entityType, $entityId, $action, $details, $userId]);
    } catch (Throwable $e) {
        // Ignora falha de auditoria para não impedir login ou navegação.
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => (int)$_SESSION['user_id'],
        'username' => (string)($_SESSION['user_username'] ?? ''),
        'role' => (string)($_SESSION['user_role'] ?? ''),
    ];
}

function requireLogin(): void
{
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(array $allowedRoles): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || !in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

function redirectAfterLogin(): string
{
    $user = currentUser();
    if (!$user) {
        return 'login.php';
    }

    return $user['role'] === 'admin' ? 'index.php' : 'pos.php';
}
