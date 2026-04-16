<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Login - Big Trading | Sistema de Tickets</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body class="login-page-split">
        <div class="login-split-container">
            <!-- Coluna esquerda: formulário -->
            <div class="login-split-form-col">
                <div class="login-card-split">
                    <div class="login-header-split">
                        <div class="login-logo-wrap">
                            <?php
                            $bp = $basePath ?? '';
                            $logoPng = $bp . '/public/images/logo.png';
                            $logoSvg = $bp . '/public/images/logo.svg';
                            $logoPathPng = __DIR__ . '/../public/images/logo.png';
                            $logoPathSvg = __DIR__ . '/../public/images/logo.svg';
                            $hasLogo = file_exists($logoPathPng) || file_exists($logoPathSvg);
                            $logoUrl = file_exists($logoPathPng) ? $logoPng : (file_exists($logoPathSvg) ? $logoSvg : '');
                            ?>
                            <?php if ($hasLogo && $logoUrl): ?>
                                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Big Trading" class="login-logo-img" />
                            <?php else: ?>
                                <div class="login-logo-icon">
                                    <i class="fa-solid fa-headset"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h1 class="login-company-name-split">Big Trading</h1>
                            </div>
                        </div>
                        <p class="login-subtitle-split">Sistema de Tickets</p>
                    </div>

                    <?php if (!empty($erro)): ?>
                        <div class="login-alert login-alert-error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($msg)): ?>
                        <div class="login-alert login-alert-success">
                            <i class="fa-solid fa-circle-check"></i>
                            <span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/login" class="login-form" id="loginForm">
                        <div class="login-form-group">
                            <label for="usuario" class="login-label"><i class="fa-solid fa-user login-input-icon"></i>Usuário</label>
                            <input
                                id="usuario"
                                name="usuario"
                                type="text"
                                required
                                placeholder="Digite seu usuário"
                                autocomplete="username"
                                class="login-input"
                            />
                        </div>

                        <div class="login-form-group">
                            <label for="senha" class="login-label"><i class="fa-solid fa-lock login-input-icon"></i>Senha</label>
                            <div class="login-input-wrapper">
                                <input
                                    id="senha"
                                    name="senha"
                                    type="password"
                                    required
                                    placeholder="Digite sua senha"
                                    autocomplete="current-password"
                                    class="login-input"
                                />
                                <button type="button" class="login-toggle-password" id="togglePassword" aria-label="Mostrar senha">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="login-btn login-btn-split" id="loginButton">
                            Entrar
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>

                        <div class="login-footer-links">
                            <a href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/criar-conta" class="login-link-create">
                                <i class="fa-solid fa-user-plus"></i> Criar conta
                            </a>
                        </div>
                        <p class="login-subtitle-ti login-ti-bottom">Tecnologia da Informação</p>
                    </form>
                </div>
            </div>

            <!-- Coluna direita: informativo -->
            <div class="login-split-info-col">
                <div class="login-info-content">
                    <h2 class="login-info-title">Bem-vindo ao Helpdesk</h2>
                    <p class="login-info-desc">Gerencie seus chamados e solicitações internas de forma simples, organizada e eficiente.</p>
                    <ul class="login-info-features">
                        <li>
                            <i class="fa-solid fa-ticket"></i>
                            <span>Abertura de chamados</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-clock"></i>
                            <span>Acompanhamento em tempo real</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-folder-open"></i>
                            <span>Histórico completo de atendimentos</span>
                        </li>
                        <li>
                            <i class="fa-solid fa-headset"></i>
                            <span>Suporte técnico especializado</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <script>
            const loginForm = document.getElementById('loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function() {
                    const button = document.getElementById('loginButton');
                    if (button) {
                        button.innerHTML = 'Entrando... <i class="fa-solid fa-spinner fa-spin"></i>';
                        button.disabled = true;
                    }
                });
            }

            document.getElementById('togglePassword')?.addEventListener('click', function() {
                const passwordInput = document.getElementById('senha');
                const icon = this.querySelector('i');

                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        </script>
    </body>
</html>
