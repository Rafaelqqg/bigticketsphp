<?php
// Variáveis: $u (usuário a editar), $filiais, $perfil, $msg, $erro, $basePath
$bp = $basePath ?? '';
$paginaUsuarios = true;
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Editar Usuário - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/style.css" />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body>
        <?php
            $paginaHome = null;
            $paginaRegistros = null;
            $paginaUsuarios = true;
            include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="tickets-page-main usuarios-edit-page">
            <div class="page-header-tickets">
                <div class="header-content-tickets">
                    <div class="header-title-section">
                        <h2>Editar usuário</h2>
                        <p class="header-subtitle-tickets"><?= htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success-tickets"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($erro)): ?>
                <div class="alert alert-error-tickets"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <section class="user-edit-shell">
                <div class="user-edit-card">
                    <div class="user-edit-title">
                        <h3>Dados do Usuário</h3>
                        <p>Atualize as informações abaixo para salvar as alterações.</p>
                    </div>

                    <form method="POST" action="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/usuarios/editar/<?= (int)($u['id'] ?? 0) ?>" class="user-edit-form" autocomplete="off">
                        <div class="user-edit-group">
                            <label for="usuario">Usuário</label>
                            <input class="form-control" id="usuario" name="usuario" required
                                   value="<?= htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="user-edit-group">
                            <label for="nome">Nome</label>
                            <input class="form-control" id="nome" name="nome"
                                   value="<?= htmlspecialchars(format_nome_pessoa((string)($u['nome'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="user-edit-group">
                            <label for="email">E-mail</label>
                            <input class="form-control" id="email" name="email" type="email"
                                   value="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>

                        <div class="user-edit-group">
                            <label for="perfil">Perfil</label>
                            <select class="form-control" id="perfil" name="perfil" required>
                                <?php
                                $perfis = ['administrador' => 'Administrador', 'moderador' => 'Moderador', 'recepcao' => 'Recepção', 'manutencao' => 'Manutenção', 'comum' => 'Comum'];
                                $perfilAtual = $u['perfil'] ?? '';
                                foreach ($perfis as $v => $l): ?>
                                    <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $perfilAtual === $v ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-edit-group">
                            <label for="filial_codigo">Filial</label>
                            <select class="form-control" id="filial_codigo" name="filial_codigo">
                                <option value="">—</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string)($u['filial_codigo'] ?? '') === (string)($f['codigo'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-edit-group">
                            <label for="cargo">Cargo</label>
                            <select class="form-control" id="cargo" name="cargo">
                                <?php
                                $cargos = ['Colaborador' => 'Colaborador', 'Gestor' => 'Gestor', 'Supervisor' => 'Supervisor'];
                                $cargoAtual = $u['cargo'] ?? $u['Cargo'] ?? 'Colaborador';
                                foreach ($cargos as $v => $l): ?>
                                    <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $cargoAtual === $v ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="user-edit-group">
                            <label for="senha">Nova senha (deixe em branco para não alterar)</label>
                            <input class="form-control" id="senha" name="senha" type="password" placeholder="Opcional">
                        </div>

                        <div class="user-edit-actions">
                            <button type="submit" class="btn-filter">Salvar</button>
                            <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/usuarios" class="btn-secondary user-edit-back">Voltar</a>
                        </div>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
