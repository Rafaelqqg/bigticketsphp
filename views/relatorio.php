<?php
// Variáveis: $tickets, $responsaveis, $filiais, $filtros, $perfil, $usuario, $basePath, $paginacao
$paginaRegistros = true;
$paginacao = $paginacao ?? ['tickets' => ['paginaAtual' => 1, 'totalPaginas' => 1, 'totalItens' => count($tickets ?? [])]];
$isCsatPeriodoAtivo = in_array((string)($filtros['csat_periodo'] ?? ''), ['semanal', 'mensal'], true);
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatórios - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    </head>
    <body>
        <?php
            $paginaHome = null;
            $paginaRegistros = true;
            include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="tickets-page-main relatorio-page">
            <div class="page-header-relatorio">
                <h2>Relatórios de Tickets</h2>
            </div>

            <div class="filters-section-relatorio">
                <h3>Filtros</h3>
                <form method="GET" action="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/relatorio">
                    <?php if (!empty($filtros['pendentes_avaliacao'])): ?><input type="hidden" name="pendentes_avaliacao" value="1"><?php endif; ?>
                    <?php if (!empty($filtros['fechados_hoje'])): ?><input type="hidden" name="fechados_hoje" value="1"><?php endif; ?>
                    <?php if (!empty($filtros['criados_hoje'])): ?><input type="hidden" name="criados_hoje" value="1"><?php endif; ?>
                    <?php if (!empty($filtros['urgentes_abertos'])): ?><input type="hidden" name="urgentes_abertos" value="1"><?php endif; ?>
                    <?php if (!empty($filtros['csat_periodo'])): ?><input type="hidden" name="csat_periodo" value="<?= htmlspecialchars((string)$filtros['csat_periodo'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                    <?php if (!empty($filtros['csat_periodo'])): ?>
                        <div style="margin:.25rem 0 .75rem 0;color:#475569;font-size:.86rem;">
                            Filtro ativo de CSAT:
                            <strong><?= (($filtros['csat_periodo'] ?? '') === 'semanal') ? 'Semanal' : 'Mensal' ?></strong>
                            (baseado na data da avaliação)
                        </div>
                    <?php endif; ?>
                    <div class="filters-grid-relatorio">
                        <div class="filter-group-relatorio">
                            <label for="numero_chamado">Nº Chamado</label>
                            <input id="numero_chamado" name="numero_chamado"
                                   value="<?= htmlspecialchars($filtros['numero_chamado'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Aberto" <?= ($filtros['status'] ?? '') === 'Aberto' ? 'selected' : '' ?>>Aberto</option>
                                <option value="Em andamento" <?= ($filtros['status'] ?? '') === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                                <option value="Fechado" <?= in_array($filtros['status'] ?? '', ['Fechado','Resolvido','resolvidos'], true) ? 'selected' : '' ?>>Fechado</option>
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
                                <option value="Baixa"  <?= ($filtros['prioridade'] ?? '') === 'Baixa'  ? 'selected' : '' ?>>Baixa</option>
                                <option value="Média"  <?= ($filtros['prioridade'] ?? '') === 'Média'  ? 'selected' : '' ?>>Média</option>
                                <option value="Alta"   <?= ($filtros['prioridade'] ?? '') === 'Alta'   ? 'selected' : '' ?>>Alta</option>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="responsavel">Responsável</label>
                            <select id="responsavel" name="responsavel">
                                <option value="">Todos</option>
                                <?php foreach ($responsaveis as $r): ?>
                                    <?php
                                    $userLogin = strtolower(trim((string)($r['usuario'] ?? '')));
                                    $userNome  = strtolower(trim((string)($r['nome'] ?? '')));
                                    if ($userLogin === 'dashboard' || $userLogin === 'dash' || str_starts_with($userNome, 'dash')) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?= htmlspecialchars($r['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= ($filtros['responsavel'] ?? '') === ($r['usuario'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($r['nome'] ?? $r['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="filial">Filial</label>
                            <select id="filial" name="filial">
                                <option value="">Todas</option>
                                <?php foreach ($filiais as $f): ?>
                                    <option value="<?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= (string)($filtros['filial'] ?? '') === (string)($f['codigo'] ?? '') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="data_ini"><?= $isCsatPeriodoAtivo ? 'Avaliado de' : 'Data inicial' ?></label>
                            <input id="data_ini" name="data_ini" type="date"
                                   value="<?= htmlspecialchars($filtros['data_ini'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="data_fim"><?= $isCsatPeriodoAtivo ? 'Avaliado até' : 'Data final' ?></label>
                            <input id="data_fim" name="data_fim" type="date"
                                   value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="filter-group-relatorio">
                            <label for="avaliacao">Avaliação</label>
                            <select id="avaliacao" name="avaliacao">
                                <option value="">Todas</option>
                                <option value="pendente" <?= (($filtros['avaliacao'] ?? '') === 'pendente' || !empty($filtros['pendentes_avaliacao'])) ? 'selected' : '' ?>>Pendentes (fechados hoje)</option>
                                <option value="1" <?= ($filtros['avaliacao'] ?? '') === '1' ? 'selected' : '' ?>>1 estrela</option>
                                <option value="2" <?= ($filtros['avaliacao'] ?? '') === '2' ? 'selected' : '' ?>>2 estrelas</option>
                                <option value="3" <?= ($filtros['avaliacao'] ?? '') === '3' ? 'selected' : '' ?>>3 estrelas</option>
                                <option value="4" <?= ($filtros['avaliacao'] ?? '') === '4' ? 'selected' : '' ?>>4 estrelas</option>
                                <option value="5" <?= ($filtros['avaliacao'] ?? '') === '5' ? 'selected' : '' ?>>5 estrelas</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions-relatorio">
                        <button type="submit" class="btn-filter-relatorio btn-primary">Filtrar</button>
                        <a href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/relatorio"
                           class="btn-filter-relatorio btn-secondary">Limpar</a>
                        <?php $queryExport = http_build_query(array_filter($filtros ?? [])); ?>
                        <a href="<?= htmlspecialchars(($basePath ?? '') . '/relatorio/exportar/excel' . ($queryExport ? '?' . $queryExport : ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="btn-filter-relatorio btn-secondary" style="margin-left:0.5rem;">Exportar Excel</a>
                        <a href="<?= htmlspecialchars(($basePath ?? '') . '/relatorio/exportar/pdf' . ($queryExport ? '?' . $queryExport : ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="btn-filter-relatorio btn-secondary" style="margin-left:0.25rem;">Exportar PDF</a>
                    </div>
                </form>
            </div>

            <div class="relatorio-results-header">
                <h3 class="section-title-relatorio">Resultados</h3>
                <?php $totalFiltrado = (int)($paginacao['tickets']['totalItens'] ?? 0); ?>
                <span class="relatorio-total-filtrado"><?= $totalFiltrado ?> ticket<?= $totalFiltrado !== 1 ? 's' : '' ?> encontrado<?= $totalFiltrado !== 1 ? 's' : '' ?></span>
            </div>

            <?php if (!empty($tickets)): ?>
                <div class="table-responsive">
                <table class="tickets-table-relatorio">
                    <thead>
                    <tr>
                        <th>Nº Chamado</th>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Prioridade</th>
                        <th>Categoria</th>
                        <th>Solicitante</th>
                        <th>Responsável</th>
                        <th>Filial</th>
                        <th>Fechado por</th>
                        <th>Avaliação</th>
                        <?php if ($isCsatPeriodoAtivo): ?><th>Avaliado em</th><?php endif; ?>
                        <th>Aberto em</th>
                        <th>Fechado em</th>
                        <th>Tempo resolução</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tickets as $t):
                        $ticketUrl = ($basePath ?? '') . '/tickets/' . (int)($t['id'] ?? 0);
                        ?>
                        <tr class="ticket-row-clickable" data-href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <td><a href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>" class="ticket-link-relatorio"><?= htmlspecialchars($t['numero_chamado'] ?? $t['id'], ENT_QUOTES, 'UTF-8') ?></a></td>
                            <td class="td-titulo"><a href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>" class="ticket-link-relatorio" title="<?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><span class="ticket-title-truncate"><?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></a></td>
                            <td>
                                <?php
                                $statusRaw = strtolower((string)($t['status'] ?? ''));
                                $class = 'status-aberto';
                                $text  = 'Aberto';
                                if ($statusRaw === 'em_andamento' || $statusRaw === 'em andamento') {
                                    $class = 'status-em-andamento';
                                    $text  = 'Em andamento';
                                } elseif (in_array($statusRaw, ['fechado','resolvido'], true)) {
                                    $class = 'status-fechado';
                                    $text  = 'Fechado';
                                }
                                ?>
                                <span class="status-badge-relatorio <?= $class ?>">
                                    <?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $prioRaw = strtolower((string)($t['prioridade'] ?? ''));
                                $prioClass = 'priority-baixa';
                                if ($prioRaw === 'alta') {
                                    $prioClass = 'priority-alta';
                                } elseif ($prioRaw === 'média' || $prioRaw === 'media') {
                                    $prioClass = 'priority-média';
                                }
                                ?>
                                <span class="priority-badge-relatorio <?= $prioClass ?>">
                                    <?= htmlspecialchars($t['prioridade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <?php $cat = $t['categoria'] ?? ''; ?>
                            <td title="<?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)$cat) : $cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)$cat) : $cat, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $sol = $t['solicitante_nome'] ?? $t['solicitante'] ?? ''; ?>
                            <td title="<?= htmlspecialchars($sol, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($sol, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $resp = $t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não atribuído'; ?>
                            <td title="<?= htmlspecialchars($resp, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($resp, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $fil = $t['filial_nome'] ?? ''; ?>
                            <td title="<?= htmlspecialchars($fil, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fil, ENT_QUOTES, 'UTF-8') ?></td>
                            <?php $fech = $t['fechado_por_nome'] ?? '-'; ?>
                            <td title="<?= htmlspecialchars($fech, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($fech, ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php
                                $av = isset($t['avaliacao']) ? (int)$t['avaliacao'] : null;
                                if ($av >= 1 && $av <= 5): ?>
                                    <span class="avaliacao-stars-dashboard" title="Avaliação: <?= $av ?>/5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= $av ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <?php if ($isCsatPeriodoAtivo): ?>
                                <td>
                                    <?php
                                    $avaliadoEm = $t['avaliacao_em'] ?? null;
                                    echo $avaliadoEm ? htmlspecialchars((new DateTime((string)$avaliadoEm))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-';
                                    ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php
                                $created = $t['created_at'] ?? null;
                                echo $created ? htmlspecialchars((new DateTime($created))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $isFechado = in_array(strtolower((string)($t['status'] ?? '')), ['fechado', 'resolvido'], true);
                                $fechadoEm = $t['fechado_em'] ?? $t['updated_at'] ?? null;
                                if ($isFechado && $fechadoEm) {
                                    echo htmlspecialchars((new DateTime((string)$fechadoEm))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8');
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($isFechado && $created && $fechadoEm) {
                                    $tsAberto = (new DateTime($created))->getTimestamp();
                                    $tsFechado = (new DateTime((string)$fechadoEm))->getTimestamp();
                                    $segundos = $tsFechado - $tsAberto;
                                    if ($segundos < 60) {
                                        echo $segundos . ' s';
                                    } elseif ($segundos < 3600) {
                                        echo (int)floor($segundos / 60) . ' min';
                                    } elseif ($segundos < 86400) {
                                        $h = (int)floor($segundos / 3600);
                                        $m = (int)floor(($segundos % 3600) / 60);
                                        echo $h . 'h ' . ($m > 0 ? $m . ' min' : '');
                                    } else {
                                        $d = (int)floor($segundos / 86400);
                                        $h = (int)floor(($segundos % 86400) / 3600);
                                        echo $d . ' dia' . ($d !== 1 ? 's' : '') . ($h > 0 ? ' ' . $h . 'h' : '');
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if (($paginacao['tickets']['totalPaginas'] ?? 1) > 1): ?>
                    <div class="pagination-relatorio" style="margin-top:1rem; display:flex; align-items:center; gap:0.75rem;">
                        <?php
                        $pagAtual = (int)($paginacao['tickets']['paginaAtual'] ?? 1);
                        $pagTotal = (int)($paginacao['tickets']['totalPaginas'] ?? 1);
                        $baseParams = array_filter($filtros ?? []);
                        ?>
                        <?php if ($pagAtual > 1): ?>
                            <a class="btn-filter-relatorio btn-secondary"
                               href="<?= htmlspecialchars(($basePath ?? '') . '/relatorio?' . http_build_query(array_merge($baseParams, ['ticketsPage' => $pagAtual - 1])), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-chevron-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        <span style="font-size:0.85rem; color:#64748b;">
                            Página <?= $pagAtual ?> de <?= $pagTotal ?>
                        </span>
                        <?php if ($pagAtual < $pagTotal): ?>
                            <a class="btn-filter-relatorio btn-secondary"
                               href="<?= htmlspecialchars(($basePath ?? '') . '/relatorio?' . http_build_query(array_merge($baseParams, ['ticketsPage' => $pagAtual + 1])), ENT_QUOTES, 'UTF-8') ?>">
                                Próxima <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p style="margin-top: 0.5rem; color: #64748b; font-size: 0.85rem;">
                    Nenhum ticket encontrado para os filtros selecionados.
                </p>
            <?php endif; ?>
        </main>
        <script>
            document.querySelectorAll('.ticket-row-clickable').forEach(function(row) {
                row.style.cursor = 'pointer';
                row.addEventListener('click', function(e) {
                    if (!e.target.closest('a')) {
                        const href = row.getAttribute('data-href');
                        if (href) window.location.href = href;
                    }
                });
            });
        </script>
    </body>
</html>

