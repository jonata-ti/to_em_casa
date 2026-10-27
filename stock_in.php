<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

$error = '';
$success = '';
$defaultDate = date('Y-m-d');
$fields = [
    'product_id' => '',
    'quantity' => '',
    'unit_cost' => '',
    'movement_date' => $defaultDate,
    'movement_reason' => 'compra',
    'supplier' => '',
    'notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fields['product_id'] = (string)($_POST['product_id'] ?? '');
    $fields['quantity'] = trim((string)($_POST['quantity'] ?? ''));
    $fields['unit_cost'] = trim((string)($_POST['unit_cost'] ?? ''));
    $fields['movement_date'] = trim((string)($_POST['movement_date'] ?? $defaultDate));
    $fields['movement_reason'] = trim((string)($_POST['movement_reason'] ?? 'compra'));
    $fields['supplier'] = trim((string)($_POST['supplier'] ?? ''));
    $fields['notes'] = trim((string)($_POST['notes'] ?? ''));

    $productId = (int)$fields['product_id'];
    $quantity = (float)str_replace(',', '.', $fields['quantity']);
    $unitCost = (float)str_replace(',', '.', $fields['unit_cost']);
    $movementDate = $fields['movement_date'] !== '' ? $fields['movement_date'] : $defaultDate;
    $reason = $fields['movement_reason'];
    $supplier = $fields['supplier'];
    $notes = $fields['notes'];

    if ($productId <= 0) {
        $error = 'Selecione um produto para registrar a entrada.';
    } elseif ($quantity <= 0) {
        $error = 'A quantidade recebida deve ser maior que zero.';
    } elseif ($unitCost < 0) {
        $error = 'O custo unitário não pode ser negativo.';
    } elseif ($reason === '') {
        $error = 'Informe o motivo da entrada.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $movementDate)) {
        $error = 'Informe uma data válida.';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, name, stock_qty, cost_price, active FROM products WHERE id = ? AND active = 1 LIMIT 1');
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            if (!$product) {
                throw new RuntimeException('Produto não encontrado ou inativo.');
            }

            $beforeQty = (float)$product['stock_qty'];
            $afterQty = $beforeQty + $quantity;

            $update = $pdo->prepare('UPDATE products SET stock_qty = ?, cost_price = ? WHERE id = ?');
            $update->execute([$afterQty, $unitCost, $productId]);

            $insert = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, before_qty, after_qty, movement_reason, notes, supplier, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([
                $productId,
                'adjustment_in',
                $quantity,
                $beforeQty,
                $afterQty,
                $reason,
                $notes !== '' ? $notes : 'Entrada de mercadoria',
                $supplier !== '' ? $supplier : null,
                (int)$user['id'],
                $movementDate . ' 00:00:00'
            ]);

            $movementId = (int)$pdo->lastInsertId();
            if ($reason === 'compra' || $reason === 'reposição') {
                $expenseText = 'Compra de mercadoria - ' . $product['name'];
                $expense = $pdo->prepare('INSERT INTO financial_entries (entry_type, category_id, description, amount, entry_date, due_date, status, payment_method, reference_key, related_entity_type, related_entity_id, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $expense->execute([
                    'expense',
                    null,
                    $expenseText,
                    round($quantity * $unitCost, 2),
                    $movementDate,
                    $movementDate,
                    'pending',
                    'outro',
                    'stock:' . $productId . ':' . $movementId,
                    'stock_movements',
                    $movementId,
                    $notes !== '' ? $notes : 'Compra registrada via entrada de mercadoria',
                    (int)$user['id'],
                ]);
            }

            logAudit($pdo, 'stock', 'stock_movements', $movementId, 'adjustment_in', 'Entrada de mercadoria registrada para produto #' . $productId . ': ' . $reason, (int)$user['id']);

            $pdo->commit();
            $success = 'Entrada registrada com sucesso.';
            $fields = [
                'product_id' => '',
                'quantity' => '',
                'unit_cost' => '',
                'movement_date' => $defaultDate,
                'movement_reason' => 'compra',
                'supplier' => '',
                'notes' => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage() ?: 'Não foi possível registrar a entrada.';
        }
    }
}

$products = $pdo->query('SELECT id, name, barcode, stock_qty, active FROM products WHERE active = 1 ORDER BY name ASC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entradas de estoque | Tô em Casa</title>
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
    <h1>Entrada de mercadorias</h1>
    <p class="muted">Registre entradas de compra, reposição ou ajustes positivos do estoque.</p>
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

        <label>Quantidade recebida *<input type="number" name="quantity" min="0.001" step="0.001" value="<?= h($fields['quantity']) ?>" required></label>
        <label>Custo unitário (R$) *<input type="number" name="unit_cost" min="0" step="0.01" value="<?= h($fields['unit_cost']) ?>" required></label>
        <label>Data da entrada *<input type="date" name="movement_date" value="<?= h($fields['movement_date']) ?>" required></label>
        <label>Motivo *
            <select name="movement_reason" required>
                <option value="compra" <?= $fields['movement_reason'] === 'compra' ? 'selected' : '' ?>>Compra</option>
                <option value="reposição" <?= $fields['movement_reason'] === 'reposição' ? 'selected' : '' ?>>Reposição</option>
                <option value="ajuste_positivo" <?= $fields['movement_reason'] === 'ajuste_positivo' ? 'selected' : '' ?>>Ajuste positivo</option>
                <option value="devolucao" <?= $fields['movement_reason'] === 'devolucao' ? 'selected' : '' ?>>Devolução</option>
            </select>
        </label>
        <label>Fornecedor<input type="text" name="supplier" maxlength="120" value="<?= h($fields['supplier']) ?>" placeholder="Opcional"></label>
        <label class="full">Observações<textarea name="notes" rows="3" placeholder="Detalhes da entrada"><?= h($fields['notes']) ?></textarea></label>
        <div class="full"><button class="button" type="submit">Registrar entrada</button></div>
    </form>
</main>
</body>
</html>
