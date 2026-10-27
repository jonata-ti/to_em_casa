<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function percent(float $v): string { return number_format($v, 2, ',', '.') . '%'; }

$selectedId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$openModal = isset($_GET['open']) || $selectedId > 0;
$saveMessage = '';
$error = '';

$form = [
    'id' => '',
    'barcode' => '',
    'name' => '',
    'category_id' => '',
    'brand' => '',
    'unit' => 'un',
    'cost_price' => '0',
    'sale_price' => '0',
    'stock_qty' => '0',
    'min_stock' => '0',
    'expires_on' => '',
    'active' => '1',
];

if ($selectedId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$selectedId]);
    $product = $stmt->fetch();
    if ($product) {
        $form = [
            'id' => (string)$product['id'],
            'barcode' => (string)$product['barcode'],
            'name' => (string)$product['name'],
            'category_id' => $product['category_id'] !== null ? (string)(int)$product['category_id'] : '',
            'brand' => (string)($product['brand'] ?? ''),
            'unit' => (string)$product['unit'],
            'cost_price' => number_format((float)$product['cost_price'], 2, '.', ''),
            'sale_price' => number_format((float)$product['sale_price'], 2, '.', ''),
            'stock_qty' => number_format((float)$product['stock_qty'], 3, '.', ''),
            'min_stock' => number_format((float)$product['min_stock'], 3, '.', ''),
            'expires_on' => $product['expires_on'] ? (string)$product['expires_on'] : '',
            'active' => (string)(int)$product['active'],
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'create');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare('SELECT name, barcode FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new RuntimeException('Produto não encontrado para exclusão.');
        }

        $pdo->prepare('DELETE FROM stock_movements WHERE product_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        logAudit($pdo, 'products', 'products', $id, 'delete', 'Produto excluído: ' . $product['name'] . ' (' . $product['barcode'] . ')', (int)$user['id']);
        header('Location: products.php?deleted=1');
        exit;
    }

    $barcode = normalizeBarcode((string)($_POST['barcode'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $brand = trim((string)($_POST['brand'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? 'un'));
    $cost = (float)str_replace(',', '.', (string)($_POST['cost_price'] ?? '0'));
    $sale = (float)str_replace(',', '.', (string)($_POST['sale_price'] ?? '0'));
    $stock = (float)str_replace(',', '.', (string)($_POST['stock_qty'] ?? '0'));
    $minStock = (float)str_replace(',', '.', (string)($_POST['min_stock'] ?? '0'));
    $expires = trim((string)($_POST['expires_on'] ?? ''));
    $active = isset($_POST['active']) ? (int)$_POST['active'] : 0;

    if ($barcode === '' || $name === '') {
        $error = 'Informe o código de barras e o nome do produto.';
    } elseif ($cost < 0 || $sale < 0 || $stock < 0 || $minStock < 0) {
        $error = 'Valores e quantidade não podem ser negativos.';
    } elseif ($sale > 0 && $cost > $sale) {
        $error = 'O preço de venda não pode ser menor que o custo.';
    } else {
        try {
            $pdo->beginTransaction();
            if ($action === 'update' && $id > 0) {
                $exists = $pdo->prepare('SELECT id FROM products WHERE barcode = ? AND id != ? LIMIT 1');
                $exists->execute([$barcode, $id]);
                if ($exists->fetch()) {
                    throw new RuntimeException('Já existe outro produto com este código de barras.');
                }
                $update = $pdo->prepare('UPDATE products SET barcode=?, name=?, category_id=?, brand=?, unit=?, cost_price=?, sale_price=?, stock_qty=?, min_stock=?, expires_on=?, active=? WHERE id=?');
                $update->execute([$barcode, $name, $categoryId, $brand !== '' ? $brand : null, $unit, $cost, $sale, $stock, $minStock, $expires !== '' ? $expires : null, $active, $id]);
                logAudit($pdo, 'products', 'products', $id, 'update', 'Produto atualizado: ' . $name, (int)$user['id']);
                $saveMessage = 'Produto atualizado com sucesso.';
            } else {
                $exists = $pdo->prepare('SELECT id FROM products WHERE barcode = ? LIMIT 1');
                $exists->execute([$barcode]);
                if ($exists->fetch()) {
                    throw new RuntimeException('Este código de barras já está cadastrado.');
                }
                $insert = $pdo->prepare('INSERT INTO products (barcode, name, category_id, brand, unit, cost_price, sale_price, stock_qty, min_stock, expires_on, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->execute([$barcode, $name, $categoryId, $brand !== '' ? $brand : null, $unit, $cost, $sale, $stock, $minStock, $expires !== '' ? $expires : null, $active]);
                $newProductId = (int)$pdo->lastInsertId();
                if ($stock > 0) {
                    $move = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, before_qty, after_qty, movement_reason, notes, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
                    $move->execute([(int)$newProductId, 'initial', (float)$stock, 0.0, (float)$stock, 'Cadastro inicial', 'Estoque inicial no cadastro', (int)$user['id']]);
                }
                logAudit($pdo, 'products', 'products', $newProductId, 'create', 'Produto cadastrado: ' . $name, (int)$user['id']);
                $saveMessage = 'Produto cadastrado com sucesso.';
            }
            $pdo->commit();
            header('Location: products.php?' . ($action === 'update' ? 'edited=1' : 'saved=1'));
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $e->getMessage() ?: 'Não foi possível salvar o produto. Tente novamente.';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : 0;
$statusFilter = $_GET['status'] ?? 'all';

$categories = $pdo->query('SELECT id, name FROM categories WHERE active = 1 ORDER BY name')->fetchAll();

$sql = 'SELECT p.*, c.name AS category_name, ((p.sale_price - p.cost_price) / NULLIF(p.sale_price, 0)) * 100 AS margin_pct FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.barcode LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($categoryFilter > 0) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryFilter;
}
if ($statusFilter === 'active') {
    $sql .= ' AND p.active = 1';
} elseif ($statusFilter === 'inactive') {
    $sql .= ' AND p.active = 0';
}
$sql .= ' ORDER BY p.name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$alerts = [];
foreach ($products as $p) {
    $stockQty = (float)$p['stock_qty'];
    $minStock = (float)$p['min_stock'];
    if ($stockQty <= $minStock) {
        $alerts[] = ['type' => 'estoque', 'name' => (string)$p['name'], 'message' => 'Estoque baixo: ' . $p['stock_qty'] . ' em ' . $p['unit']];
    }
    if (!empty($p['expires_on'])) {
        $expires = new DateTimeImmutable((string)$p['expires_on']);
        $today = new DateTimeImmutable('today');
        $diff = (int)$today->diff($expires)->format('%r%a');
        if ($diff <= 30 && $diff >= 0) {
            $alerts[] = ['type' => 'validade', 'name' => (string)$p['name'], 'message' => 'Validade próxima: ' . $expires->format('d/m/Y')];
        }
    }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Produtos | Tô em Casa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
    <nav class="main-menu">
        <a href="index.php">Início</a>
        <a class="active" href="products.php?open=1">Produtos</a>
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
    <h1>Cadastro de produtos</h1>
    <p class="muted">Cadastre, pesquise, edite e acompanhe margens e alertas do estoque.</p>

    <?php if ($error !== ''): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if (isset($_GET['saved'])): ?><div class="success">Produto cadastrado com sucesso.</div><?php endif; ?>
    <?php if (isset($_GET['edited'])): ?><div class="success">Produto atualizado com sucesso.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="success">Produto excluído com sucesso.</div><?php endif; ?>

    <?php if ($alerts): ?>
        <section class="card">
            <h2>Alertas</h2>
            <ul class="alert-list">
                <?php foreach ($alerts as $alert): ?>
                    <li class="alert-item <?= $alert['type'] === 'estoque' ? 'warn' : 'info' ?>"><?= h($alert['name']) ?> — <?= h($alert['message']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="actions" style="margin: 18px 0 12px;">
        <button type="button" class="button" id="open-product-modal">Novo produto</button>
    </div>

    <div id="product-modal" class="modal <?= $openModal ? 'open' : '' ?>">
        <div class="modal-backdrop" data-close="modal"></div>
        <div class="modal-card">
            <div class="modal-header">
                <h2><?= $selectedId > 0 ? 'Editar produto' : 'Cadastrar produto' ?></h2>
                <button type="button" class="button secondary small" data-close="modal">Fechar</button>
            </div>

            <form method="post" class="card form-grid" autocomplete="off">
                <input type="hidden" name="action" value="<?= $selectedId > 0 ? 'update' : 'create' ?>">
                <?php if ($selectedId > 0): ?><input type="hidden" name="id" value="<?= (int)$selectedId ?>"><?php endif; ?>
                <label>Código de barras *<input name="barcode" required maxlength="64" value="<?= h($form['barcode']) ?>" placeholder="Leia ou digite o código"></label>
                <label>Nome do produto *<input name="name" required maxlength="160" value="<?= h($form['name']) ?>" placeholder="Ex.: Refrigerante 2 L"></label>
                <label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ((string)(int)$c['id'] === (string)$form['category_id']) ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
                <label>Marca<input name="brand" maxlength="100" value="<?= h($form['brand']) ?>"></label>
                <label>Unidade<select name="unit">
                    <?php $units = ['un'=>'Unidade','lata'=>'Lata','garrafa'=>'Garrafa','pacote'=>'Pacote','kg'=>'Kg','g'=>'g','ml'=>'ml','l'=>'Litro']; foreach($units as $key => $label): ?><option value="<?= h($key) ?>" <?= $form['unit'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                </select></label>
                <label>Custo de compra<span class="muted-small">(R$)</span><input name="cost_price" type="number" min="0" step="0.01" value="<?= h($form['cost_price']) ?>" required></label>
                <label>Preço de venda<span class="muted-small">(R$)</span><input name="sale_price" type="number" min="0" step="0.01" value="<?= h($form['sale_price']) ?>" required></label>
                <label>Estoque atual<input name="stock_qty" type="number" min="0" step="0.001" value="<?= h($form['stock_qty']) ?>" required></label>
                <label>Estoque mínimo<input name="min_stock" type="number" min="0" step="0.001" value="<?= h($form['min_stock']) ?>" required></label>
                <label>Validade (opcional)<input name="expires_on" type="date" value="<?= h($form['expires_on']) ?>"></label>
                <label>Status<select name="active"><option value="1" <?= $form['active'] === '1' ? 'selected' : '' ?>>Ativo</option><option value="0" <?= $form['active'] === '0' ? 'selected' : '' ?>>Inativo</option></select></label>
                <div class="full"><button class="button" type="submit"><?= $selectedId > 0 ? 'Salvar alterações' : 'Salvar produto' ?></button><?php if ($selectedId > 0): ?><a class="button secondary" href="products.php">Cancelar edição</a><?php endif; ?></div>
            </form>
        </div>
    </div>

    <section class="card">
        <h2>Pesquisar produtos</h2>
        <form method="get" class="search-form">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Nome ou código de barras">
            <select name="category_id">
                <option value="">Todas as categorias</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $categoryFilter === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
            </select>
            <select name="status">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Ativos</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inativos</option>
            </select>
            <button type="submit" class="button">Filtrar</button>
        </form>
    </section>

    <section class="card">
        <h2>Produtos cadastrados</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Estoque</th>
                        <th>Custo</th>
                        <th>Venda</th>
                        <th>Margem</th>
                        <th>Validade</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                        <?php $margin = 0.0; if ((float)$p['sale_price'] > 0) { $margin = ((float)$p['sale_price'] - (float)$p['cost_price']) / (float)$p['sale_price'] * 100; } ?>
                        <tr>
                            <td><?= h((string)$p['barcode']) ?></td>
                            <td><?= h((string)$p['name']) ?></td>
                            <td><?= h((string)($p['category_name'] ?? '—')) ?></td>
                            <td><?= h((string)$p['stock_qty']) ?> <?= h((string)$p['unit']) ?><?php if ((float)$p['stock_qty'] <= (float)$p['min_stock']): ?> <span class="pill warn">Baixo</span><?php endif; ?></td>
                            <td><?= money((float)$p['cost_price']) ?></td>
                            <td><?= money((float)$p['sale_price']) ?></td>
                            <td><?= percent($margin) ?></td>
                            <td><?= !empty($p['expires_on']) ? h((string)$p['expires_on']) : '—' ?></td>
                            <td><?= (int)$p['active'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                            <td><div class="actions" style="margin-top:0;"><a class="button small secondary" href="products.php?edit=<?= (int)$p['id'] ?>">Editar</a><form method="post" style="display:inline;" onsubmit="return confirm('Deseja realmente excluir este produto?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit" class="button small danger">Excluir</button></form></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script>
const modal = document.getElementById('product-modal');
const openBtn = document.getElementById('open-product-modal');
const closeButtons = document.querySelectorAll('[data-close="modal"]');

function closeProductModal() {
  if (!modal) return;
  modal.classList.remove('open');
  modal.style.display = 'none';

  const url = new URL(window.location.href);
  url.searchParams.delete('open');
  window.history.replaceState({}, '', url);
}

if (modal && modal.classList.contains('open')) {
  modal.style.display = 'flex';
}

if (openBtn) {
  openBtn.addEventListener('click', () => {
    if (!modal) return;
    modal.classList.add('open');
    modal.style.display = 'flex';

    const url = new URL(window.location.href);
    url.searchParams.set('open', '1');
    window.history.replaceState({}, '', url);
  });
}

closeButtons.forEach((button) => {
  button.addEventListener('click', closeProductModal);
});

if (modal) {
  modal.addEventListener('click', (event) => {
    if (event.target && event.target.matches('.modal-backdrop')) {
      closeProductModal();
    }
  });
}
</script>
</body>
</html>
