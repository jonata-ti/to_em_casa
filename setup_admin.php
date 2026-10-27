<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($userCount > 0) {
    header('Location: login.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($username === '' || $name === '' || $password === '' || $confirm === '') {
        $message = 'Preencha todos os campos para criar o primeiro administrador.';
    } elseif (strlen($password) < 8) {
        $message = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $message = 'As senhas não coincidem.';
    } else {
        try {
            $user = createUser($pdo, $username, $name, $password, 'admin', true);
            logAudit($pdo, 'auth', 'users', (int)$user['id'], 'create', 'Primeiro administrador criado', (int)$user['id']);
            header('Location: login.php?created=1');
            exit;
        } catch (Throwable $e) {
            $message = $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeiro administrador</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-card">
            <div class="brand-block">
                <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
            </div>
            <h1>Criar primeiro administrador</h1>
            <p class="muted">Crie a conta inicial do sistema. Não use uma senha padrão e mantenha esta etapa em ambiente local.</p>

            <?php if ($message !== ''): ?>
                <div class="error"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" class="auth-form">
                <label>
                    Usuário
                    <input type="text" name="username" maxlength="64" placeholder="Ex.: admin" required>
                </label>

                <label>
                    Nome
                    <input type="text" name="name" maxlength="120" placeholder="Ex.: João da Loja" required>
                </label>

                <label>
                    Senha
                    <input type="password" name="password" maxlength="255" placeholder="Mínimo 8 caracteres" required>
                </label>

                <label>
                    Confirmar senha
                    <input type="password" name="confirm_password" maxlength="255" placeholder="Repita a senha" required>
                </label>

                <button type="submit" class="button">Salvar administrador</button>
            </form>
        </section>
    </main>
</body>
</html>
