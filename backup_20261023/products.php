<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $barcode = trim((string)($_POST['barcode'] ?? ''));
  $name = trim((string)($_POST['name'] ?? ''));
  $categoryId = (($_POST['category_id'] ?? '') !== '') ? (int)$_POST['category_id'] : null;
  $brand = trim((string)($_POST['brand'] ?? ''));
  $unit = trim((string)($_POST['unit'] ?? 'un'));
  $cost = (float)str_replace(',', '.', (string)($_POST['cost_price'] ?? '0'));
  $price = (float)str_replace(',', '.', (string)($_POST['sale_price'] ?? '0'));
  $stock = (float)str_replace(',', '.', (string)($_POST['stock_qty'] ?? '0'));
  $min = (float)str_replace(',', '.', (string)($_POST['min_stock'] ?? '0'));
  $expires = trim((string)($_POST['expires_on'] ?? ''));
  if ($barcode === '' || $name === '') $error = 'Informe o código de barras e o nome do produto.';
  elseif ($cost < 0 || $price < 0 || $stock < 0 || $min < 0) $error = 'Valores e estoque não podem ser negativos.';
  else {
    try {
      $pdo->beginTransaction();
      $stmt = $pdo->prepare("INSERT INTO products (barcode,name,category_id,brand,unit,cost_price,sale_price,stock_qty,min_stock,expires_on) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$barcode,$name,$categoryId,$brand ?: null,$unit,$cost,$price,$stock,$min,$expires ?: null]);
      $id = (int)$pdo->lastInsertId();
      if ($stock > 0) {
        $m = $pdo->prepare("INSERT INTO stock_movements (product_id,movement_type,quantity,notes) VALUES (?,'initial',?,'Estoque inicial no cadastro')");
        $m->execute([$id,$stock]);
      }
      $pdo->commit();
      header('Location: products.php?saved=1'); exit;
    } catch (PDOException $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = $e->getCode() === '23000' ? 'Esse código de barras já está cadastrado.' : 'Não foi possível salvar o produto. Confira os dados.';
    }
  }
}
$categories = $pdo->query("SELECT id,name FROM categories WHERE active=1 ORDER BY name")->fetchAll();
$products = $pdo->query("SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC LIMIT 200")->fetchAll();
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Produtos | Tô em Casa</title><link rel="stylesheet" href="style.css"></head><body>
<header><div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div><nav><a href="index.php">Início</a><a class="active" href="products.php">Produtos</a><a href="pos.php">PDV / Vendas</a></nav></header><main><h1>Cadastro de produtos</h1><p class="muted">Informe o código de barras impresso na embalagem ou use o leitor no campo abaixo.</p>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?><?php if (isset($_GET['saved'])): ?><div class="success">Produto cadastrado com sucesso.</div><?php endif; ?>
<form method="post" class="card form-grid" autocomplete="off"><label>Código de barras *<input name="barcode" required autofocus maxlength="64" placeholder="Leia ou digite o código"></label><label>Nome do produto *<input name="name" required maxlength="160" placeholder="Ex.: Refrigerante 2 L"></label>
<label>Categoria<select name="category_id"><option value="">Sem categoria</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?></select></label><label>Marca<input name="brand" maxlength="100"></label>
<label>Unidade<select name="unit"><option value="un">Unidade</option><option value="lata">Lata</option><option value="garrafa">Garrafa</option><option value="pacote">Pacote</option><option value="kg">Kg</option><option value="g">g</option><option value="ml">ml</option><option value="l">Litro</option></select></label>
<label>Custo de compra (R$)<input name="cost_price" type="number" min="0" step="0.01" value="0" required></label><label>Preço de venda (R$)<input name="sale_price" type="number" min="0" step="0.01" value="0" required></label><label>Estoque inicial<input name="stock_qty" type="number" min="0" step="0.001" value="0" required></label><label>Estoque mínimo<input name="min_stock" type="number" min="0" step="0.001" value="0" required></label><label>Validade (opcional)<input name="expires_on" type="date"></label><div class="full"><button class="button" type="submit">Salvar produto</button></div></form>
<section class="card"><h2>Produtos cadastrados</h2><div class="table-wrap"><table><thead><tr><th>Código</th><th>Produto</th><th>Categoria</th><th>Custo</th><th>Venda</th><th>Estoque</th><th>Status</th></tr></thead><tbody><?php foreach($products as $p): ?><tr><td><?= h($p['barcode']) ?></td><td><?= h($p['name']) ?></td><td><?= h($p['category_name'] ?? '—') ?></td><td><?= money((float)$p['cost_price']) ?></td><td><?= money((float)$p['sale_price']) ?></td><td><?= h((string)$p['stock_qty']) ?> <?= h($p['unit']) ?><?php if((float)$p['stock_qty'] <= (float)$p['min_stock']): ?> <span class="pill warn">Baixo</span><?php endif; ?></td><td><?= $p['active'] ? 'Ativo' : 'Inativo' ?></td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
