<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

if (currentUser()) {
    header('Location: ' . redirectAfterLogin());
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $message = 'Informe usuário e senha para continuar.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, role, password_hash FROM users WHERE username = ? AND active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_username'] = (string)$user['username'];
            $_SESSION['user_role'] = (string)$user['role'];
            logAudit($pdo, 'auth', 'users', (int)$user['id'], 'login', 'Usuário autenticado com sucesso', (int)$user['id']);
            header('Location: ' . redirectAfterLogin());
            exit;
        }

        logAudit($pdo, 'auth', 'users', null, 'login_failed', 'Tentativa de login inválida para usuário: ' . $username, null);
        $message = 'Usuário ou senha inválidos.';
    }
}

$hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Tô em Casa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="brand-block">
                <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
            </div>
            <h1>Entrar no sistema</h1>
            <p class="muted">Acesse com seu usuário e senha.</p>

            <?php if ($message !== ''): ?>
                <div class="error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form" autocomplete="off">
                <label>
                    Usuário
                    <input type="text" name="username" maxlength="64" placeholder="Digite seu usuário" required>
                </label>

                <label>
                    Senha
                    <input type="password" name="password" maxlength="255" placeholder="Digite sua senha" required>
                </label>

                <button type="submit" class="button">Entrar</button>
            </form>

            <?php if ($hasUsers === 0): ?>
                <p class="notice">
                    Nenhum usuário foi cadastrado ainda. Crie o primeiro administrador em
                    <a href="setup_admin.php">setup_admin.php</a>.
                </p>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
