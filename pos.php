<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
requireRole(['admin','vendedor']);

$user = currentUser();
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return 'R$ ' . number_format($v, 2, ',', '.'); }
function buildPixPayload(string $pixKey, float $amount, string $merchantName = 'TÔ EM CASA', string $description = 'PAGAMENTO PIX'): string
{
    $pixKey = preg_replace('/\s+/', '', trim($pixKey)) ?: 'SEU_PIX_KEY_AQUI';
    $merchantName = preg_replace('/[^\pL\pN ._-]/u', '', trim($merchantName)) ?: 'TÔ EM CASA';
    $description = preg_replace('/[^\pL\pN ._-]/u', '', trim($description)) ?: 'PAGAMENTO PIX';
    $amountText = number_format($amount, 2, '.', '');

    $payload = '000201'
        . '26'
        . '0014BR.GOV.BCB.PIX'
        . '01'
        . '12'
        . (string)strlen($pixKey)
        . $pixKey
        . '54'
        . (string)strlen($amountText)
        . $amountText
        . '58'
        . '02BR'
        . '59'
        . (string)strlen($merchantName)
        . $merchantName
        . '60'
        . '10'
        . 'BRASIL'
        . '61'
        . (string)strlen($description)
        . $description
        . '6304';

    return $payload;
}
function normalizePosCart(array $cart): array
{
    $normalized = [];
    foreach ($cart as $line) {
        if (!is_array($line)) {
            continue;
        }

        $barcode = normalizeBarcode((string)($line['barcode'] ?? ''));
        $qty = (float)($line['qty'] ?? 0);
        if ($barcode === '' || !is_finite($qty) || $qty <= 0) {
            continue;
        }

        if (!isset($normalized[$barcode])) {
            $normalized[$barcode] = 0.0;
        }

        $normalized[$barcode] += $qty;
    }

    return $normalized;
}
function validateDiscountAmount(float $discount, float $subtotal, string $role): float
{
    $discount = round(max(0.0, $discount), 2);
    $limit = $role === 'admin' ? 0.30 : 0.10;
    $maxDiscount = round($subtotal * $limit, 2);
    if ($discount > $maxDiscount) {
        throw new RuntimeException('Desconto excede o limite permitido para o perfil atual.');
    }
    return $discount;
}

$error = '';
$success = '';
$receipt = null;
$pixQr = null;

if (empty($_SESSION['pos_checkout_token'])) {
    $_SESSION['pos_checkout_token'] = bin2hex(random_bytes(16));
}

$cash = $pdo->prepare('SELECT * FROM cash_registers WHERE status = ? AND opened_by_user_id = ? ORDER BY opened_at DESC LIMIT 1');
$cash->execute(['open', $user['id']]);
$cashRegister = $cash->fetch();
if (!$cashRegister) {
    $error = 'É necessário abrir o caixa antes de vender.';
}

$canSell = (bool)$cashRegister;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    if (!$canSell) {
        $error = 'Não é possível registrar venda com o caixa fechado. Abra o caixa antes de prosseguir.';
    } else {
        $checkoutToken = (string)($_POST['checkout_token'] ?? '');
        if (!isset($_POST['csrf']) || $_POST['csrf'] !== ($_SESSION['csrf_token'] ?? '') || $checkoutToken !== ($_SESSION['pos_checkout_token'] ?? '')) {
            $error = 'Requisição inválida ou venda já processada. Tente novamente.';
        } else {
            $rawCart = json_decode((string)($_POST['cart'] ?? '[]'), true);
            $methods = $_POST['method'] ?? [];
            $amounts = $_POST['amount'] ?? [];
            $discountType = in_array($_POST['discount_type'] ?? 'amount', ['amount','percent'], true) ? $_POST['discount_type'] : 'amount';
            $discountInput = (float)str_replace(',', '.', (string)($_POST['discount'] ?? '0'));

            if (!is_array($rawCart) || !$rawCart) {
                $error = 'Adicione pelo menos um produto à venda.';
            } else {
                try {
                    $cart = normalizePosCart($rawCart);
                    if (!$cart) {
                        throw new RuntimeException('Carrinho inválido. Revise os itens da venda.');
                    }

                    $pdo->beginTransaction();
                    $cashCheck = $pdo->prepare('SELECT * FROM cash_registers WHERE status = ? ORDER BY opened_at DESC LIMIT 1');
                    $cashCheck->execute(['open']);
                    $activeCash = $cashCheck->fetch();
                    if (!$activeCash) {
                        throw new RuntimeException('Não existe caixa aberto no momento.');
                    }

                    $items = [];
                    $subtotal = 0.0;
                    $productQuery = $pdo->prepare('SELECT id, barcode, name, cost_price, sale_price, stock_qty, active FROM products WHERE barcode = ? LIMIT 1 FOR UPDATE');

                    foreach ($cart as $barcode => $qty) {
                        $productQuery->execute([$barcode]);
                        $p = $productQuery->fetch();
                        if (!$p || !(int)$p['active']) {
                            throw new RuntimeException('Produto não encontrado ou inativo: ' . $barcode);
                        }
                        if ((float)$p['stock_qty'] < $qty) {
                            throw new RuntimeException('Estoque insuficiente para ' . $p['name'] . '.');
                        }

                        $lineTotal = round((float)$p['sale_price'] * $qty, 2);
                        $subtotal += $lineTotal;
                        $items[] = ['p' => $p, 'qty' => $qty, 'line' => $lineTotal];
                    }

                    if ($discountType === 'percent') {
                        $discount = round($subtotal * max(0, min((float)$discountInput, 100)) / 100, 2);
                    } else {
                        $discount = round(max(0, (float)$discountInput), 2);
                    }
                    $discount = validateDiscountAmount($discount, $subtotal, (string)$user['role']);
                    $grandTotal = round($subtotal - $discount, 2);
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
                    if (round($paid, 2) < round($grandTotal, 2) - 0.01) {
                        throw new RuntimeException('A soma dos pagamentos deve cobrir o total da venda.');
                    }

                    $saleNumber = 'V' . date('YmdHis') . '-' . random_int(100, 999);
                    $saleStmt = $pdo->prepare('INSERT INTO sales (sale_number, total, status, seller_id, cash_id, discount, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $saleStmt->execute([$saleNumber, $grandTotal, 'completed', (int)$user['id'], (int)$activeCash['id'], $discount, 'Venda registrada no PDV']);
                    $saleId = (int)$pdo->lastInsertId();

                    $insItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, barcode, quantity, unit_price, unit_cost, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $updItem = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
                    $movStock = $pdo->prepare('INSERT INTO stock_movements (product_id, movement_type, quantity, before_qty, after_qty, reference_id, notes, movement_reason, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');

                    foreach ($items as $it) {
                        $p = $it['p'];
                        $qty = $it['qty'];
                        $beforeQty = (float)$p['stock_qty'];
                        $afterQty = $beforeQty - $qty;
                        $insItem->execute([$saleId, $p['id'], $p['name'], $p['barcode'], $qty, $p['sale_price'], $p['cost_price'], $it['line']]);
                        $updItem->execute([$qty, $p['id'], $qty]);
                        if ($updItem->rowCount() !== 1) {
                            throw new RuntimeException('Estoque alterado durante a venda: ' . $p['name'] . '.');
                        }
                        $movStock->execute([$p['id'], 'sale', -$qty, $beforeQty, $afterQty, $saleId, 'Venda #' . $saleId, 'venda', (int)$user['id']]);
                    }

                    $pay = $pdo->prepare('INSERT INTO sale_payments (sale_id, method, amount) VALUES (?, ?, ?)');
                    foreach ($payments as $payment) {
                        $pay->execute([$saleId, $payment['method'], $payment['amount']]);
                    }

                    $cashMovement = $pdo->prepare('INSERT INTO cash_movements (cash_register_id, movement_type, related_sale_id, user_id, amount, reason, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
                    $cashMovement->execute([(int)$activeCash['id'], 'sale', $saleId, (int)$user['id'], $grandTotal, 'Venda ' . $saleNumber]);
                    logAudit($pdo, 'sales', 'sales', $saleId, 'create', 'Venda registrada: ' . $saleNumber . ' total ' . number_format($grandTotal, 2, '.', ''), (int)$user['id']);

                    $pdo->commit();
                    $_SESSION['pos_checkout_token'] = bin2hex(random_bytes(16));
                    $receipt = [
                        'id' => $saleId,
                        'number' => $saleNumber,
                        'total' => round($grandTotal, 2),
                        'payments' => $payments,
                        'items' => $items,
                        'discount' => $discount,
                    ];
                    $success = 'Venda registrada com sucesso.';
                    if ($user['role'] === 'admin') {
                        $success .= ' Desconto aplicado e autorização registrada.';
                    }

                    $pixMethod = array_filter($payments, fn($payment) => $payment['method'] === 'pix');
                    if ($pixMethod) {
                        $pixTotal = array_sum(array_map(fn($payment) => (float)$payment['amount'], $pixMethod));
                        $pixQr = [
                            'total' => round($pixTotal, 2),
                            'key' => PIX_KEY,
                            'name' => PIX_NAME,
                            'payload' => buildPixPayload(PIX_KEY, round($pixTotal, 2), PIX_NAME, 'PAGAMENTO PIX'),
                        ];
                    }
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = $e instanceof RuntimeException ? $e->getMessage() : 'Erro ao registrar a venda. Nenhuma alteração foi confirmada.';
                }
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
<main class="pos-page">
    <section class="pos-topbar card">
        <div>
            <div class="eyebrow">Operação ativa</div>
            <h1>Frente de Caixa</h1>
            <p class="muted">Leia o código de barras ou digite o código do produto para iniciar a venda.</p>
        </div>
        <div class="pos-status-wrap">
            <span class="status-pill <?= $cashRegister ? 'success' : 'warning' ?>">
                <?= $cashRegister ? 'Caixa aberto' : 'Caixa fechado' ?>
            </span>
        </div>
    </section>

    <?php if($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
    <?php if($success): ?><div class="success"><?= h($success) ?></div><?php endif; ?>
    <?php if($receipt): ?><div class="success">Venda #<?= h((string)$receipt['number']) ?> concluída — total <?= money((float)$receipt['total']) ?>.</div><?php endif; ?>
    <?php if($pixQr): ?>
        <section class="card pix-card">
            <div>
                <h2>Pagamento via Pix</h2>
                <p>Chave Pix: <strong><?= h(PIX_KEY) ?></strong></p>
                <p>Valor: <strong><?= money((float)$pixQr['total']) ?></strong></p>
                <p class="muted">Pagamento registrado para confirmação externa do operador. Confirme o recebimento antes de encerrar o fluxo financeiro.</p>
            </div>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode((string)$pixQr['payload']) ?>" alt="QR Code Pix" class="pix-qr">
        </section>
    <?php endif; ?>

    <div class="pos-layout">
        <section class="card pos-main-panel">
            <div class="pos-scan-box">
                <label class="scan-label">Código de barras
                    <div class="scan-input-wrap">
                        <span class="scan-icon">⌕</span>
                        <input id="barcode" autocomplete="off" autofocus placeholder="Leia o código de barras ou digite o código do produto" <?= $canSell ? '' : 'disabled' ?>>
                    </div>
                </label>
                <p id="scan-message" class="muted scan-message" aria-live="polite"><?= $canSell ? 'Pronto para leitura do scanner.' : 'Caixa fechado. Abra o caixa para iniciar as vendas.' ?></p>
            </div>

            <div class="cart-head">
                <h2>Carrinho</h2>
                <span class="cart-count"><strong id="items-count">0</strong> itens</span>
            </div>

            <div class="table-wrap pos-table-wrap">
                <table class="pos-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Preço</th>
                            <th>Qtd.</th>
                            <th>Subtotal</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="cart-body">
                        <tr>
                            <td colspan="5" class="empty-state">
                                <div class="empty-state-inner">
                                    <span class="empty-icon">◌</span>
                                    <span>Nenhum produto adicionado. Faça a leitura do primeiro item.</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <aside class="pos-sidebar">
            <section class="card summary-card">
                <div class="summary-header">
                    <h2>Resumo</h2>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <span>Subtotal</span>
                        <strong id="subtotal">R$ 0,00</strong>
                    </div>
                    <div class="summary-item">
                        <span>Desconto</span>
                        <strong id="discount-readonly">R$ 0,00</strong>
                    </div>
                    <div class="summary-item total-item">
                        <span>Total</span>
                        <strong id="total">R$ 0,00</strong>
                    </div>
                    <div class="summary-item compact-item">
                        <span>Itens</span>
                        <strong id="items-count-inline">0</strong>
                    </div>
                    <div class="summary-item compact-item">
                        <span>Pago</span>
                        <strong id="payment-summary">R$ 0,00</strong>
                    </div>
                    <div class="summary-item compact-item">
                        <span>Restante</span>
                        <strong id="remaining-amount">R$ 0,00</strong>
                    </div>
                    <div class="summary-item compact-item">
                        <span>Troco</span>
                        <strong id="change-amount">R$ 0,00</strong>
                    </div>
                </div>

                <div class="discount-box-block">
                    <label>Desconto</label>
                    <div class="discount-box">
                        <select id="discount-type" aria-label="Tipo de desconto" <?= $canSell ? '' : 'disabled' ?>>
                            <option value="amount">R$</option>
                            <option value="percent">%</option>
                        </select>
                        <input id="discount-input" type="number" min="0" step="0.01" value="0" <?= $canSell ? '' : 'disabled' ?>>
                    </div>
                </div>
            </section>

            <form method="post" id="checkout-form" class="card payment-card">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="checkout_token" value="<?= h($_SESSION['pos_checkout_token']) ?>">
                <input type="hidden" name="cart" id="cart-json">
                <input type="hidden" name="discount" id="discount-hidden" value="0">
                <input type="hidden" name="discount_type" id="discount-type-hidden" value="amount">

                <div class="payment-header">
                    <h2>Pagamento</h2>
                </div>

                <div id="payments" class="payments-list">
                    <div class="payment-row">
                        <select name="method[]" <?= $canSell ? '' : 'disabled' ?>>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="pix" selected>Pix</option>
                            <option value="debito">Cartão de débito</option>
                            <option value="credito">Cartão de crédito</option>
                            <option value="outro">Outro</option>
                        </select>
                        <input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required <?= $canSell ? '' : 'disabled' ?>>
                        <button type="button" class="button secondary small remove-payment" <?= $canSell ? '' : 'disabled' ?>>Remover</button>
                    </div>
                </div>

                <div class="actions actions-payment">
                    <button type="button" class="button secondary" id="add-payment" <?= $canSell ? '' : 'disabled' ?>>Adicionar forma de pagamento</button>
                    <button type="submit" class="button" id="finish" <?= $canSell ? '' : 'disabled' ?>>Finalizar venda</button>
                </div>
            </form>
        </aside>
    </div>
</main>
<script>
const input = document.getElementById('barcode');
const tbody = document.getElementById('cart-body');
const subtotalEl = document.getElementById('subtotal');
const totalEl = document.getElementById('total');
const discountReadOnlyEl = document.getElementById('discount-readonly');
const changeEl = document.getElementById('change-amount');
const paymentSummaryEl = document.getElementById('payment-summary');
const itemCountEl = document.getElementById('items-count');
const itemCountInlineEl = document.getElementById('items-count-inline');
const remainingEl = document.getElementById('remaining-amount');
const discountInput = document.getElementById('discount-input');
const discountType = document.getElementById('discount-type');
const discountHidden = document.getElementById('discount-hidden');
const discountTypeHidden = document.getElementById('discount-type-hidden');
const cart = new Map();
let busy = false;
const canSell = <?= $canSell ? 'true' : 'false' ?>;
const money = (n) => n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

if (!canSell) {
  input.disabled = true;
  if (discountInput) discountInput.disabled = true;
  if (discountType) discountType.disabled = true;
  document.querySelectorAll('input[name="amount[]"]').forEach((field) => field.disabled = true);
  document.querySelectorAll('select[name="method[]"]').forEach((field) => field.disabled = true);
  document.querySelectorAll('.remove-payment').forEach((button) => button.disabled = true);
  document.getElementById('add-payment').disabled = true;
  document.getElementById('finish').disabled = true;
}

function subtotal() {
  let total = 0;
  for (const item of cart.values()) {
    total += item.price * item.qty;
  }
  return Math.round((total + Number.EPSILON) * 100) / 100;
}

function currentDiscountValue() {
  const value = Number(discountInput.value || 0);
  if (discountType.value === 'percent') {
    return Math.min(100, Math.max(0, value)) / 100 * subtotal();
  }
  return Math.max(0, value);
}

function total() {
  const subtotalValue = subtotal();
  const discountValue = currentDiscountValue();
  return Math.max(0, Math.round((subtotalValue - discountValue + Number.EPSILON) * 100) / 100);
}

function updatePaymentSummary() {
  const rows = [...document.querySelectorAll('input[name="amount[]"]')];
  const received = rows.reduce((sum, field) => sum + Number(field.value || 0), 0);
  paymentSummaryEl.textContent = money(received);

  const totalValue = total();
  const remaining = Math.max(0, Number((totalValue - received).toFixed(2)));
  remainingEl.textContent = money(remaining);

  const hasCashPayment = [...document.querySelectorAll('select[name="method[]"]')].some((field) => field.value === 'dinheiro');
  const change = hasCashPayment ? Math.max(0, Number((received - totalValue).toFixed(2))) : 0;
  changeEl.textContent = money(change);
}

function render() {
  tbody.innerHTML = '';
  let itemsCount = 0;

  if (!cart.size) {
    tbody.innerHTML = '<tr><td colspan="5" class="empty-state"><div class="empty-state-inner"><span class="empty-icon">◌</span><span>Nenhum produto adicionado. Faça a leitura do primeiro item.</span></div></td></tr>';
  } else {
    for (const [barcode, product] of cart) {
      itemsCount += product.qty;
      const tr = document.createElement('tr');

      const name = document.createElement('td');
      name.textContent = product.name;

      const price = document.createElement('td');
      price.textContent = money(product.price);

      const qtyCell = document.createElement('td');
      const qtyInput = document.createElement('input');
      qtyInput.type = 'number';
      qtyInput.min = '1';
      qtyInput.step = '1';
      qtyInput.value = product.qty;
      qtyInput.disabled = !canSell;
      qtyInput.addEventListener('change', () => {
        const nextQty = Number(qtyInput.value) || 0;
        if (nextQty <= 0) {
          cart.delete(barcode);
        } else {
          product.qty = nextQty;
        }
        render();
      });
      qtyCell.appendChild(qtyInput);

      const subtotalCell = document.createElement('td');
      subtotalCell.textContent = money(product.price * product.qty);

      const actions = document.createElement('td');
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.className = 'button danger small';
      removeButton.textContent = 'Remover';
      removeButton.disabled = !canSell;
      removeButton.addEventListener('click', () => {
        cart.delete(barcode);
        render();
      });
      actions.appendChild(removeButton);

      tr.append(name, price, qtyCell, subtotalCell, actions);
      tbody.appendChild(tr);
    }
  }

  itemCountEl.textContent = String(itemsCount);
  itemCountInlineEl.textContent = String(itemsCount);
  subtotalEl.textContent = money(subtotal());
  totalEl.textContent = money(total());
  discountReadOnlyEl.textContent = money(currentDiscountValue());
  discountHidden.value = String(Number(currentDiscountValue().toFixed(2)));
  discountTypeHidden.value = discountType.value;
  document.getElementById('cart-json').value = JSON.stringify([...cart].map(([barcode, product]) => ({ barcode, qty: product.qty })));
  updatePaymentSummary();
}

if (canSell) {
  input.addEventListener('keydown', async (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    if (busy) return;

    const code = input.value.trim();
    if (!code) return;

    busy = true;
    input.disabled = true;

    try {
      const response = await fetch('product_lookup.php?barcode=' + encodeURIComponent(code), {
        headers: { Accept: 'application/json' }
      });
      const data = await response.json();

      if (!response.ok || !data.ok) {
        document.getElementById('scan-message').textContent = data.message || 'Produto não encontrado. Cadastre-o antes de vender.';
        return;
      }

      const product = data.product;
      const existing = cart.get(product.barcode);
      if (existing) {
        existing.qty += 1;
      } else {
        cart.set(product.barcode, { ...product, qty: 1 });
      }

      document.getElementById('scan-message').textContent = product.name + ' adicionado ao carrinho.';
      render();
    } catch (error) {
      document.getElementById('scan-message').textContent = 'Falha na consulta. Verifique a conexão com o sistema.';
    } finally {
      input.disabled = false;
      input.value = '';
      input.focus();
      busy = false;
    }
  });

  discountInput.addEventListener('input', render);
  discountType.addEventListener('change', () => {
    const value = Number(discountInput.value || 0);
    if (discountType.value === 'percent') {
      discountInput.max = '100';
      discountInput.min = '0';
      discountInput.step = '0.01';
      if (value > 100) discountInput.value = 100;
    } else {
      discountInput.max = '';
      discountInput.min = '0';
      discountInput.step = '0.01';
    }
    render();
  });

  document.querySelectorAll('input[name="amount[]"]').forEach((field) => field.addEventListener('input', updatePaymentSummary));
  document.getElementById('payments').addEventListener('input', (event) => {
    const target = event.target;
    if (target && target.matches('input[name="amount[]"]')) {
      updatePaymentSummary();
    }
  });
  document.getElementById('payments').addEventListener('change', (event) => {
    const target = event.target;
    if (target && target.matches('select[name="method[]"]')) {
      updatePaymentSummary();
    }
  });
  document.getElementById('add-payment').addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'payment-row';
    row.innerHTML = '<select name="method[]"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="debito">Cartão de débito</option><option value="credito">Cartão de crédito</option><option value="outro">Outro</option></select><input name="amount[]" type="number" min="0" step="0.01" placeholder="Valor recebido" required><button type="button" class="button secondary small remove-payment">Remover</button>';

    const amountInput = row.querySelector('input[name="amount[]"]');
    if (amountInput) amountInput.addEventListener('input', updatePaymentSummary);

    const methodSelect = row.querySelector('select[name="method[]"]');
    if (methodSelect) methodSelect.addEventListener('change', updatePaymentSummary);

    document.getElementById('payments').appendChild(row);
  });

  document.getElementById('payments').addEventListener('click', (event) => {
    const button = event.target.closest('.remove-payment');
    if (!button) return;

    const rows = document.querySelectorAll('.payment-row');
    if (rows.length > 1) {
      button.closest('.payment-row').remove();
      updatePaymentSummary();
    }
  });

  document.getElementById('checkout-form').addEventListener('submit', (event) => {
    if (!cart.size) {
      event.preventDefault();
      alert('Adicione produtos antes de finalizar.');
      return;
    }

    const sum = [...document.querySelectorAll('input[name="amount[]"]')].reduce((accumulator, field) => accumulator + Number(field.value || 0), 0);
    if (Math.round(sum * 100) < Math.round(total() * 100)) {
      event.preventDefault();
      alert('A soma dos pagamentos deve cobrir o total da venda.');
      return;
    }

    document.getElementById('cart-json').value = JSON.stringify([...cart].map(([barcode, product]) => ({ barcode, qty: product.qty })));
    document.getElementById('discount-hidden').value = String(Number(currentDiscountValue().toFixed(2)));
    document.getElementById('discount-type-hidden').value = discountType.value;
    document.getElementById('finish').disabled = true;
  });
}

render();
if (canSell) input.focus();
</script>
</body>
</html>
