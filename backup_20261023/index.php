<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$products = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn();
$sales = (int)$pdo->query("SELECT COUNT(*) FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
$today = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM sales WHERE status='completed' AND DATE(created_at)=CURDATE()")->fetchColumn();
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tô em Casa | Gestão</title><link rel="stylesheet" href="style.css"></head>
<body><header><div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div><nav><a class="active" href="index.php">Início</a><a href="products.php">Produtos</a><a href="pos.php">PDV / Vendas</a></nav></header>
<main><h1>Painel de gestão</h1><p class="muted">Resumo de hoje</p><section class="cards"><article class="card"><span>Produtos ativos</span><strong><?= $products ?></strong></article><article class="card"><span>Vendas hoje</span><strong><?= $sales ?></strong></article><article class="card"><span>Faturamento hoje</span><strong><?= money($today) ?></strong></article></section>
<section class="card"><h2>Acesso rápido</h2><p>Cadastre seus produtos com código de barras e use o leitor no PDV para registrar vendas.</p><div class="actions"><a class="button" href="products.php">Cadastrar produtos</a><a class="button secondary" href="pos.php">Abrir PDV</a></div></section>
<p class="notice">MVP inicial: use em ambiente local e valide os procedimentos antes de operar comercialmente.</p></main></body></html>
