<button type="button" class="menu-toggle-mobile" id="menu-toggle-mobile" aria-label="Abrir menu" title="Menu">
    <i class="fa-solid fa-bars"></i>
</button>
<div class="nav-overlay" id="nav-overlay" aria-hidden="true"></div>
<nav id="main-nav">
    <?php include __DIR__ . '/menu.php'; ?>
</nav>
<style>
.global-comment-toast-stack {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 9996;
    display: flex;
    flex-direction: column-reverse;
    gap: .65rem;
    pointer-events: none;
}
.global-comment-toast {
    min-width: 280px;
    max-width: min(92vw, 370px);
    background: linear-gradient(180deg, #0f172a 0%, #111827 100%);
    color: #fff;
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 14px;
    padding: .7rem .78rem;
    box-shadow: 0 18px 34px rgba(2, 6, 23, .42);
    display: flex;
    gap: .5rem;
    align-items: flex-start;
    transform: translateY(12px) scale(.98);
    opacity: 0;
    transition: transform .18s ease, opacity .18s ease;
    cursor: pointer;
    pointer-events: auto;
}
.global-comment-toast.show { display:flex; transform:translateY(0) scale(1); opacity:1; }
.global-comment-toast.attention {
    animation: toast-wiggle 0.9s ease-in-out 0s 3;
}
@keyframes toast-wiggle {
    0% { transform: translateY(0) rotate(0deg); }
    15% { transform: translateY(0) rotate(-2deg); }
    30% { transform: translateY(0) rotate(2deg); }
    45% { transform: translateY(0) rotate(-1.5deg); }
    60% { transform: translateY(0) rotate(1.5deg); }
    75% { transform: translateY(0) rotate(-1deg); }
    100% { transform: translateY(0) rotate(0deg); }
}
.global-comment-toast-avatar { width:34px; height:34px; min-width:34px; border-radius:999px; background:linear-gradient(145deg,#25d366 0%,#16a34a 100%); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:.9rem; }
.global-comment-toast-content { min-width:0; flex:1; }
.global-comment-toast-content strong { display:block; font-size:.8rem; margin-bottom:.14rem; }
.global-comment-toast-time { display:block; font-size:.66rem; color:#94a3b8; margin-bottom:.12rem; }
.global-comment-toast-content p { margin:0; font-size:.74rem; line-height:1.35; color:#e5e7eb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.global-comment-toast-actions { display:flex; flex-direction:column; align-items:center; gap:.3rem; }
.global-comment-toast-btn { border:none; background:transparent; color:#cbd5e1; cursor:pointer; font-size:1rem; line-height:1; padding:0; }
</style>
<div id="global-comment-toast-stack" class="global-comment-toast-stack" aria-live="polite"></div>
<script>
(function() {
    var btn = document.getElementById('menu-toggle-mobile');
    var overlay = document.getElementById('nav-overlay');
    var nav = document.getElementById('main-nav');
    function toggleMenu() {
        document.body.classList.toggle('menu-open');
        var icon = btn && btn.querySelector('i');
        if (icon) icon.className = document.body.classList.contains('menu-open') ? 'fa-solid fa-times' : 'fa-solid fa-bars';
        if (overlay) overlay.setAttribute('aria-hidden', document.body.classList.contains('menu-open') ? 'false' : 'true');
    }
    function closeMenu() { document.body.classList.remove('menu-open'); if (btn) { var i = btn.querySelector('i'); if (i) i.className = 'fa-solid fa-bars'; } if (overlay) overlay.setAttribute('aria-hidden', 'true'); }
    if (btn) btn.addEventListener('click', toggleMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    if (nav) nav.querySelectorAll('a').forEach(function(a) { a.addEventListener('click', closeMenu); });

    var toastStack = document.getElementById('global-comment-toast-stack');
    var soundKey = 'bigtickets_comment_toast_sound';
    var soundEnabled = true;
    var lastId = 0;
    var initialized = false;
    var basePath = <?= json_encode($basePath ?? '', JSON_UNESCAPED_UNICODE) ?>;
    var toastDurationMs = 5 * 60 * 1000; // 5 minutos
    var maxToasts = 8;

    function soundIconHtml() {
        return soundEnabled ? '<i class="fa-solid fa-volume-high"></i>' : '<i class="fa-solid fa-volume-xmark"></i>';
    }
    function renderSoundButtons() {
        if (!toastStack) return;
        toastStack.querySelectorAll('[data-toast-sound="1"]').forEach(function(btnEl) {
            btnEl.innerHTML = soundIconHtml();
            btnEl.title = soundEnabled ? 'Som ligado' : 'Som desligado';
        });
    }
    try {
        var s = localStorage.getItem(soundKey);
        if (s === 'off') soundEnabled = false;
    } catch (e) {}
    renderSoundButtons();

    function beep() {
        if (!soundEnabled) return;
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = new Ctx();
            if (ctx.state === 'suspended' && typeof ctx.resume === 'function') {
                ctx.resume();
            }
            function playTone(startAt, freq, peakGain, duration) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, startAt);
                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(peakGain, startAt + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, startAt + duration);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(startAt);
                osc.stop(startAt + duration + 0.03);
            }
            // Chime de notificação em 3 notas (mais agradável)
            playTone(ctx.currentTime, 784, 0.14, 0.20);      // G5
            playTone(ctx.currentTime + 0.15, 988, 0.13, 0.20); // B5
            playTone(ctx.currentTime + 0.30, 1175, 0.15, 0.28); // D6
        } catch (e) {}
    }

    function removeToastCard(card) {
        if (!card || !card.parentNode) return;
        card.classList.remove('show');
        setTimeout(function() {
            if (card.parentNode) card.parentNode.removeChild(card);
        }, 220);
    }

    function showToast(title, text, ticketId) {
        if (!toastStack) return;
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        var timeLabel = hh + ':' + mm;
        var card = document.createElement('div');
        card.className = 'global-comment-toast';
        card.setAttribute('role', 'status');
        card.setAttribute('data-ticket-id', String(ticketId || 0));
        card.innerHTML =
            '<span class="global-comment-toast-avatar"><i class="fa-solid fa-message"></i></span>'
            + '<div class="global-comment-toast-content">'
            + '<strong>' + title.replace(/</g, '&lt;') + '</strong>'
            + '<span class="global-comment-toast-time">' + timeLabel + '</span>'
            + '<p>' + text.replace(/</g, '&lt;') + '</p>'
            + '</div>'
            + '<div class="global-comment-toast-actions">'
            + '<button type="button" class="global-comment-toast-btn" data-toast-sound="1" title="Som ligado" aria-label="Som">' + soundIconHtml() + '</button>'
            + '<button type="button" class="global-comment-toast-btn" data-toast-close="1" aria-label="Fechar">&times;</button>'
            + '</div>';
        toastStack.appendChild(card);
        requestAnimationFrame(function() {
            card.classList.add('show');
            card.classList.add('attention');
        });
        beep();
        setTimeout(function() { removeToastCard(card); }, toastDurationMs);

        // Limita quantidade na pilha
        var cards = toastStack.querySelectorAll('.global-comment-toast');
        if (cards.length > maxToasts) {
            removeToastCard(cards[0]);
        }
    }

    async function pollCommentNotifications() {
        try {
            var resp = await fetch((basePath || '') + '/tickets/api/comment-notifications?lastId=' + encodeURIComponent(String(lastId)), { credentials: 'same-origin' });
            if (!resp.ok) return;
            var data = await resp.json();
            if (!data || typeof data !== 'object') return;
            if (!initialized) {
                initialized = true;
                if (data.latestId) lastId = Number(data.latestId) || 0;
                return;
            }
            if (!data.hasNew || !data.notification) return;
            var n = data.notification;
            lastId = Number(data.latestId || n.comment_id || lastId) || lastId;
            var ticketId = Number(n.ticket_id || 0) || 0;
            var isEquipe = ['administrador', 'moderador', 'recepcao', 'manutencao'].indexOf(String(n.autor_perfil || '').toLowerCase()) !== -1;
            var autorNome = String(n.autor_nome || n.autor || 'Usuário');
            var title = 'Ticket #' + String(n.numero_chamado || n.ticket_id || '') + ' - ' + (isEquipe ? 'Suporte respondeu' : 'Usuário respondeu');
            var txt = 'Por ' + autorNome + ' - ' + (n.texto || 'Nova mensagem');
            showToast(title, txt, ticketId);
        } catch (e) {}
    }

    if (toastStack) {
        toastStack.addEventListener('click', function(e) {
            var target = e.target;
            if (!(target instanceof Element)) return;
            var closeBtn = target.closest('[data-toast-close="1"]');
            if (closeBtn) {
                e.stopPropagation();
                var cardClose = closeBtn.closest('.global-comment-toast');
                removeToastCard(cardClose);
                return;
            }
            var soundBtn = target.closest('[data-toast-sound="1"]');
            if (soundBtn) {
                e.stopPropagation();
                soundEnabled = !soundEnabled;
                try { localStorage.setItem(soundKey, soundEnabled ? 'on' : 'off'); } catch (e) {}
                renderSoundButtons();
                return;
            }
            var card = target.closest('.global-comment-toast');
            if (!card) return;
            var tid = Number(card.getAttribute('data-ticket-id') || '0');
            if (!tid) return;
            window.location.href = (basePath || '') + '/tickets/' + tid;
        });
    }
    pollCommentNotifications();
    setInterval(pollCommentNotifications, 3000);
})();
</script>
