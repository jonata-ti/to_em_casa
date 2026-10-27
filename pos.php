<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin','vendedor']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function pixPayload(string $pixKey, string $description, float $amount, string $merchantName = 'TÔ EM CASA'): string
{
    $cleanKey = preg_replace('/\s+/', '', $pixKey) ?: 'SEU_PIX_KEY_AQUI';
    $description = preg_replace('/[^A-Za-z0-9 ._-]/u', '', $description);
    $description = substr($description, 0, 140);
    $payload = '000201' .
        '26' . '0014BR.GOV.BCB.PIX' .
        '01' . '12' . '0014' . 'BR.GOV.BCB.PIX' .
        '26' . '0014' . 'BR.GOV.BCB.PIX' .
        '29' . strlen($cleanKey) . $cleanKey .
        '54' . '20' . '0100' .
        '52' . '04' . '0000' .
        '53' . '98' . 'BR' .
        '58' . 'BR' .
        '59' . $merchantName .
        '60' . '10' . 'BRASIL' .
        '62' . '05' . '04' .
        '63' . '04' .
        '64' . '16' .
        '65' . '04';
    return $payload;
}

$error = '';
$success = '';
$receipt = null;
$pixQr = null;

$cash = $pdo->prepare('SELECT * FROM cash_registers WHERE status = ? AND opened_by_user_id = ? ORDER BY opened_at DESC LIMIT 1');
$cash->execute(['open', $user['id']]);
$cashRegister = $cash->fetch();
if (!$cashRegister && $user['role'] === 'vendedor') {
    $error = 'É necessário abrir o caixa antes de vender.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Requisição inválida. Tente novamente.';
    } else {
        $cart = json_decode((string)($_POST['cart'] ?? '[]'), true);
        $methods = $_POST['method'] ?? [];
        $amounts = $_POST['amount'] ?? [];
        $discount = (float)str_replace(',', '.', (string)($_POST['discount'] ?? '0'));

        if (!is_array($cart) || !$cart) {
            $error = 'Adicione pelo menos um produto à venda.';
        } elseif ($discount < 0) {
            $error = 'Desconto não pode ser negativo.';
        } else {
            try {
                $pdo->beginTransaction();
                $cashCheck = $pdo->prepare('SELECT * FROM cash_registers WHERE status = ? ORDER BY opened_at DESC LIMIT 1');
                $cashCheck->execute(['open']);
                $activeCash = $cashCheck->fetch();
                if (!$activeCash) {
                    throw new RuntimeException('Não existe caixa aberto no momento.');
                }

                $items = [];
                $total = 0.0;
                $find = $pdo->prepare('SELECT id, barcode, name, cost_price, sale_price, stock_qty, active FROM products WHERE barcode = ? LIMIT 1 FOR UPDATE');
                foreach ($cart as $line) {
                    $barcode = trim((string)($line['barcode'] ?? ''));
                    $qty = (float)($line['qty'] ?? 0);
                    if ($barcode === '' || $qty <= 0) {
                        throw new RuntimeException('Produto ou quantidade inválida.');
                    }
                    $find->execute([$barcode]);
                    $p = $find->fetch();
                    if (!$p || !(int)$p['active']) {
                        throw new RuntimeException('Produto não encontrado ou inativo: ' . $barcode);
                    }
                    if ((float)$p['stock_qty'] < $qty) {
                        throw new RuntimeException('Estoque insuficiente para ' . $p['name'] . '.');
                    }
                    $lineTotal = round((float)$p['sale_price'] * $qty, 2);
                    $total += $lineTotal;
                    $items[] = ['p' => $p, 'qty' => $qty, 'line' => $lineTotal];
                }

                $grandTotal = round($total - $discount, 2);
                if ($grandTotal < 0) {
                    throw new RuntimeException('O desconto não pode exceder o total da venda.');
                }

                $payments = [];
                $paid = 0.0;
                foreach ($methods as $i => $method) {
                    $amount = round((float)str_replace(',', '.', (string)($amounts[$i] ?? 0)), 2);
                    if ($amount <= 0) {
                        continue;
                    }
                    if (!in_array($method, ['dinheiro','pix','debito','credito','outro'], true)) {
                        throw new RuntimeException('Forma de pagamento inválida.');
                    }
                    $payments[] = ['method' => $method, 'amount' => $amount];
                    $paid += $amount;
                }

                if (count($payments) === 0) {
                    throw new RuntimeException('Informe ao menos uma forma de pagamento.');
                }
                if (abs(round($paid, 2) - round($grandTotal, 2)) > 0.009) {
                    throw new RuntimeException('A soma dos pagamentos deve cobrir o total da venda.');
                }

                $saleNumber = 'V' . date('YmdHis') . '-' . random_int(100, 999);
                $saleStmt = $pdo->prepare('INSERT INTO sales (sale_number, total, status, seller_id, cash_id, discount, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $saleStmt->execute([$saleNumber, $grandTotal, 'completed', (int)$user['id'], (int)$activeCash['id'], $discount, 'Venda registrada no PDV']);
                $saleId = (int)$pdo->lastInsertId();

                $ins = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, barcode, quantity, unit_price, unit_cost, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $upd = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
                $mov = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, before_qty, after_qty, reference_id, notes, movement_reason, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');

                foreach ($items as $it) {
                    $p = $it['p'];
                    $qty = $it['qty'];
                    $beforeQty = (float)$p['stock_qty'];
                    $afterQty = $beforeQty - $qty;
                    $ins->execute([$saleId, $p['id'], $p['name'], $p['barcode'], $qty, $p['sale_price'], $p['cost_price'], $it['line']]);
                    $upd->execute([$qty, $p['id'], $qty]);
                    if ($upd->rowCount() !== 1) {
                        throw new RuntimeException('Estoque alterado durante a venda: ' . $p['name'] . '.');
                    }
                    $mov->execute([$p['id'], 'sale', -$qty, $beforeQty, $afterQty, $saleId, 'Venda #' . $saleId, 'venda', (int)$user['id']]);
                }

                $pay = $pdo->prepare('INSERT INTO sale_payments (sale_id, method, amount) VALUES (?, ?, ?)');
                foreach ($payments as $payment) {
                    $pay->execute([$saleId, $payment['method'], $payment['amount']]);
                }

                $cashMovement = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, related_sale_id, user_id, amount, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                $cashMovement->execute([(int)$activeCash['id'], 'sale', $saleId, (int)$user['id'], $grandTotal, 'Venda ' . $saleNumber]);
                logAudit($pdo, 'sales', 'sales', $saleId, 'create', 'Venda registrada: ' . $saleNumber . ' total ' . number_format($grandTotal, 2, '.', ''), (int)$user['id']);

                $pixMethod = array_filter($payments, fn($payment) => $payment['method'] === 'pix');
                if ($pixMethod) {
                    $pixTotal = array_sum(array_map(fn($payment) => (float)$payment['amount'], $pixMethod));
                    $pixQr = [
                        'total' => round($pixTotal, 2),
                        'key' => PIX_KEY,
                        'name' => PIX_NAME,
                        'payload' => '00020126580014BR.GOV.BCB.PIX0136' . PIX_KEY . '520400005303986540' . number_format($pixTotal, 2, '.', '') . '5802BR5909' . PIX_NAME . '6009SAO PAULO62070503***6304' . '000000',
                    ];
                }

                $pdo->commit();
                $receipt = [
                    'id' => $saleId,
                    'number' => $saleNumber,
                    'total' => round($grandTotal, 2),
                    'payments' => $payments,
                    'items' => $items,
                ];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $success = 'Venda registrada com sucesso.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao registrar a venda. Nenhuma alteração foi confirmada.';
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDV | Tô em Casa</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="brand">TÔ EM CASA <small>ADEGA & CONVENIÊNCIA</small></div>
    <nav class="main-menu">
        <a href="index.php">Início</a>
        <a href="products.php?open=1">Produtos</a>
        <a class="active" href="pos.php">PDV</a>
        <a href="cash_register.php">Caixa</a>
        <a href="financial.php">Financeiro</a>
        <a href="reports.php">Relatórios</a>
        <a href="audit.php">Auditoria</a>
        <a href="users.php">Usuários</a>
        <span class="user-badge"><?= h($user['username']) ?> · <?= h($user['role']) ?></span>
        <a href="logout.php">Sair</a>
    </nav>
</header>
<main>
    <h1>Frente de caixa</h1>
    <p class="muted">Clique no campo e leia o código de barras. O leitor deve enviar Enter ao final da leitura.</p>
    <?php if($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
    <?php if($receipt): ?><div class="success">Venda #<?= h((string)$receipt['number']) ?> concluída — total <?= money((float)$receipt['total']) ?>.</div><?php endif; ?>
    <?php if($pixQr): ?>
        <section class="card">
            <h2>Pagamento via Pix</h2>
            <p>Chave Pix: <strong><?= h(PIX_KEY) ?></strong></p>
            <p>Valor: <strong><?= money((float)$pixQr['total']) ?></strong></p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode('00020126' . '0014BR.GOV.BCB.PIX' . '0136' . PIX_KEY . '520400005303986540' . number_format((float)$pixQr['total'], 2, '.', '') . '5802BR5909' . PIX_NAME . '6009SAO PAULO62070503***6304' . '000000') ?>" alt="QR Code Pix" />
        </section>
    <?php endif; ?>
    <section class="card">
        <label class="scan-label">Código de barras<input id="barcode" autocomplete="off" autofocus placeholder="Passe o leitor ou digite o código e pressione Enter"></label>
        <p id="scan-message" class="muted" aria-live="polite"></p>
        <div class="table-wrap"><table><thead><tr><th>Produto</th><th>Preço</th><th>Qtd.</th><th>Total</th><th></th></tr></thead><tbody id="cart-body"><tr><td colspan="5">Nenhum produto adicionado.</td></tr></tbody></table></div>
        <div class="total-row"><span>Subtotal</span><strong id="subtotal">R$ 0,00</strong></div>
        <div class="total-row"><span>Desconto</span><input id="discount-input" type="number" min="0" step="0.01" value="0" style="max-width:140px"></div>
        <div class="total-row">Total da venda <strong id="total">R$ 0,00</strong></div>
        <div class="total-row"><span>Troco</span><strong id="change-amount">R$ 0,00</strong></div>
        <form method="post" id="checkout-form"><input type="hidden" name="action" value="checkout"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>"><input type="hidden" name="cart" id="cart-json"><input type="hidden" name="discount" id="discount-hidden" value="0"><h2>Pagamento</h2><div id="payments"><div class="payment-row"><select name="method[]"><option value="dinheiro">Dinheiro</option><option value="pix" selected>Pix</option><option value="debito">Cartão de débito</option><option value="credito">Cartão de crédito</option><option value="outro">Outro</option></select><input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required><button type="button" class="button secondary small remove-payment">Remover</button></div></div><div class="actions"><button type="button" class="button secondary" id="add-payment">Adicionar forma de pagamento</button><button type="submit" class="button" id="finish">Finalizar venda</button></div></form>
    </section>
</main>
<script>
const input = document.getElementById('barcode');
const tbody = document.getElementById('cart-body');
const subtotalEl = document.getElementById('subtotal');
const totalEl = document.getElementById('total');
const changeEl = document.getElementById('change-amount');
const discountInput = document.getElementById('discount-input');
const discountHidden = document.getElementById('discount-hidden');
const cart = new Map();
let busy = false;
const money = n => n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
function subtotal(){let n=0;for(const x of cart.values())n+=x.price*x.qty;return Math.round((n+Number.EPSILON)*100)/100;}
function total(){const d=Number(discountInput.value||0);return Math.max(0, Math.round((subtotal()-d+Number.EPSILON)*100)/100);}
function updateChange(){
  const received = [...document.querySelectorAll('input[name="amount[]"]')].reduce((sum, input) => sum + Number(input.value || 0), 0);
  const change = Math.max(0, Math.round((received - total() + Number.EPSILON) * 100) / 100);
  changeEl.textContent = money(change);
}
function render(){
  tbody.innerHTML='';
  if(!cart.size){tbody.innerHTML='<tr><td colspan="5">Nenhum produto adicionado.</td></tr>';} 
  for(const [barcode,p] of cart){
    const tr=document.createElement('tr');
    const name=document.createElement('td'); name.textContent=p.name;
    const price=document.createElement('td'); price.textContent=money(p.price);
    const q=document.createElement('td');
    const qty=document.createElement('input'); qty.type='number'; qty.min='0.001'; qty.step='0.001'; qty.value=p.qty; qty.style.width='90px'; qty.addEventListener('change',()=>{const v=Number(qty.value);if(v<=0){cart.delete(barcode)}else{p.qty=v;}render();}); q.appendChild(qty);
    const line=document.createElement('td'); line.textContent=money(p.price*p.qty);
    const act=document.createElement('td');
    const del=document.createElement('button'); del.type='button'; del.className='button danger small'; del.textContent='Remover'; del.addEventListener('click',()=>{cart.delete(barcode);render();}); act.appendChild(del);
    tr.append(name,price,q,line,act); tbody.appendChild(tr);
  }
  subtotalEl.textContent=money(subtotal());
  totalEl.textContent=money(total());
  discountHidden.value=String(Number(discountInput.value||0));
  document.getElementById('cart-json').value=JSON.stringify([...cart].map(([barcode,p])=>({barcode,qty:p.qty})));
  updateChange();
}
input.addEventListener('keydown',async e=>{
  if(e.key!=='Enter')return;
  e.preventDefault();
  if(busy) return;
  const code=input.value.trim();
  if(!code) return;
  busy=true; input.disabled=true;
  try{
    const r=await fetch('product_lookup.php?barcode='+encodeURIComponent(code),{headers:{'Accept':'application/json'}});
    const data=await r.json();
    if(!r.ok||!data.ok){document.getElementById('scan-message').textContent=data.message||'Produto não encontrado. Cadastre-o antes de vender.'; return; }
    const p=data.product; const existing=cart.get(p.barcode);
    if(existing){ existing.qty += 1; } else { cart.set(p.barcode,{...p,qty:1}); }
    document.getElementById('scan-message').textContent=p.name+' adicionado.';
    render();
  }catch(err){document.getElementById('scan-message').textContent='Falha na consulta. Verifique a conexão com o sistema.';}
  finally{ input.disabled=false; input.value=''; input.focus(); busy=false; }
});
discountInput.addEventListener('input', render);
document.querySelectorAll('input[name="amount[]"]').forEach((inputField) => {
  inputField.addEventListener('input', updateChange);
});
document.getElementById('add-payment').addEventListener('click',()=>{
  const row=document.createElement('div'); row.className='payment-row';
  row.innerHTML='<select name="method[]"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Cartão de débito</option><option value="credito">Cartão de crédito</option><option value="outro">Outro</option></select><input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required><button type="button" class="button secondary small remove-payment">Remover</button>';
  const amountInput = row.querySelector('input[name="amount[]"]');
  amountInput.addEventListener('input', updateChange);
  document.getElementById('payments').appendChild(row);
});
document.getElementById('payments').addEventListener('click', (event) => {
  const button = event.target.closest('.remove-payment');
  if (!button) return;
  const rows = document.querySelectorAll('.payment-row');
  if (rows.length > 1) {
    button.closest('.payment-row').remove();
    updateChange();
  }
});
document.getElementById('checkout-form').addEventListener('submit',e=>{
  if(!cart.size){e.preventDefault(); alert('Adicione produtos antes de finalizar.'); return; }
  const sum=[...document.querySelectorAll('input[name="amount[]"]')].reduce((s,x)=>s+Number(x.value||0),0);
  if(Math.round(sum*100) < Math.round(total()*100)){ e.preventDefault(); alert('A soma dos pagamentos deve cobrir o total da venda.'); return; }
  document.getElementById('cart-json').value=JSON.stringify([...cart].map(([barcode,p])=>({barcode,qty:p.qty})));
  document.getElementById('discount-hidden').value=String(Number(discountInput.value||0));
  document.getElementById('finish').disabled=true;
});
render();
</script>
</body>
</html>
