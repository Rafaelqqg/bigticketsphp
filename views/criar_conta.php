<?php
// Variáveis: $erro, $msg, $filiais (array), $basePath
$bp = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Criar Conta - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/style.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="criar-conta-page">
        <div class="login-container">
            <div class="login-card" style="max-width: 480px;">
                <div class="login-header">
                    <h1 class="login-company-name">Criar conta</h1>
                    <p class="login-subtitle-secondary">Big Trading · Sistema de Tickets</p>
                </div>

                <?php if (!empty($erro)): ?>
                    <div class="login-alert login-alert-error">
                        <span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($msg)): ?>
                    <div class="login-alert login-alert-success">
                        <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                <?php endif; ?>

                <?php if (empty($msg)): ?>
                    <form method="POST" action="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/criar-conta" class="login-form">
                        <div class="login-form-group">
                            <label for="nome" class="login-label">Nome completo</label>
                            <input id="nome" name="nome" type="text" class="login-input"
                                   placeholder="Digite seu nome completo" autocomplete="name" />
                        </div>
                        <div class="login-form-group">
                            <label for="email" class="login-label">E-mail *</label>
                            <input id="email" name="email" type="email" class="login-input" required
                                   placeholder="Digite seu e-mail" autocomplete="email" />
                        </div>
                        <div class="login-form-group">
                            <label for="filial_codigo" class="login-label">Filial *</label>
                            <select id="filial_codigo" name="filial_codigo" class="login-input" required>
                                <option value="">Selecione uma filial</option>
                                <?php if (!empty($filiais)): ?>
                                    <?php foreach ($filiais as $filial): ?>
                                        <option value="<?= htmlspecialchars($filial['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($filial['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="login-form-group">
                            <label for="usuario" class="login-label">Nome de usuário *</label>
                            <input id="usuario" name="usuario" type="text" class="login-input" required
                                   placeholder="Digite um nome de usuário" autocomplete="username" />
                            <small style="display:block;margin-top:0.35rem;color:#64748b;font-size:0.75rem;">
                                Sem acentuação (ex.: joao.silva)
                            </small>
                        </div>
                        <div class="login-form-group">
                            <label for="senha" class="login-label">Senha *</label>
                            <input id="senha" name="senha" type="password" class="login-input" required
                                   placeholder="Digite sua senha" autocomplete="new-password" />
                        </div>
                        <div class="login-form-group">
                            <label for="confirmarSenha" class="login-label">Confirmar senha *</label>
                            <input id="confirmarSenha" name="confirmarSenha" type="password" class="login-input" required
                                   placeholder="Confirme sua senha" autocomplete="new-password" />
                        </div>
                        <button type="submit" class="login-btn criar-conta-btn">
                            Criar conta
                        </button>
                        <div class="criar-conta-voltar">
                            <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/login">Voltar para o login</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/login" class="login-btn" style="text-decoration: none; display: inline-block; width: 100%; text-align: center;">
                            Ir para o login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            (function () {
                const userInput = document.getElementById('usuario');
                if (!userInput) return;

                function removeAccents(value) {
                    if (!value) return '';
                    try {
                        return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    } catch (e) {
                        return value;
                    }
                }

                userInput.addEventListener('input', function () {
                    const current = userInput.value || '';
                    const noAccent = removeAccents(current);
                    if (current !== noAccent) {
                        userInput.value = noAccent;
                    }
                });
            })();
        </script>
    </body>
</html>
