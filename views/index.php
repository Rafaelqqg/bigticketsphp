<?php
// Variáveis: $perfil, $usuario, $stats, $recentTickets, $basePath
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Dashboard - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css?v=dashboard-exec-2" />
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            /* Força visual do dashboard executivo */
            .dashboard-topbar { background: linear-gradient(180deg,#0b2f27 0%,#0f4a3d 100%) !important; border-radius: 0 0 10px 10px !important; }
            .dashboard-topbar h1 { color:#fff !important; font-size: 1.22rem !important; font-weight:700 !important; }
            .dashboard-main-card { background:#fff !important; border:1px solid #e5e7eb !important; border-radius:8px !important; padding:.65rem !important; }
            .kpis-grid { display:grid !important; grid-template-columns:repeat(5,minmax(0,1fr)) !important; gap:.55rem !important; }
            .kpi-card { border:1px solid #e2e8f0 !important; border-radius:8px !important; padding:.5rem .62rem !important; background:#fff !important; position:relative !important; }
            .kpi-card::before { content:'' !important; position:absolute !important; left:0 !important; top:0 !important; bottom:0 !important; width:3px !important; border-radius:8px 0 0 8px !important; background:#334155 !important; }
            .kpi-card.primary::before { background:#111827 !important; }
            .kpi-card.danger::before { background:#ef4444 !important; }
            .kpi-card.warning::before { background:#f59e0b !important; }
            .kpi-card.info::before { background:#2563eb !important; }
            .kpi-card.success::before { background:#16a34a !important; }
            .kpi-value { font-size:1.7rem !important; font-weight:800 !important; color:#111827 !important; line-height:1 !important; }
            .kpi-label { font-size:.7rem !important; color:#6b7280 !important; margin-top:.35rem !important; letter-spacing:.04em !important; }
            .recent-tickets-section,.chart-section { border:1px solid #e5e7eb !important; border-radius:8px !important; background:#fff !important; padding:.6rem !important; margin-bottom:.6rem !important; }
            .chart-section { padding:.6rem .8rem !important; }
            .dashboard-section-title { font-size:.82rem !important; font-weight:700 !important; color:#111827 !important; }
            .recent-ticket-item { border:1px solid #e5e7eb !important; border-radius:8px !important; padding:.5rem .62rem !important; margin-top:.35rem !important; }
            .recent-ticket-title { display:flex !important; align-items:center !important; gap:.35rem !important; min-width:0 !important; }
            .recent-ticket-title .ticket-title-text { overflow:hidden !important; text-overflow:ellipsis !important; white-space:nowrap !important; min-width:0 !important; flex:1 !important; color:#111827 !important; font-size:1rem !important; font-weight:600 !important; }
            .recent-ticket-meta { font-size:.78rem !important; color:#6b7280 !important; display:flex !important; gap:.36rem !important; flex-wrap:wrap !important; margin-top:.3rem !important; }
            .chart-and-csat-row { display:flex !important; gap:1.5rem !important; flex-wrap:wrap !important; align-items:stretch !important; }
            .chart-block { flex:1 1 60% !important; min-width:320px !important; max-width:calc(100% - 240px) !important; }
            .csat-metric-block { flex:0 0 220px !important; min-width:200px !important; min-height:260px !important; display:flex !important; flex-direction:column !important; align-self:stretch !important; }
            .csat-metric-header { display:flex !important; flex-wrap:wrap !important; align-items:center !important; justify-content:space-between !important; gap:.4rem !important; margin-bottom:.5rem !important; }
            .csat-metric-header .dashboard-section-title { margin-bottom:0 !important; }
            .csat-periodo { font-size:.75rem !important; color:#64748b !important; font-weight:500 !important; white-space:nowrap !important; }
            .csat-metric-block .dashboard-section-title { margin-bottom:.5rem !important; }
            .csat-metric-cards { display:flex !important; flex-direction:column !important; gap:.5rem !important; margin-top:0 !important; flex:1 !important; min-height:0 !important; }
            .csat-metric-card { background:linear-gradient(145deg,#fff 0%,#f8fafc 100%) !important; border:1px solid #e2e8f0 !important; border-radius:10px !important; padding:.85rem 1rem !important; box-shadow:0 2px 8px rgba(0,0,0,.04) !important; flex:1 !important; display:flex !important; flex-direction:column !important; justify-content:center !important; min-height:0 !important; }
            .csat-metric-card:first-child { border-left:3px solid #f59e0b !important; }
            .csat-metric-card:last-child { border-left:3px solid #10b981 !important; }
            .csat-metric-value { font-size:2rem !important; font-weight:800 !important; color:#111827 !important; line-height:1 !important; }
            .csat-metric-value .csat-max { font-size:1rem !important; font-weight:600 !important; color:#94a3b8 !important; }
            .csat-metric-label { font-size:.7rem !important; color:#64748b !important; margin-top:.3rem !important; font-weight:600 !important; text-transform:uppercase !important; letter-spacing:.05em !important; }
            .csat-metric-sub { font-size:.65rem !important; color:#94a3b8 !important; margin-top:.2rem !important; }
            .chart-container-dashboard { height:260px !important; width:100% !important; margin:0 !important; }
            .chart-section .dashboard-section-title-row { align-items:flex-start !important; }
            .chart-dates-wrap { display:flex !important; flex-direction:column !important; align-items:flex-end !important; gap:.2rem !important; }
            .chart-date-hoje { font-size:.75rem !important; color:#64748b !important; font-weight:500 !important; }
            .badge-new-dashboard { display:inline-block !important; background:#22c55e !important; color:#fff !important; font-size:.65rem !important; font-weight:700 !important; padding:.2rem .4rem !important; border-radius:4px !important; margin-left:.35rem !important; vertical-align:middle !important; animation: badge-new-bounce .6s ease-in-out infinite !important; }
            .badge-fechado-por { display:inline-block; background:#6b7280; color:#fff; font-size:.6rem; padding:.15rem .35rem; border-radius:4px; margin-left:.35rem; vertical-align:middle; }
            .badge-solicitante-dashboard { display:inline-block; background:#d97706; color:#fff; font-size:.6rem; font-weight:700; padding:.15rem .35rem; border-radius:4px; margin-left:.35rem; vertical-align:middle; }
            .avaliacao-stars-dashboard { display:inline-flex; gap:.15rem; margin-left:.35rem; vertical-align:middle; font-size:.7rem; }
            .avaliacao-stars-dashboard .fa-solid.fa-star { color:#f59e0b; }
            .avaliacao-stars-dashboard .fa-regular.fa-star { color:#e2e8f0; }
            @keyframes badge-new-bounce { 0%, 100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-5px) scale(1.05); } }
            .btn-recarregar-dashboard { margin-left:.5rem; padding:.25rem .5rem; font-size:.7rem; background:#2563eb; color:#fff; border:none; border-radius:4px; cursor:pointer; }
            .btn-recarregar-dashboard:hover { background:#1d4ed8; }
            .kpi-card-link { display:block !important; text-decoration:none !important; color:inherit !important; cursor:pointer !important; transition:transform .15s, box-shadow .15s !important; }
            .kpi-card-link:hover { transform:translateY(-2px) !important; box-shadow:0 4px 12px rgba(0,0,0,.08) !important; }
            .recent-ticket-item-link { display:block !important; text-decoration:none !important; color:inherit !important; cursor:pointer !important; }
            .recent-ticket-item-link:hover { background:#f8fafc !important; }
            .dashboard-alert-usuarios-pendentes {
                display:flex !important;
                align-items:center !important;
                justify-content:space-between !important;
                gap:.6rem !important;
                border:1px solid #f59e0b !important;
                background:#fffbeb !important;
                color:#92400e !important;
                border-radius:8px !important;
                padding:.6rem .75rem !important;
                margin-bottom:.7rem !important;
                text-decoration:none !important;
                cursor:pointer !important;
            }
            .dashboard-alert-usuarios-pendentes:hover {
                background:#fef3c7 !important;
            }
            .dashboard-alert-usuarios-pendentes .dashboard-alert-usuarios-link {
                color:#92400e !important;
                font-weight:700 !important;
                text-decoration:none !important;
            }
            .dashboard-alert-usuarios-pendentes:hover .dashboard-alert-usuarios-link { text-decoration:underline !important; }
            .dashboard-alert-usuarios-pendentes.is-animated {
                animation: pulse-user-pending 1.2s ease-in-out infinite;
                box-shadow: 0 0 0 rgba(245, 158, 11, 0.4);
            }
            @keyframes pulse-user-pending {
                0% {
                    transform: translateY(0);
                    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.35);
                }
                50% {
                    transform: translateY(-1px);
                    box-shadow: 0 0 0 8px rgba(245, 158, 11, 0);
                }
                100% {
                    transform: translateY(0);
                    box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
                }
            }
        </style>
    </head>
    <body>
        <?php
        $paginaHome = true;
        $paginaRegistros = null;
        include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main>
            <div class="tickets-container dashboard-exec">
                <div class="dashboard-topbar">
                    <h1>Dashboard Executivo</h1>
                    <button type="button" class="dashboard-expand-btn" id="btn-dashboard-fullscreen" title="Tela cheia">
                        <i class="fa-solid fa-expand"></i>
                    </button>
                </div>

                <div class="dashboard-main-card">
                    <?php
                    $usuariosPendentes = (int)($stats['usuarios_pendentes_aprovacao'] ?? 0);
                    $isAdminDashboard = strtolower((string)($perfil ?? '')) === 'administrador';
                    ?>
                    <?php if ($isAdminDashboard): ?>
                        <a
                            href="<?= htmlspecialchars(($basePath ?? '') . '/usuarios', ENT_QUOTES, 'UTF-8') ?>"
                            id="dashboard-alert-usuarios-pendentes"
                            class="dashboard-alert-usuarios-pendentes <?= $usuariosPendentes > 0 ? 'is-animated' : '' ?>"
                            style="display: <?= $usuariosPendentes > 0 ? 'flex' : 'none' ?>;">
                            <span id="dashboard-alert-usuarios-pendentes-text">
                                <i class="fa-solid fa-user-clock"></i>
                                <?= $usuariosPendentes ?> novo(s) usuário(s) aguardando aprovação.
                            </span>
                            <span class="dashboard-alert-usuarios-link">
                                Ir para Usuários
                            </span>
                        </a>
                    <?php endif; ?>

                    <div class="kpis-section">
                        <div class="dashboard-section-title-row">
                            <h3 class="dashboard-section-title"><i class="fa-solid fa-ticket"></i> Tickets</h3>
                            <span class="kpi-periodo-badge">GERAL</span>
                        </div>
                        <?php $baseTickets = ($basePath ?? '') . '/tickets'; ?>
                        <div class="kpis-grid">
                            <a href="<?= htmlspecialchars($baseTickets, ENT_QUOTES, 'UTF-8') ?>" class="kpi-card primary kpi-card-link">
                                <div class="kpi-value" id="kpi-total-mes"><?= (int)($stats['total'] ?? 0) ?></div>
                                <div class="kpi-label">TOTAL</div>
                            </a>
                            <a href="<?= htmlspecialchars($baseTickets . '?status=Aberto', ENT_QUOTES, 'UTF-8') ?>" class="kpi-card danger kpi-card-link">
                                <div class="kpi-value" id="kpi-abertos-mes"><?= (int)($stats['abertos'] ?? 0) ?></div>
                                <div class="kpi-label">
                                    ABERTOS
                                    <?php $abertos3d = (int)($stats['abertos_3dias'] ?? 0); ?>
                                    <span id="kpi-abertos-3dias"
                                          class="attention-indicator"
                                          style="display: <?= $abertos3d > 0 ? 'inline-block' : 'none' ?>; margin-left:0.35rem; font-size:0.58rem;">
                                        <?= $abertos3d ?>+ 3 dias
                                    </span>
                                </div>
                            </a>
                            <a href="<?= htmlspecialchars($baseTickets . '?status=' . urlencode('Em andamento'), ENT_QUOTES, 'UTF-8') ?>" class="kpi-card warning kpi-card-link">
                                <div class="kpi-value" id="kpi-em-andamento-mes"><?= (int)($stats['em_andamento'] ?? 0) ?></div>
                                <div class="kpi-label">EM ANDAMENTO</div>
                            </a>
                            <a href="<?= htmlspecialchars($baseTickets . '?status=Fechado', ENT_QUOTES, 'UTF-8') ?>" class="kpi-card info kpi-card-link">
                                <div class="kpi-value" id="kpi-fechados-mes"><?= (int)($stats['fechados'] ?? 0) ?></div>
                                <div class="kpi-label">FECHADOS</div>
                            </a>
                            <a href="<?= htmlspecialchars($baseTickets . '?data_ini=' . date('Y-m-d') . '&data_fim=' . date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" class="kpi-card success kpi-card-link">
                                <div class="kpi-value" id="kpi-criados-hoje"><?= (int)($stats['novos_hoje'] ?? 0) ?></div>
                                <div class="kpi-label">CRIADOS HOJE</div>
                            </a>
                        </div>
                    </div>

                    <div class="dashboard-recent-row">
                        <div class="recent-tickets-section">
                        <div class="dashboard-section-title-row">
                            <h3 class="dashboard-section-title">Últimos Tickets Abertos</h3>
                            <span id="last-update-text" class="last-update-text">Atualizado agora</span>
                        </div>
                        <div id="recent-tickets-list">
                            <?php if (!empty($recentTickets)): ?>
                                <?php foreach ($recentTickets as $t):
                                    $st = strtolower((string)($t['status'] ?? ''));
                                    $isFechado = (strpos($st, 'fech') !== false || strpos($st, 'resol') !== false);
                                    $statusCls = $isFechado ? 'status-fechado' : (strpos($st, 'and') !== false ? 'status-em-andamento' : 'status-aberto');
                                    $prio = strtolower((string)($t['prioridade'] ?? ''));
                                    $prioCls = strpos($prio, 'alta') !== false ? 'priority-alta' : (strpos($prio, 'méd') !== false || strpos($prio, 'med') !== false ? 'priority-média' : 'priority-baixa');
                                    $respNome = $t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não atribuído';
                                    $createdTs = !empty($t['created_at']) ? strtotime($t['created_at']) : 0;
                                    $isNew = $createdTs && (time() - $createdTs) < 900;
                                    $solicitanteComentou = (int)($t['solicitante_comentou'] ?? 0) === 1;
                                ?>
                                    <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>" class="recent-ticket-item recent-ticket-item-link">
                                        <div class="recent-ticket-title">
                                            <span class="ticket-title-text" title="<?= htmlspecialchars(($t['numero_chamado'] ?? $t['id']) . ' - ' . ($t['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(($t['numero_chamado'] ?? $t['id']) . ' - ' . ($t['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <div class="recent-ticket-meta">
                                            <span class="status-badge-relatorio <?= $statusCls ?>"><?= htmlspecialchars(function_exists('status_display') ? status_display($t['status'] ?? '') : ($t['status'] ?? 'Aberto'), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="priority-badge-relatorio <?= $prioCls ?>"><?= htmlspecialchars($t['prioridade'] ?? 'Média', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span>Aberto por: <?= htmlspecialchars($t['solicitante_nome'] ?? $t['solicitante'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span>Filial: <?= htmlspecialchars($t['filial_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span>
                                            <span>Responsável: <span class="ticket-responsavel-badge"><?= htmlspecialchars($respNome, ENT_QUOTES, 'UTF-8') ?></span></span>
                                            <span><?= htmlspecialchars(function_exists('time_ago') ? time_ago($t['created_at'] ?? null) : 'agora', ENT_QUOTES, 'UTF-8') ?></span><?php if ($isNew): ?><span class="badge-new-dashboard">New</span><?php endif; ?><?php if ($solicitanteComentou): ?><span class="badge-solicitante-dashboard" title="O solicitante respondeu ao chamado">Solicitante</span><?php endif; ?><?php if ($isFechado): ?><span class="badge-fechado-por">Fechado por <?= htmlspecialchars($t['fechado_por_nome'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></span><?php if (!empty($t['avaliacao']) && $t['avaliacao'] >= 1 && $t['avaliacao'] <= 5): ?><span class="avaliacao-stars-dashboard" title="Avaliação: <?= (int)$t['avaliacao'] ?>/5"><?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= (int)$t['avaliacao'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></span><?php endif; ?><?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="recent-ticket-empty">Nenhum ticket no momento.</div>
                            <?php endif; ?>
                        </div>
                        </div>
                        <div class="dashboard-recent-right">
                            <div class="resumo-do-dia-section">
                                <div class="dashboard-section-title-row">
                                    <h3 class="dashboard-section-title"><i class="fa-solid fa-sun" style="color:#f59e0b;"></i> Resumo do dia</h3>
                                </div>
                                <?php
                                $baseRel = ($basePath ?? '') . '/relatorio';
                                ?>
                                <div class="resumo-do-dia-cards">
                                    <a href="<?= htmlspecialchars($baseRel . '?criados_hoje=1', ENT_QUOTES, 'UTF-8') ?>" class="resumo-do-dia-card resumo-do-dia-card-link">
                                        <div class="resumo-do-dia-value" id="rdo-criados-hoje"><?= (int)($stats['novos_hoje'] ?? 0) ?></div>
                                        <div class="resumo-do-dia-label">Criados hoje</div>
                                    </a>
                                    <a href="<?= htmlspecialchars($baseRel . '?fechados_hoje=1', ENT_QUOTES, 'UTF-8') ?>" class="resumo-do-dia-card resumo-do-dia-card-link">
                                        <div class="resumo-do-dia-value" id="rdo-fechados-hoje"><?= (int)($stats['fechados_hoje'] ?? 0) ?></div>
                                        <div class="resumo-do-dia-label">Fechados hoje</div>
                                    </a>
                                    <a href="<?= htmlspecialchars($baseRel . '?urgentes_abertos=1', ENT_QUOTES, 'UTF-8') ?>" class="resumo-do-dia-card resumo-do-dia-card-urgente resumo-do-dia-card-link">
                                        <div class="resumo-do-dia-value" id="rdo-urgentes-abertos"><?= (int)($stats['urgentes_abertos'] ?? 0) ?></div>
                                        <div class="resumo-do-dia-label">Urgentes abertos</div>
                                    </a>
                                    <a href="<?= htmlspecialchars($baseRel . '?pendentes_avaliacao=1', ENT_QUOTES, 'UTF-8') ?>" class="resumo-do-dia-card resumo-do-dia-card-pendentes resumo-do-dia-card-link">
                                        <div class="resumo-do-dia-value" id="rdo-pendentes-avaliacao"><?= (int)($stats['pendentes_avaliacao'] ?? 0) ?></div>
                                        <div class="resumo-do-dia-label">Pendentes de avaliação</div>
                                    </a>
                                </div>
                                <div class="resumo-do-dia-footer">
                                    <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets', ENT_QUOTES, 'UTF-8') ?>" class="resumo-do-dia-link">
                                        <i class="fa-solid fa-arrow-right"></i> Ver todos os tickets
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chart-section chart-and-csat-row">
                        <div class="chart-block">
                            <div class="dashboard-section-title-row">
                                <h3 class="dashboard-section-title"><i class="fa-solid fa-chart-column"></i> Chamados da Semana</h3>
                                <div class="chart-dates-wrap">
                                    <span id="chart-date-reference" class="chart-date-reference"></span>
                                    <span class="chart-date-hoje"><i class="fa-solid fa-calendar-day"></i> Hoje: <?= date('d/m/Y') ?></span>
                                </div>
                            </div>
                            <div class="chart-container-dashboard">
                                <canvas id="ticketsSemanaChart"></canvas>
                            </div>
                        </div>
                        <div class="csat-metric-block" id="csat-metric-block">
                            <div class="csat-metric-header">
                                <h3 class="dashboard-section-title"><i class="fa-solid fa-star" style="color:#f59e0b;"></i> Avaliação (CSAT)</h3>
                                <span class="csat-periodo" id="csat-periodo"><?= htmlspecialchars($stats['periodo_semanal'] ?? (date('d/m', strtotime('-6 days')) . ' a ' . date('d/m')), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php $baseRelatorio = ($basePath ?? '') . '/relatorio'; ?>
                            <div class="csat-metric-cards">
                                <a href="<?= htmlspecialchars($baseRelatorio . '?csat_periodo=semanal', ENT_QUOTES, 'UTF-8') ?>" class="kpi-card-link">
                                    <div class="csat-metric-card">
                                        <div class="csat-metric-value" id="csat-semanal-val"><?= $stats['csat_semanal'] !== null ? number_format($stats['csat_semanal'], 1, ',', '') . '<span class="csat-max">/5</span>' : '<span class="csat-max">—</span>' ?></div>
                                        <div class="csat-metric-label">Média Semanal</div>
                                        <div class="csat-metric-sub" id="csat-semanal-qtd"><?= (int)($stats['csat_semanal_qtd'] ?? 0) ?> avaliações</div>
                                    </div>
                                </a>
                                <a href="<?= htmlspecialchars($baseRelatorio . '?csat_periodo=mensal', ENT_QUOTES, 'UTF-8') ?>" class="kpi-card-link">
                                    <div class="csat-metric-card">
                                        <div class="csat-metric-value" id="csat-mensal-val"><?= $stats['csat_mensal'] !== null ? number_format($stats['csat_mensal'], 1, ',', '') . '<span class="csat-max">/5</span>' : '<span class="csat-max">—</span>' ?></div>
                                        <div class="csat-metric-label">Média Mensal</div>
                                        <div class="csat-metric-sub" id="csat-mensal-qtd"><?= (int)($stats['csat_mensal_qtd'] ?? 0) ?> avaliações</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <script>
            (function () {
                const basePath = <?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?>;
                const path = (p) => (basePath || '') + p;
                const defaultTitle = 'Dashboard - Big Trading';
                const newTicketTitle = '(Novo ticket) Dashboard - Big Trading';
                const isDashboardUser = <?php
                    $uLogin = is_array($usuario ?? null) ? strtolower((string)($usuario['usuario'] ?? '')) : strtolower((string)($usuario ?? ''));
                    echo ($uLogin === 'dashboard' || $uLogin === 'dash') ? 'true' : 'false';
                ?>;
                // Áudio para novo ticket (arquivo convertido para MP3) - apenas para usuário Dashboard/Dash
                const newTicketAudio = isDashboardUser ? new Audio(path('/public/newticket.mp3')) : null;
                if (newTicketAudio) {
                    newTicketAudio.preload = 'auto';
                    newTicketAudio.volume = 1.0;
                }
                let newTicketAudioTimeout = null;
                let newTicketAudioEnabled = false;

                // Muitos navegadores só permitem áudio após interação do usuário.
                // No primeiro clique/tecla bem-sucedido, "desbloqueamos" o áudio.
                function habilitarAudioNovoTicketUmaVez() {
                    if (!isDashboardUser) return;
                    if (newTicketAudioEnabled) return;
                    if (!newTicketAudio) return;
                    // Apenas marca como habilitado após a primeira interação,
                    // sem tocar nenhum som imediato no clique do usuário.
                    newTicketAudioEnabled = true;
                    window.removeEventListener('click', habilitarAudioNovoTicketUmaVez);
                    window.removeEventListener('keydown', habilitarAudioNovoTicketUmaVez);
                }
                if (isDashboardUser && newTicketAudio) {
                    window.addEventListener('click', habilitarAudioNovoTicketUmaVez);
                    window.addEventListener('keydown', habilitarAudioNovoTicketUmaVez);
                }
                let lastCheckedTicketId = null;
                let ticketsSemanaChart = null;

                function escapeHtml(v) {
                    return String(v ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                let lastSeenTicketIds = new Set();
                const ticketBeepInfo = {}; // controla toques por ticket (id -> timers)
                const ticketBeepedIds = new Set(); // tickets que já tiveram o ciclo de 3 toques agendado

                function scheduleTicketBeeps(ticketId) {
                    if (!isDashboardUser) return;
                    ticketId = Number(ticketId || 0);
                    if (!ticketId) return;
                    if (ticketBeepedIds.has(ticketId)) return; // já agendado antes
                    if (ticketBeepInfo[ticketId]) return;
                    ticketBeepedIds.add(ticketId);
                    const timers = [];
                    // 1) Quando o ticket entra como "New" (agora)
                    // 2) 30 segundos depois
                    // 3) Perto do fim do "New" (quase 60s) -> 55s
                    [0, 30000, 55000].forEach(function (delay) {
                        const t = setTimeout(function () {
                            tocarSomNovoTicket();
                        }, delay);
                        timers.push(t);
                    });
                    ticketBeepInfo[ticketId] = { timers: timers };
                }

                function renderRecentes(tickets) {
                    const list = document.getElementById('recent-tickets-list');
                    if (!list) return;
                    const previousIds = new Set(lastSeenTicketIds);
                    if (!tickets.length) {
                        list.innerHTML = '<div class="recent-ticket-empty">Nenhum ticket no momento. <button type="button" class="btn-recarregar-dashboard" id="btn-recarregar-tickets">Recarregar</button></div>';
                        lastSeenTicketIds = new Set();
                        const btn = document.getElementById('btn-recarregar-tickets');
                        if (btn) btn.onclick = () => carregarRecentes();
                        return;
                    }
                    const nowIds = new Set(tickets.map((t) => Number(t.id || 0)));
                    const isFirstLoad = previousIds.size === 0;
                    if (isFirstLoad) lastSeenTicketIds = new Set(nowIds);
                    function isCreatedRecently(t) {
                        // Preferir timestamp do servidor (evita problema de fuso) ou parsed created_at
                        const tsMs = t.created_at_ts != null
                            ? (Number(t.created_at_ts) * 1000)
                            : (t.created_at ? new Date(t.created_at).getTime() : 0);
                        if (!tsMs) return false;
                        // Considera "New" por 1 minuto (60 segundos)
                        return (Date.now() - tsMs) < 60 * 1000;
                    }
                    list.innerHTML = tickets.map((t) => {
                        const id = Number(t.id || 0);
                        const isNew = (!isFirstLoad && !previousIds.has(id)) || isCreatedRecently(t);
                        const numero = escapeHtml(t.numero_chamado || id);
                        const titulo = escapeHtml(t.titulo || 'Sem título');
                        const solicitante = escapeHtml(t.solicitante_nome || 'N/A');
                        const filial = escapeHtml(t.filial_nome || 'N/A');
                        const responsavel = escapeHtml(t.responsavel || 'Não atribuído');
                        const timeAgo = escapeHtml(t.timeAgo || 'agora');
                        const isFechado = String(t.status || '').toLowerCase().includes('fech') || String(t.status || '').toLowerCase().includes('resol');
                        const statusClass = isFechado ? 'status-fechado' : (String(t.status || '').toLowerCase().includes('and') ? 'status-em-andamento' : 'status-aberto');
                        const prioridadeClass = String(t.prioridade || '').toLowerCase().includes('alta') ? 'priority-alta' : (String(t.prioridade || '').toLowerCase().includes('méd') || String(t.prioridade || '').toLowerCase().includes('med') ? 'priority-média' : 'priority-baixa');
                        const fechadoPorNome = t.fechado_por_nome || 'N/A';
                        const avaliacao = (t.avaliacao != null && t.avaliacao >= 1 && t.avaliacao <= 5) ? parseInt(t.avaliacao, 10) : null;
                        let fechadoBadge = '';
                        if (isFechado) {
                            fechadoBadge = '<span class="badge-fechado-por">Fechado por ' + escapeHtml(fechadoPorNome) + '</span>';
                            if (avaliacao) {
                                let stars = '';
                                for (let i = 1; i <= 5; i++) {
                                    stars += '<i class="fa-' + (i <= avaliacao ? 'solid' : 'regular') + ' fa-star"></i>';
                                }
                                fechadoBadge += '<span class="avaliacao-stars-dashboard" title="Avaliação: ' + avaliacao + '/5">' + stars + '</span>';
                            }
                        }
                        const newBadge = isNew ? '<span class="badge-new-dashboard">New</span>' : '';
                        const solicitanteBadge = t.solicitante_comentou ? '<span class="badge-solicitante-dashboard" title="O solicitante respondeu ao chamado">Solicitante</span>' : '';
                        return `
                            <a href="${escapeHtml(path('/tickets/' + id))}" class="recent-ticket-item recent-ticket-item-link" data-ticket-id="${id}">
                                <div class="recent-ticket-title">
                                    <span class="ticket-title-text" title="${escapeHtml(String(t.numero_chamado || id) + ' - ' + String(t.titulo || 'Sem título'))}">${numero} - ${titulo}</span>
                                </div>
                                <div class="recent-ticket-meta">
                                    <span class="status-badge-relatorio ${statusClass}">${escapeHtml(t.statusDisplay || t.status || 'Aberto')}</span>
                                    <span class="priority-badge-relatorio ${prioridadeClass}">${escapeHtml(String(t.prioridade || 'Média'))}</span>
                                    <span>Aberto por: ${solicitante}</span>
                                    <span>Filial: ${filial}</span>
                                    <span>Responsável: <span class="ticket-responsavel-badge">${responsavel}</span></span>
                                    <span>${timeAgo}</span>${newBadge}${solicitanteBadge}${fechadoBadge}
                                </div>
                            </a>
                        `;
                    }).join('');
                    lastSeenTicketIds = nowIds;

                    // Para cada ticket, decidimos quando agendar o ciclo de 3 toques:
                    // - Na primeira carga da página: todos os tickets "recentes" (último 1 minuto) ganham ciclo.
                    // - Nas cargas seguintes: apenas os que não existiam antes (novos na lista).
                    tickets.forEach(function (t) {
                        const id = Number(t.id || 0);
                        if (!id) return;
                        const existsBefore = previousIds.has(id);
                        const isRecent = isCreatedRecently(t);
                        if ((isFirstLoad && isRecent) || (!isFirstLoad && !existsBefore)) {
                            scheduleTicketBeeps(id);
                        }
                    });
                    const txt = document.getElementById('last-update-text');
                    if (txt) {
                        const d = new Date();
                        txt.textContent = 'Atualizado ' + String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0') + ':' + String(d.getSeconds()).padStart(2,'0');
                    }
                }

                async function carregarRecentes() {
                    try {
                        const resp = await fetch(path('/tickets/api/recentes'));
                        const data = await resp.json();
                        if (Array.isArray(data.tickets)) {
                            renderRecentes(data.tickets);
                        }
                    } catch (e) {
                        // silencioso para não quebrar a página
                    }
                }

                async function carregarGraficoSemana() {
                    try {
                        const resp = await fetch(path('/tickets/api/grafico-semana'));
                        const data = await resp.json();
                        const canvas = document.getElementById('ticketsSemanaChart');
                        if (!canvas || typeof Chart === 'undefined') return;
                        const ctx = canvas.getContext('2d');
                        const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                        const labels = [];
                        for (let i = -6; i <= 0; i++) {
                            const d = new Date();
                            d.setDate(d.getDate() + i);
                            const dia = diasSemana[d.getDay()];
                            const dd = String(d.getDate()).padStart(2, '0');
                            const mm = String(d.getMonth() + 1).padStart(2, '0');
                            labels.push(dia + ' ' + dd + '/' + mm);
                        }
                        const aberto = Array.isArray(data.dadosAbertos) ? data.dadosAbertos : [0,0,0,0,0,0,0];
                        const andamento = Array.isArray(data.dadosEmAndamento) ? data.dadosEmAndamento : [0,0,0,0,0,0,0];
                        const resolvido = Array.isArray(data.dadosResolvidos) ? data.dadosResolvidos : [0,0,0,0,0,0,0];

                        if (ticketsSemanaChart && ticketsSemanaChart.destroy) {
                            ticketsSemanaChart.destroy();
                        }
                        ticketsSemanaChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [
                                    { label: 'Tickets Abertos', data: aberto, backgroundColor: '#ef4444', stack: 'stack0', minBarLength: 6 },
                                    { label: 'Em Andamento', data: andamento, backgroundColor: '#f59e0b', stack: 'stack0', minBarLength: 6 },
                                    { label: 'Tickets Fechados', data: resolvido, backgroundColor: '#22c55e', stack: 'stack0', minBarLength: 6 }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                layout: { padding: { top: 8, bottom: 8, left: 4, right: 4 } },
                                datasets: {
                                    bar: { barPercentage: 0.75, categoryPercentage: 0.85 }
                                },
                                scales: {
                                    x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 0, font: { size: 12 } } },
                                    y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, font: { size: 12 } }, grid: { color: 'rgba(0,0,0,0.08)', drawBorder: false } }
                                },
                                plugins: { legend: { position: 'top', labels: { boxWidth: 14, usePointStyle: true, font: { size: 13 } } } }
                            }
                        });
                        const ref = document.getElementById('chart-date-reference');
                        if (ref) {
                            const now = new Date();
                            const start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
                            const fmt = (d) => String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
                            ref.textContent = fmt(start) + ' a ' + fmt(now);
                        }
                    } catch (e) {
                        // silencioso
                    }
                }

                function tocarSomNovoTicket() {
                    try {
                        if (!isDashboardUser) return;
                        if (!newTicketAudio) return;
                        // Para garantir que sempre comece do início
                        newTicketAudio.pause();
                        newTicketAudio.currentTime = 0;
                        newTicketAudio.play().then(function () {
                            // Se conseguir tocar via timer, marcamos como habilitado também
                            newTicketAudioEnabled = true;
                        }).catch(function () {
                            // Alguns navegadores podem bloquear autoplay; ignoramos o erro
                        });
                        if (newTicketAudioTimeout) {
                            clearTimeout(newTicketAudioTimeout);
                        }
                        newTicketAudioTimeout = setTimeout(function () {
                            try {
                                newTicketAudio.pause();
                                newTicketAudio.currentTime = 0;
                            } catch (e) {}
                        }, 10000); // 10 segundos
                    } catch (e) {
                        // silencioso
                    }
                }

                async function checkNewTickets() {
                    try {
                        const query = lastCheckedTicketId ? ('?lastId=' + encodeURIComponent(lastCheckedTicketId)) : '';
                        const resp = await fetch(path('/tickets/api/check-new' + query));
                        const data = await resp.json();
                        if (data && data.latestId) {
                            if (data.hasNew) {
                                document.title = newTicketTitle;
                                // Agenda o ciclo de 3 toques (agora + 2x em 10s) para o ticket mais recente
                                scheduleTicketBeeps(data.latestId);
                            }
                            lastCheckedTicketId = data.latestId;
                        }
                    } catch (e) {
                        // silencioso
                    }
                }

                carregarRecentes();
                carregarGraficoSemana();
                checkNewTickets();

                // Botão tela cheia
                (function () {
                    const btn = document.getElementById('btn-dashboard-fullscreen');
                    const container = document.querySelector('.dashboard-exec');
                    if (!btn || !container) return;
                    function updateIcon() {
                        const isFull = !!document.fullscreenElement || !!document.webkitFullscreenElement || !!document.mozFullScreenElement;
                        btn.querySelector('i').className = isFull ? 'fa-solid fa-compress' : 'fa-solid fa-expand';
                        btn.title = isFull ? 'Sair da tela cheia' : 'Tela cheia';
                    }
                    btn.addEventListener('click', function () {
                        const isFull = !!document.fullscreenElement || !!(document.webkitFullscreenElement || document.mozFullScreenElement);
                        if (!isFull) {
                            const req = container.requestFullscreen || container.webkitRequestFullscreen || container.mozRequestFullScreen || container.msRequestFullscreen;
                            if (req) req.call(container).then(updateIcon).catch(function () {});
                        } else {
                            const exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
                            if (exit) exit.call(document).then(updateIcon).catch(function () {});
                        }
                    });
                    document.addEventListener('fullscreenchange', updateIcon);
                    document.addEventListener('webkitfullscreenchange', updateIcon);
                    document.addEventListener('mozfullscreenchange', updateIcon);
                })();

                // Reseta o título ao focar/voltar para a aba.
                window.addEventListener('focus', function () {
                    document.title = defaultTitle;
                });
                document.addEventListener('visibilitychange', function () {
                    if (!document.hidden) {
                        document.title = defaultTitle;
                    }
                });

                async function carregarCsat() {
                    try {
                        const resp = await fetch(path('/api/dashboard/csat'));
                        if (!resp.ok) return;
                        const data = await resp.json();
                        const fmt = (v) => v != null ? String(v).replace('.', ',') : '—';
                        const elS = document.getElementById('csat-semanal-val');
                        const elM = document.getElementById('csat-mensal-val');
                        const elSq = document.getElementById('csat-semanal-qtd');
                        const elMq = document.getElementById('csat-mensal-qtd');
                        if (elS) elS.innerHTML = data.csat_semanal != null ? fmt(data.csat_semanal) + '<span class="csat-max">/5</span>' : '<span class="csat-max">—</span>';
                        if (elM) elM.innerHTML = data.csat_mensal != null ? fmt(data.csat_mensal) + '<span class="csat-max">/5</span>' : '<span class="csat-max">—</span>';
                        if (elSq) elSq.textContent = (data.csat_semanal_qtd || 0) + ' avaliações';
                        if (elMq) elMq.textContent = (data.csat_mensal_qtd || 0) + ' avaliações';
                        const elPeriodo = document.getElementById('csat-periodo');
                        if (elPeriodo && data.periodo_semanal) elPeriodo.textContent = data.periodo_semanal;
                    } catch (e) {}
                }

                async function carregarStatsDashboard() {
                    try {
                        const resp = await fetch(path('/api/dashboard/stats'));
                        if (!resp.ok) return;
                        const data = await resp.json();

                        const setInt = (id, v, fallback = 0) => {
                            const el = document.getElementById(id);
                            if (!el) return;
                            const n = v != null && v !== '' ? parseInt(String(v), 10) : fallback;
                            el.textContent = String(Number.isFinite(n) ? n : fallback);
                        };

                        setInt('kpi-total-mes', data.total);
                        setInt('kpi-abertos-mes', data.abertos);
                        setInt('kpi-em-andamento-mes', data.em_andamento);
                        setInt('kpi-fechados-mes', data.fechados);
                        setInt('kpi-criados-hoje', data.novos_hoje);

                        // Atenção: abertos há mais de 3 dias
                        const elA3d = document.getElementById('kpi-abertos-3dias');
                        if (elA3d) {
                            const n3d = data.abertos_3dias != null ? parseInt(String(data.abertos_3dias), 10) : 0;
                            if (Number.isFinite(n3d) && n3d > 0) {
                                elA3d.style.display = 'inline-block';
                                elA3d.textContent = n3d + '+ 3 dias';
                            } else {
                                elA3d.style.display = 'none';
                            }
                        }

                        setInt('rdo-criados-hoje', data.novos_hoje);
                        setInt('rdo-fechados-hoje', data.fechados_hoje);
                        setInt('rdo-urgentes-abertos', data.urgentes_abertos);
                        setInt('rdo-pendentes-avaliacao', data.pendentes_avaliacao);

                        const alertaUsuarios = document.getElementById('dashboard-alert-usuarios-pendentes');
                        const alertaUsuariosTxt = document.getElementById('dashboard-alert-usuarios-pendentes-text');
                        if (alertaUsuarios && alertaUsuariosTxt) {
                            const pend = data.usuarios_pendentes_aprovacao != null
                                ? parseInt(String(data.usuarios_pendentes_aprovacao), 10)
                                : 0;
                            const totalPend = Number.isFinite(pend) ? pend : 0;
                            alertaUsuarios.style.display = totalPend > 0 ? 'flex' : 'none';
                            alertaUsuarios.classList.toggle('is-animated', totalPend > 0);
                            alertaUsuariosTxt.innerHTML = '<i class="fa-solid fa-user-clock"></i> ' + totalPend + ' novo(s) usuário(s) aguardando aprovação.';
                        }
                    } catch (e) {}
                }
                // Atualizações automáticas
                // Últimos tickets e verificação de novos: a cada 10 segundos
                setInterval(carregarRecentes, 10000);
                setInterval(checkNewTickets, 10000);
                // Gráfico e CSAT permanecem a cada 60 segundos
                setInterval(carregarGraficoSemana, 60000);
                setInterval(carregarCsat, 60000);
                // KPIs e Resumo do dia atualizam a cada 30 segundos
                carregarStatsDashboard();
                setInterval(carregarStatsDashboard, 30000);
            })();
        </script>
    </body>
</html>

