<?php
// Variáveis: $tickets, $perfil, $usuario, $responsaveis, $filiais, $filtros, $paginacao, $basePath
$paginaTickets = true;
$filtros = $filtros ?? [];
$paginacao = $paginacao ?? ['totalItens' => 0, 'limit' => 15];
$bp = $basePath ?? '';
$msg = $msg ?? null;
$erro = $erro ?? null;
$podeExcluirTicket = is_array($usuario ?? null) && strtolower((string)($usuario['usuario'] ?? '')) === 'admin';
$canAssign = in_array(strtolower((string)($perfil ?? '')), ['administrador', 'moderador', 'recepcao', 'manutencao'], true);
$returnToCurrent = (string)($_SERVER['REQUEST_URI'] ?? '/tickets');
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sistema de Tickets - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/public/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body>
        <?php
        $paginaHome = null;
        $paginaRegistros = null;
        include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="tickets-page-main tickets-list-page">
            <div class="page-header-tickets">
                <div class="header-content-tickets">
                    <div class="header-title-section">
                        <h2>Sistema de Tickets de Suporte</h2>
                        <p class="header-subtitle-tickets">Gerencie e acompanhe todos os chamados de suporte técnico</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success-tickets"><span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>
            <?php if (!empty($erro)): ?>
                <div class="alert alert-error-tickets"><span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span></div>
            <?php endif; ?>

            <div class="filters-section-relatorio">
                <h3>Filtros</h3>
                <form method="GET" action="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/tickets">
                    <div class="filters-grid-relatorio">
                        <div class="filter-group-relatorio">
                            <label for="numero_chamado">Nº Chamado</label>
                            <input id="numero_chamado" name="numero_chamado" value="<?= htmlspecialchars($filtros['numero_chamado'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Aberto" <?= ($filtros['status'] ?? '') === 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em andamento" <?= ($filtros['status'] ?? '') === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                                <option value="Fechado" <?= in_array($filtros['status'] ?? '', ['Fechado','Resolvido']) ? 'selected' : '' ?>>Fechado</option>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="categoria">Categoria</label>
                            <select id="categoria" name="categoria">
                                <option value="">Todas</option>
                                <option value="Suporte" <?= ($filtros['categoria'] ?? '') === 'Suporte' ? 'selected' : '' ?>>Suporte</option>
                                <option value="Hardware" <?= ($filtros['categoria'] ?? '') === 'Hardware' ? 'selected' : '' ?>>Hardware</option>
                                <option value="Software" <?= ($filtros['categoria'] ?? '') === 'Software' ? 'selected' : '' ?>>Software</option>
                                <option value="Rede/Infraestrutura" <?= in_array(($filtros['categoria'] ?? ''), ['Rede/Infraestrutura', 'Rede'], true) ? 'selected' : '' ?>>Rede/Infraestrutura</option>
                                <option value="Desenvolvimento" <?= ($filtros['categoria'] ?? '') === 'Desenvolvimento' ? 'selected' : '' ?>>Desenvolvimento</option>
                                <option value="manutencao" <?= ($filtros['categoria'] ?? '') === 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
                                <option value="Tonner" <?= ($filtros['categoria'] ?? '') === 'Tonner' ? 'selected' : '' ?>>Tonner</option>
                                <option value="Drum" <?= ($filtros['categoria'] ?? '') === 'Drum' ? 'selected' : '' ?>>Drum</option>
                                <option value="Tonner & Drum" <?= ($filtros['categoria'] ?? '') === 'Tonner & Drum' ? 'selected' : '' ?>>Tonner & Drum</option>
                                <option value="Uso e Consumo" <?= ($filtros['categoria'] ?? '') === 'Uso e Consumo' ? 'selected' : '' ?>>Uso e Consumo</option>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="prioridade">Prioridade</label>
                            <select id="prioridade" name="prioridade">
                                <option value="">Todas</option>
                                <option value="Baixa" <?= ($filtros['prioridade'] ?? '') === 'Baixa' ? 'selected' : '' ?>>Baixa</option>
                                <option value="Média" <?= ($filtros['prioridade'] ?? '') === 'Média' ? 'selected' : '' ?>>Média</option>
                                <option value="Alta" <?= ($filtros['prioridade'] ?? '') === 'Alta' ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="responsavel">Responsável</label>
                            <select id="responsavel" name="responsavel">
                                <option value="">Todos</option>
                                <?php foreach ($responsaveis ?? [] as $r): ?>
                                    <?php
                                    $userLogin = strtolower(trim((string)($r['usuario'] ?? '')));
                                    $userNome  = strtolower(trim((string)($r['nome'] ?? '')));
                                    if ($userLogin === 'dashboard' || $userLogin === 'dash' || str_starts_with($userNome, 'dash')) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= htmlspecialchars($r['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= ($filtros['responsavel'] ?? '') === ($r['usuario'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['nome'] ?? $r['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="filial">Filial</label>
                            <select id="filial" name="filial">
                                <option value="">Todas</option>
                                <?php foreach ($filiais ?? [] as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" <?= (string)($filtros['filial'] ?? '') === (string)($f['codigo'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="data_ini">Data inicial</label>
                            <input id="data_ini" name="data_ini" type="date" value="<?= htmlspecialchars($filtros['data_ini'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="data_fim">Data final</label>
                            <input id="data_fim" name="data_fim" type="date" value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="filter-actions-relatorio">
                        <button type="submit" class="btn-filter-relatorio btn-primary">Filtrar</button>
                        <a href="<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/tickets" class="btn-filter-relatorio btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>

            <div class="tickets-list-header tickets-board-header">
                <div class="list-header-content">
                    <h3 class="tickets-list-title">Lista de Tickets</h3>
                    <span class="tickets-count-badge">
                        <?= (int)($paginacao['totalItens'] ?? 0) ?> encontrado<?= (int)($paginacao['totalItens'] ?? 0) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="tickets-board-actions">
                    <input type="text" id="quick-search-tickets" class="quick-search-input" placeholder="Buscar por número, título ou solicitante..." autocomplete="off">
                </div>
            </div>

            <?php if (!empty($tickets)): ?>
                <?php
                $totalItens = (int)($paginacao['totalItens'] ?? 0);
                $limit = (int)($paginacao['limit'] ?? 15);
                $mostrando = count($tickets);
                $temMais = $totalItens > $limit;
                $proximoLimit = $limit + 10;
                $baseUrl = $bp . '/tickets';
                $baseParams = array_filter($filtros);
                ?>
                <div class="table-responsive">
                <table class="tickets-table-main">
                    <thead>
                    <tr>
                        <th>Nº Chamado</th>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Prioridade</th>
                        <th>Categoria</th>
                        <th>Solicitante</th>
                        <th>Filial</th>
                        <th>Data de Criação</th>
                        <th>Responsável</th>
                        <th>Ação</th>
                    </tr>
                    </thead>
                    <tbody id="tickets-main-body">
                    <?php foreach ($tickets as $t): ?>
                        <?php
                        $titleL = strtolower((string)($t['titulo'] ?? ''));
                        $numeroL = strtolower((string)($t['numero_chamado'] ?? $t['id'] ?? ''));
                        $solL = strtolower((string)($t['solicitante_nome'] ?? $t['solicitante'] ?? ''));
                        $searchText = trim($numeroL . ' ' . $titleL . ' ' . $solL);
                        $isNew = false;
                        $isAttention = false;
                        $solicitanteComentou = (int)($t['solicitante_comentou'] ?? 0) === 1;
                        if (!empty($t['created_at'])) {
                            $ts = strtotime((string)$t['created_at']);
                            if ($ts) {
                                $diff = time() - $ts;
                                $isNew = $diff <= (15 * 60);
                                // mais de 3 dias em aberto (não fechado)
                                $stLower = strtolower((string)($t['status'] ?? ''));
                                if ($diff > (3 * 24 * 60 * 60) && !in_array($stLower, ['fechado', 'resolvido'], true)) {
                                    $isAttention = true;
                                }
                            }
                        }
                        ?>
                        <tr class="ticket-row-main"
                            data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>"
                            data-ticket-url="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>"
                            data-is-new="<?= $isNew ? '1' : '0' ?>">
                            <td>
                                <div class="ticket-number-cell">
                                    <a class="ticket-link"
                                       href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="ticket-number">
                                            <?= htmlspecialchars($t['numero_chamado'] ?? $t['id'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <div class="ticket-title-cell">
                                    <span class="ticket-title" title="<?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($isNew): ?><span class="new-indicator">New</span><?php endif; ?>
                                    <?php if ($solicitanteComentou): ?><span class="solicitante-indicator" title="O solicitante respondeu ao chamado">Solicitante</span><?php endif; ?>
                                    <?php if ($isAttention): ?><span class="attention-indicator" title="Aberto há mais de 3 dias">Atenção</span><?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $st = strtolower((string)($t['status'] ?? ''));
                                $statusClass = 'status-aberto';
                                $statusText = 'Aberto';
                                if ($st === 'em_andamento' || $st === 'em andamento') { $statusClass = 'status-em-andamento'; $statusText = 'Em andamento'; }
                                elseif (in_array($st, ['fechado','resolvido'], true)) { $statusClass = 'status-fechado'; $statusText = 'Fechado'; }
                                ?>
                                <span class="status-badge-relatorio <?= $statusClass ?>"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td>
                                <?php
                                $pr = strtolower((string)($t['prioridade'] ?? ''));
                                $prioClass = 'priority-baixa';
                                if ($pr === 'alta') $prioClass = 'priority-alta';
                                elseif ($pr === 'média' || $pr === 'media') $prioClass = 'priority-média';
                                ?>
                                <span class="priority-badge-relatorio <?= $prioClass ?>"><?= htmlspecialchars($t['prioridade'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <?php $cat = $t['categoria'] ?? ''; ?>
                            <td title="<?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)$cat) : $cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)$cat) : $cat, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $sol = $t['solicitante_nome'] ?? $t['solicitante'] ?? ''; ?>
                            <td title="<?= htmlspecialchars($sol, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sol, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $fil = $t['filial_nome'] ?? ''; ?>
                            <td title="<?= htmlspecialchars($fil, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fil, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="ticket-time">
                                    <?php if (!empty($t['created_at'])): ?>
                                        <div class="ticket-date"><?= htmlspecialchars((new DateTime((string)$t['created_at']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="ticket-age"><?= htmlspecialchars((new DateTime((string)$t['created_at']))->format('H:i'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php $resp = $t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não definido'; ?>
                            <td title="<?= htmlspecialchars($resp, ENT_QUOTES, 'UTF-8') ?>">
                                <span class="ticket-responsavel-badge">
                                    <?= htmlspecialchars($resp, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <div class="ticket-actions-main">
                                    <?php if ($canAssign): ?>
                                    <button type="button"
                                            class="btn-atribuir-mini btn-open-assign-modal"
                                            title="Atribuir responsável"
                                            data-ticket-id="<?= (int)($t['id'] ?? 0) ?>"
                                            data-ticket-numero="<?= htmlspecialchars((string)($t['numero_chamado'] ?? $t['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-ticket-titulo="<?= htmlspecialchars((string)($t['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-ticket-responsavel="<?= htmlspecialchars((string)($t['responsavel'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa-solid fa-user-check"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($podeExcluirTicket): ?>
                                        <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'] . '/excluir', ENT_QUOTES, 'UTF-8') ?>" style="display:inline;" onsubmit="return confirm('Excluir ticket <?= htmlspecialchars((string)($t['numero_chamado'] ?? $t['id']), ENT_QUOTES, 'UTF-8') ?>?');">
                                            <input type="hidden" name="return_to" value="<?= htmlspecialchars((string)$returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn-delete-mini" title="Excluir"><i class="fa-solid fa-xmark"></i></button>
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
                if ($pagTotal > 1):
                    $baseParams = array_filter($filtros ?? []);
                ?>
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
                    <h3>Nenhum ticket encontrado</h3>
                    <p>Não há tickets cadastrados com os filtros atuais.</p>
                </div>
            <?php endif; ?>

            <div id="assign-modal" class="ticket-assign-modal" aria-hidden="true">
                <div class="ticket-assign-backdrop" data-close-assign-modal="1"></div>
                <div class="ticket-assign-dialog" role="dialog" aria-modal="true" aria-labelledby="assign-modal-title">
                    <div class="ticket-assign-header">
                        <h3 id="assign-modal-title"><i class="fa-solid fa-user-check"></i> Atribuir responsável</h3>
                        <button type="button" class="ticket-assign-close" data-close-assign-modal="1" aria-label="Fechar">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <form id="assign-modal-form" method="POST" action="">
                        <input type="hidden" id="assign-return-to" name="return_to" value="/tickets">
                        <div class="ticket-assign-body">
                            <p class="ticket-assign-ticket-info">
                                Ticket <strong id="assign-ticket-numero">-</strong><br>
                                <span id="assign-ticket-titulo">-</span>
                            </p>
                            <div class="ticket-assign-group">
                                <label for="assign-responsavel">Responsável</label>
                                <select id="assign-responsavel" name="responsavel">
                                    <option value="">Não atribuído</option>
                                    <?php foreach (($responsaveis ?? []) as $r): ?>
                                        <?php
                                        $userLogin = strtolower(trim((string)($r['usuario'] ?? '')));
                                        $userNome  = strtolower(trim((string)($r['nome'] ?? '')));
                                        if ($userLogin === 'dashboard' || $userLogin === 'dash' || str_starts_with($userNome, 'dash')) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?= htmlspecialchars((string)($r['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string)($r['nome'] ?? $r['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="ticket-assign-actions">
                            <button type="submit" class="btn-filter-relatorio btn-primary">Salvar</button>
                            <button type="button" class="btn-filter-relatorio btn-secondary" data-close-assign-modal="1">Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        <script>
            document.getElementById('quick-search-tickets')?.addEventListener('input', function () {
                var term = (this.value || '').toLowerCase().trim();
                document.querySelectorAll('#tickets-main-body .ticket-row-main').forEach(function (row) {
                    var txt = row.getAttribute('data-search') || '';
                    row.style.display = (!term || txt.includes(term)) ? '' : 'none';
                });
            });

            document.querySelectorAll('#tickets-main-body .ticket-row-main').forEach(function (row) {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('a, button, form, input, select, textarea, label')) {
                        return;
                    }
                    var to = row.getAttribute('data-ticket-url');
                    if (to) window.location.href = to;
                });
            });

            (function () {
                var modal = document.getElementById('assign-modal');
                var form = document.getElementById('assign-modal-form');
                var numeroEl = document.getElementById('assign-ticket-numero');
                var tituloEl = document.getElementById('assign-ticket-titulo');
                var selectEl = document.getElementById('assign-responsavel');
                var returnToEl = document.getElementById('assign-return-to');
                var basePath = '<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>' || '';

                function openAssignModal(btn) {
                    if (!modal || !form || !selectEl) return;
                    var id = btn.getAttribute('data-ticket-id') || '';
                    if (!id) return;

                    form.action = '<?= htmlspecialchars($bp, ENT_QUOTES, 'UTF-8') ?>/tickets/' + id + '/atribuir';
                    if (returnToEl) {
                        var current = window.location.pathname + window.location.search;
                        if (basePath && current.indexOf(basePath) === 0) {
                            current = current.substring(basePath.length) || '/';
                        }
                        returnToEl.value = current.startsWith('/tickets') ? current : '/tickets';
                    }
                    numeroEl.textContent = btn.getAttribute('data-ticket-numero') || '-';
                    tituloEl.textContent = btn.getAttribute('data-ticket-titulo') || '-';
                    selectEl.value = btn.getAttribute('data-ticket-responsavel') || '';

                    modal.classList.add('open');
                    modal.setAttribute('aria-hidden', 'false');
                }

                function closeAssignModal() {
                    if (!modal) return;
                    modal.classList.remove('open');
                    modal.setAttribute('aria-hidden', 'true');
                }

                document.querySelectorAll('.btn-open-assign-modal').forEach(function (btn) {
                    btn.addEventListener('click', function () { openAssignModal(btn); });
                });

                document.querySelectorAll('[data-close-assign-modal="1"]').forEach(function (el) {
                    el.addEventListener('click', closeAssignModal);
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') closeAssignModal();
                });
            })();
        </script>
    </body>
</html>

