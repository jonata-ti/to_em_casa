<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin','vendedor']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }

$products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
$salesToday = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
$todayRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
$monthRevenue = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND DATE(created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$monthSales = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE status='completed' AND DATE(created_at) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$averageTicket = $monthSales > 0 ? $monthRevenue / $monthSales : 0.0;
$paidExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM financial_entries WHERE entry_type='expense' AND status IN ('paid','received')")->fetchColumn();
$pendingExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM financial_entries WHERE entry_type='expense' AND status IN ('pending','overdue')")->fetchColumn();
$overdue = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM financial_entries WHERE status='overdue'")->fetchColumn();
$lowStock = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active = 1 AND stock_qty <= min_stock")->fetchColumn();
$expiringSoon = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active = 1 AND expires_on IS NOT NULL AND DATEDIFF(expires_on, CURDATE()) <= 30")->fetchColumn();
$cashOpen = (float)$pdo->query("SELECT COALESCE(SUM(expected_amount),0) FROM cash_registers WHERE status='open'")->fetchColumn();
$lastCashDifference = (float)$pdo->query("SELECT COALESCE(SUM(difference_amount),0) FROM cash_registers WHERE status='closed'")->fetchColumn();
$topProducts = $pdo->query("SELECT p.name, SUM(si.quantity) AS qty FROM sale_items si JOIN products p ON p.id = si.product_id GROUP BY p.id, p.name ORDER BY qty DESC LIMIT 5")->fetchAll();
$paymentBreakdown = $pdo->query("SELECT method, SUM(amount) AS total FROM sale_payments GROUP BY method ORDER BY total DESC")->fetchAll();

$dashboardText = 'Faturamento não é o mesmo que lucro. O lucro estimado deve considerar custo dos itens vendidos e despesas operacionais; o caixa representa dinheiro em mãos, não resultado final.';
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tô em Casa | Gestão</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
    <nav class="main-menu">
        <a class="active" href="index.php">Início</a>
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
    <h1>Painel de gestão</h1>
    <p class="muted">Resumo do negócio por período e indicadores úteis para operação, financeiro e controle.</p>
    <div class="notice"><?= h($dashboardText) ?></div>
    <section class="cards">
        <article class="card"><span>Produtos ativos</span><strong><?= $products ?></strong></article>
        <article class="card"><span>Vendas hoje</span><strong><?= $salesToday ?></strong></article>
        <article class="card"><span>Faturamento hoje</span><strong><?= money((float)$todayRevenue) ?></strong></article>
        <article class="card"><span>Ticket médio (mês)</span><strong><?= money((float)$averageTicket) ?></strong></article>
    </section>

    <section class="cards">
        <article class="card"><span>Receitas mês</span><strong><?= money((float)$monthRevenue) ?></strong></article>
        <article class="card"><span>Despesas pagas</span><strong><?= money((float)$paidExpenses) ?></strong></article>
        <article class="card"><span>Despesas pendentes</span><strong><?= money((float)$pendingExpenses) ?></strong></article>
        <article class="card"><span>Contas vencidas</span><strong><?= money((float)$overdue) ?></strong></article>
    </section>

    <section class="card">
        <h2>Vendas por forma de pagamento</h2>
        <ul class="alert-list">
            <?php foreach ($paymentBreakdown as $row): ?>
                <li><?= h((string)$row['method']) ?> — <?= money((float)$row['total']) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h2>Produtos mais vendidos</h2>
        <ul class="alert-list">
            <?php foreach ($topProducts as $product): ?>
                <li><?= h((string)$product['name']) ?> — <?= h((string)$product['qty']) ?> unidades</li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="cards">
        <article class="card"><span>Produtos abaixo do mínimo</span><strong><?= $lowStock ?></strong></article>
        <article class="card"><span>Próximos da validade</span><strong><?= $expiringSoon ?></strong></article>
        <article class="card"><span>Caixa em aberto</span><strong><?= money((float)$cashOpen) ?></strong></article>
        <article class="card"><span>Diferença de caixas fechados</span><strong><?= money((float)$lastCashDifference) ?></strong></article>
    </section>

    <section class="card">
        <h2>Acesso rápido</h2>
        <div class="actions">
            <a class="button" href="products.php?open=1">Produtos</a>
            <a class="button secondary" href="pos.php">PDV</a>
            <a class="button secondary" href="financial.php">Financeiro</a>
            <a class="button secondary" href="reports.php">Relatórios</a>
        </div>
    </section>
</main>
</body>
</html>
