<?php
$paginaHome = null;
$paginaTickets = null;
$paginaRegistros = null;
$paginaUsuarios = null;
$perfil = $perfil ?? current_profile();
$bp = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada - Big Trading</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>
    <?php if (current_user()): ?>
    <?php include __DIR__ . '/partials/nav_with_toggle.php'; ?>
    <?php endif; ?>

    <main class="main-404" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; padding: 2rem; text-align: center;">
        <div class="page-header-tickets" style="margin-bottom: 1.5rem; padding: 1.5rem; border-radius: 8px;">
            <h2 style="margin: 0; color: #fff;">404</h2>
            <p class="header-subtitle-tickets" style="margin: 0.5rem 0 0;">Página não encontrada</p>
        </div>
        <p style="color: #64748b; margin-bottom: 1.5rem;">A página que você procura não existe ou foi movida.</p>
        <?php $homeUrl = current_user() ? ($bp ? rtrim($bp,'/').'/' : '/') : ($bp ? $bp.'/login' : '/login'); ?>
        <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary">
            <i class="fa-solid fa-house"></i> <?= current_user() ? 'Voltar ao início' : 'Ir para login' ?>
        </a>
    </main>
</body>
</html>
