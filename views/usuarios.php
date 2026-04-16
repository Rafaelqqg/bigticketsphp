<?php
// Variáveis: $usuarios, $filiais, $solicitacoes, $filtros, $paginacao, $perfil, $msg, $erro, $basePath
$paginaUsuarios = true;
$filtros = $filtros ?? ['nome' => '', 'filial' => '', 'ativo' => '', 'cargo' => ''];
$paginacao = $paginacao ?? ['totalItens' => 0, 'limit' => 15, 'paginaAtual' => 1, 'totalPaginas' => 1];
$bp = $basePath ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Gerenciamento de Usuários - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css" />
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

        <main class="tickets-page-main usuarios-page">
            <div class="page-header-tickets">
                <div class="header-content-tickets">
                    <div class="header-title-section">
                        <h2><i class="fa-solid fa-users"></i> Gerenciamento de Usuários</h2>
                        <p class="header-subtitle-tickets">Controle de acesso e permissões do sistema</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success-tickets"><span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if (!empty($erro)): ?>
                <div class="alert alert-error-tickets"><span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>

            <?php if (!empty($solicitacoes)): ?>
                <div class="users-container users-card solicitacoes-card">
                    <div class="section-header">
                        <div class="header-left">
                            <h3><i class="fa-solid fa-user-clock"></i> Solicitações de cadastro (pendentes)</h3>
                            <div class="user-count">
                                <span class="count-number"><?= count($solicitacoes) ?></span>
                                <span class="count-label">pendentes</span>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive users-table-container">
                    <table class="users-table solicitacoes-table">
                    <thead>
                        <tr>
                            <th>#</th><th>Usuário</th><th>Nome</th><th>E-mail</th><th>Filial</th><th>Data</th><th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($solicitacoes as $idx => $s): ?>
                            <tr>
                                <td style="text-align:center;color:#6b7280;font-size:0.75rem;font-weight:500;"><?= $idx + 1 ?></td>
                                <td class="user-info">
                                    <div class="user-avatar"><span class="avatar-text"><?= htmlspecialchars(strtoupper(substr((string)($s['usuario'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="user-details">
                                        <span class="user-name"><?= htmlspecialchars($s['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td><span class="user-name"><?php $sn = format_nome_pessoa((string)($s['nome'] ?? '')); echo htmlspecialchars($sn, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><?= htmlspecialchars($s['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="filial-badge"><?= htmlspecialchars($s['filial_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td style="font-size:0.72rem;color:#64748b;"><?php $dt = $s['data_solicitacao'] ?? null; echo $dt ? htmlspecialchars((new DateTime($dt))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td>
                                    <div class="ticket-actions users-actions">
                                        <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/usuarios/solicitacoes/aprovar/' . (int)($s['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="user-action-btn solic-approve-btn" title="Aprovar"><i class="fa-solid fa-check"></i></button>
                                        </form>
                                        <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/usuarios/solicitacoes/rejeitar/' . (int)($s['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="user-action-btn solic-reject-btn" title="Rejeitar"><i class="fa-solid fa-xmark"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                </div>
            <?php endif; ?>

            <div class="users-tabs">
                <button class="tab-btn active" type="button"><i class="fa-solid fa-users"></i> Usuários</button>
                <button class="tab-btn" type="button" id="toggleUserFormBtn" onclick="toggleUserForm()"><i class="fa-solid fa-plus"></i> Novo Usuário</button>
            </div>

            <div class="filters-section-relatorio filters-usuarios-card">
                <h3>Filtros</h3>
                <form method="GET" action="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/usuarios" class="filters-form">
                    <div class="filters-grid-relatorio">
                        <div class="filter-group-relatorio">
                            <label for="filtro-nome">Nome ou usuário</label>
                            <input id="filtro-nome" name="nome" type="text" value="<?= htmlspecialchars($filtros['nome'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por nome ou login">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="filtro-filial">Filial</label>
                            <select id="filtro-filial" name="filial">
                                <option value="">Todas</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filtros['filial'] ?? '') === (string)($f['codigo'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="filtro-ativo">Status</label>
                            <select id="filtro-ativo" name="ativo">
                                <option value="">Todos</option>
                                <option value="1" <?= ($filtros['ativo'] ?? '') === '1' ? 'selected' : '' ?>>Ativo</option>
                                <option value="0" <?= ($filtros['ativo'] ?? '') === '0' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="filtro-cargo">Cargo</label>
                            <select id="filtro-cargo" name="cargo">
                                <option value="">Todos</option>
                                <option value="Colaborador" <?= ($filtros['cargo'] ?? '') === 'Colaborador' ? 'selected' : '' ?>>Colaborador</option>
                                <option value="Gestor" <?= ($filtros['cargo'] ?? '') === 'Gestor' ? 'selected' : '' ?>>Gestor</option>
                                <option value="Supervisor" <?= ($filtros['cargo'] ?? '') === 'Supervisor' ? 'selected' : '' ?>>Supervisor</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions-relatorio">
                        <button type="submit" class="btn-filter-relatorio btn-primary">Filtrar</button>
                        <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/usuarios" class="btn-filter-relatorio btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>

            <div id="new-user-form-wrap" class="filters-container" style="display:none;">
                <div class="filters-header"><h3>Novo usuário</h3></div>
                <form method="POST" action="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/usuarios" class="filters-form">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label for="usuario">Usuário</label>
                            <input class="form-control" id="usuario" name="usuario" required>
                        </div>
                        <div class="filter-group">
                            <label for="nome">Nome</label>
                            <input class="form-control" id="nome" name="nome" placeholder="Ex.: Mara Almeida" autocomplete="name">
                        </div>
                        <div class="filter-group">
                            <label for="email">E-mail</label>
                            <input class="form-control" id="email" name="email" type="email">
                        </div>
                        <div class="filter-group">
                            <label for="perfil">Perfil</label>
                            <select class="form-control" id="perfil" name="perfil" required>
                                <option value="">Selecione</option>
                                <option value="administrador">Administrador</option>
                                <option value="moderador">Moderador</option>
                                <option value="recepcao">Recepção</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="comum">Comum</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="filial_codigo">Filial</label>
                            <select class="form-control" id="filial_codigo" name="filial_codigo" required>
                                <option value="">Selecione</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="cargo">Cargo</label>
                            <select class="form-control" id="cargo" name="cargo">
                                <option value="Colaborador" selected>Colaborador</option>
                                <option value="Gestor">Gestor</option>
                                <option value="Supervisor">Supervisor</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="senha">Senha</label>
                            <input class="form-control" id="senha" name="senha" type="password" required>
                        </div>
                    </div>
                    <div class="filters-row">
                        <div class="filter-group filter-actions">
                            <label>&nbsp;</label>
                            <div class="filters-buttons">
                                <button type="submit" class="btn-filter">Cadastrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="users-container users-card">
                <div class="section-header">
                    <div class="header-left">
                        <h3>Usuários do Sistema</h3>
                        <div class="user-count">
                            <span class="count-number"><?= (int)($paginacao['totalItens'] ?? 0) ?></span>
                            <span class="count-label">usuários cadastrados</span>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-box">
                            <input type="text" placeholder="Buscar usuários..." id="searchInput" />
                        </div>
                    </div>
                </div>

                <?php if (!empty($usuarios)): ?>
                    <div class="table-responsive users-table-container">
                    <table class="users-table">
                        <thead>
                        <tr>
                            <th style="width:50px;">#</th>
                            <th>Usuário</th>
                            <th>Nome</th>
                            <th>Nível de Acesso</th>
                            <th>Cargo</th>
                            <th>Filial</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody id="users-table-body">
                        <?php foreach (($usuarios ?? []) as $idx => $u): ?>
                            <?php $ativo = (int)($u['ativo'] ?? 1); ?>
                            <tr class="user-row"
                                data-username="<?= htmlspecialchars(strtolower((string)($u['usuario'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                data-name="<?= htmlspecialchars(strtolower(format_nome_pessoa((string)($u['nome'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>"
                                data-email="<?= htmlspecialchars(strtolower((string)($u['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                <td style="text-align:center;color:#6b7280;font-size:0.75rem;font-weight:500;"><?= $idx + 1 ?></td>
                                <td class="user-info">
                                    <div class="user-avatar"><span class="avatar-text"><?= htmlspecialchars(strtoupper(substr((string)($u['usuario'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="user-details">
                                        <span class="user-name"><?= htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="user-id">ID: <?= (int)($u['id'] ?? 0) ?></span>
                                    </div>
                                </td>
                                <td><span class="user-name"><?php $un = format_nome_pessoa((string)($u['nome'] ?? '')); echo htmlspecialchars($un !== '' ? $un : 'Não informado', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td>
                                    <?php
                                    $perfilTxt = strtolower((string)($u['perfil'] ?? 'comum'));
                                    $perfilLabel = $perfilTxt === 'administrador' ? 'Administrador' : ($perfilTxt === 'moderador' ? 'Moderador' : ($perfilTxt === 'recepcao' ? 'Recepção' : ($perfilTxt === 'manutencao' ? 'Manutenção' : 'Comum')));
                                    ?>
                                    <span class="access-level access-<?= htmlspecialchars($perfilTxt, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($perfilLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><span class="cargo-badge"><?= htmlspecialchars($u['cargo'] ?? $u['Cargo'] ?? 'Colaborador', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="filial-badge"><?= htmlspecialchars($u['filial_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><span class="status-badge <?= $ativo ? 'status-active' : 'status-inactive' ?>"><?= $ativo ? 'Ativo' : 'Inativo' ?></span></td>
                                <td>
                                    <div class="ticket-actions users-actions">
                                        <?php if (($u['usuario'] ?? '') === 'admin'): ?>
                                            <span class="admin-principal-badge">ADMINISTRADOR PRINCIPAL</span>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="user-action-btn user-action-edit"
                                                    title="Editar"
                                                    data-edit-user="1"
                                                    data-id="<?= (int)($u['id'] ?? 0) ?>"
                                                    data-usuario="<?= htmlspecialchars((string)($u['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-nome="<?= htmlspecialchars(format_nome_pessoa((string)($u['nome'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-email="<?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-perfil="<?= htmlspecialchars((string)($u['perfil'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-filial-codigo="<?= htmlspecialchars((string)($u['filial_codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                    data-cargo="<?= htmlspecialchars((string)($u['cargo'] ?? $u['Cargo'] ?? 'Colaborador'), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/usuarios/toggle-ativo/' . (int)$u['id'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;">
                                                <button type="submit"
                                                        class="user-action-btn user-action-toggle <?= $ativo ? 'is-on' : 'is-off' ?>"
                                                        title="<?= $ativo ? 'Desativar (ON)' : 'Ativar (OFF)' ?>"
                                                        data-toggle-ativo="<?= $ativo ? '1' : '0' ?>"
                                                        data-usuario-toggle="<?= htmlspecialchars((string)($u['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <i class="fa-solid <?= $ativo ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/usuarios/excluir/' . (int)$u['id'], ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Excluir usuário <?= htmlspecialchars($u['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>?');">
                                                <button type="submit" class="user-action-btn user-action-delete" title="Excluir"><i class="fa-solid fa-trash-can"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php
                $pagAtual = (int)($paginacao['paginaAtual'] ?? 1);
                $pagTotal = (int)($paginacao['totalPaginas'] ?? 1);
                $limit = (int)($paginacao['limit'] ?? 15);
                $baseUrl = $bp . '/usuarios';
                $baseParams = array_filter([
                    'nome'   => $filtros['nome'] ?? '',
                    'filial' => $filtros['filial'] ?? '',
                    'ativo'  => (string)($filtros['ativo'] ?? ''),
                    'cargo'  => $filtros['cargo'] ?? '',
                ], fn($v) => $v !== '');
                if ($pagTotal > 1): ?>
                    <div class="pagination-relatorio" style="margin-top:1rem; display:flex; align-items:center; gap:0.75rem;">
                        <?php if ($pagAtual > 1): ?>
                            <a class="btn-filter-relatorio btn-secondary"
                               href="<?= htmlspecialchars($baseUrl . '?' . http_build_query(array_merge($baseParams, ['page' => $pagAtual - 1, 'perPage' => $limit])), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        <span style="font-size:0.85rem; color:#64748b;">
                            Página <?= $pagAtual ?> de <?= $pagTotal ?>
                        </span>
                        <?php if ($pagAtual < $pagTotal): ?>
                            <a class="btn-filter-relatorio btn-secondary"
                               href="<?= htmlspecialchars($baseUrl . '?' . http_build_query(array_merge($baseParams, ['page' => $pagAtual + 1, 'perPage' => $limit])), ENT_QUOTES, 'UTF-8') ?>">
                                Próxima <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state-tickets">
                        <h3>Nenhum usuário encontrado</h3>
                        <p>Use o botão "Novo Usuário" para cadastrar o primeiro.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="userEditModal" class="user-edit-modal" aria-hidden="true">
                <div class="user-edit-modal-backdrop" data-close-user-modal="1"></div>
                <div class="user-edit-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-edit-modal-title">
                    <div class="user-edit-modal-header">
                        <h3 id="user-edit-modal-title"><i class="fa-solid fa-user-pen"></i> Editar Usuário</h3>
                        <button type="button" class="user-edit-modal-close" data-close-user-modal="1" aria-label="Fechar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <form id="userEditModalForm" method="POST" action="" class="user-edit-modal-form">
                        <div class="user-edit-modal-group">
                            <label for="modal-usuario">Usuário</label>
                            <input class="form-control" id="modal-usuario" name="usuario" required>
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-nome">Nome</label>
                            <input class="form-control" id="modal-nome" name="nome" placeholder="Nome completo" autocomplete="name">
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-email">E-mail</label>
                            <input class="form-control" id="modal-email" name="email" type="email">
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-perfil">Perfil</label>
                            <select class="form-control" id="modal-perfil" name="perfil" required>
                                <option value="administrador">Administrador</option>
                                <option value="moderador">Moderador</option>
                                <option value="recepcao">Recepção</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="comum">Comum</option>
                            </select>
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-filial-codigo">Filial</label>
                            <select class="form-control" id="modal-filial-codigo" name="filial_codigo">
                                <option value="">—</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-cargo">Cargo</label>
                            <select class="form-control" id="modal-cargo" name="cargo">
                                <option value="Colaborador">Colaborador</option>
                                <option value="Gestor">Gestor</option>
                                <option value="Supervisor">Supervisor</option>
                            </select>
                        </div>
                        <div class="user-edit-modal-group">
                            <label for="modal-senha">Nova senha (opcional)</label>
                            <input class="form-control" id="modal-senha" name="senha" type="password" placeholder="Deixe em branco para não alterar">
                        </div>
                        <div class="user-edit-modal-actions">
                            <button type="submit" class="btn-filter">Salvar</button>
                            <button type="button" class="btn-secondary" data-close-user-modal="1">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>

        <script>
            const basePath = <?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?>;

            function toggleUserForm() {
                const wrap = document.getElementById('new-user-form-wrap');
                const btn = document.getElementById('toggleUserFormBtn');
                const isHidden = wrap.style.display === 'none' || wrap.style.display === '';
                wrap.style.display = isHidden ? 'block' : 'none';
                btn.classList.toggle('active', isHidden);
            }

            function forceUppercaseName(input) {
                if (!input) return;
                input.value = (input.value || '').toLocaleUpperCase('pt-BR');
            }

            function openUserEditModal(dataset) {
                const modal = document.getElementById('userEditModal');
                const form = document.getElementById('userEditModalForm');
                if (!modal || !form) return;

                const id = String(dataset.id || '').trim();
                if (!id) return;

                form.action = (basePath || '') + '/usuarios/editar/' + id;
                document.getElementById('modal-usuario').value = dataset.usuario || '';
                document.getElementById('modal-nome').value = (dataset.nome || '').toLocaleUpperCase('pt-BR');
                document.getElementById('modal-email').value = dataset.email || '';
                document.getElementById('modal-perfil').value = dataset.perfil || 'comum';
                document.getElementById('modal-filial-codigo').value = dataset.filialCodigo || '';
                document.getElementById('modal-cargo').value = dataset.cargo || 'Colaborador';
                document.getElementById('modal-senha').value = '';

                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
            }

            function closeUserEditModal() {
                const modal = document.getElementById('userEditModal');
                if (!modal) return;
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
            }

            function filterUsers() {
                const term = (document.getElementById('searchInput')?.value || '').toLowerCase().trim();
                document.querySelectorAll('#users-table-body .user-row').forEach((row) => {
                    const username = row.getAttribute('data-username') || '';
                    const name = row.getAttribute('data-name') || '';
                    const email = row.getAttribute('data-email') || '';
                    const ok = !term || username.includes(term) || name.includes(term) || email.includes(term);
                    row.style.display = ok ? '' : 'none';
                });
            }

            document.getElementById('searchInput')?.addEventListener('input', filterUsers);
            const novoNomeInput = document.getElementById('nome');
            const modalNomeInput = document.getElementById('modal-nome');
            novoNomeInput?.addEventListener('input', () => forceUppercaseName(novoNomeInput));
            modalNomeInput?.addEventListener('input', () => forceUppercaseName(modalNomeInput));
            document.querySelectorAll('[data-edit-user="1"]').forEach((btn) => {
                btn.addEventListener('click', () => openUserEditModal(btn.dataset));
            });
            document.querySelectorAll('[data-close-user-modal="1"]').forEach((el) => {
                el.addEventListener('click', closeUserEditModal);
            });
            document.querySelectorAll('.user-action-toggle[data-toggle-ativo="1"]').forEach((btn) => {
                btn.addEventListener('click', function (e) {
                    const usuario = btn.getAttribute('data-usuario-toggle') || 'este usuário';
                    const ok = window.confirm('Ao inativar "' + usuario + '", ele será desconectado automaticamente e não poderá mais logar. Deseja continuar?');
                    if (!ok) {
                        e.preventDefault();
                    }
                });
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeUserEditModal();
            });
        </script>
    </body>
</html>

