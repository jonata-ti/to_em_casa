<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';

$user = currentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

if ($user['role'] !== 'admin') {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$error = '';
$success = '';
$defaultDate = date('Y-m-d');
$fields = [
    'product_id' => '',
    'quantity' => '',
    'movement_date' => $defaultDate,
    'movement_reason' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields['product_id'] = (string)($_POST['product_id'] ?? '');
    $fields['quantity'] = trim((string)($_POST['quantity'] ?? ''));
    $fields['movement_date'] = trim((string)($_POST['movement_date'] ?? $defaultDate));
    $fields['movement_reason'] = trim((string)($_POST['movement_reason'] ?? ''));
    $fields['notes'] = trim((string)($_POST['notes'] ?? ''));

    $productId = (int)$fields['product_id'];
    $quantity = (float)str_replace(',', '.', $fields['quantity']);
    $movementDate = $fields['movement_date'];
    $reason = $fields['movement_reason'];

    if ($productId <= 0) {
        $error = 'Selecione um produto.';
    } elseif ($quantity <= 0) {
        $error = 'A quantidade deve ser maior que zero.';
    } elseif ($reason === '') {
        $error = 'Informe o motivo da saída.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $movementDate)) {
        $error = 'Informe uma data válida.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, name, stock_qty, active FROM products WHERE id = ? AND active = 1 LIMIT 1 FOR UPDATE');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('Produto não encontrado ou inativo.');
            }
            $beforeQty = (float)$product['stock_qty'];
            if ($beforeQty < $quantity) {
                throw new RuntimeException('Quantidade maior que o estoque disponível.');
            }
            $afterQty = $beforeQty - $quantity;

            $upd = $pdo->prepare('UPDATE products SET stock_qty = ? WHERE id = ?');
            $upd->execute([$afterQty, $productId]);

            $insert = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, before_qty, after_qty, movement_reason, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $productId,
                'adjustment_out',
                -$quantity,
                $beforeQty,
                $afterQty,
                $reason,
                $fields['notes'] !== '' ? $fields['notes'] : 'Saída registrada',
                (int)$user['id'],
                $movementDate . ' 00:00:00'
            ]);

            logAudit($pdo, 'stock', 'stock_movements', (int)$pdo->lastInsertId(), 'adjustment_out', 'Saída de mercadoria registrada para produto #' . $productId . ': ' . $reason, (int)$user['id']);

            $pdo->commit();
            $success = 'Saída registrada com sucesso.';
            $fields = [
                'product_id' => '',
                'quantity' => '',
                'movement_date' => $defaultDate,
                'movement_reason' => '',
                'notes' => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage() ?: 'Não foi possível registrar a saída.';
        }
    }
}

$products = $pdo->query('SELECT id, name, barcode, stock_qty FROM products WHERE active = 1 ORDER BY name ASC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Saídas e ajustes | Tô em Casa</title>
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
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Saídas e ajustes</h1>
    <p class="muted">Registre perdas, avarias, vencimento, consumo interno ou ajustes autorizados.</p>
    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success !== ''): ?><div class="success"><?= h($success) ?></div><?php endif; ?>

    <form method="post" class="card form-grid" autocomplete="off">
        <label>Produto *
            <select name="product_id" required>
                <option value="">Selecione</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int)$product['id'] ?>" <?= (string)$fields['product_id'] === (string)$product['id'] ? 'selected' : '' ?>><?= h((string)$product['name']) ?> (<?= h((string)$product['barcode']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Quantidade *<input type="number" name="quantity" min="0.001" step="0.001" value="<?= h($fields['quantity']) ?>" required></label>
        <label>Data *<input type="date" name="movement_date" value="<?= h($fields['movement_date']) ?>" required></label>
        <label>Motivo *
            <select name="movement_reason" required>
                <option value="">Selecione</option>
                <option value="perda" <?= $fields['movement_reason'] === 'perda' ? 'selected' : '' ?>>Perda</option>
                <option value="avaria" <?= $fields['movement_reason'] === 'avaria' ? 'selected' : '' ?>>Avaria</option>
                <option value="vencimento" <?= $fields['movement_reason'] === 'vencimento' ? 'selected' : '' ?>>Vencimento</option>
                <option value="consumo_interno" <?= $fields['movement_reason'] === 'consumo_interno' ? 'selected' : '' ?>>Consumo interno</option>
                <option value="ajuste_autorizado" <?= $fields['movement_reason'] === 'ajuste_autorizado' ? 'selected' : '' ?>>Ajuste autorizado</option>
            </select>
        </label>
        <label class="full">Observações<textarea name="notes" rows="3" placeholder="Descreva o motivo da saída"><?= h($fields['notes']) ?></textarea></label>
        <div class="full"><button class="button danger" type="submit">Registrar saída</button></div>
    </form>
</main>
</body>
</html>
