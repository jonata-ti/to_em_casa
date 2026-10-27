<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function csvLabel(string $value): string { return str_replace(["\r\n", "\n", ";", ","], [' ', ' ', ' ', ' '], $value); }

$reportType = $_GET['report'] ?? 'sales';
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$displayRows = [];

if ($reportType === 'sales') {
    $sql = 'SELECT s.id, s.sale_number, s.total, s.created_at, u.username AS seller_name, sp.method, sp.amount FROM sales s LEFT JOIN users u ON u.id = s.seller_id LEFT JOIN sale_payments sp ON sp.sale_id = s.id WHERE s.status = ?';
    $params = ['completed'];
    if ($dateFrom !== '') { $sql .= ' AND s.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo !== '') { $sql .= ' AND s.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $sql .= ' ORDER BY s.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $displayRows = $stmt->fetchAll();
}

if ($reportType === 'stock') {
    $sql = 'SELECT sm.*, p.name AS product_name, u.username AS user_name FROM stock_movements sm LEFT JOIN products p ON p.id = sm.product_id LEFT JOIN users u ON u.id = sm.user_id WHERE 1=1';
    $params = [];
    if ($dateFrom !== '') { $sql .= ' AND sm.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo !== '') { $sql .= ' AND sm.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $sql .= ' ORDER BY sm.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $displayRows = $stmt->fetchAll();
}

if ($reportType === 'finance') {
    $sql = 'SELECT fe.*, fc.name AS category_name FROM financial_entries fe LEFT JOIN financial_categories fc ON fc.id = fe.category_id WHERE 1=1';
    $params = [];
    if ($dateFrom !== '') { $sql .= ' AND fe.entry_date >= ?'; $params[] = $dateFrom; }
    if ($dateTo !== '') { $sql .= ' AND fe.entry_date <= ?'; $params[] = $dateTo; }
    $sql .= ' ORDER BY fe.entry_date DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $displayRows = $stmt->fetchAll();
}

if ($reportType === 'cash') {
    $sql = 'SELECT cm.*, cr.id AS cash_id, u.username FROM cash_movements cm LEFT JOIN cash_registers cr ON cr.id = cm.cash_register_id LEFT JOIN users u ON u.id = cm.user_id WHERE 1=1';
    $params = [];
    if ($dateFrom !== '') { $sql .= ' AND cm.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
    if ($dateTo !== '') { $sql .= ' AND cm.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
    $sql .= ' ORDER BY cm.created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $displayRows = $stmt->fetchAll();
}

$csvUrl = 'reports.php?report=' . urlencode($reportType) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '&csv=1';

if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_' . $reportType . '.csv');
    $out = fopen('php://output', 'w');
    if ($reportType === 'sales') {
        fputcsv($out, ['id', 'sale_number', 'total', 'created_at', 'seller_name', 'method', 'amount']);
        foreach ($displayRows as $row) { fputcsv($out, [$row['id'], $row['sale_number'], $row['total'], $row['created_at'], $row['seller_name'] ?? '', $row['method'] ?? '', $row['amount'] ?? '']); }
    } elseif ($reportType === 'stock') {
        fputcsv($out, ['id', 'product', 'movement_type', 'quantity', 'before_qty', 'after_qty', 'created_at', 'reason', 'user']);
        foreach ($displayRows as $row) { fputcsv($out, [$row['id'], $row['product_name'] ?? '', $row['movement_type'], $row['quantity'], $row['before_qty'], $row['after_qty'], $row['created_at'], $row['movement_reason'] ?? '', $row['user_name'] ?? '']); }
    } elseif ($reportType === 'finance') {
        fputcsv($out, ['id', 'type', 'category', 'description', 'amount', 'status', 'entry_date', 'due_date']);
        foreach ($displayRows as $row) { fputcsv($out, [$row['id'], $row['entry_type'], $row['category_name'] ?? '', $row['description'], $row['amount'], $row['status'], $row['entry_date'], $row['due_date'] ?? '']); }
    } else {
        fputcsv($out, ['id', 'cash_register_id', 'movement_type', 'related_sale_id', 'amount', 'reason', 'created_at', 'user']);
        foreach ($displayRows as $row) { fputcsv($out, [$row['id'], $row['cash_id'] ?? '', $row['movement_type'], $row['related_sale_id'] ?? '', $row['amount'], $row['reason'], $row['created_at'], $row['username'] ?? '']); }
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatórios | Tô em Casa</title>
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
        <a class="active" href="reports.php">Relatórios</a>
        <a href="audit.php">Auditoria</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Relatórios</h1>
    <p class="muted">Filtros por período e exportação em CSV para análise e conferência.</p>
    <section class="card">
        <form method="get" class="search-form">
            <select name="report"><option value="sales" <?= $reportType === 'sales' ? 'selected' : '' ?>>Vendas</option><option value="stock" <?= $reportType === 'stock' ? 'selected' : '' ?>>Estoque</option><option value="finance" <?= $reportType === 'finance' ? 'selected' : '' ?>>Financeiro</option><option value="cash" <?= $reportType === 'cash' ? 'selected' : '' ?>>Caixa</option></select>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
            <button class="button" type="submit">Filtrar</button>
            <a class="button secondary" href="<?= h($csvUrl) ?>">Exportar CSV</a>
        </form>
    </section>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <?php if ($reportType === 'sales'): ?><tr><th>ID</th><th>Número</th><th>Total</th><th>Data</th><th>Vendedor</th><th>Meio</th><th>Valor</th></tr><?php endif; ?>
                    <?php if ($reportType === 'stock'): ?><tr><th>ID</th><th>Produto</th><th>Tipo</th><th>Qtd.</th><th>Antes</th><th>Depois</th><th>Data</th><th>Motivo</th></tr><?php endif; ?>
                    <?php if ($reportType === 'finance'): ?><tr><th>ID</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Status</th><th>Data</th></tr><?php endif; ?>
                    <?php if ($reportType === 'cash'): ?><tr><th>ID</th><th>Caixa</th><th>Tipo</th><th>Venda</th><th>Valor</th><th>Motivo</th><th>Data</th></tr><?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($displayRows as $row): ?>
                        <?php if ($reportType === 'sales'): ?><tr><td><?= (int)$row['id'] ?></td><td><?= h((string)($row['sale_number'] ?? '')) ?></td><td><?= h((string)$row['total']) ?></td><td><?= h((string)$row['created_at']) ?></td><td><?= h((string)($row['seller_name'] ?? '—')) ?></td><td><?= h((string)($row['method'] ?? '—')) ?></td><td><?= h((string)($row['amount'] ?? '0')) ?></td></tr><?php endif; ?>
                        <?php if ($reportType === 'stock'): ?><tr><td><?= (int)$row['id'] ?></td><td><?= h((string)($row['product_name'] ?? '—')) ?></td><td><?= h((string)$row['movement_type']) ?></td><td><?= h((string)$row['quantity']) ?></td><td><?= h((string)$row['before_qty']) ?></td><td><?= h((string)$row['after_qty']) ?></td><td><?= h((string)$row['created_at']) ?></td><td><?= h((string)($row['movement_reason'] ?? '—')) ?></td></tr><?php endif; ?>
                        <?php if ($reportType === 'finance'): ?><tr><td><?= (int)$row['id'] ?></td><td><?= h((string)$row['entry_type']) ?></td><td><?= h((string)($row['category_name'] ?? '—')) ?></td><td><?= h((string)$row['description']) ?></td><td><?= h((string)$row['amount']) ?></td><td><?= h((string)$row['status']) ?></td><td><?= h((string)$row['entry_date']) ?></td></tr><?php endif; ?>
                        <?php if ($reportType === 'cash'): ?><tr><td><?= (int)$row['id'] ?></td><td><?= (int)($row['cash_id'] ?? 0) ?></td><td><?= h((string)$row['movement_type']) ?></td><td><?= h((string)($row['related_sale_id'] ?? '—')) ?></td><td><?= h((string)$row['amount']) ?></td><td><?= h((string)$row['reason']) ?></td><td><?= h((string)$row['created_at']) ?></td></tr><?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
