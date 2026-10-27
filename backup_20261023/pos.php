<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
$error = '';
$receipt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
  $cart = json_decode((string)($_POST['cart'] ?? '[]'), true);
  $methods = $_POST['method'] ?? [];
  $amounts = $_POST['amount'] ?? [];
  if (!is_array($cart) || !$cart) $error = 'Adicione pelo menos um produto à venda.';
  else {
    try {
      $pdo->beginTransaction();
      $items = []; $total = 0.0;
      $find = $pdo->prepare("SELECT id,barcode,name,cost_price,sale_price,stock_qty,active FROM products WHERE barcode=? FOR UPDATE");
      foreach ($cart as $line) {
        $barcode = trim((string)($line['barcode'] ?? ''));
        $qty = (float)($line['qty'] ?? 0);
        if ($barcode === '' || $qty <= 0) throw new RuntimeException('Produto ou quantidade inválida.');
        $find->execute([$barcode]); $p = $find->fetch();
        if (!$p || !(int)$p['active']) throw new RuntimeException("Produto não encontrado ou inativo: {$barcode}");
        if ((float)$p['stock_qty'] < $qty) throw new RuntimeException("Estoque insuficiente para {$p['name']}.");
        $lineTotal = round((float)$p['sale_price'] * $qty, 2);
        $total += $lineTotal;
        $items[] = ['p'=>$p,'qty'=>$qty,'line'=>$lineTotal];
      }
      $payments = []; $paid = 0.0;
      foreach ($methods as $i => $method) {
        $amount = round((float)str_replace(',', '.', (string)($amounts[$i] ?? 0)), 2);
        if ($amount > 0 && in_array($method, ['dinheiro','pix','debito','credito','outro'], true)) {
          $payments[] = [$method,$amount]; $paid += $amount;
        }
      }
      if (abs(round($paid,2) - round($total,2)) > 0.009) throw new RuntimeException('A soma dos pagamentos precisa ser igual ao total da venda.');
      $s = $pdo->prepare("INSERT INTO sales (total) VALUES (?)"); $s->execute([round($total,2)]);
      $saleId = (int)$pdo->lastInsertId();
      $ins = $pdo->prepare("INSERT INTO sale_items (sale_id,product_id,product_name,barcode,quantity,unit_price,unit_cost,line_total) VALUES (?,?,?,?,?,?,?,?)");
      $upd = $pdo->prepare("UPDATE products SET stock_qty=stock_qty-? WHERE id=? AND stock_qty>=?");
      $mov = $pdo->prepare("INSERT INTO stock_movements (product_id,movement_type,quantity,reference_id,notes) VALUES (?,'sale',?,?,?)");
      foreach ($items as $it) {
        $p = $it['p']; $qty = $it['qty'];
        $ins->execute([$saleId,$p['id'],$p['name'],$p['barcode'],$qty,$p['sale_price'],$p['cost_price'],$it['line']]);
        $upd->execute([$qty,$p['id'],$qty]);
        if ($upd->rowCount() !== 1) throw new RuntimeException("Estoque alterado durante a venda: {$p['name']}.");
        $mov->execute([$p['id'],-$qty,$saleId,"Venda #{$saleId}"]);
      }
      $pay = $pdo->prepare("INSERT INTO sale_payments (sale_id,method,amount) VALUES (?,?,?)");
      foreach ($payments as $payment) $pay->execute([$saleId,$payment[0],$payment[1]]);
      $pdo->commit();
      $receipt = ['id'=>$saleId,'total'=>round($total,2)];
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $error = $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao registrar a venda. Nenhuma alteração foi confirmada.';
    }
  }
}
?>
<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PDV | Tô em Casa</title><link rel="stylesheet" href="style.css"></head><body>
<header><div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div><nav><a href="index.php">Início</a><a href="products.php">Produtos</a><a class="active" href="pos.php">PDV / Vendas</a></nav></header><main><h1>Frente de caixa</h1><p class="muted">Clique no campo e leia o código de barras. O leitor deve enviar Enter ao final da leitura.</p>
<?php if($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?><?php if($receipt): ?><div class="success">Venda #<?= (int)$receipt['id'] ?> concluída — total <?= money((float)$receipt['total']) ?>.</div><?php endif; ?>
<section class="card"><label class="scan-label">Código de barras<input id="barcode" autocomplete="off" autofocus placeholder="Passe o leitor ou digite o código e pressione Enter"></label><p id="scan-message" class="muted" aria-live="polite"></p>
<div class="table-wrap"><table><thead><tr><th>Produto</th><th>Preço</th><th>Qtd.</th><th>Total</th><th></th></tr></thead><tbody id="cart-body"><tr><td colspan="5">Nenhum produto adicionado.</td></tr></tbody></table></div>
<div class="total-row">Total da venda <strong id="total">R$ 0,00</strong></div>
<form method="post" id="checkout-form"><input type="hidden" name="action" value="checkout"><input type="hidden" name="cart" id="cart-json"><h2>Pagamento</h2><div id="payments"><div class="payment-row"><select name="method[]"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Cartão de débito</option><option value="credito">Cartão de crédito</option><option value="outro">Outro</option></select><input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required></div></div><div class="actions"><button type="button" class="button secondary" id="add-payment">Adicionar forma de pagamento</button><button type="submit" class="button" id="finish">Finalizar venda</button></div></form></section></main>
<script>
const input = document.getElementById('barcode'), tbody = document.getElementById('cart-body'), totalEl = document.getElementById('total');
const cart = new Map(); let busy = false;
const money = n => n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
function total(){let n=0;for(const x of cart.values())n+=x.price*x.qty;return Math.round((n+Number.EPSILON)*100)/100;}
function render(){
  tbody.innerHTML=''; if(!cart.size){tbody.innerHTML='<tr><td colspan="5">Nenhum produto adicionado.</td></tr>';}
  for(const [barcode,p] of cart){const tr=document.createElement('tr');
    const name=document.createElement('td'); name.textContent=p.name; const price=document.createElement('td');price.textContent=money(p.price);
    const q=document.createElement('td');const qty=document.createElement('input');qty.type='number';qty.min='0.001';qty.step='0.001';qty.value=p.qty;qty.style.width='90px';qty.addEventListener('change',()=>{const v=Number(qty.value);if(v<=0){cart.delete(barcode)}else{p.qty=v;}render();});q.appendChild(qty);
    const line=document.createElement('td');line.textContent=money(p.price*p.qty);const act=document.createElement('td');const del=document.createElement('button');del.type='button';del.className='button danger small';del.textContent='Remover';del.addEventListener('click',()=>{cart.delete(barcode);render();});act.appendChild(del);
    tr.append(name,price,q,line,act);tbody.appendChild(tr);
  }
  totalEl.textContent=money(total());document.getElementById('cart-json').value=JSON.stringify([...cart].map(([barcode,p])=>({barcode,qty:p.qty})));
}
input.addEventListener('keydown',async e=>{
  if(e.key!=='Enter')return;e.preventDefault();if(busy)return;const code=input.value.trim();if(!code)return;
  busy=true;input.disabled=true;
  try{
    const r=await fetch('product_lookup.php?barcode='+encodeURIComponent(code),{headers:{'Accept':'application/json'}});
    const data=await r.json();
    if(!r.ok||!data.ok){document.getElementById('scan-message').textContent=data.message||'Produto não encontrado. Cadastre-o antes de vender.';return;}
    const p=data.product;const existing=cart.get(p.barcode);
    if(existing){existing.qty+=1;}else{cart.set(p.barcode,{...p,qty:1});}
    document.getElementById('scan-message').textContent=p.name+' adicionado.';
    render();
  }catch(err){document.getElementById('scan-message').textContent='Falha na consulta. Verifique a conexão com o sistema.';}
  finally{input.disabled=false;input.value='';input.focus();busy=false;}
});
document.getElementById('add-payment').addEventListener('click',()=>{
  const row=document.createElement('div');row.className='payment-row';
  row.innerHTML='<select name="method[]"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Cartão de débito</option><option value="credito">Cartão de crédito</option><option value="outro">Outro</option></select><input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required>';
  document.getElementById('payments').appendChild(row);
});
document.getElementById('checkout-form').addEventListener('submit',e=>{
  if(!cart.size){e.preventDefault();alert('Adicione produtos antes de finalizar.');return;}
  const sum=[...document.querySelectorAll('input[name="amount[]"]')].reduce((s,x)=>s+Number(x.value||0),0);
  if(Math.round(sum*100)!==Math.round(total()*100)){e.preventDefault();alert('A soma dos pagamentos precisa ser igual ao total da venda.');return;}
  document.getElementById('cart-json').value=JSON.stringify([...cart].map(([barcode,p])=>({barcode,qty:p.qty})));
  document.getElementById('finish').disabled=true;
});
render();
</script></body></html>
