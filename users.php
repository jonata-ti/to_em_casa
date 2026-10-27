<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$notice = '';
$error = '';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingUser = $editId > 0 ? getUserById($pdo, $editId) : null;
$formUser = [
    'username' => $editingUser['username'] ?? '',
    'name' => $editingUser['name'] ?? '',
    'role' => $editingUser['role'] ?? 'vendedor',
    'active' => $editingUser['active'] ?? 1,
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $currentUserId = (int)($user['id'] ?? 0);

    try {
        if ($action === 'create') {
            $username = trim((string)($_POST['username'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $role = in_array($_POST['role'] ?? '', ['admin', 'vendedor'], true) ? $_POST['role'] : 'vendedor';
            $active = !empty($_POST['active']);

            $created = createUser($pdo, $username, $name, $password, $role, $active);
            logAudit($pdo, 'users', 'users', (int)$created['id'], 'create', 'Usuário criado: ' . $created['username'], $currentUserId);
            $notice = 'Usuário criado com sucesso.';
        }

        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim((string)($_POST['username'] ?? ''));
            $name = trim((string)($_POST['name'] ?? ''));
            $role = in_array($_POST['role'] ?? '', ['admin', 'vendedor'], true) ? $_POST['role'] : 'vendedor';
            $password = isset($_POST['password']) ? (string)$_POST['password'] : null;
            $active = !empty($_POST['active']);

            $updated = updateUser($pdo, $id, $username, $name, $role, $password !== '' ? $password : null, $active);
            logAudit($pdo, 'users', 'users', $id, 'update', 'Usuário atualizado: ' . $updated['username'], $currentUserId);
            $notice = 'Usuário atualizado com sucesso.';
            $editId = 0;
            $editingUser = null;
        }

        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $status = toggleUserStatus($pdo, $id);
            logAudit($pdo, 'users', 'users', $id, 'toggle', 'Usuário ' . ($status ? 'ativado' : 'desativado'), $currentUserId);
            $notice = 'Status do usuário alterado com sucesso.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$users = listUsers($pdo);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários | Tô em Casa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
    <nav class="main-menu">
        <a href="index.php">Início</a>
        <a href="products.php?open=1">Produtos</a>
        <a href="pos.php">PDV</a>
        <a href="cash_register.php">Caixa</a>
        <a href="financial.php">Financeiro</a>
        <a href="reports.php">Relatórios</a>
        <a href="audit.php">Auditoria</a>
        <a class="active" href="users.php">Usuários</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Usuários</h1>
    <p class="muted">Cadastro, edição e controle de ativação de usuários do sistema.</p>

    <?php if ($notice !== ''): ?><div class="success"><?= h($notice) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>

    <section class="card">
        <h2><?= $editingUser ? 'Editar usuário' : 'Criar usuário' ?></h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="<?= $editingUser ? 'update' : 'create' ?>">
            <?php if ($editingUser): ?><input type="hidden" name="id" value="<?= (int)$editingUser['id'] ?>"><?php endif; ?>
            <label>Usuário<input type="text" name="username" maxlength="64" value="<?= h($formUser['username']) ?>" required></label>
            <label>Nome<input type="text" name="name" maxlength="120" value="<?= h($formUser['name']) ?>" required></label>
            <label>Perfil<select name="role"><option value="vendedor" <?= $formUser['role'] === 'vendedor' ? 'selected' : '' ?>>Vendedor</option><option value="admin" <?= $formUser['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option></select></label>
            <label>Senha<?php if ($editingUser): ?><small class="muted-small">Deixe em branco para manter a atual</small><?php endif; ?><input type="password" name="password" maxlength="255" <?= $editingUser ? '' : 'required' ?>></label>
            <label>Ativo<select name="active"><option value="1" <?= (int)$formUser['active'] === 1 ? 'selected' : '' ?>>Ativo</option><option value="0" <?= (int)$formUser['active'] === 0 ? 'selected' : '' ?>>Inativo</option></select></label>
            <div class="full">
                <button class="button" type="submit"><?= $editingUser ? 'Salvar alterações' : 'Salvar usuário' ?></button>
                <?php if ($editingUser): ?><a class="button secondary" href="users.php">Cancelar edição</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Usuários cadastrados</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Nome</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h((string)$u['username']) ?></td>
                            <td><?= h((string)$u['name']) ?></td>
                            <td><?= h((string)$u['role']) ?></td>
                            <td><?= ((int)$u['active'] === 1) ? 'Ativo' : 'Inativo' ?></td>
                            <td><?= h((string)$u['created_at']) ?></td>
                            <td>
                                <div class="actions" style="margin-top:0;">
                                    <a class="button secondary small" href="users.php?edit=<?= (int)$u['id'] ?>">Editar</a>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="button secondary small"><?= ((int)$u['active'] === 1) ? 'Desativar' : 'Ativar' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
