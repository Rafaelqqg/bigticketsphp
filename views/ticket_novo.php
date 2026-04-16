<?php
// Variáveis esperadas: $tickets (array), $perfil, $usuario, $msg, $erro, $basePath, $acompanhamentoAtivo, $currentStatus, $currentPage, $totalPages, $perPage, $totalTickets, $countAbertos, $countAndamento, $countFechados
$perfilLower = isset($perfil) ? strtolower((string)$perfil) : '';
$paginaTicketsNovo = true;
$acompanhamentoAtivo = !empty($acompanhamentoAtivo);
$currentStatus = $currentStatus ?? '';
$currentPage = (int)($currentPage ?? 1);
$totalPages = (int)($totalPages ?? 1);
$perPage = (int)($perPage ?? 5);
$totalTickets = (int)($totalTickets ?? count($tickets ?? []));
$countAbertos = (int)($countAbertos ?? 0);
$countAndamento = (int)($countAndamento ?? 0);
$countFechados = (int)($countFechados ?? 0);
$ticketsAcompanhamentoPopupSuporte = $ticketsAcompanhamentoPopupSuporte ?? [];
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>Gerenciamento de Tickets - Big Trading</title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    </head>
    <body>
        <?php
            $paginaHome = null;
            $paginaRegistros = null;
            $paginaTicketsNovo = true;
            include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="tickets-page-main novo-ticket-page">
            <div class="tickets-container">
                <div class="tabs-header">
                    <div class="tab-buttons">
                        <button type="button" class="tab-button <?= !$acompanhamentoAtivo ? 'active' : '' ?>" onclick="showTab('novo-ticket', this)">
                            <span class="tab-text">Novo Ticket</span>
                        </button>
                        <button type="button" class="tab-button <?= $acompanhamentoAtivo ? 'active' : '' ?>" onclick="showTab('acompanhamento', this)">
                            <span class="tab-text">Acompanhamento</span>
                            <?php if ($totalTickets > 0): ?>
                                <span class="ticket-count"><?= $totalTickets ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>

                <div class="tab-content">
                    <div id="novo-ticket" class="tab-panel <?= !$acompanhamentoAtivo ? 'active' : '' ?>">
                        <div class="novo-ticket-container">
                            <div class="novo-ticket-form-card">
                                <div class="novo-ticket-header">
                                    <div class="novo-ticket-icon"><i class="fa-regular fa-circle-check"></i></div>
                                    <div>
                                        <h2>Solicitação de Suporte</h2>
                                        <p class="novo-ticket-desc">Descreva detalhadamente sua solicitação para que possamos atendê-lo com eficiência e qualidade.</p>
                                    </div>
                                </div>

                                <?php if (!empty($msg)): ?>
                                    <div class="alert alert-success-novo"><span><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span></div>
                                <?php endif; ?>
                                <?php if (!empty($erro)): ?>
                                    <div class="alert alert-error-novo"><span><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span></div>
                                <?php endif; ?>

                                <form method="POST"
                                      action="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/tickets/novo"
                                      enctype="multipart/form-data"
                                      autocomplete="off"
                                      onsubmit="return enviarDescricaoQuill();">
                                    <input type="hidden" id="descricao" name="descricao">

                                    <div class="form-group">
                                        <label for="titulo"><i class="fa-solid fa-layer-group form-label-icon"></i>Título da Solicitação <span class="obrigatorio">*</span></label>
                                        <input id="titulo" name="titulo" type="text" required maxlength="30"
                                               placeholder="Máx. 30 caracteres (ex: Computador não liga)"
                                               class="form-input-enhanced" oninput="atualizarPreview()">
                                        <small class="form-hint">Máximo 30 caracteres</small>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="categoria">
                                                <i class="fa-solid fa-briefcase form-label-icon"></i>Categoria <span class="obrigatorio">*</span>
                                                <span class="categoria-help" tabindex="0" aria-label="Exemplos de categorias">
                                                    <i class="fa-regular fa-circle-question"></i>
                                                    <span class="categoria-help-tooltip">
                                                        <strong>Exemplos:</strong><br>
                                                        Suporte: acesso, senha, orientação.<br>
                                                        Hardware: computador, monitor, impressora.<br>
                                                        Software: erro no sistema, travamento.<br>
                                                        <span class="tooltip-no-wrap">Rede/Infraestrutura: internet, Wi-Fi, VPN, cabeamento.</span><br>
                                                        Desenvolvimento: solicitação para equipe de devs.<br>
                                                        Manutenção: chamado para equipe de manutenção.<br>
                                                        Tonner/Drum: solicitação de tonner ou drum.<br>
                                                        Uso e Consumo: papel, celular, notebook, etc.
                                                    </span>
                                                </span>
                                            </label>
                                            <select id="categoria" name="categoria" required class="form-select-enhanced" onchange="atualizarPreview()">
                                                <option value="">📂 Selecione uma categoria</option>
                                                <option value="Suporte" title="Ex: acesso ao sistema, senha, orientação">🛠️ Suporte</option>
                                                <option value="Hardware" title="Ex: computador, monitor, impressora">🖥️ Hardware</option>
                                                <option value="Software" title="Ex: erro no sistema, travamento">💻 Software</option>
                                                <option value="Rede/Infraestrutura" title="Ex: internet, Wi-Fi, VPN, cabeamento">🌐 Rede/Infraestrutura</option>
                                                <option value="Desenvolvimento" title="Ex: demanda para time de desenvolvimento">👨‍💻 Desenvolvimento</option>
                                                <option value="manutencao" title="Ex: demanda para manutenção">🛠️ Manutenção</option>
                                                <option value="Tonner" title="Ex: solicitação de tonner">🖨️ Tonner</option>
                                                <option value="Drum" title="Ex: solicitação de drum">🖨️ Drum</option>
                                                <option value="Tonner &amp; Drum" title="Ex: solicitação de tonner ou drum">🖨️ Tonner &amp; Drum</option>
                                                <option value="Uso e Consumo" title="Ex: papel, celular, notebook">📦 Uso e Consumo</option>
                                            </select>
                                            <div class="file-hint">Passe o mouse no <i class="fa-regular fa-circle-question"></i> para ver exemplos.</div>
                                        </div>
                                        <?php if (in_array($perfilLower, ['administrador', 'moderador', 'recepcao'], true)): ?>
                                            <div class="form-group">
                                                <label for="filial_codigo">
                                                    <i class="fa-solid fa-building form-label-icon"></i>Filial
                                                </label>
                                                <select id="filial_codigo" name="filial_codigo" required class="form-select-enhanced" onchange="atualizarPreview()">
                                                    <option value="">Selecione a filial</option>
                                                    <?php foreach (($filiais ?? []) as $f): ?>
                                                        <?php $cod = (string)($f['codigo'] ?? ''); ?>
                                                        <option value="<?= htmlspecialchars($cod, ENT_QUOTES, 'UTF-8') ?>" <?= ($filialAtual ?? '') === $cod ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($cod, ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" id="filial_codigo" name="filial_codigo" value="<?= htmlspecialchars((string)($filialAtual ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label for="prioridade"><i class="fa-solid fa-plus form-label-icon"></i>Prioridade <span class="obrigatorio">*</span></label>
                                            <select id="prioridade" name="prioridade" required class="form-select-enhanced" onchange="atualizarPreview()">
                                                <option value="Baixa">Baixa - Não urgente</option>
                                                <option value="Média" selected>Média - Normal</option>
                                                <option value="Alta">Alta - Importante</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="ip_anydesk">
                                                <i class="fa-solid fa-network-wired form-label-icon"></i>IP ou AnyDesk
                                            </label>
                                            <input
                                                id="ip_anydesk"
                                                name="ip_anydesk"
                                                type="text"
                                                class="form-input-enhanced"
                                                maxlength="100"
                                                placeholder="Ex: 192.168.0.10 ou ID AnyDesk"
                                                oninput="atualizarPreview()"
                                            />
                                            <small class="form-hint">Opcional, mas ajuda no acesso remoto.</small>
                                        </div>
                                    </div>

                                    <div class="form-group form-group-descricao">
                                        <label for="quill-editor"><i class="fa-regular fa-file-lines form-label-icon"></i>Descrição Detalhada <span class="obrigatorio">*</span></label>
                                        <div class="descricao-editor-wrap">
                                            <div id="quill-editor" style="height: 140px; font-size: 13px;"></div>
                                            <textarea id="descricao-fallback" style="display:none;"></textarea>
                                            <div class="descricao-editor-footer">
                                                <div class="contador-caracteres"><span id="contador">0</span> caracteres</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="anexos"><i class="fa-solid fa-download form-label-icon"></i>Anexos (imagens ou documentos, até 10MB cada)</label>
                                        <input type="file" id="anexos" name="anexos[]" multiple>
                                        <div class="file-hint">Você pode selecionar vários arquivos. Tamanho máximo por arquivo: 10MB.</div>
                                    </div>

                                    <div class="form-group" style="margin-top: 1rem;">
                                        <button type="submit" class="btn-primary btn-enviar-ticket" id="btnEnviarTicket">Enviar Solicitação</button>
                                    </div>
                                </form>
                            </div>

                            <aside class="novo-ticket-preview">
                                <div class="preview-card">
                                    <div class="preview-header">
                                        <i class="fa-regular fa-eye"></i>
                                        <span class="preview-title">Pré-visualização</span>
                                    </div>
                                    <div class="preview-content">
                                        <div class="preview-item"><span class="preview-label">Título:</span><span id="preview-titulo" class="preview-value">-</span></div>
                                        <div class="preview-item"><span class="preview-label">Categoria:</span><span id="preview-categoria" class="preview-value">-</span></div>
                                        <div class="preview-item"><span class="preview-label">Filial:</span><span id="preview-filial_codigo" class="preview-value">-</span></div>
                                        <div class="preview-item"><span class="preview-label">Prioridade:</span><span id="preview-prioridade" class="preview-value">Média</span></div>
                                        <div class="preview-item"><span class="preview-label">IP / AnyDesk:</span><span id="preview-ip_anydesk" class="preview-value">-</span></div>
                                        <div class="preview-item"><span class="preview-label">Descrição:</span><div id="preview-descricao" class="preview-descricao">-</div></div>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>

                    <div id="acompanhamento" class="tab-panel <?= $acompanhamentoAtivo ? 'active' : '' ?>">
                        <div class="acompanhamento-container">
                            <div class="acompanhamento-header">
                                <div class="header-content">
                                    <div class="header-title">
                                        <h2>Meus Tickets</h2>
                                        <p class="header-subtitle">Acompanhe o status e progresso de suas solicitações <span class="acompanhamento-auto-refresh-hint" title="A lista atualiza sozinha enquanto esta aba estiver aberta">· atualização automática a cada 30&nbsp;s</span></p>
                                    </div>
                                    <div class="header-stats">
                                        <div class="stat-card"><span class="stat-number"><?= $totalTickets ?></span><span class="stat-label">Total</span></div>
                                        <div class="stat-card"><span class="stat-number"><?= $countAbertos ?></span><span class="stat-label">Abertos</span></div>
                                        <div class="stat-card"><span class="stat-number"><?= $countAndamento ?></span><span class="stat-label">Em andamento</span></div>
                                        <div class="stat-card"><span class="stat-number"><?= $countFechados ?></span><span class="stat-label">Fechados</span></div>
                                    </div>
                                </div>
                            </div>

                            <div class="filters-section">
                                <div class="filters-container">
                                    <div class="filters-left">
                                        <h3>Filtros Rápidos</h3>
                                        <div class="quick-filters">
                                            <?php
                                            $dataIni = $dataIni ?? '';
                                            $dataFim = $dataFim ?? '';
                                            $baseUrl = ($basePath ?? '') . '/tickets/novo?acompanhamento=true';
                                            if ($dataIni !== '') $baseUrl .= '&data_ini=' . urlencode($dataIni);
                                            if ($dataFim !== '') $baseUrl .= '&data_fim=' . urlencode($dataFim);
                                            ?>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=todos', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= ($currentStatus === '' || $currentStatus === 'todos') ? 'active' : '' ?>">Todos</a>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=aberto', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= $currentStatus === 'aberto' ? 'active' : '' ?>">Abertos</a>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=em-andamento', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= $currentStatus === 'em-andamento' ? 'active' : '' ?>">Em Andamento</a>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=fechados', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= in_array($currentStatus, ['fechados', 'resolvidos'], true) ? 'active' : '' ?>">Fechados</a>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=respondidos', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= $currentStatus === 'respondidos' ? 'active' : '' ?>">Respondidos</a>
                                            <a href="<?= htmlspecialchars($baseUrl . '&status=nao-avaliados', ENT_QUOTES, 'UTF-8') ?>" class="filter-btn <?= $currentStatus === 'nao-avaliados' ? 'active' : '' ?>">Não Avaliados</a>
                                        </div>
                                    </div>
                                    <div class="filters-right">
                                        <form method="GET" action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo', ENT_QUOTES, 'UTF-8') ?>" class="acompanhamento-date-form">
                                            <input type="hidden" name="acompanhamento" value="true">
                                            <?php if ($currentStatus !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                            <span class="date-filter-label">Data:</span>
                                            <input type="date" name="data_ini" value="<?= htmlspecialchars($dataIni ?? '', ENT_QUOTES, 'UTF-8') ?>" class="date-input-acomp" title="Data inicial">
                                            <span class="date-filter-sep">até</span>
                                            <input type="date" name="data_fim" value="<?= htmlspecialchars($dataFim ?? '', ENT_QUOTES, 'UTF-8') ?>" class="date-input-acomp" title="Data final">
                                            <button type="submit" class="btn-filter-acomp">Filtrar</button>
                                            <?php if ($dataIni !== '' || $dataFim !== ''): ?>
                                            <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo?acompanhamento=true' . ($currentStatus !== '' ? '&status=' . urlencode($currentStatus) : ''), ENT_QUOTES, 'UTF-8') ?>" class="link-limpar-acomp">Limpar</a>
                                            <?php endif; ?>
                                        </form>
                                        <div class="search-container">
                                            <input type="text" id="search-tickets" placeholder="Buscar tickets..." class="search-input">
                                            <button type="button" class="search-btn" onclick="buscarTickets()">Buscar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php if (!empty($tickets)): ?>
                            <div class="acompanhamento-list-wrap">
                            <div class="tickets-list">
                                <?php foreach ($tickets as $t): ?>
                                    <div class="ticket-card"
                                         data-titulo="<?= htmlspecialchars(strtolower((string)($t['titulo'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                         data-numero="<?= htmlspecialchars(strtolower((string)($t['numero_chamado'] ?? $t['id'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                         data-solicitante="<?= htmlspecialchars(strtolower((string)($t['solicitante_nome'] ?? $t['solicitante'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="ticket-header">
                                            <div class="ticket-info">
                                                <div class="ticket-id">
                                                    <span class="ticket-number">#<?= htmlspecialchars($t['numero_chamado'] ?? $t['id'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="ticket-date"><?= !empty($t['created_at']) ? htmlspecialchars((new DateTime((string)$t['created_at']))->format('d/m/Y'), ENT_QUOTES, 'UTF-8') : '-' ?></span>
                                                </div>
                                                <h4 class="ticket-title">
                                                    <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></a>
                                                </h4>
                                                <div class="ticket-meta">
                                                    <span class="ticket-categoria"><?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)($t['categoria'] ?? '')) : (string)($t['categoria'] ?? 'Geral'), ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </div>
                                            <div class="ticket-status">
                                                <div class="status-priority">
                                            <?php
                                            $st = strtolower((string)($t['status'] ?? ''));
                                            $statusClass = 'status-aberto';
                                            $statusText = 'Aberto';
                                            if ($st === 'em_andamento' || $st === 'em andamento') { $statusClass = 'status-em-andamento'; $statusText = 'Em andamento'; }
                                            elseif (in_array($st, ['fechado', 'resolvido'], true)) { $statusClass = 'status-fechado'; $statusText = 'Fechado'; }
                                            ?>
                                                    <span class="status-badge-relatorio <?= $statusClass ?>"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span>
                                                    <span class="priority-badge-relatorio priority-<?= strtolower((string)($t['prioridade'] ?? 'baixa')) ?>"><?= htmlspecialchars($t['prioridade'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="ticket-footer">
                                            <div class="ticket-users">
                                                <span class="user-label">Filial:</span> <span class="user-name"><?= htmlspecialchars($t['filial_nome'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="user-label">Responsável:</span>
                                                <span class="ticket-responsavel-badge">
                                                    <?= htmlspecialchars($t['responsavel_nome'] ?? $t['responsavel'] ?? 'Não definido', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <?php
                                                $stT = strtolower((string)($t['status'] ?? ''));
                                                $fechado = in_array($stT, ['fechado', 'resolvido'], true);
                                                $av = isset($t['avaliacao']) ? (int)$t['avaliacao'] : null;
                                                $temAvaliacao = $av >= 1 && $av <= 5;
                                                $loginUser = is_array($usuario ?? null) ? ($usuario['usuario'] ?? '') : (string)($usuario ?? '');
                                                $ehSolic = (string)($t['solicitante'] ?? '') === $loginUser;
                                                $temResponsavelTicket = trim((string)($t['responsavel'] ?? '')) !== '';
                                                ?>
                                                <?php if ($fechado): ?>
                                                    <?php if ($temAvaliacao): ?>
                                                        <?php
                                                        $avaliacaoRotulos = [1 => 'Extremamente Insatisfeito', 2 => 'Insatisfeito', 3 => 'Indiferente', 4 => 'Satisfeito', 5 => 'Extremamente Satisfeito'];
                                                        $rotuloAv = $avaliacaoRotulos[$av] ?? '';
                                                        ?>
                                                        <span class="avaliacao-badge avaliacao-badge-<?= $av >= 5 ? 'excelente' : ($av >= 4 ? 'bom' : ($av >= 3 ? 'regular' : 'ruim')) ?>">
                                                            <i class="fa-solid fa-star avaliacao-star-icon"></i>
                                                            <span class="avaliacao-stars-text"><?= str_repeat('★', $av) ?><?= str_repeat('☆', 5 - $av) ?></span>
                                                            <span class="avaliacao-label"><?= htmlspecialchars($rotuloAv, ENT_QUOTES, 'UTF-8') ?></span>
                                                        </span>
                                                    <?php elseif ($ehSolic): ?>
                                                        <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>#avaliacao" class="avaliacao-badge avaliacao-badge-pendente">
                                                            <i class="fa-solid fa-star-half-stroke"></i>
                                                            <span>Avalie este atendimento</span>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ticket-actions">
                                                <?php if (!empty($t['hasTiReply']) && $perfilLower === 'comum' && $ehSolic): ?><span class="ticket-comentario-pill-recente ticket-acomp-suporte-pill">Resposta do suporte</span><?php endif; ?>
                                                <?php if ($perfilLower === 'comum' && $ehSolic && !$fechado && $temResponsavelTicket): ?>
                                                    <form method="POST"
                                                          id="form-fechar-acomp-<?= (int)($t['id'] ?? 0) ?>"
                                                          action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'] . '/fechar-solicitante', ENT_QUOTES, 'UTF-8') ?>"
                                                          class="ticket-acomp-fechar-form">
                                                        <button type="button"
                                                                class="btn-fechar-avaliar-acomp"
                                                                data-form-id="form-fechar-acomp-<?= (int)($t['id'] ?? 0) ?>"
                                                                title="Encerrar e avaliar o atendimento"
                                                                aria-haspopup="dialog"
                                                                aria-controls="modal-fechar-acomp">
                                                            <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Encerrar e avaliar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $t['id'], ENT_QUOTES, 'UTF-8') ?>" class="btn-view">Ver Detalhes</a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="modal-fechar-acomp" class="ticket-confirm-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modal-fechar-acomp-title">
                                <div class="ticket-confirm-modal__backdrop" tabindex="-1" data-modal-acomp-dismiss></div>
                                <div class="ticket-confirm-modal__panel">
                                    <div class="ticket-confirm-modal__header">
                                        <div class="ticket-confirm-modal__icon-wrap" aria-hidden="true">
                                            <i class="fa-solid fa-circle-check"></i>
                                        </div>
                                        <h3 id="modal-fechar-acomp-title" class="ticket-confirm-modal__title">Encerrar chamado</h3>
                                    </div>
                                    <p class="ticket-confirm-modal__text">
                                        Encerrar este chamado? Em seguida você poderá <strong>avaliar o atendimento</strong> (CSAT).
                                    </p>
                                    <ul class="ticket-confirm-modal__bullets">
                                        <li>O status será alterado para <strong>fechado</strong>.</li>
                                        <li>Você poderá registrar sua satisfação com o suporte.</li>
                                    </ul>
                                    <div class="ticket-confirm-modal__actions">
                                        <button type="button" class="ticket-confirm-modal__btn ticket-confirm-modal__btn--secondary" data-modal-acomp-dismiss>
                                            Cancelar
                                        </button>
                                        <button type="button" class="ticket-confirm-modal__btn ticket-confirm-modal__btn--primary" id="modal-fechar-acomp-confirm">
                                            <i class="fa-solid fa-check"></i> Sim, encerrar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <script>
                            (function () {
                                var modal = document.getElementById('modal-fechar-acomp');
                                var btnConfirm = document.getElementById('modal-fechar-acomp-confirm');
                                if (!modal || !btnConfirm) return;
                                var pendingFormId = null;
                                var lastFocus = null;
                                function openModal(formId) {
                                    pendingFormId = formId;
                                    lastFocus = document.activeElement;
                                    modal.classList.add('is-open');
                                    modal.setAttribute('aria-hidden', 'false');
                                    document.body.classList.add('ticket-modal-open');
                                    btnConfirm.focus();
                                }
                                function closeModal() {
                                    modal.classList.remove('is-open');
                                    modal.setAttribute('aria-hidden', 'true');
                                    document.body.classList.remove('ticket-modal-open');
                                    pendingFormId = null;
                                    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
                                }
                                document.querySelectorAll('.btn-fechar-avaliar-acomp').forEach(function (btn) {
                                    btn.addEventListener('click', function () {
                                        var fid = btn.getAttribute('data-form-id');
                                        if (fid) openModal(fid);
                                    });
                                });
                                modal.querySelectorAll('[data-modal-acomp-dismiss]').forEach(function (el) {
                                    el.addEventListener('click', closeModal);
                                });
                                btnConfirm.addEventListener('click', function () {
                                    if (pendingFormId) {
                                        var f = document.getElementById(pendingFormId);
                                        if (f) f.submit();
                                    }
                                    closeModal();
                                });
                                modal.addEventListener('keydown', function (e) {
                                    if (e.key === 'Escape') {
                                        e.preventDefault();
                                        closeModal();
                                    }
                                });
                            })();
                            </script>

                            <?php if ($acompanhamentoAtivo && $totalPages > 1): ?>
                                <div class="pagination-relatorio" style="margin-top:1rem; display:flex; align-items:center; gap:0.75rem;">
                                    <?php
                                    $baseParams = ['acompanhamento' => 'true', 'status' => $currentStatus];
                                    if ($dataIni !== '') $baseParams['data_ini'] = $dataIni;
                                    if ($dataFim !== '') $baseParams['data_fim'] = $dataFim;
                                    ?>
                                    <?php if ($currentPage > 1): ?>
                                        <a class="btn-filter-relatorio btn-secondary"
                                           href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo?' . http_build_query(array_merge($baseParams, ['page' => $currentPage - 1])), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="fa-solid fa-chevron-left"></i> Anterior
                                        </a>
                                    <?php endif; ?>
                                    <span style="font-size:0.85rem; color:#64748b;">
                                        Página <?= $currentPage ?> de <?= $totalPages ?>
                                    </span>
                                    <?php if ($currentPage < $totalPages): ?>
                                        <a class="btn-filter-relatorio btn-secondary"
                                           href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo?' . http_build_query(array_merge($baseParams, ['page' => $currentPage + 1])), ENT_QUOTES, 'UTF-8') ?>">
                                            Próxima <i class="fa-solid fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="acompanhamento-list-wrap">
                                <div class="empty-state">
                                    <h3>Nenhum ticket encontrado</h3>
                                    <p>Não há tickets para exibir com os filtros atuais.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <?php
        // Perfil comum: aviso ao abrir /tickets/novo quando há chamados (seus) com última mensagem do suporte
        $exibirModalAcompSuporte = $perfilLower === 'comum'
            && !empty($ticketsAcompanhamentoPopupSuporte);
        ?>
        <?php if ($exibirModalAcompSuporte): ?>
        <div id="modal-acomp-suporte-overlay" class="ticket-modal-suporte-overlay" aria-hidden="true">
            <div class="ticket-modal-suporte-dialog ticket-modal-acomp-suporte-dialog" role="dialog" aria-modal="true" aria-labelledby="modal-acomp-suporte-titulo">
                <div class="ticket-modal-suporte-icon"><i class="fa-solid fa-headset"></i></div>
                <h3 id="modal-acomp-suporte-titulo" class="ticket-modal-suporte-titulo">Resposta do suporte no seu chamado</h3>
                <p class="ticket-modal-suporte-texto" id="modal-acomp-suporte-resumo"></p>
                <ul class="ticket-modal-acomp-lista" id="modal-acomp-suporte-lista" hidden></ul>
                <label class="ticket-modal-suporte-check">
                    <input type="checkbox" id="modal-acomp-suporte-nao-repetir">
                    Não mostrar este aviso de novo para estes chamados
                </label>
                <div class="ticket-modal-suporte-acoes ticket-modal-acomp-acoes">
                    <button type="button" class="btn-secondary ticket-modal-suporte-btn" id="modal-acomp-suporte-ficar">Ficar na lista</button>
                    <button type="button" class="btn-primary ticket-modal-suporte-btn" id="modal-acomp-suporte-abrir">Abrir chamado</button>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var basePath = <?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?>;
            var path = function (p) { return (basePath || '') + p; };
            var rawList = <?= json_encode(array_values($ticketsAcompanhamentoPopupSuporte), JSON_UNESCAPED_UNICODE) ?>;

            function filtrarNaoDismissados(arr) {
                return arr.filter(function (t) {
                    return t && t.id && !sessionStorage.getItem('bigtickets_hide_suporte_popup_' + t.id);
                });
            }

            var list = filtrarNaoDismissados(rawList);
            var ov = document.getElementById('modal-acomp-suporte-overlay');
            if (!ov || !list.length) return;

            var resumo = document.getElementById('modal-acomp-suporte-resumo');
            var ul = document.getElementById('modal-acomp-suporte-lista');
            var primeiro = list[0];

            function appendItemLink(li, t) {
                var a = document.createElement('a');
                a.className = 'ticket-modal-acomp-link';
                a.href = path('/tickets/' + t.id);
                a.textContent = '#' + (t.numero_chamado || t.id) + ' — ' + (t.titulo || 'Sem título');
                li.appendChild(a);
            }

            var btnAbrir = document.getElementById('modal-acomp-suporte-abrir');

            if (list.length === 1) {
                resumo.innerHTML = 'O chamado <strong>#' + String(primeiro.numero_chamado || primeiro.id) + '</strong> tem a <strong>última mensagem do suporte</strong>. Use o botão abaixo ou <a class="ticket-modal-acomp-inline-link" href="' + path('/tickets/' + primeiro.id) + '">abra o chamado aqui</a>.';
                ul.hidden = false;
                ul.innerHTML = '';
                var li1 = document.createElement('li');
                appendItemLink(li1, primeiro);
                ul.appendChild(li1);
                if (btnAbrir) btnAbrir.textContent = 'Abrir chamado';
            } else {
                resumo.innerHTML = 'Você tem <strong>' + list.length + ' chamados</strong> em que a <strong>última mensagem é do suporte</strong>. <em>Clique no chamado desejado</em> na lista ou use o botão para abrir o <strong>mais recente</strong> (primeiro da lista).';
                ul.hidden = false;
                ul.innerHTML = '';
                list.forEach(function (t) {
                    var li = document.createElement('li');
                    appendItemLink(li, t);
                    ul.appendChild(li);
                });
                if (btnAbrir) {
                    btnAbrir.textContent = 'Abrir o mais recente (#' + String(primeiro.numero_chamado || primeiro.id) + ')';
                }
            }

            function fecharModal(salvarPreferencia) {
                var chk = document.getElementById('modal-acomp-suporte-nao-repetir');
                if (salvarPreferencia && chk && chk.checked) {
                    try {
                        list.forEach(function (t) {
                            if (t && t.id) sessionStorage.setItem('bigtickets_hide_suporte_popup_' + t.id, '1');
                        });
                    } catch (e) {}
                }
                ov.classList.remove('is-open');
                ov.setAttribute('aria-hidden', 'true');
            }

            function abrirModal() {
                ov.classList.add('is-open');
                ov.setAttribute('aria-hidden', 'false');
            }

            function init() {
                if (typeof showTab === 'function') {
                    var tabBtns = document.querySelectorAll('.tab-buttons .tab-button');
                    if (tabBtns.length >= 2) {
                        showTab('acompanhamento', tabBtns[1]);
                    }
                }
                abrirModal();
                document.getElementById('modal-acomp-suporte-ficar').addEventListener('click', function () {
                    fecharModal(true);
                });
                document.getElementById('modal-acomp-suporte-abrir').addEventListener('click', function () {
                    fecharModal(true);
                    window.location.href = path('/tickets/' + primeiro.id);
                });
                ov.addEventListener('click', function (e) {
                    if (e.target === ov) fecharModal(false);
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php endif; ?>

        <script>
            var quill = null;
            var acompanhamentoRefreshTimer = null;
            var ACOMPANHAMENTO_REFRESH_MS = 30000;
            function startAcompanhamentoAutoRefresh() {
                if (acompanhamentoRefreshTimer) return;
                acompanhamentoRefreshTimer = setInterval(function () {
                    if (document.hidden) return;
                    var panel = document.getElementById('acompanhamento');
                    if (panel && panel.classList.contains('active')) {
                        location.reload();
                    }
                }, ACOMPANHAMENTO_REFRESH_MS);
            }
            function stopAcompanhamentoAutoRefresh() {
                if (acompanhamentoRefreshTimer) {
                    clearInterval(acompanhamentoRefreshTimer);
                    acompanhamentoRefreshTimer = null;
                }
            }
            function showTab(tabId, btn) {
                document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
                document.querySelectorAll('.tab-button').forEach(function(b){ b.classList.remove('active'); });
                document.getElementById(tabId)?.classList.add('active');
                if (btn) btn.classList.add('active');
                if (tabId === 'acompanhamento') {
                    startAcompanhamentoAutoRefresh();
                } else {
                    stopAcompanhamentoAutoRefresh();
                }
            }
            function buscarTickets() {
                var termo = (document.getElementById('search-tickets')?.value || '').toLowerCase().trim();
                document.querySelectorAll('.ticket-card').forEach(function(card){
                    var titulo = card.getAttribute('data-titulo') || '';
                    var numero = card.getAttribute('data-numero') || '';
                    var solicitante = card.getAttribute('data-solicitante') || '';
                    var ok = !termo || titulo.includes(termo) || numero.includes(termo) || solicitante.includes(termo);
                    card.style.display = ok ? '' : 'none';
                });
            }
            function atualizarPreview() {
                var titulo = document.getElementById('titulo')?.value?.trim() || '-';
                var selCat = document.getElementById('categoria');
                var optCat = selCat && selCat.options[selCat.selectedIndex];
                var categoria = (optCat && optCat.text) ? optCat.text.trim() : (selCat?.value || '-');
                var ipAnydesk = (document.getElementById('ip_anydesk')?.value || '').trim() || '-';
                var prioridade = document.getElementById('prioridade')?.value || '-';
                var descricao = '-';
                if (quill) {
                    var html = quill.root.innerHTML;
                    descricao = (html && html !== '<p><br></p>') ? html : '-';
                    document.getElementById('contador').textContent = quill.getText().trim().length;
                }
                document.getElementById('preview-titulo').textContent = titulo;
                document.getElementById('preview-categoria').textContent = categoria;
                var filialCodigo = (document.getElementById('filial_codigo')?.value || '').trim() || '-';
                document.getElementById('preview-filial_codigo').textContent = filialCodigo;
                document.getElementById('preview-ip_anydesk').textContent = ipAnydesk;
                document.getElementById('preview-prioridade').textContent = prioridade;
                document.getElementById('preview-descricao').innerHTML = descricao;
            }
            function enviarDescricaoQuill() {
                var hidden = document.getElementById('descricao');
                if (quill && hidden) {
                    var html = quill.root.innerHTML;
                    hidden.value = (html && html !== '<p><br></p>') ? html : '';
                }
                if (!hidden || !hidden.value.trim()) {
                    alert('Descrição detalhada é obrigatória.');
                    return false;
                }
                var btn = document.getElementById('btnEnviarTicket');
                if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
                return true;
            }
            document.addEventListener('DOMContentLoaded', function () {
                var editorElement = document.getElementById('quill-editor');
                if (editorElement && typeof Quill !== 'undefined') {
                    quill = new Quill('#quill-editor', {
                        theme: 'snow',
                        modules: {
                            toolbar: [
                                [{ 'header': [1, 2, false] }],
                                ['bold', 'italic', 'underline'],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['clean']
                            ]
                        }
                    });
                    quill.on('text-change', atualizarPreview);
                }
                document.getElementById('titulo')?.addEventListener('input', atualizarPreview);
                document.getElementById('categoria')?.addEventListener('change', atualizarPreview);
                document.getElementById('prioridade')?.addEventListener('change', atualizarPreview);
                document.getElementById('search-tickets')?.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') { e.preventDefault(); buscarTickets(); }
                });
                atualizarPreview();
                if (document.getElementById('acompanhamento')?.classList.contains('active')) {
                    startAcompanhamentoAutoRefresh();
                }
            });
        </script>
    </body>
</html>

