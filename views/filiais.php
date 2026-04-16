<?php
// Variáveis: $filiais, $perfil, $msg, $erro, $basePath
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Filiais - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css" />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body>
        <?php
            $paginaHome = null;
            $paginaRegistros = null;
            include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="tickets-page-main">
            <div class="page-header-tickets">
                <div class="header-content-tickets">
                    <div class="header-title-section">
                        <h2>Cadastro de Filiais</h2>
                        <p class="header-subtitle-tickets">Gerencie as filiais utilizadas nos tickets</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success-tickets">
                    <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-error-tickets">
                    <span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <div class="filters-container">
                <div class="filters-header">
                    <h3>Nova filial</h3>
                </div>
                <form method="POST" action="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/filiais" class="filters-form">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="codigo">Código</label>
                            <input class="form-control" id="codigo" name="codigo" required>
                        </div>
                        <div class="filter-group">
                            <label for="cnpj">CNPJ / Nome</label>
                            <input class="form-control" id="cnpj" name="cnpj" required>
                        </div>
                        <div class="filter-group filter-actions">
                            <label>&nbsp;</label>
                            <div class="filters-buttons">
                                <button type="submit" class="btn-filter">Cadastrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="tickets-list-header">
                <div class="list-header-content">
                    <h3 class="tickets-list-title">Filiais</h3>
                    <span class="tickets-count-badge">
                        <?= count($filiais) ?> filial<?= count($filiais) !== 1 ? 'es' : '' ?>
                    </span>
                </div>
            </div>

            <?php if (!empty($filiais)): ?>
                <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Código</th>
                        <th>CNPJ / Nome</th>
                        <th>Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filiais as $idx => $f): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($f['cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="ticket-actions" style="gap: 0.25rem;">
                                    <form method="POST"
                                          action="<?= htmlspecialchars(($basePath ?? '') . '/filiais/editar/' . rawurlencode($f['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                          style="display: inline;">
                                        <input type="hidden" name="codigo" value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="cnpj" value="<?= htmlspecialchars($f['cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn-edit" title="Editar"><i class="fa-solid fa-pen"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <div class="empty-state-tickets">
                    <h3>Nenhuma filial cadastrada</h3>
                    <p>Use o formulário acima para cadastrar a primeira filial.</p>
                </div>
            <?php endif; ?>
        </main>
    </body>
</html>

