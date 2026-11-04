<?php
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$user = currentUser();

$role = $user['role'] ?? 'vendedor';
$receiptDefaults = [
    'empresa_nome' => 'TÔ EM CASA',
    'slogan' => 'ADEGA & CONVENIÊNCIA',
    'cnpj' => '00.000.000/0001-00',
    'endereco' => 'Rua da Loja, 123 - Centro',
    'telefone' => 'Tel/WhatsApp: (11) 99999-9999',
];
$headerReceiptConfig = $_SESSION['receipt_config'] ?? $receiptDefaults;
foreach ($receiptDefaults as $key => $value) {
    if (!isset($headerReceiptConfig[$key])) {
        $headerReceiptConfig[$key] = $value;
    }
}

$headerMenu = [
    ['label' => 'Início', 'url' => 'index.php', 'icon' => '🏠', 'page' => 'index.php', 'roles' => ['admin', 'vendedor']],
    ['label' => 'Produtos', 'url' => 'products.php?open=1', 'icon' => '📦', 'page' => 'products.php', 'roles' => ['admin']],
    ['label' => 'PDV', 'url' => 'pos.php', 'icon' => '🧾', 'page' => 'pos.php', 'roles' => ['admin', 'vendedor']],
    ['label' => 'Informação da empresa', 'url' => '#', 'icon' => '🏪', 'page' => 'pos.php', 'roles' => ['admin', 'vendedor'], 'popup' => true],
    ['label' => 'Usuários', 'url' => 'users.php', 'icon' => '👤', 'page' => 'users.php', 'roles' => ['admin']],
    ['label' => 'Caixa', 'url' => 'cash_register.php', 'icon' => '💰', 'page' => 'cash_register.php', 'roles' => ['admin', 'vendedor']],
    ['label' => 'Financeiro', 'url' => 'financial.php', 'icon' => '📊', 'page' => 'financial.php', 'roles' => ['admin']],
    ['label' => 'Relatórios', 'url' => 'reports.php', 'icon' => '📈', 'page' => 'reports.php', 'roles' => ['admin']],
    ['label' => 'Auditoria', 'url' => 'audit.php', 'icon' => '🛡️', 'page' => 'audit.php', 'roles' => ['admin']],
];

$headerMenu = array_values(array_filter($headerMenu, function (array $item) use ($role): bool {
    return in_array($role, $item['roles'], true);
}));

$userDisplay = $user ? h($user['username']) . ' · ' . h($user['role']) : 'Visitante';
$userInitials = $user ? strtoupper(substr((string)$user['username'], 0, 2)) : 'VS';
?>
<header class="app-header">
    <div class="header-left">
        <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <div class="header-brand" aria-label="Tô em Casa Adega & Conveniência">
        <span class="brand-main">TÔ EM CASA</span>
        <small>ADEGA &amp; CONVENIÊNCIA</small>
    </div>

    <div class="header-right" aria-hidden="true">
        <span class="header-pill">Sistema</span>
    </div>

    <div class="menu-overlay" aria-hidden="true"></div>

    <aside class="side-menu" aria-label="Menu principal">
        <div class="side-menu-header">
            <div class="side-user-card">
                <div class="user-avatar"><?= $userInitials ?></div>
                <div>
                    <strong><?= $user ? h($user['username']) : 'Visitante' ?></strong>
                    <small><?= $user ? h($user['role']) : 'Sem sessão' ?></small>
                </div>
            </div>
        </div>

        <nav class="side-nav">
            <?php foreach ($headerMenu as $item): ?>
                <?php $isActive = $currentPage === $item['page']; ?>
                <a href="<?= h($item['url']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>" <?= !empty($item['popup']) ? 'data-open-receipt-config="true"' : '' ?>>
                    <span class="nav-icon"><?= $item['icon'] ?></span>
                    <span><?= h($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="side-footer">
            <div class="user-summary">
                <span>Conectado</span>
                <strong><?= $userDisplay ?></strong>
            </div>
            <a href="logout.php" class="logout-link">Sair</a>
        </div>
    </aside>
</header>

<div id="receipt-config-modal-global" class="modal" aria-hidden="true">
    <div class="modal-backdrop" data-close-receipt-modal-global="true"></div>
    <div class="modal-card" style="max-width:620px; width:min(92vw,620px);">
        <div class="modal-header">
            <h2>Editar dados da notinha</h2>
            <button type="button" class="button secondary small" data-close-receipt-modal-global="true">Fechar</button>
        </div>

        <form method="post" action="pos.php" class="form-grid" style="margin-top:12px;">
            <input type="hidden" name="action" value="update_receipt_config">
            <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">

            <label class="full">
                Nome da loja
                <input type="text" name="receipt_empresa_nome" value="<?= h((string)$headerReceiptConfig['empresa_nome']) ?>" maxlength="80">
            </label>

            <label class="full">
                Slogan
                <input type="text" name="receipt_slogan" value="<?= h((string)$headerReceiptConfig['slogan']) ?>" maxlength="120">
            </label>

            <label class="full">
                CNPJ
                <input type="text" name="receipt_cnpj" value="<?= h((string)$headerReceiptConfig['cnpj']) ?>" maxlength="40">
            </label>

            <label class="full">
                Endereço
                <input type="text" name="receipt_endereco" value="<?= h((string)$headerReceiptConfig['endereco']) ?>" maxlength="120">
            </label>

            <label class="full">
                Telefone / WhatsApp
                <input type="text" name="receipt_telefone" value="<?= h((string)$headerReceiptConfig['telefone']) ?>" maxlength="80">
            </label>

            <div class="actions full" style="margin-top:8px;">
                <button type="submit" class="button">Salvar dados da nota</button>
                <button type="button" class="button secondary" data-close-receipt-modal-global="true">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const toggle = document.querySelector('.menu-toggle');
        const sideMenu = document.querySelector('.side-menu');
        const overlay = document.querySelector('.menu-overlay');
        const navLinks = document.querySelectorAll('.side-nav a');
        const receiptConfigTrigger = document.querySelectorAll('[data-open-receipt-config="true"]');
        const receiptGlobalModal = document.getElementById('receipt-config-modal-global');

        function closeMenu() {
            if (sideMenu) {
                sideMenu.classList.remove('open');
            }
            if (overlay) {
                overlay.classList.remove('open');
            }
            if (toggle) {
                toggle.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        }

        function openMenu() {
            if (sideMenu) {
                sideMenu.classList.add('open');
            }
            if (overlay) {
                overlay.classList.add('open');
            }
            if (toggle) {
                toggle.classList.add('open');
                toggle.setAttribute('aria-expanded', 'true');
            }
        }

        if (toggle && sideMenu && overlay) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                const isOpen = sideMenu.classList.contains('open');
                if (isOpen) {
                    closeMenu();
                } else {
                    openMenu();
                }
            });

            overlay.addEventListener('click', closeMenu);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && sideMenu.classList.contains('open')) {
                    closeMenu();
                }
            });

            document.addEventListener('click', function (event) {
                const clickInsideMenu = sideMenu.contains(event.target);
                const clickToggle = toggle.contains(event.target);
                const clickOverlay = overlay.contains(event.target);

                if (!clickInsideMenu && !clickToggle && !clickOverlay && sideMenu.classList.contains('open')) {
                    closeMenu();
                }
            });
        }

        navLinks.forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (link.getAttribute('data-open-receipt-config') === 'true') {
                    event.preventDefault();
                    if (receiptGlobalModal) {
                        receiptGlobalModal.classList.add('open');
                        receiptGlobalModal.setAttribute('aria-hidden', 'false');
                    }
                    closeMenu();
                    return;
                }
                closeMenu();
            });
        });

        if (receiptGlobalModal) {
            const closeGlobalModal = () => {
                receiptGlobalModal.classList.remove('open');
                receiptGlobalModal.setAttribute('aria-hidden', 'true');
            };

            receiptConfigTrigger.forEach(function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    receiptGlobalModal.classList.add('open');
                    receiptGlobalModal.setAttribute('aria-hidden', 'false');
                    closeMenu();
                });
            });

            receiptGlobalModal.addEventListener('click', function (event) {
                if (event.target === receiptGlobalModal || event.target.classList.contains('modal-backdrop')) {
                    closeGlobalModal();
                }
            });

            document.querySelectorAll('[data-close-receipt-modal-global="true"]').forEach(function (button) {
                button.addEventListener('click', closeGlobalModal);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && receiptGlobalModal.classList.contains('open')) {
                    closeGlobalModal();
                }
            });
        }
    })();
</script>
