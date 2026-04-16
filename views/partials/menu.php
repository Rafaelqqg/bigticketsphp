<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<?php
// Variáveis esperadas: $perfil, $paginaHome, $paginaRegistros, $paginaUsuarios, $paginaTickets, $paginaTicketsNovo, $basePath
$perfilLower = isset($perfil) ? strtolower((string)$perfil) : '';
$menuUser = (isset($usuario) && is_array($usuario)) ? $usuario : (function_exists('current_user') ? current_user() : null);
$menuPerfilLabel = '';
if ($menuUser) {
    $pr = strtolower((string)($menuUser['perfil'] ?? $perfil ?? ''));
    if ($pr === 'administrador') {
        $menuPerfilLabel = 'Administrador';
    } elseif ($pr === 'moderador') {
        $menuPerfilLabel = 'Moderador';
    } elseif ($pr === 'recepcao') {
        $menuPerfilLabel = 'Recepção';
    } elseif ($pr === 'manutencao') {
        $menuPerfilLabel = 'Manutenção';
    } elseif ($pr === 'comum') {
        $menuPerfilLabel = ''; /* solicitante: não mostrar linha de perfil no menu */
    } else {
        $menuPerfilLabel = (string)($menuUser['perfil'] ?? $perfil ?? '—');
    }
}
?>

<div class="nav-background-wrapper"></div>

<div class="nav-content-inner">
    <div class="logo-container">
        <h1>Big Trading</h1>
        <p class="logo-subtitle">Tecnologia da Informação</p>
    </div>

    <?php if (is_array($menuUser) && (trim((string)($menuUser['usuario'] ?? '')) !== '' || trim((string)($menuUser['nome'] ?? '')) !== '')): ?>
        <?php
        $menuLogin = trim((string)($menuUser['usuario'] ?? ''));
        $menuNome = trim((string)($menuUser['nome'] ?? ''));
        $menuNomeExibir = $menuNome !== '' ? $menuNome : ($menuLogin !== '' ? $menuLogin : 'Usuário');
        $menuInicial = function_exists('mb_substr')
            ? strtoupper((string)mb_substr($menuNomeExibir, 0, 1, 'UTF-8'))
            : strtoupper(substr($menuNomeExibir, 0, 1));
        ?>
        <div class="menu-user-card" role="status" aria-label="Sessão atual">
            <div class="menu-user-avatar" aria-hidden="true"><?= htmlspecialchars($menuInicial, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="menu-user-info">
                <span class="menu-user-name" title="<?= htmlspecialchars($menuNomeExibir, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menuNomeExibir, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($menuLogin !== '' && $menuNome !== '' && strcasecmp($menuLogin, $menuNome) !== 0): ?>
                    <span class="menu-user-login" title="Login">@<?= htmlspecialchars($menuLogin, ENT_QUOTES, 'UTF-8') ?></span>
                <?php elseif ($menuLogin !== ''): ?>
                    <span class="menu-user-login" title="Login"><?= htmlspecialchars($menuLogin, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <?php if ($menuPerfilLabel !== ''): ?>
                    <span class="menu-user-perfil"><i class="fa-solid fa-id-badge" aria-hidden="true"></i> <?= htmlspecialchars($menuPerfilLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (in_array($perfilLower, ['administrador', 'moderador', 'recepcao', 'manutencao'], true)): ?>
        <a href="<?= htmlspecialchars(($basePath ?? '') . '/', ENT_QUOTES, 'UTF-8') ?>"
           class="menu-item <?= !empty($paginaHome) ? 'active' : '' ?>"
           data-menu-item="home">
            <span class="menu-icon"><i class="fa-solid fa-house"></i></span> Início
        </a>
        <div class="menu-separator"></div>
        <a href="<?= htmlspecialchars(($basePath ?? '') . '/relatorio', ENT_QUOTES, 'UTF-8') ?>"
           class="menu-item <?= !empty($paginaRegistros) ? 'active' : '' ?>"
           data-menu-item="relatorio">
            <span class="menu-icon"><i class="fa-solid fa-chart-column"></i></span> Relatórios
        </a>
    <?php endif; ?>

    <?php if ($perfilLower === 'administrador'): ?>
        <div class="menu-separator"></div>
        <a href="<?= htmlspecialchars(($basePath ?? '') . '/usuarios', ENT_QUOTES, 'UTF-8') ?>"
           class="menu-item <?= !empty($paginaUsuarios) ? 'active' : '' ?>"
           data-menu-item="usuarios">
            <span class="menu-icon"><i class="fa-solid fa-users"></i></span> Usuários
        </a>
        <div class="menu-separator"></div>
    <?php endif; ?>

    <?php if (in_array($perfilLower, ['administrador', 'moderador', 'recepcao', 'manutencao'], true)): ?>
        <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets', ENT_QUOTES, 'UTF-8') ?>"
           class="menu-item tickets-link <?= !empty($paginaTickets) ? 'active' : '' ?>"
           data-menu-item="tickets">
            <span class="menu-icon"><i class="fa-solid fa-ticket"></i></span> Tickets
        </a>
    <?php endif; ?>

    <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo', ENT_QUOTES, 'UTF-8') ?>"
       class="menu-item tickets-link <?= !empty($paginaTicketsNovo) ? 'active' : '' ?>"
       data-menu-item="novo-ticket">
        <span class="menu-icon"><i class="fa-solid fa-plus"></i></span> Novo Ticket
    </a>

    <a href="<?= htmlspecialchars(($basePath ?? '') . '/logout', ENT_QUOTES, 'UTF-8') ?>"
       class="menu-item logout"
       data-menu-item="logout">
        <span class="menu-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></span> Sair
    </a>

    <div class="menu-spacer"></div>
</div>

