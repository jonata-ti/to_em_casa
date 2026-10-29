<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('to_em_casa_session');
    session_start();
}

$user = currentUser();
if ($user) {
    logAudit($pdo, 'auth', 'users', (int)$user['id'], 'logout', 'Logout realizado com sucesso', (int)$user['id']);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();
header('Location: login.php?logout=1');
exit;
