<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$barcode = normalizeBarcode((string)($_GET['barcode'] ?? ''));
if ($barcode === '' || strlen($barcode) > 64) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Código de barras inválido.'], JSON_UNESCAPED_UNICODE);
  exit;
}
$stmt = $pdo->prepare("SELECT barcode,name,sale_price,stock_qty,unit FROM products WHERE UPPER(REPLACE(REPLACE(REPLACE(barcode, ' ', ''), '-', ''), '.', '')) = ? AND active=1 LIMIT 1");
$stmt->execute([$barcode]);
$p = $stmt->fetch();
if (!$p) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'message'=>'Produto não encontrado. Cadastre-o antes de vender.'], JSON_UNESCAPED_UNICODE);
  exit;
}
if ((float)$p['stock_qty'] <= 0) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'message'=>'Produto sem estoque disponível.'], JSON_UNESCAPED_UNICODE);
  exit;
}
echo json_encode(['ok'=>true,'product'=>[
  'barcode'=>$p['barcode'],'name'=>$p['name'],'price'=>(float)$p['sale_price'],
  'stock'=>(float)$p['stock_qty'],'unit'=>$p['unit']
]], JSON_UNESCAPED_UNICODE);
