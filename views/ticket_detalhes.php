<?php
// Variáveis: $ticket (array|null), $comentarios (array), $responsaveis (array),
//            $perfil, $usuario, $msg, $erro, $basePath
$perfilLower = isset($perfil) ? strtolower((string)$perfil) : '';
$isAdminLike = in_array($perfilLower, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);
$canAssign   = in_array($perfilLower, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);
$returnToCurrent = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($returnToCurrent === '' || !str_starts_with($returnToCurrent, '/tickets')) {
    $bpLocal = rtrim((string)($basePath ?? ''), '/');
    if ($bpLocal !== '' && str_starts_with($returnToCurrent, $bpLocal . '/tickets')) {
        $tmpRt = substr($returnToCurrent, strlen($bpLocal));
        $returnToCurrent = $tmpRt !== '' ? $tmpRt : '/tickets';
    } else {
        $returnToCurrent = '/tickets';
    }
}
$previewImgExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
$previewPdfExts = ['pdf'];
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>
            <?php if ($ticket): ?>
                Ticket #<?= htmlspecialchars($ticket['numero_chamado'] ?? $ticket['id'], ENT_QUOTES, 'UTF-8') ?> - Big Trading
            <?php else: ?>
                Ticket não encontrado - Big Trading
            <?php endif; ?>
        </title>
        <link rel="stylesheet" href="<?= htmlspecialchars($basePath ?? '', ENT_QUOTES, 'UTF-8') ?>/public/style.css">
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
        <style>
            .live-chat-toggle-btn {
                position: fixed;
                right: 1rem;
                bottom: 1rem;
                z-index: 9997;
                border: none;
                border-radius: 999px;
                padding: 0.75rem 1rem;
                background: #2563eb;
                color: #fff;
                font-weight: 700;
                cursor: pointer;
                box-shadow: 0 8px 24px rgba(37, 99, 235, .35);
            }
            .live-chat-unread-badge {
                display: none;
                margin-left: .35rem;
                min-width: 1.2rem;
                height: 1.2rem;
                border-radius: 999px;
                background: #ef4444;
                color: #fff;
                font-size: .68rem;
                line-height: 1.2rem;
                text-align: center;
                font-weight: 800;
                padding: 0 .25rem;
            }
            .live-chat-panel {
                position: fixed;
                right: 1rem;
                bottom: 4.5rem;
                width: min(95vw, 360px);
                height: min(70vh, 520px);
                z-index: 9998;
                background: #fff;
                border: 1px solid #dbe3ef;
                border-radius: 12px;
                display: none;
                flex-direction: column;
                overflow: hidden;
                box-shadow: 0 18px 36px rgba(15, 23, 42, .25);
            }
            .live-chat-panel.open { display: flex; }
            .live-chat-header {
                padding: .65rem .8rem;
                background: #f8fafc;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .6rem;
                font-size: .84rem;
                font-weight: 700;
                color: #111827;
            }
            .live-chat-header-actions {
                display: flex;
                align-items: center;
                gap: .35rem;
            }
            .live-chat-messages {
                flex: 1;
                overflow: auto;
                padding: .65rem;
                background: #f8fafc;
                display: flex;
                flex-direction: column;
                gap: .45rem;
            }
            .live-chat-msg {
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                padding: .45rem .55rem;
            }
            .live-chat-messages.drag-over { outline: 2px dashed #2563eb; outline-offset: -6px; }
            .live-chat-msg-meta {
                font-size: .7rem;
                color: #64748b;
                margin-bottom: .2rem;
                display:flex;
                justify-content:space-between;
                gap:.4rem;
            }
            .live-chat-msg-text {
                font-size: .83rem;
                color: #1f2937;
                line-height: 1.35;
                word-break: break-word;
            }
            .live-chat-msg-text .mention { color:#1d4ed8; font-weight:700; }
            .live-chat-msg-reply { border-left:3px solid #cbd5e1; padding-left:.4rem; margin-bottom:.28rem; font-size:.72rem; color:#475569; }
            .live-chat-msg-actions { margin-top:.3rem; display:flex; align-items:center; gap:.25rem; flex-wrap:wrap; }
            .live-chat-mini-btn { border:1px solid #cbd5e1; background:#fff; border-radius:999px; padding:.08rem .38rem; font-size:.64rem; cursor:pointer; }
            .live-chat-status { font-size:.62rem; color:#64748b; margin-left:auto; }
            .live-chat-reactions { margin-top:.28rem; display:flex; gap:.24rem; flex-wrap:wrap; }
            .live-chat-reaction-btn { border:1px solid #cbd5e1; background:#f8fafc; border-radius:999px; padding:.08rem .34rem; font-size:.67rem; cursor:pointer; }
            .live-chat-reaction-btn.active { border-color:#2563eb; color:#1d4ed8; background:#eff6ff; }
            .live-chat-attachments {
                margin-top: .35rem;
                display: flex;
                flex-direction: column;
                gap: .3rem;
            }
            .live-chat-attachment-link {
                color: #2563eb;
                text-decoration: none;
                font-size: .78rem;
                font-weight: 600;
                word-break: break-all;
            }
            .live-chat-attachment-link:hover { text-decoration: underline; }
            .live-chat-attachment-image {
                display: block;
                max-width: 180px;
                max-height: 140px;
                border-radius: 8px;
                border: 1px solid #dbe3ef;
                object-fit: cover;
                background: #fff;
            }
            .live-chat-form {
                border-top: 1px solid #e5e7eb;
                padding: .6rem;
                background: #fff;
                display: flex;
                flex-wrap: wrap;
                gap: .5rem;
            }
            .live-chat-tools-row {
                flex: 1 1 100%;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: .5rem;
                flex-wrap: wrap;
            }
            .live-chat-quick-replies {
                display: flex;
                flex-wrap: wrap;
                gap: .3rem;
            }
            .live-chat-quick-btn {
                border: 1px solid #cbd5e1;
                background: #f8fafc;
                color: #334155;
                border-radius: 999px;
                font-size: .68rem;
                font-weight: 700;
                padding: .2rem .5rem;
                cursor: pointer;
            }
            .live-chat-emoji-wrap {
                position: relative;
            }
            .live-chat-emoji-btn {
                border: 1px solid #cbd5e1;
                background: #fff;
                border-radius: 8px;
                padding: .3rem .45rem;
                cursor: pointer;
                font-size: .9rem;
            }
            .live-chat-emoji-panel {
                position: absolute;
                right: 0;
                bottom: 2rem;
                z-index: 3;
                display: none;
                grid-template-columns: repeat(6, minmax(24px, 1fr));
                gap: .25rem;
                padding: .45rem;
                border: 1px solid #dbe3ef;
                border-radius: 10px;
                background: #fff;
                box-shadow: 0 10px 24px rgba(15, 23, 42, .18);
                width: 210px;
            }
            .live-chat-emoji-panel.open { display: grid; }
            .live-chat-emoji-item {
                border: none;
                background: #fff;
                border-radius: 6px;
                padding: .2rem;
                cursor: pointer;
                font-size: 1rem;
            }
            .live-chat-emoji-item:hover { background: #f1f5f9; }
            .live-chat-input {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: .55rem .65rem;
                font-size: .82rem;
                min-width: 180px;
            }
            .live-chat-send {
                border: none;
                border-radius: 8px;
                background: #16a34a;
                color: #fff;
                font-weight: 700;
                padding: .5rem .7rem;
                cursor: pointer;
            }
            .live-chat-file {
                flex: 1 1 100%;
                font-size: .75rem;
                color: #334155;
            }
            .live-chat-paste-preview {
                flex: 1 1 100%;
                display: flex;
                flex-wrap: wrap;
                gap: .35rem;
            }
            .live-chat-paste-chip {
                display: inline-flex;
                align-items: center;
                gap: .3rem;
                border: 1px solid #cbd5e1;
                border-radius: 999px;
                padding: .2rem .45rem;
                font-size: .68rem;
                background: #f8fafc;
                color: #334155;
            }
            .live-chat-paste-chip button {
                border: none;
                background: transparent;
                color: #ef4444;
                cursor: pointer;
                font-size: .75rem;
                line-height: 1;
            }
            .live-chat-replying {
                flex: 1 1 100%;
                display: none;
                align-items: center;
                justify-content: space-between;
                gap: .35rem;
                border: 1px solid #dbe3ef;
                background: #f8fafc;
                border-radius: 8px;
                padding: .2rem .45rem;
                font-size: .72rem;
                color: #334155;
            }
            .live-chat-typing {
                font-size: .72rem;
                color: #64748b;
                padding: .25rem .65rem .45rem;
                border-top: 1px dashed #e2e8f0;
                background: #fff;
                min-height: 1.5rem;
            }
            .ticket-comment-toast {
                position: fixed;
                right: 1rem;
                bottom: 1rem;
                z-index: 9996;
                min-width: 280px;
                max-width: min(92vw, 360px);
                background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
                color: #fff;
                border: 1px solid rgba(148, 163, 184, 0.25);
                border-radius: 14px;
                padding: .7rem .78rem;
                box-shadow: 0 18px 34px rgba(2, 6, 23, .42);
                display: none;
                gap: .5rem;
                align-items: flex-start;
                transform: translateY(12px) scale(.98);
                opacity: 0;
                transition: transform .18s ease, opacity .18s ease;
            }
            .ticket-comment-toast.show {
                display: flex;
                transform: translateY(0) scale(1);
                opacity: 1;
            }
            .ticket-comment-toast-avatar {
                width: 34px;
                height: 34px;
                min-width: 34px;
                border-radius: 999px;
                background: linear-gradient(145deg, #25d366 0%, #16a34a 100%);
                color: #fff;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: .9rem;
                box-shadow: 0 6px 14px rgba(34, 197, 94, .35);
            }
            .ticket-comment-toast-content { min-width: 0; flex: 1; }
            .ticket-comment-toast strong {
                display: block;
                font-size: .8rem;
                margin-bottom: .14rem;
                letter-spacing: .01em;
            }
            .ticket-comment-toast p {
                margin: 0;
                font-size: .74rem;
                line-height: 1.35;
                color: #e5e7eb;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .ticket-comment-toast-actions {
                display:flex;
                flex-direction:column;
                align-items:center;
                gap:.3rem;
            }
            .ticket-comment-toast-close {
                border: none;
                background: transparent;
                color: #cbd5e1;
                cursor: pointer;
                font-size: 1rem;
                line-height: 1;
                padding: 0;
            }
            .ticket-comment-toast-sound {
                border:none;
                background:transparent;
                color:#cbd5e1;
                cursor:pointer;
                font-size:.95rem;
                line-height:1;
                padding:0;
            }
        </style>
    </head>
    <body>
        <?php
            $paginaHome = null;
            $paginaRegistros = null;
            $paginaTickets = true;
            include __DIR__ . '/partials/nav_with_toggle.php';
        ?>

        <main class="main-ticket-detalhes">
            <?php if ($ticket): ?>
                <div class="ticket-detail-topbar">
                    <div class="ticket-detail-topbar-left">
                        <h2>Ticket #<?= htmlspecialchars($ticket['numero_chamado'] ?? $ticket['id'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p><?= htmlspecialchars($ticket['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <div class="ticket-detail-topbar-actions">
                        <a href="<?= htmlspecialchars(($basePath ?? '') . ($perfilLower === 'comum' ? '/tickets/novo' : '/tickets'), ENT_QUOTES, 'UTF-8') ?>" class="ticket-topbar-btn ticket-topbar-btn-secondary">
                            <i class="fa-solid fa-arrow-left"></i> Voltar aos tickets
                        </a>
                        <a href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo', ENT_QUOTES, 'UTF-8') ?>" class="ticket-topbar-btn ticket-topbar-btn-primary">
                            Novo ticket
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success" style="margin-bottom: 1rem;">
                    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($erro)): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (!$ticket): ?>
                <h2>Ticket não encontrado</h2>
                <p>O ticket que você está procurando não foi encontrado ou você não tem permissão para visualizá-lo.</p>
                <p>
                    <a class="btn-secondary" href="<?= htmlspecialchars(($basePath ?? '') . '/tickets/novo', ENT_QUOTES, 'UTF-8') ?>">
                        Voltar aos tickets
                    </a>
                </p>
            <?php else: ?>
                <div class="ticket-top-sections ticket-detail-layout">
                    <div class="card ticket-comments-card">
                        <h3 class="ticket-section-title ticket-comments-title">Comentários (<?= count($comentarios) ?>)</h3>

                        <form method="POST"
                              action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/comentario', ENT_QUOTES, 'UTF-8') ?>"
                              class="ticket-comentario-form"
                              onsubmit="return enviarComentarioQuill();"
                              enctype="multipart/form-data">
                            <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="form-group">
                                <label for="comentario-editor">Adicionar Comentário:</label>
                                <div id="comentario-editor" class="ticket-comentario-editor"></div>
                                <textarea id="comentario" name="comentario" style="display:none;"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="anexos-comentario">Anexos (opcional)</label>
                                <input type="file" id="anexos-comentario" name="anexos[]" multiple>
                            </div>
                            <button type="submit" class="btn-primary btn-enviar-ticket" id="btnComentario">
                                Adicionar comentário
                            </button>
                        </form>
                        <script>
                            var quillComentario = null;
                            document.addEventListener('DOMContentLoaded', function () {
                                if (typeof Quill !== 'undefined' && document.getElementById('comentario-editor')) {
                                    quillComentario = new Quill('#comentario-editor', {
                                        theme: 'snow',
                                        placeholder: 'Digite seu comentário aqui...',
                                        modules: {
                                            toolbar: [
                                                ['bold', 'italic', 'underline'],
                                                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                                                ['clean']
                                            ]
                                        }
                                    });
                                }
                            });

                            function enviarComentarioQuill() {
                                var hidden = document.getElementById('comentario');
                                if (!hidden) return false;

                                if (quillComentario) {
                                    var html = quillComentario.root.innerHTML || '';
                                    hidden.value = (html && html !== '<p><br></p>') ? html : '';
                                }

                                if (!hidden.value.trim()) {
                                    alert('Comentário obrigatório.');
                                    return false;
                                }

                                var btn = document.getElementById('btnComentario');
                                if (btn) {
                                    btn.disabled = true;
                                    btn.textContent = 'Enviando...';
                                }
                                return true;
                            }
                        </script>

                        <?php
                        // Lista vem do servidor em ordem DESC (mais recente primeiro) — índice 0 = última mensagem no tempo
                        $ultimoComentarioEquipe = false;
                        if (!empty($comentarios)) {
                            $perfUlt = strtolower((string)($comentarios[0]['autor_perfil'] ?? ''));
                            $ultimoComentarioEquipe = in_array($perfUlt, ['administrador', 'moderador', 'recepcao', 'manutencao'], true);
                        }
                        // Destaque laranja / selo "Resposta do suporte": somente para o solicitante (perfil comum)
                        $loginViewerComentarios = is_array($usuario ?? null) ? ($usuario['usuario'] ?? '') : (string)($usuario ?? '');
                        $viewerEhSolicitanteDoTicket = (string)($ticket['solicitante'] ?? '') === $loginViewerComentarios;
                        $podeVerDestaqueRespostaSuporte = ($perfilLower === 'comum') && $viewerEhSolicitanteDoTicket;
                        ?>

                        <?php if (!empty($comentarios)): ?>
                            <div class="comentarios-container ticket-comentarios-list ticket-timeline">
                                <?php foreach ($comentarios as $idx => $c): ?>
                                    <?php
                                    $ehDestaqueSuporte = ($idx === 0 && $ultimoComentarioEquipe) && $podeVerDestaqueRespostaSuporte;
                                    $clsComentario = 'comentario ticket-comentario-item' . ($ehDestaqueSuporte ? ' ticket-comentario-item--resposta-suporte' : '');
                                    $idComentario = $ehDestaqueSuporte ? 'comentario-destaque-suporte' : '';
                                    ?>
                                    <div class="<?= htmlspecialchars($clsComentario, ENT_QUOTES, 'UTF-8') ?>"<?= $idComentario !== '' ? ' id="' . htmlspecialchars($idComentario, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                        <div class="ticket-comentario-head">
                                            <div class="ticket-comentario-head-main">
                                            <?php if ($ehDestaqueSuporte): ?>
                                                <span class="ticket-comentario-pill-recente">Resposta do suporte</span>
                                            <?php endif; ?>
                                            <div class="ticket-comentario-author-wrap">
                                                <span class="ticket-comentario-avatar">
                                                    <?= htmlspecialchars(strtoupper(substr((string)($c['autor_nome'] ?? $c['autor'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                <strong class="ticket-comentario-autor">
                                                    <?= htmlspecialchars($c['autor_nome'] ?? $c['autor'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </strong>
                                                <?php
                                                $autorPerfil = strtolower((string)($c['autor_perfil'] ?? ''));
                                                $perfilBadgeLabel = '';
                                                if ($autorPerfil === 'administrador') {
                                                    $perfilBadgeLabel = 'Suporte Técnico';
                                                } elseif ($autorPerfil === 'moderador') {
                                                    $perfilBadgeLabel = 'Service Desk';
                                                } elseif ($autorPerfil === 'recepcao') {
                                                    $perfilBadgeLabel = 'Help Desk';
                                                } elseif ($autorPerfil === 'manutencao') {
                                                    $perfilBadgeLabel = 'Manutenção';
                                                } elseif ($autorPerfil === 'comum') {
                                                    $perfilBadgeLabel = 'Solicitante';
                                                }
                                                ?>
                                                <?php if ($perfilBadgeLabel !== ''): ?>
                                                    <span class="ticket-comentario-role-badge role-<?= htmlspecialchars($autorPerfil, ENT_QUOTES, 'UTF-8') ?>">
                                                        <?= htmlspecialchars($perfilBadgeLabel, ENT_QUOTES, 'UTF-8') ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            </div>
                                            <span class="ticket-comentario-data">
                                                <?php
                                                $dt = $c['data_criacao'] ?? null;
                                                echo $dt ? htmlspecialchars((new DateTime($dt))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="ticket-comentario-texto">
                                            <?= $c['comentario'] ?? '' ?>
                                        </div>
                                        <?php
                                        $anexosComent = [];
                                        if (!empty($c['anexos'])) {
                                            try {
                                                $tmp = json_decode((string)$c['anexos'], true, 512, JSON_THROW_ON_ERROR);
                                                if (is_array($tmp)) {
                                                    $anexosComent = $tmp;
                                                }
                                            } catch (Throwable $e) {
                                                $anexosComent = [];
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($anexosComent)): ?>
                                            <div class="ticket-comentario-anexos">
                                                <span class="ticket-comentario-anexos-label">Anexos:</span>
                                                <ul class="ticket-comentario-anexos-list">
                                                    <?php foreach ($anexosComent as $ax): ?>
                                                        <?php
                                                        $href = ($basePath ?? '') . ($ax['arquivo'] ?? '');
                                                        $nome = $ax['nome_original'] ?? basename((string)($ax['arquivo'] ?? ''));
                                                        $arquivoPath = (string)($ax['arquivo'] ?? '');
                                                        $ext = strtolower((string)pathinfo((string)(parse_url($arquivoPath, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                                                        $previewType = in_array($ext, $previewImgExts, true) ? 'image' : (in_array($ext, $previewPdfExts, true) ? 'pdf' : 'other');
                                                        ?>
                                                        <li>
                                                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                                                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                                                                   class="anexo-inline-open"
                                                                   data-preview-type="<?= htmlspecialchars($previewType, ENT_QUOTES, 'UTF-8') ?>"
                                                                   data-preview-title="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                                                                   style="color:#2563eb;text-decoration:none;font-size:.9rem;font-weight:600;">
                                                                    <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>
                                                                </a>
                                                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="color:#6b7280;text-decoration:none;font-size:.8rem;">nova aba</a>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="ticket-sem-comentario">
                                Nenhum comentário ainda.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="ticket-detalhes-container"
                         >
                        <div class="ticket-detail-head">
                            <div class="ticket-detail-title">
                                <span>Ticket #<?= htmlspecialchars($ticket['numero_chamado'] ?? $ticket['id'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="ticket-detail-subtitle" title="<?= htmlspecialchars($ticket['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    | <?= htmlspecialchars($ticket['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>

                        <div class="ticket-info-card" style="margin-bottom: 1rem;">
                            <div style="display: flex; flex-wrap: wrap; gap: 1.2rem 1.5rem; align-items: flex-start; justify-content: flex-start;">
                                <div style="flex: 1 1 340px; min-width: 260px; max-width: 420px;">
                                    <div class="ticket-info-lines">
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Número do Chamado</span>
                                            <span class="ticket-info-value-inline">
                                                #<?= htmlspecialchars($ticket['numero_chamado'] ?? $ticket['id'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Título</span>
                                            <span class="ticket-info-value-inline" title="<?= htmlspecialchars($ticket['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($ticket['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Status</span>
                                            <span class="ticket-info-value-inline">
                                                <?php
                                                $st = strtolower((string)($ticket['status'] ?? ''));
                                                $statusClass = 'status-aberto';
                                                $statusText = 'Aberto';
                                                if ($st === 'em_andamento' || $st === 'em andamento') { $statusClass = 'status-em-andamento'; $statusText = 'Em andamento'; }
                                                elseif (in_array($st, ['fechado', 'resolvido'], true)) { $statusClass = 'status-fechado'; $statusText = 'Fechado'; }
                                                ?>
                                                <span class="status-badge-relatorio <?= $statusClass ?>"><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Prioridade</span>
                                            <span class="ticket-info-value-inline">
                                                <?php
                                                $pr = strtolower((string)($ticket['prioridade'] ?? ''));
                                                $prioClass = 'priority-baixa';
                                                if ($pr === 'alta') $prioClass = 'priority-alta';
                                                elseif ($pr === 'média' || $pr === 'media') $prioClass = 'priority-média';
                                                ?>
                                                <span class="priority-badge-relatorio <?= $prioClass ?>"><?= htmlspecialchars($ticket['prioridade'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Categoria</span>
                                            <span class="ticket-info-value-inline">
                                                <?php if ($isAdminLike): ?>
                                                    <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/categoria', ENT_QUOTES, 'UTF-8') ?>" class="ticket-categoria-inline-form">
                                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                                        <select name="categoria" class="ticket-categoria-select" onchange="this.form.submit()">
                                                            <?php
                                                            $categoriasOpcoes = [
                                                                'Suporte' => 'Suporte',
                                                                'Hardware' => 'Hardware',
                                                                'Software' => 'Software',
                                                                'Rede/Infraestrutura' => 'Rede/Infraestrutura',
                                                                'Desenvolvimento' => 'Desenvolvimento',
                                                                'manutencao' => 'Manutenção',
                                                                'Tonner' => 'Tonner',
                                                                'Drum' => 'Drum',
                                                                'Tonner & Drum' => 'Tonner & Drum',
                                                                'Uso e Consumo' => 'Uso e Consumo',
                                                            ];
                                                            $catAtual = $ticket['categoria'] ?? '';
                                                            $catAtualNorm = function_exists('categoria_normalize_storage') ? categoria_normalize_storage($catAtual) : $catAtual;
                                                            foreach ($categoriasOpcoes as $val => $label):
                                                                $sel = ($val === $catAtualNorm || (in_array($catAtual, ['Rede', 'Rede/Infraestrutura'], true) && $val === 'Rede/Infraestrutura')) ? ' selected' : '';
                                                            ?>
                                                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>"<?= $sel ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </form>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(function_exists('categoria_display') ? categoria_display((string)($ticket['categoria'] ?? '')) : (string)($ticket['categoria'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">IP / AnyDesk</span>
                                            <span class="ticket-info-value-inline">
                                                <?php $ipAnyDesk = trim((string)($ticket['ip_anydesk'] ?? '')); ?>
                                                <span class="ticket-responsavel-badge">
                                                    <?= htmlspecialchars($ipAnyDesk !== '' ? $ipAnyDesk : '—', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Responsável</span>
                                            <span class="ticket-info-value-inline">
                                                <span class="ticket-responsavel-badge">
                                                    <?= htmlspecialchars($ticket['responsavel_nome'] ?? $ticket['responsavel'] ?? 'Não atribuído', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Solicitante</span>
                                            <span class="ticket-info-value-inline">
                                                <?= htmlspecialchars($ticket['solicitante_nome'] ?? $ticket['solicitante'] ?? 'Não informado', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($ticket['solicitante_cargo'])): ?>
                                                    <span style="color:#6b7280;font-size:0.78em;margin-left:0.35rem;">
                                                        (<?= htmlspecialchars($ticket['solicitante_cargo'], ENT_QUOTES, 'UTF-8') ?>)
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($ticket['filial_nome'])): ?>
                                                    <span style="color:#6b7280;font-size:0.78em;margin-left:0.35rem;">
                                                        [Filial: <?= htmlspecialchars($ticket['filial_nome'], ENT_QUOTES, 'UTF-8') ?>]
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="ticket-info-line">
                                            <span class="ticket-info-label-inline">Data de criação</span>
                                            <span class="ticket-info-value-inline">
                                                <?php
                                                $created = $ticket['created_at'] ?? null;
                                                echo $created ? htmlspecialchars((new DateTime($created))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-';
                                                ?>
                                            </span>
                                        </div>
                                        <?php if (!empty($ticket['sla_prazo_ts'])): ?>
                                            <div class="ticket-info-line">
                                                <span class="ticket-info-label-inline">Prazo alvo</span>
                                                <span class="ticket-info-value-inline">
                                                    <?= htmlspecialchars(date('d/m/Y H:i', (int)$ticket['sla_prazo_ts']), ENT_QUOTES, 'UTF-8') ?>
                                                    <?php if (($ticket['sla_status'] ?? null) === 'dentro'): ?>
                                                        <span class="sla-badge sla-badge-dentro">Dentro do prazo</span>
                                                    <?php elseif (($ticket['sla_status'] ?? null) === 'atrasado'): ?>
                                                        <span class="sla-badge sla-badge-atrasado">Atrasado</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                <?php
                                $stStatus = strtolower((string)($ticket['status'] ?? ''));
                                $isFechadoStatus = in_array($stStatus, ['fechado', 'resolvido'], true);
                                ?>
                                <?php if ($isFechadoStatus): ?>
                                            <div class="ticket-info-line">
                                                <span class="ticket-info-label-inline" style="color:#b91c1c;">Fechado em</span>
                                                <span class="ticket-info-value-inline" style="color:#b91c1c;font-weight:600;">
                                                    <?php
                                                    $fechadoEmInfo = $ticket['fechado_em'] ?? $ticket['updated_at'] ?? null;
                                                    echo $fechadoEmInfo ? htmlspecialchars((new DateTime((string)$fechadoEmInfo))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') : '-';
                                                    ?>
                                                </span>
                                            </div>
                                <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="ticket-section-title">Descrição</div>
                        <div class="ticket-descricao-box">
                            <span style="line-height: 1.5;">
                                <?= $ticket['descricao'] ?? '' ?>
                            </span>
                        </div>

                        <?php
                        $anexosTicket = [];
                        if (!empty($ticket['anexos'])) {
                            try {
                                $tmp = json_decode((string)$ticket['anexos'], true, 512, JSON_THROW_ON_ERROR);
                                if (is_array($tmp)) {
                                    $anexosTicket = $tmp;
                                }
                            } catch (Throwable $e) {
                                $anexosTicket = [];
                            }
                        }
                        ?>
                        <?php if (!empty($anexosTicket)): ?>
                            <div class="ticket-anexos-wrap">
                                <div class="ticket-section-title ticket-anexos-title">Anexos do ticket</div>
                                <ul class="ticket-anexos-list">
                                    <?php foreach ($anexosTicket as $ax): ?>
                                        <?php
                                        $href = ($basePath ?? '') . ($ax['arquivo'] ?? '');
                                        $nome = $ax['nome_original'] ?? basename((string)($ax['arquivo'] ?? ''));
                                        $arquivoPath = (string)($ax['arquivo'] ?? '');
                                        $ext = strtolower((string)pathinfo((string)(parse_url($arquivoPath, PHP_URL_PATH) ?? ''), PATHINFO_EXTENSION));
                                        $previewType = in_array($ext, $previewImgExts, true) ? 'image' : (in_array($ext, $previewPdfExts, true) ? 'pdf' : 'other');
                                        ?>
                                        <li>
                                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                                                   class="anexo-inline-open"
                                                   data-preview-type="<?= htmlspecialchars($previewType, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-preview-title="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>"
                                                   style="color:#2563eb;text-decoration:none;font-size:.95rem;font-weight:600;">
                                                    <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" target="_blank" style="color:#6b7280;text-decoration:none;font-size:.82rem;">nova aba</a>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php
                        $stSolFechar = strtolower((string)($ticket['status'] ?? ''));
                        $ticketNaoFechadoSol = !in_array($stSolFechar, ['fechado', 'resolvido'], true);
                        $loginUsuarioFechar = is_array($usuario ?? null) ? ($usuario['usuario'] ?? '') : (string)($usuario ?? '');
                        $ehSolicitanteFechar = (string)($ticket['solicitante'] ?? '') === $loginUsuarioFechar;
                        $temResponsavelAtribuido = trim((string)($ticket['responsavel'] ?? '')) !== '';
                        $exibirFecharSolicitante = ($perfilLower === 'comum') && $ehSolicitanteFechar && $ticketNaoFechadoSol && $temResponsavelAtribuido;
                        ?>
                        <?php if ($exibirFecharSolicitante): ?>
                            <div class="ticket-fechar-solicitante-box ticket-detalhes-acao-solicitante">
                                <p class="ticket-fechar-solicitante-texto">
                                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                                    Se sua demanda foi atendida, você pode <strong>encerrar o chamado</strong> e ir direto para a <strong>avaliação</strong> do suporte.
                                </p>
                                <form method="POST"
                                      id="form-fechar-solicitante"
                                      action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/fechar-solicitante', ENT_QUOTES, 'UTF-8') ?>"
                                      class="ticket-fechar-solicitante-form">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="button" class="ticket-fechar-solicitante-btn" id="btn-abrir-modal-fechar" aria-haspopup="dialog" aria-controls="modal-fechar-ticket">
                                        <i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Encerrar e avaliar
                                    </button>
                                </form>

                                <div id="modal-fechar-ticket" class="ticket-confirm-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="modal-fechar-ticket-title">
                                    <div class="ticket-confirm-modal__backdrop" tabindex="-1" data-modal-fechar-dismiss></div>
                                    <div class="ticket-confirm-modal__panel">
                                        <div class="ticket-confirm-modal__header">
                                            <div class="ticket-confirm-modal__icon-wrap" aria-hidden="true">
                                                <i class="fa-solid fa-circle-check"></i>
                                            </div>
                                            <h3 id="modal-fechar-ticket-title" class="ticket-confirm-modal__title">Encerrar chamado</h3>
                                        </div>
                                        <p class="ticket-confirm-modal__text">
                                            Encerrar este chamado? Em seguida você poderá <strong>avaliar o atendimento</strong> (CSAT).
                                        </p>
                                        <ul class="ticket-confirm-modal__bullets">
                                            <li>O status será alterado para <strong>fechado</strong>.</li>
                                            <li>Você poderá registrar sua satisfação com o suporte.</li>
                                        </ul>
                                        <div class="ticket-confirm-modal__actions">
                                            <button type="button" class="ticket-confirm-modal__btn ticket-confirm-modal__btn--secondary" data-modal-fechar-dismiss>
                                                Cancelar
                                            </button>
                                            <button type="button" class="ticket-confirm-modal__btn ticket-confirm-modal__btn--primary" id="modal-fechar-ticket-confirm">
                                                <i class="fa-solid fa-check"></i> Sim, encerrar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                (function () {
                                    var modal = document.getElementById('modal-fechar-ticket');
                                    var btnOpen = document.getElementById('btn-abrir-modal-fechar');
                                    var form = document.getElementById('form-fechar-solicitante');
                                    var btnConfirm = document.getElementById('modal-fechar-ticket-confirm');
                                    if (!modal || !btnOpen || !form) return;

                                    var dismissEls = modal.querySelectorAll('[data-modal-fechar-dismiss]');
                                    var lastFocus = null;

                                    function openModal() {
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
                                        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
                                    }
                                    btnOpen.addEventListener('click', function (e) {
                                        e.preventDefault();
                                        openModal();
                                    });
                                    dismissEls.forEach(function (el) {
                                        el.addEventListener('click', closeModal);
                                    });
                                    btnConfirm.addEventListener('click', function () {
                                        form.submit();
                                    });
                                    modal.addEventListener('keydown', function (e) {
                                        if (e.key === 'Escape') {
                                            e.preventDefault();
                                            closeModal();
                                        }
                                    });
                                })();
                                </script>
                            </div>
                        <?php endif; ?>

                        <?php
                        $stTicket = strtolower((string)($ticket['status'] ?? ''));
                        $ticketFechado = in_array($stTicket, ['fechado', 'resolvido'], true);
                        $loginUsuario = is_array($usuario ?? null) ? ($usuario['usuario'] ?? '') : (string)($usuario ?? '');
                        $avaliacaoAtual = isset($ticket['avaliacao']) ? (int)$ticket['avaliacao'] : null;
                        $ehAdminUsuario = (strtolower((string)$loginUsuario) === 'admin');
                        $ehSolicitante = (string)($ticket['solicitante'] ?? '') === $loginUsuario;
                        $perfilLowerAval = strtolower((string)($perfil ?? ''));
                        $semAvaliacaoValida = ($avaliacaoAtual === null || $avaliacaoAtual < 1 || $avaliacaoAtual > 5);
                        $podeAvaliar = $ticketFechado && (
                            $ehAdminUsuario ||
                            ($semAvaliacaoValida && $ehSolicitante && $perfilLowerAval === 'comum')
                        );
                        $avaliacaoRotulos = [1 => 'Extremamente Insatisfeito', 2 => 'Insatisfeito', 3 => 'Indiferente', 4 => 'Satisfeito', 5 => 'Extremamente Satisfeito'];
                        ?>
                        <?php if ($ticketFechado): ?>
                            <div id="avaliacao" class="ticket-avaliacao-section ticket-inline-form ticket-bottom-form ticket-detalhes-acao-solicitante">
                                <label class="ticket-inline-form-label ticket-bottom-form-title">
                                    <i class="fa-solid fa-star" style="margin-right:0.25rem; color:#f59e0b;"></i> Avaliação de satisfação (CSAT)
                                </label>
                                <?php if ($podeAvaliar): ?>
                                    <form method="POST" action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/avaliar', ENT_QUOTES, 'UTF-8') ?>" class="ticket-avaliacao-form">
                                        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                        <div class="ticket-avaliacao-stars<?= ($avaliacaoAtual >= 1 && $avaliacaoAtual <= 5) ? ' hover-' . $avaliacaoAtual : '' ?>" role="group" aria-label="Avaliação de 1 a 5 estrelas">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="ticket-avaliacao-star-col">
                                                    <label class="ticket-avaliacao-star-label">
                                                        <input type="radio" name="avaliacao" value="<?= $i ?>" required<?= ($avaliacaoAtual >= 1 && $avaliacaoAtual <= 5 && $avaliacaoAtual === $i) ? ' checked' : '' ?>>
                                                        <span class="ticket-avaliacao-star<?= ($avaliacaoAtual >= 1 && $avaliacaoAtual <= 5 && $i <= $avaliacaoAtual) ? ' ticket-avaliacao-star-filled' : '' ?>"><i class="fa-solid fa-star"></i></span>
                                                    </label>
                                                    <span class="ticket-avaliacao-legend-text"><?= htmlspecialchars($avaliacaoRotulos[$i] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <button type="submit" class="btn-primary ticket-inline-save ticket-avaliacao-btn">
                                            <i class="fa-solid fa-paper-plane"></i> <?= ($avaliacaoAtual >= 1 && $avaliacaoAtual <= 5) ? 'Alterar avaliação' : 'Enviar avaliação' ?>
                                        </button>
                                    </form>
                                <?php elseif ($avaliacaoAtual >= 1 && $avaliacaoAtual <= 5): ?>
                                    <div class="ticket-avaliacao-exibicao">
                                        <div class="ticket-avaliacao-stars ticket-avaliacao-stars-readonly ticket-avaliacao-rated-<?= $avaliacaoAtual ?>">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="ticket-avaliacao-star-col">
                                                    <span class="ticket-avaliacao-star ticket-avaliacao-star-readonly <?= $i <= $avaliacaoAtual ? 'ticket-avaliacao-star-filled' : '' ?>"><i class="fa-solid fa-star"></i></span>
                                                    <span class="ticket-avaliacao-legend-text"><?= htmlspecialchars($avaliacaoRotulos[$i] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if (!empty($ticket['avaliacao_em'])): ?>
                                        <div class="ticket-avaliacao-info-inline ticket-avaliado-em">
                                            Avaliado em <?= htmlspecialchars((new DateTime($ticket['avaliacao_em']))->format('d/m/Y H:i'), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="ticket-avaliacao-aguardando"><i class="fa-solid fa-clock"></i> Aguardando avaliação do solicitante.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $listaResponsaveis = isset($responsaveis) && is_array($responsaveis) ? $responsaveis : [];
                        ?>
                        <?php if ($canAssign && !empty($listaResponsaveis)): ?>
                                <form method="POST"
                                      action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/atribuir', ENT_QUOTES, 'UTF-8') ?>"
                                      class="ticket-inline-form ticket-bottom-form">
                                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                    <label for="responsavel-select" class="ticket-inline-form-label ticket-bottom-form-title">
                                        Atribuir responsável
                                    </label>
                                    <div class="ticket-inline-form-row">
                                        <select id="responsavel-select" name="responsavel" class="ticket-inline-select">
                                            <option value="">Não atribuído</option>
                                            <?php foreach ($listaResponsaveis as $resp): ?>
                                                <?php
                                                $usuarioResp = $resp['usuario'] ?? '';
                                                $nomeResp    = $resp['nome'] ?? $usuarioResp;
                                                $selecionado = ($ticket['responsavel'] ?? '') === $usuarioResp;
                                                ?>
                                                <option value="<?= htmlspecialchars($usuarioResp, ENT_QUOTES, 'UTF-8') ?>"
                                                    <?= $selecionado ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($nomeResp, ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn-primary ticket-inline-save">
                                            Salvar
                                        </button>
                                    </div>
                                </form>
                        <?php endif; ?>
                        <?php
                        $stStatusForm = strtolower((string)($ticket['status'] ?? ''));
                        $ticketFechadoStatus = in_array($stStatusForm, ['fechado', 'resolvido'], true);
                        $podeAlterarStatus = $isAdminLike && (!$ticketFechadoStatus || $perfilLower === 'administrador');
                        ?>
                        <?php if ($podeAlterarStatus): ?>
                                <?php
                                $temResponsavelStatus = trim((string)($ticket['responsavel'] ?? '')) !== '';
                                $bloquearFecharSemResp = !$ticketFechadoStatus && !$temResponsavelStatus;
                                ?>
                                <form method="POST"
                                  action="<?= htmlspecialchars(($basePath ?? '') . '/tickets/' . $ticket['id'] . '/status', ENT_QUOTES, 'UTF-8') ?>"
                                  class="ticket-inline-form ticket-bottom-form">
                                <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnToCurrent, ENT_QUOTES, 'UTF-8') ?>">
                                <label for="status-select" class="ticket-inline-form-label ticket-bottom-form-title">
                                    Atualizar status
                                </label>
                                <?php if ($bloquearFecharSemResp): ?>
                                    <p class="ticket-fechar-sem-resp-aviso"><i class="fa-solid fa-circle-info"></i> Para fechar o chamado, atribua um responsável acima.</p>
                                <?php endif; ?>
                                <div class="ticket-inline-form-row">
                                    <select id="status-select" name="status" class="ticket-inline-select">
                                        <?php
                                        $statusAtual = $ticket['status'] ?? '';
                                        $opcoesStatus = [
                                            'aberto'       => 'Aberto',
                                            'em_andamento' => 'Em andamento',
                                            'fechado'      => 'Fechado',
                                        ];
                                        foreach ($opcoesStatus as $valor => $rotulo):
                                            $optDisabled = ($valor === 'fechado' && $bloquearFecharSemResp);
                                        ?>
                                            <option value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $statusAtual === $valor ? 'selected' : '' ?>
                                                <?= $optDisabled ? ' disabled' : '' ?>
                                                <?= $optDisabled ? ' title="Atribua um responsável para fechar"' : '' ?>>
                                                <?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn-primary ticket-inline-save">
                                        Atualizar
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endif; ?>
            <div id="anexo-preview-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.7);z-index:9999;align-items:center;justify-content:center;padding:1rem;">
                <div style="background:#fff;border-radius:10px;width:min(96vw,1000px);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem .9rem;border-bottom:1px solid #e5e7eb;">
                        <strong id="anexo-preview-title" style="font-size:.92rem;color:#111827;">Pré-visualização</strong>
                        <button type="button" id="anexo-preview-close" style="border:none;background:transparent;font-size:1.2rem;cursor:pointer;line-height:1;">&times;</button>
                    </div>
                    <div id="anexo-preview-body" style="padding:.75rem;background:#f8fafc;overflow:auto;min-height:240px;"></div>
                </div>
            </div>
            <?php if ($ticket && false): ?>
                <button type="button" id="live-chat-toggle-btn" class="live-chat-toggle-btn">
                    <i class="fa-solid fa-comments"></i> Chat em tempo real
                    <span id="live-chat-unread-badge" class="live-chat-unread-badge">0</span>
                </button>
                <div id="live-chat-panel" class="live-chat-panel" aria-hidden="true">
                    <div class="live-chat-header">
                        <span>Chat rápido do ticket #<?= htmlspecialchars($ticket['numero_chamado'] ?? $ticket['id'], ENT_QUOTES, 'UTF-8') ?></span>
                        <div class="live-chat-header-actions">
                            <button type="button" id="live-chat-sound-btn" class="live-chat-send" style="background:#2563eb;padding:.25rem .5rem;" title="Alternar som">
                                <i class="fa-solid fa-volume-high"></i>
                            </button>
                            <button type="button" id="live-chat-close-btn" class="live-chat-send" style="background:#ef4444;padding:.25rem .5rem;">Fechar</button>
                        </div>
                    </div>
                    <div id="live-chat-messages" class="live-chat-messages"></div>
                    <form id="live-chat-form" class="live-chat-form">
                        <div class="live-chat-tools-row">
                            <div class="live-chat-quick-replies">
                                <button type="button" class="live-chat-quick-btn" data-quick-reply="Olá! Vou verificar e já retorno.">Saudação</button>
                                <button type="button" class="live-chat-quick-btn" data-quick-reply="Estou verificando seu chamado agora.">Em atendimento</button>
                                <button type="button" class="live-chat-quick-btn" data-quick-reply="Ajuste concluído. Pode validar para mim, por favor?">Concluído</button>
                            </div>
                            <div class="live-chat-emoji-wrap">
                                <button type="button" id="live-chat-emoji-btn" class="live-chat-emoji-btn" title="Inserir emoji">😀</button>
                                <div id="live-chat-emoji-panel" class="live-chat-emoji-panel">
                                    <button type="button" class="live-chat-emoji-item" data-emoji="😀">😀</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="😁">😁</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="😉">😉</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="😊">😊</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="👍">👍</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="🙏">🙏</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="✅">✅</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="⚠️">⚠️</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="📎">📎</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="📷">📷</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="🖨️">🖨️</button>
                                    <button type="button" class="live-chat-emoji-item" data-emoji="💬">💬</button>
                                </div>
                            </div>
                        </div>
                        <input type="text" id="live-chat-input" class="live-chat-input" placeholder="Digite uma mensagem (ou envie só anexo)" maxlength="2000">
                        <input type="file" id="live-chat-files" class="live-chat-file" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,.rar">
                        <div id="live-chat-replying" class="live-chat-replying">
                            <span id="live-chat-replying-text"></span>
                            <button type="button" id="live-chat-replying-cancel" class="live-chat-mini-btn">Cancelar</button>
                        </div>
                        <div id="live-chat-paste-preview" class="live-chat-paste-preview"></div>
                        <button type="submit" id="live-chat-send-btn" class="live-chat-send">Enviar</button>
                    </form>
                    <div id="live-chat-typing" class="live-chat-typing"></div>
                </div>
            <?php endif; ?>
            <?php if ($ticket): ?>
                <div id="ticket-comment-toast" class="ticket-comment-toast" role="status" aria-live="polite">
                    <span class="ticket-comment-toast-avatar">
                        <i class="fa-solid fa-message"></i>
                    </span>
                    <div class="ticket-comment-toast-content">
                        <strong id="ticket-comment-toast-title">Nova resposta</strong>
                        <p id="ticket-comment-toast-text"></p>
                    </div>
                    <div class="ticket-comment-toast-actions">
                        <button type="button" id="ticket-comment-toast-sound" class="ticket-comment-toast-sound" aria-label="Alternar som" title="Som ligado">
                            <i class="fa-solid fa-volume-high"></i>
                        </button>
                        <button type="button" id="ticket-comment-toast-close" class="ticket-comment-toast-close" aria-label="Fechar">&times;</button>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        <?php if (!empty($focus_avaliacao)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var el = document.getElementById('avaliacao');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        </script>
        <?php endif; ?>
        <script>
            (function() {
                var stars = document.querySelector('.ticket-avaliacao-stars');
                if (stars) {
                    var cols = stars.querySelectorAll('.ticket-avaliacao-star-col');
                    cols.forEach(function(col, idx) {
                        var n = idx + 1;
                        var label = col.querySelector('.ticket-avaliacao-star-label');
                        if (label) {
                            label.addEventListener('mouseenter', function() { stars.className = 'ticket-avaliacao-stars hover-' + n; });
                            label.addEventListener('mouseleave', function() {
                                var checked = stars.querySelector('input:checked');
                                stars.className = 'ticket-avaliacao-stars' + (checked ? ' hover-' + checked.value : '');
                            });
                            label.querySelector('input')?.addEventListener('change', function() {
                                stars.className = 'ticket-avaliacao-stars hover-' + this.value;
                            });
                        }
                    });
                    stars.addEventListener('mouseleave', function() {
                        var checked = stars.querySelector('input:checked');
                        stars.className = 'ticket-avaliacao-stars' + (checked ? ' hover-' + checked.value : '');
                    });
                }
            })();
        </script>
        <script>
            (function () {
                var modal = document.getElementById('anexo-preview-modal');
                var body = document.getElementById('anexo-preview-body');
                var title = document.getElementById('anexo-preview-title');
                var btnClose = document.getElementById('anexo-preview-close');
                if (!modal || !body || !title || !btnClose) return;

                function closeModal() {
                    modal.style.display = 'none';
                    body.innerHTML = '';
                }

                document.addEventListener('click', function (ev) {
                    var target = ev.target;
                    if (!(target instanceof Element)) return;
                    var link = target.closest('.anexo-inline-open');
                    if (!link) return;

                    ev.preventDefault();
                    var href = link.getAttribute('href') || '';
                    var previewType = link.getAttribute('data-preview-type') || 'other';
                    var previewTitle = link.getAttribute('data-preview-title') || 'Pré-visualização';
                    title.textContent = previewTitle;

                    if (previewType === 'image') {
                        body.innerHTML = '<img src="' + href + '" alt="Pré-visualização do anexo" style="display:block;max-width:100%;height:auto;margin:0 auto;border:1px solid #d1d5db;border-radius:8px;background:#fff;">';
                    } else if (previewType === 'pdf') {
                        body.innerHTML = '<iframe src="' + href + '" title="Pré-visualização PDF" style="width:100%;height:72vh;border:1px solid #d1d5db;border-radius:8px;background:#fff;"></iframe>';
                    } else {
                        body.innerHTML = '<p style="margin:0;color:#475569;">Este tipo de arquivo não suporta pré-visualização aqui.</p>'
                            + '<p style="margin:.65rem 0 0;"><a href="' + href + '" target="_blank" style="color:#2563eb;">Abrir em nova aba</a></p>';
                    }
                    modal.style.display = 'flex';
                });

                btnClose.addEventListener('click', closeModal);
                modal.addEventListener('click', function (ev) {
                    if (ev.target === modal) closeModal();
                });
                document.addEventListener('keydown', function (ev) {
                    if (ev.key === 'Escape' && modal.style.display !== 'none') closeModal();
                });
            })();
        </script>
        <?php if ($ticket && false): ?>
        <script>
            (function () {
                var basePath = <?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?>;
                var ticketId = <?= (int)($ticket['id'] ?? 0) ?>;
                var currentUserLogin = <?= json_encode((is_array($usuario ?? null) ? ($usuario['usuario'] ?? '') : (string)($usuario ?? '')), JSON_UNESCAPED_UNICODE) ?>;
                var toast = document.getElementById('ticket-comment-toast');
                var toastTitle = document.getElementById('ticket-comment-toast-title');
                var toastText = document.getElementById('ticket-comment-toast-text');
                var toastClose = document.getElementById('ticket-comment-toast-close');
                var toastSoundBtn = document.getElementById('ticket-comment-toast-sound');
                var lastSeenCommentId = 0;
                var initialized = false;
                var hideToastTimer = null;
                var soundEnabled = true;
                var soundStorageKey = 'bigtickets_comment_toast_sound';

                if (!toast || !toastTitle || !toastText || !toastClose || !toastSoundBtn || !ticketId) return;

                function renderSoundButton() {
                    toastSoundBtn.innerHTML = soundEnabled
                        ? '<i class="fa-solid fa-volume-high"></i>'
                        : '<i class="fa-solid fa-volume-xmark"></i>';
                    toastSoundBtn.title = soundEnabled ? 'Som ligado' : 'Som desligado';
                }

                function loadSoundPref() {
                    try {
                        var val = localStorage.getItem(soundStorageKey);
                        if (val === 'off') soundEnabled = false;
                    } catch (e) {}
                    renderSoundButton();
                }

                function showToast(title, text) {
                    toastTitle.textContent = title;
                    toastText.textContent = text;
                    toast.classList.add('show');
                    if (soundEnabled) {
                        try {
                            var Ctx = window.AudioContext || window.webkitAudioContext;
                            if (Ctx) {
                                var ctx = new Ctx();
                                var osc = ctx.createOscillator();
                                var gain = ctx.createGain();
                                osc.type = 'sine';
                                osc.frequency.value = 920;
                                gain.gain.setValueAtTime(0.0001, ctx.currentTime);
                                gain.gain.exponentialRampToValueAtTime(0.06, ctx.currentTime + 0.01);
                                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.18);
                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.start();
                                osc.stop(ctx.currentTime + 0.2);
                            }
                        } catch (e) {}
                    }
                    if (hideToastTimer) clearTimeout(hideToastTimer);
                    hideToastTimer = setTimeout(function () {
                        toast.classList.remove('show');
                    }, 7000);
                }

                function normalizeText(v) {
                    return String(v || '').replace(/\s+/g, ' ').trim();
                }

                async function refreshCommentsDom() {
                    try {
                        var url = window.location.pathname + window.location.search;
                        var sep = url.indexOf('?') === -1 ? '?' : '&';
                        var resp = await fetch(url + sep + '_comments_refresh=' + Date.now(), { credentials: 'same-origin' });
                        if (!resp.ok) return;
                        var html = await resp.text();
                        var parser = new DOMParser();
                        var doc = parser.parseFromString(html, 'text/html');

                        var currentTitle = document.querySelector('.ticket-comments-title');
                        var newTitle = doc.querySelector('.ticket-comments-title');
                        if (currentTitle && newTitle) {
                            currentTitle.textContent = newTitle.textContent || currentTitle.textContent;
                        }

                        var currentList = document.querySelector('.ticket-comentarios-list');
                        var newList = doc.querySelector('.ticket-comentarios-list');
                        var currentEmpty = document.querySelector('.ticket-sem-comentario');
                        var newEmpty = doc.querySelector('.ticket-sem-comentario');

                        if (currentList && newList) {
                            currentList.innerHTML = newList.innerHTML;
                        } else if (currentList && newEmpty) {
                            var p = document.createElement('p');
                            p.className = 'ticket-sem-comentario';
                            p.textContent = newEmpty.textContent || 'Nenhum comentário ainda.';
                            currentList.replaceWith(p);
                        } else if (currentEmpty && newList) {
                            currentEmpty.replaceWith(newList);
                        } else if (currentEmpty && newEmpty) {
                            currentEmpty.textContent = newEmpty.textContent || currentEmpty.textContent;
                        }
                    } catch (e) {}
                }

                async function checkNewComments() {
                    try {
                        var resp = await fetch((basePath || '') + '/tickets/' + ticketId + '/comentarios-live', { credentials: 'same-origin' });
                        if (!resp.ok) return;
                        var data = await resp.json();
                        var comentarios = Array.isArray(data.comentarios) ? data.comentarios : [];
                        if (!comentarios.length) return;

                        var maxId = 0;
                        comentarios.forEach(function (c) {
                            var cid = parseInt(String(c.id || 0), 10);
                            if (Number.isFinite(cid) && cid > maxId) maxId = cid;
                        });
                        if (!initialized) {
                            initialized = true;
                            lastSeenCommentId = maxId;
                            return;
                        }
                        if (maxId <= lastSeenCommentId) return;

                        var novos = comentarios.filter(function (c) {
                            var cid = parseInt(String(c.id || 0), 10);
                            return Number.isFinite(cid) && cid > lastSeenCommentId && String(c.autor || '') !== String(currentUserLogin || '');
                        });
                        lastSeenCommentId = maxId;
                        if (!novos.length) return;

                        var ultimo = novos[novos.length - 1];
                        var autorPerfil = String(ultimo.autor_perfil || '').toLowerCase();
                        var isEquipe = ['administrador', 'moderador', 'recepcao', 'manutencao'].indexOf(autorPerfil) !== -1;
                        var titulo = isEquipe ? 'Nova resposta do suporte' : 'Nova resposta do usuário';
                        var texto = normalizeText(ultimo.comentario || '');
                        if (!texto && Array.isArray(ultimo.anexos) && ultimo.anexos.length) {
                            texto = 'Enviou anexo(s).';
                        }
                        showToast(titulo, texto || 'Você recebeu uma nova mensagem.');
                        refreshCommentsDom();
                    } catch (e) {}
                }

                toastClose.addEventListener('click', function () {
                    toast.classList.remove('show');
                });
                toastSoundBtn.addEventListener('click', function () {
                    soundEnabled = !soundEnabled;
                    try {
                        localStorage.setItem(soundStorageKey, soundEnabled ? 'on' : 'off');
                    } catch (e) {}
                    renderSoundButton();
                });

                loadSoundPref();
                checkNewComments();
                setInterval(checkNewComments, 3000);
            })();
        </script>
        <?php endif; ?>
        <?php if ($ticket): ?>
        <script>
            (function () {
                var ticketId = <?= (int)($ticket['id'] ?? 0) ?>;
                if (!ticketId) return;

                var lastCommentId = 0;
                var initialized = false;

                function refreshCommentsDomFromHtml(html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');

                    var currentTitle = document.querySelector('.ticket-comments-title');
                    var newTitle = doc.querySelector('.ticket-comments-title');
                    if (currentTitle && newTitle) {
                        currentTitle.textContent = newTitle.textContent || currentTitle.textContent;
                    }

                    var currentList = document.querySelector('.ticket-comentarios-list');
                    var newList = doc.querySelector('.ticket-comentarios-list');
                    var currentEmpty = document.querySelector('.ticket-sem-comentario');
                    var newEmpty = doc.querySelector('.ticket-sem-comentario');

                    if (currentList && newList) {
                        currentList.innerHTML = newList.innerHTML;
                    } else if (currentList && newEmpty) {
                        var p = document.createElement('p');
                        p.className = 'ticket-sem-comentario';
                        p.textContent = newEmpty.textContent || 'Nenhum comentário ainda.';
                        currentList.replaceWith(p);
                    } else if (currentEmpty && newList) {
                        currentEmpty.replaceWith(newList);
                    } else if (currentEmpty && newEmpty) {
                        currentEmpty.textContent = newEmpty.textContent || currentEmpty.textContent;
                    }
                }

                async function pollTicketComments() {
                    try {
                        var resp = await fetch((<?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?> || '') + '/tickets/' + ticketId + '/comentarios-live', { credentials: 'same-origin' });
                        if (!resp.ok) return;
                        var data = await resp.json();
                        var comentarios = Array.isArray(data.comentarios) ? data.comentarios : [];
                        var maxId = 0;
                        for (var i = 0; i < comentarios.length; i++) {
                            var cid = parseInt(String(comentarios[i].id || 0), 10);
                            if (Number.isFinite(cid) && cid > maxId) maxId = cid;
                        }

                        if (!initialized) {
                            initialized = true;
                            lastCommentId = maxId;
                            return;
                        }

                        if (maxId > lastCommentId) {
                            lastCommentId = maxId;
                            var url = window.location.pathname + window.location.search;
                            var sep = url.indexOf('?') === -1 ? '?' : '&';
                            var htmlResp = await fetch(url + sep + '_refresh_comments=' + Date.now(), { credentials: 'same-origin' });
                            if (!htmlResp.ok) return;
                            var html = await htmlResp.text();
                            refreshCommentsDomFromHtml(html);
                        }
                    } catch (e) {}
                }

                pollTicketComments();
                setInterval(pollTicketComments, 3000);
            })();
        </script>
        <?php endif; ?>
    </body>
</html>

