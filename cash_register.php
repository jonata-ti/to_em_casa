<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin','vendedor']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

$cash = $pdo->prepare('SELECT * FROM cash_registers WHERE status = ? AND opened_by_user_id = ? ORDER BY opened_at DESC LIMIT 1');
$cash->execute(['open', (int)$user['id']]);
$openCash = $cash->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(400);
        exit('Requisição inválida.');
    }

    if ($action === 'open') {
        if ($openCash) {
            header('Location: cash_register.php?error=ja_aberto');
            exit;
        }
        $initial = (float)str_replace(',', '.', (string)($_POST['opened_amount'] ?? '0'));
        if ($initial < 0) {
            header('Location: cash_register.php?error=valor_invalido');
            exit;
        }
        $insert = $pdo->prepare('INSERT INTO cash_registers (opened_by_user_id, opened_amount, status, expected_amount, counted_amount, difference_amount, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([(int)$user['id'], $initial, 'open', $initial, $initial, 0, 'Abertura de caixa']);
        $id = (int)$pdo->lastInsertId();
        $mov = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, user_id, amount, reason) VALUES (?, ?, ?, ?, ?)');
        $mov->execute([$id, 'opening', (int)$user['id'], $initial, 'Abertura de caixa']);
        header('Location: cash_register.php?success=abertura');
        exit;
    }

    if ($action === 'supply') {
        if (!$openCash) {
            header('Location: cash_register.php?error=caixa_fechado');
            exit;
        }
        $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($amount <= 0 || $reason === '') {
            header('Location: cash_register.php?error=suprimento_invalido');
            exit;
        }
        $upd = $pdo->prepare('UPDATE cash_registers SET opened_amount = opened_amount + ?, expected_amount = expected_amount + ? WHERE id = ?');
        $upd->execute([$amount, $amount, (int)$openCash['id']]);
        $mov = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, user_id, amount, reason) VALUES (?, ?, ?, ?, ?)');
        $mov->execute([(int)$openCash['id'], 'supply', (int)$user['id'], $amount, $reason]);
        header('Location: cash_register.php?success=suprimento');
        exit;
    }

    if ($action === 'sangria') {
        if (!$openCash) {
            header('Location: cash_register.php?error=caixa_fechado');
            exit;
        }
        $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($amount <= 0 || $reason === '') {
            header('Location: cash_register.php?error=sangria_invalida');
            exit;
        }
        $upd = $pdo->prepare('UPDATE cash_registers SET opened_amount = opened_amount - ?, expected_amount = expected_amount - ? WHERE id = ?');
        $upd->execute([$amount, $amount, (int)$openCash['id']]);
        $mov = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, user_id, amount, reason) VALUES (?, ?, ?, ?, ?)');
        $mov->execute([(int)$openCash['id'], 'sangria', (int)$user['id'], $amount, $reason]);
        header('Location: cash_register.php?success=sangria');
        exit;
    }

    if ($action === 'close') {
        if (!$openCash) {
            header('Location: cash_register.php?error=caixa_fechado');
            exit;
        }
        $counted = (float)str_replace(',', '.', (string)($_POST['counted_amount'] ?? '0'));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $expected = (float)$openCash['expected_amount'];
        $difference = round($counted - $expected, 2);

        $upd = $pdo->prepare('UPDATE cash_registers SET status = ?, counted_amount = ?, difference_amount = ?, notes = ?, closed_at = NOW() WHERE id = ?');
        $upd->execute(['closed', $counted, $difference, $notes !== '' ? $notes : 'Fechamento de caixa', (int)$openCash['id']]);
        $mov = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, user_id, amount, reason) VALUES (?, ?, ?, ?, ?)');
        $mov->execute([(int)$openCash['id'], 'closing', (int)$user['id'], $counted, 'Fechamento de caixa']);
        header('Location: cash_register.php?success=fechamento');
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Caixa | Tô em Casa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
    <nav class="main-menu">
        <a href="index.php">Início</a>
        <a href="products.php?open=1">Produtos</a>
        <a href="pos.php">PDV</a>
        <a class="active" href="cash_register.php">Caixa</a>
        <a href="financial.php">Financeiro</a>
        <a href="reports.php">Relatórios</a>
        <a href="audit.php">Auditoria</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Controle de caixa</h1>
    <?php if (isset($_GET['success'])): ?><div class="success">Operação realizada com sucesso.</div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div class="error">Não foi possível concluir a operação.</div><?php endif; ?>

    <?php if ($openCash): ?>
        <section class="card">
            <h2>Caixa em aberto</h2>
            <p><strong>Operador:</strong> <?= h((string)$user['username']) ?></p>
            <p><strong>Valor inicial:</strong> <?= money((float)$openCash['opened_amount']) ?></p>
            <p><strong>Esperado:</strong> <?= money((float)$openCash['expected_amount']) ?></p>

            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="supply">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <label>Suprimento<input type="number" name="amount" min="0.01" step="0.01" required></label>
                <label>Motivo<input type="text" name="reason" maxlength="160" required></label>
                <div class="full"><button type="submit" class="button">Registrar suprimento</button></div>
            </form>

            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="sangria">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <label>Sangria<input type="number" name="amount" min="0.01" step="0.01" required></label>
                <label>Motivo<input type="text" name="reason" maxlength="160" required></label>
                <div class="full"><button type="submit" class="button secondary">Registrar sangria</button></div>
            </form>

            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <label>Valor contado<input type="number" name="counted_amount" min="0" step="0.01" required></label>
                <label>Observações<input type="text" name="notes" maxlength="255"></label>
                <div class="full"><button type="submit" class="button danger">Fechar caixa</button></div>
            </form>
        </section>
    <?php else: ?>
        <section class="card">
            <h2>Abrir caixa</h2>
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="open">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <label>Valor inicial (R$)<input type="number" name="opened_amount" min="0" step="0.01" required></label>
                <div class="full"><button type="submit" class="button">Abrir caixa</button></div>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
