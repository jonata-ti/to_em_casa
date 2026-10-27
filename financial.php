<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function statusLabel(string $status): string {
    $map = ['pending' => 'Pendente', 'paid' => 'Pago', 'received' => 'Recebido', 'overdue' => 'Vencido', 'cancelled' => 'Cancelado'];
    return $map[$status] ?? ucfirst($status);
}

$categoryMessage = '';
$entryMessage = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_category') {
        $name = trim((string)($_POST['name'] ?? ''));
        $type = in_array($_POST['type'] ?? '', ['income', 'expense'], true) ? $_POST['type'] : 'expense';
        if ($name === '') {
            $error = 'Informe o nome da categoria.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO financial_categories (name, type, active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE active = 1');
            $stmt->execute([$name, $type]);
            $categoryMessage = 'Categoria salva com sucesso.';
            logAudit($pdo, 'finance', 'financial_categories', (int)$pdo->lastInsertId(), 'create', 'Categoria financeira registrada: ' . $name, (int)$user['id']);
        }
    }

    if ($action === 'save_entry') {
        $type = in_array($_POST['entry_type'] ?? '', ['income', 'expense'], true) ? $_POST['entry_type'] : 'expense';
        $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
        $description = trim((string)($_POST['description'] ?? ''));
        $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
        $entryDate = trim((string)($_POST['entry_date'] ?? date('Y-m-d')));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['pending','paid','received','overdue','cancelled'], true) ? $_POST['status'] : 'pending';
        $paymentMethod = trim((string)($_POST['payment_method'] ?? 'outro'));
        $reference = trim((string)($_POST['reference_key'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($description === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
            $error = 'Preencha descrição, valor e data válidos.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO financial_entries (entry_type, category_id, description, amount, entry_date, due_date, status, payment_method, reference_key, notes, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $type,
                $categoryId,
                $description,
                $amount,
                $entryDate,
                $dueDate !== '' ? $dueDate : null,
                $status,
                $paymentMethod !== '' ? $paymentMethod : 'outro',
                $reference !== '' ? $reference : null,
                $notes !== '' ? $notes : null,
                (int)$user['id'],
            ]);
            $entryId = (int)$pdo->lastInsertId();
            $entryMessage = 'Lançamento financeiro registrado com sucesso.';
            logAudit($pdo, 'finance', 'financial_entries', $entryId, 'create', 'Lançamento financeiro registrado: ' . $description, (int)$user['id']);
        }
    }
}

$categoryFilter = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;
$statusFilter = $_GET['status'] ?? 'all';
$typeFilter = $_GET['type'] ?? 'all';
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$categories = $pdo->query('SELECT * FROM financial_categories WHERE active = 1 ORDER BY name ASC')->fetchAll();

$sql = 'SELECT fe.*, fc.name AS category_name, u.username AS user_name FROM financial_entries fe LEFT JOIN financial_categories fc ON fc.id = fe.category_id LEFT JOIN users u ON u.id = fe.user_id WHERE 1=1';
$params = [];
if ($categoryFilter > 0) { $sql .= ' AND fe.category_id = ?'; $params[] = $categoryFilter; }
if ($statusFilter !== 'all') { $sql .= ' AND fe.status = ?'; $params[] = $statusFilter; }
if ($typeFilter !== 'all') { $sql .= ' AND fe.entry_type = ?'; $params[] = $typeFilter; }
if ($dateFrom !== '') { $sql .= ' AND fe.entry_date >= ?'; $params[] = $dateFrom; }
if ($dateTo !== '') { $sql .= ' AND fe.entry_date <= ?'; $params[] = $dateTo; }
$sql .= ' ORDER BY fe.entry_date DESC, fe.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$totals = [
    'income' => 0.0,
    'expense' => 0.0,
    'pending' => 0.0,
    'overdue' => 0.0,
];
foreach ($entries as $entry) {
    $amount = (float)$entry['amount'];
    if ($entry['entry_type'] === 'income') { $totals['income'] += $amount; }
    if ($entry['entry_type'] === 'expense') { $totals['expense'] += $amount; }
    if (in_array($entry['status'], ['pending','overdue'], true)) { $totals[$entry['status']] += $amount; }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Financeiro | Tô em Casa</title>
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
        <a class="active" href="financial.php">Financeiro</a>
        <a href="reports.php">Relatórios</a>
        <a href="audit.php">Auditoria</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Financeiro</h1>
    <p class="muted">Controle de receitas, despesas, vencimentos e histórico de lançamentos.</p>
    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if ($categoryMessage !== ''): ?><div class="success"><?= h($categoryMessage) ?></div><?php endif; ?>
    <?php if ($entryMessage !== ''): ?><div class="success"><?= h($entryMessage) ?></div><?php endif; ?>

    <section class="cards">
        <article class="card"><span>Receitas</span><strong><?= money((float)$totals['income']) ?></strong></article>
        <article class="card"><span>Despesas</span><strong><?= money((float)$totals['expense']) ?></strong></article>
        <article class="card"><span>Pendentes</span><strong><?= money((float)$totals['pending']) ?></strong></article>
        <article class="card"><span>Vencidas</span><strong><?= money((float)$totals['overdue']) ?></strong></article>
    </section>

    <section class="card">
        <h2>Nova categoria</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_category">
            <label>Nome<input type="text" name="name" maxlength="120" required></label>
            <label>Tipo<select name="type"><option value="expense">Despesa</option><option value="income">Receita</option></select></label>
            <div class="full"><button class="button" type="submit">Salvar categoria</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Novo lançamento</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_entry">
            <label>Tipo<select name="entry_type"><option value="expense">Despesa</option><option value="income">Receita</option></select></label>
            <label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h((string)$c['name']) ?></option><?php endforeach; ?></select></label>
            <label>Descrição<input type="text" name="description" maxlength="180" required></label>
            <label>Valor<input type="number" name="amount" min="0.01" step="0.01" required></label>
            <label>Data do lançamento<input type="date" name="entry_date" value="<?= h(date('Y-m-d')) ?>" required></label>
            <label>Vencimento<input type="date" name="due_date"></label>
            <label>Status<select name="status"><option value="pending">Pendente</option><option value="paid">Pago</option><option value="received">Recebido</option><option value="overdue">Vencido</option></select></label>
            <label>Forma de pagamento<select name="payment_method"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Débito</option><option value="credito">Crédito</option><option value="boleto">Boleto</option><option value="transferencia">Transferência</option><option value="outro">Outro</option></select></label>
            <label>Referência<input type="text" name="reference_key" maxlength="120" placeholder="Código/nota/fornecedor"></label>
            <label class="full">Observações<textarea name="notes" rows="3" maxlength="255"></textarea></label>
            <div class="full"><button class="button" type="submit">Registrar lançamento</button></div>
        </form>
    </section>

    <section class="card">
        <h2>Filtros</h2>
        <form method="get" class="search-form">
            <select name="type"><option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Todos os tipos</option><option value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>Receita</option><option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>Despesa</option></select>
            <select name="status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos os status</option><option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pendente</option><option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pago</option><option value="received" <?= $statusFilter === 'received' ? 'selected' : '' ?>>Recebido</option><option value="overdue" <?= $statusFilter === 'overdue' ? 'selected' : '' ?>>Vencido</option><option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelado</option></select>
            <select name="category_id"><option value="">Todas as categorias</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>><?= h((string)$c['name']) ?></option><?php endforeach; ?></select>
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
            <button class="button" type="submit">Filtrar</button>
        </form>
    </section>

    <section class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Tipo</th><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Status</th><th>Vencimento</th><th>Responsável</th><th>Data</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?= h((string)($entry['entry_type'] === 'income' ? 'Receita' : 'Despesa')) ?></td>
                            <td><?= h((string)$entry['description']) ?></td>
                            <td><?= h((string)($entry['category_name'] ?? '—')) ?></td>
                            <td><?= money((float)$entry['amount']) ?></td>
                            <td><span class="pill <?= $entry['status'] === 'overdue' || $entry['status'] === 'cancelled' ? 'warn' : 'info' ?>"><?= h(statusLabel((string)$entry['status'])) ?></span></td>
                            <td><?= h((string)($entry['due_date'] ?? '—')) ?></td>
                            <td><?= h((string)($entry['user_name'] ?? '—')) ?></td>
                            <td><?= h((string)$entry['entry_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
