<?php
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID','Número da venda','Total da venda','Data e hora','Vendedor','Meio de pagamento','Valor recebido'], ';', '"', '\\');
$rows = [
    [1,'V20261103-001','1.500,50','2026-11-03 10:30:00','José da Silva','pix','30,00'],
    [2,'V20261103-002','100,00','2026-11-03 12:00:00','Maria Ângela','dinheiro','100,00'],
];
foreach ($rows as $row) {
    fputcsv($out, $row, ';', '"', '\\');
}
