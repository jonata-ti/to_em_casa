<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$user = currentUser();

$role = $user['role'] ?? 'vendedor';
$headerMenu = [
    ['label' => 'Início', 'url' => 'index.php', 'icon' => '🏠', 'page' => 'index.php', 'roles' => ['admin', 'vendedor']],
    ['label' => 'Produtos', 'url' => 'products.php?open=1', 'icon' => '📦', 'page' => 'products.php', 'roles' => ['admin']],
    ['label' => 'PDV', 'url' => 'pos.php', 'icon' => '🧾', 'page' => 'pos.php', 'roles' => ['admin', 'vendedor']],
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
                <a href="<?= h($item['url']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>">
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

<script>
    (function () {
        const toggle = document.querySelector('.menu-toggle');
        const sideMenu = document.querySelector('.side-menu');
        const overlay = document.querySelector('.menu-overlay');
        const navLinks = document.querySelectorAll('.side-nav a');

        if (!toggle || !sideMenu || !overlay) {
            return;
        }

        function closeMenu() {
            sideMenu.classList.remove('open');
            overlay.classList.remove('open');
            toggle.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
        }

        function openMenu() {
            sideMenu.classList.add('open');
            overlay.classList.add('open');
            toggle.classList.add('open');
            toggle.setAttribute('aria-expanded', 'true');
        }

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

        navLinks.forEach(function (link) {
            link.addEventListener('click', function () {
                closeMenu();
            });
        });
    })();
</script>
