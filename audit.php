<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$userFilter = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : 0;
$typeFilter = trim((string)($_GET['event_type'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$sql = 'SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE 1=1';
$params = [];
if ($userFilter > 0) { $sql .= ' AND al.user_id = ?'; $params[] = $userFilter; }
if ($typeFilter !== '') { $sql .= ' AND al.event_type = ?'; $params[] = $typeFilter; }
if ($dateFrom !== '') { $sql .= ' AND al.created_at >= ?'; $params[] = $dateFrom . ' 00:00:00'; }
if ($dateTo !== '') { $sql .= ' AND al.created_at <= ?'; $params[] = $dateTo . ' 23:59:59'; }
$sql .= ' ORDER BY al.created_at DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
$users = $pdo->query('SELECT id, username FROM users ORDER BY username ASC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auditoria | Tô em Casa</title>
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
        <a class="active" href="audit.php">Auditoria</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Registro de auditoria</h1>
    <p class="muted">Eventos de login, produtos, estoque, vendas, caixa e financeiros.</p>
    <section class="card">
        <form method="get" class="search-form">
            <select name="user_id"><option value="">Todos os usuários</option><?php foreach ($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $userFilter === (int)$u['id'] ? 'selected' : '' ?>><?= h((string)$u['username']) ?></option><?php endforeach; ?></select>
            <input type="text" name="event_type" value="<?= h($typeFilter) ?>" placeholder="Tipo do evento">
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
            <button class="button" type="submit">Filtrar</button>
        </form>
    </section>
    <section class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Data</th><th>Evento</th><th>Operação</th><th>Entidade</th><th>Usuário</th><th>Detalhes</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= h((string)$log['created_at']) ?></td>
                            <td><?= h((string)$log['event_type']) ?></td>
                            <td><?= h((string)$log['action']) ?></td>
                            <td><?= h((string)$log['entity_type']) ?> #<?= (int)$log['entity_id'] ?></td>
                            <td><?= h((string)($log['username'] ?? 'Sistema')) ?></td>
                            <td><?= h((string)($log['details'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
