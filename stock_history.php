<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

$filters = [
    'product_id' => isset($_GET['product_id']) && $_GET['product_id'] !== '' ? (int)$_GET['product_id'] : '',
    'movement_type' => $_GET['movement_type'] ?? '',
    'user_id' => isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : '',
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];

$sql = 'SELECT sm.*, p.name AS product_name, u.username AS user_name FROM stock_movements sm LEFT JOIN products p ON p.id = sm.product_id LEFT JOIN users u ON u.id = sm.user_id WHERE 1=1';
$params = [];

if ($filters['product_id'] !== '') {
    $sql .= ' AND sm.product_id = ?';
    $params[] = $filters['product_id'];
}
if ($filters['movement_type'] !== '') {
    $sql .= ' AND sm.movement_type = ?';
    $params[] = $filters['movement_type'];
}
if ($filters['user_id'] !== '') {
    $sql .= ' AND sm.user_id = ?';
    $params[] = $filters['user_id'];
}
if ($filters['date_from'] !== '') {
    $sql .= ' AND sm.created_at >= ?';
    $params[] = $filters['date_from'] . ' 00:00:00';
}
if ($filters['date_to'] !== '') {
    $sql .= ' AND sm.created_at <= ?';
    $params[] = $filters['date_to'] . ' 23:59:59';
}
$sql .= ' ORDER BY sm.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$products = $pdo->query('SELECT id, name FROM products ORDER BY name ASC')->fetchAll();
$users = $pdo->query('SELECT id, username FROM users WHERE active = 1 ORDER BY username ASC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Histórico de movimentações | Tô em Casa</title>
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
    <h1>Histórico de movimentações</h1>
    <p class="muted">Filtros por produto, período, tipo e responsável.</p>

    <section class="card">
        <form method="get" class="search-form">
            <select name="product_id">
                <option value="">Todos os produtos</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= (int)$product['id'] ?>" <?= $filters['product_id'] === (int)$product['id'] ? 'selected' : '' ?>><?= h((string)$product['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="movement_type">
                <option value="">Todos os tipos</option>
                <option value="initial" <?= $filters['movement_type'] === 'initial' ? 'selected' : '' ?>>Estoque inicial</option>
                <option value="sale" <?= $filters['movement_type'] === 'sale' ? 'selected' : '' ?>>Venda</option>
                <option value="adjustment_in" <?= $filters['movement_type'] === 'adjustment_in' ? 'selected' : '' ?>>Entrada</option>
                <option value="adjustment_out" <?= $filters['movement_type'] === 'adjustment_out' ? 'selected' : '' ?>>Saída</option>
            </select>
            <select name="user_id">
                <option value="">Todos os usuários</option>
                <?php foreach ($users as $userItem): ?>
                    <option value="<?= (int)$userItem['id'] ?>" <?= $filters['user_id'] === (int)$userItem['id'] ? 'selected' : '' ?>><?= h((string)$userItem['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" value="<?= h($filters['date_from']) ?>">
            <input type="date" name="date_to" value="<?= h($filters['date_to']) ?>">
            <button type="submit" class="button">Filtrar</button>
        </form>
    </section>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Tipo</th>
                        <th>Qtd.</th>
                        <th>Antes</th>
                        <th>Depois</th>
                        <th>Data</th>
                        <th>Motivo</th>
                        <th>Responsável</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= h((string)($item['product_name'] ?? '—')) ?></td>
                            <td><?= h((string)$item['movement_type']) ?></td>
                            <td><?= h((string)$item['quantity']) ?></td>
                            <td><?= h((string)$item['before_qty']) ?></td>
                            <td><?= h((string)$item['after_qty']) ?></td>
                            <td><?= h((string)$item['created_at']) ?></td>
                            <td><?= h((string)($item['movement_reason'] ?? $item['notes'] ?? '—')) ?></td>
                            <td><?= h((string)($item['user_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
