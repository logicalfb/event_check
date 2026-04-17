<?php
// affiche.php — Page publique de l'affiche d'un événement.
// Affiche : image de l'événement à gauche + QR code & compteur live à droite.
require 'class.db.php';

$db    = new EventManager();
$token = trim($_GET['token'] ?? '');
$event = $token ? $db->getEventByToken($token) : false;

if (!$event) {
    http_response_code(404);
    die('Événement introuvable.');
}

$hasImage   = !empty($event['image']);
$checkInUrl = rtrim(BASE_URL, '/') . '/form.php?token=' . urlencode($token);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiche — <?php echo htmlspecialchars($event['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:   #6366f1;
            --accent2:  #818cf8;
            --green:    #22c55e;
            --bg-dark:  #0d1117;
            --surface:  rgba(255,255,255,.06);
            --border:   rgba(255,255,255,.10);
        }

        html, body {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: var(--bg-dark);
            color: #fff;
            overflow: hidden;
        }

        /* ── Fond animé ── */
        .bg-anim {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, #1e1b4b 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, #052e16 0%, transparent 60%),
                var(--bg-dark);
        }
        .bg-anim::before, .bg-anim::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }
        .bg-anim::before {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(99,102,241,.15), transparent 70%);
            top: -100px; left: -100px;
        }
        .bg-anim::after {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(34,197,94,.1), transparent 70%);
            bottom: -80px; right: -80px;
            animation-delay: -4s;
        }
        @keyframes float {
            0%,100% { transform: translate(0,0); }
            50%      { transform: translate(30px, 20px); }
        }

        /* ── Mise en page : 2 colonnes ── */
        .layout {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            height: 100vh;
        }

        @media (max-width: 800px) {
            .layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
                overflow-y: auto;
            }
            html, body { overflow: auto; }
        }

        /* ═══════════════════════════════════════════
           PANNEAU GAUCHE — Affiche de l'événement
        ═══════════════════════════════════════════ */
        .panel-affiche {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem;
            border-right: 1px solid var(--border);
            overflow: hidden;
        }

        /* Quand une image est disponible */
        .affiche-frame {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow:
                0 0 80px rgba(99,102,241,.25),
                0 24px 60px rgba(0,0,0,.55);
            border: 1px solid rgba(255,255,255,.12);
            max-width: 100%;
            max-height: 90vh;
            animation: fadeUp .6s ease both;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .affiche-frame img {
            display: block;
            max-width: 100%;
            max-height: 85vh;
            width: auto;
            height: auto;
            object-fit: contain;
            transition: transform .6s ease;
        }
        .affiche-frame:hover img { transform: scale(1.02); }

        /* Overlay bas avec le titre (s'affiche sur l'image) */
        .affiche-overlay {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,.75));
            padding: 2rem 1.5rem 1.5rem;
        }
        .affiche-title {
            font-size: clamp(1rem, 2.5vw, 1.6rem);
            font-weight: 800;
            line-height: 1.2;
            color: #fff;
            text-shadow: 0 2px 8px rgba(0,0,0,.6);
        }

        /* Quand il n'y a pas d'image */
        .affiche-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
        }
        .affiche-placeholder .icon-evt { font-size: 5rem; margin-bottom: 1.5rem; opacity: .5; }
        .affiche-placeholder h1 {
            font-size: clamp(2rem, 4vw, 3.5rem);
            font-weight: 900;
            line-height: 1.15;
            background: linear-gradient(135deg, #fff 0%, var(--accent2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ═══════════════════════════════════════════
           PANNEAU DROIT — QR Code & Live
        ═══════════════════════════════════════════ */
        .panel-live {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2rem;
            padding: 2.5rem;
        }

        /* Badge événement */
        .event-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(99,102,241,.18);
            border: 1px solid rgba(99,102,241,.35);
            color: var(--accent2);
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            padding: .35rem .9rem;
            border-radius: 999px;
        }

        /* Titre (affiché uniquement si pas d'image) */
        .panel-event-name {
            font-size: clamp(1.1rem, 2.5vw, 1.75rem);
            font-weight: 800;
            text-align: center;
            line-height: 1.2;
            background: linear-gradient(135deg, #fff 0%, var(--accent2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* QR Code */
        .qr-block { text-align: center; }
        .qr-wrapper {
            background: #fff;
            padding: 1.1rem;
            border-radius: 22px;
            display: inline-block;
            box-shadow:
                0 0 60px rgba(99,102,241,.35),
                0 16px 40px rgba(0,0,0,.45);
            margin-bottom: 1.25rem;
            position: relative;
        }
        /* Halo animé autour du QR */
        .qr-wrapper::after {
            content: '';
            position: absolute;
            inset: -6px;
            border-radius: 26px;
            background: linear-gradient(135deg, var(--accent), var(--green), var(--accent));
            background-size: 200% 200%;
            animation: halo 3s linear infinite;
            z-index: -1;
            opacity: .45;
        }
        @keyframes halo {
            0%   { background-position: 0% 50%; }
            50%  { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .scan-arrow {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .25rem;
            margin-bottom: .75rem;
            animation: bounce 1.6s ease-in-out infinite;
        }
        @keyframes bounce {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(8px); }
        }
        .scan-arrow span {
            font-size: .75rem;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--accent2);
            background: rgba(99,102,241,.12);
            border: 1px solid rgba(99,102,241,.25);
            padding: .3rem .8rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .scan-arrow svg {
            width: 24px; height: 24px;
            color: var(--green);
            filter: drop-shadow(0 0 6px var(--green));
        }

        .form-url {
            font-size: .68rem;
            color: rgba(255,255,255,.35);
            word-break: break-all;
            text-align: center;
            max-width: 280px;
            margin: 0 auto;
        }

        /* Compteur live */
        .live-block {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 1.5rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            backdrop-filter: blur(12px);
            width: 100%;
            max-width: 320px;
        }
        .live-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 14px var(--green);
            flex-shrink: 0;
            animation: blink 1.4s ease-in-out infinite;
        }
        @keyframes blink {
            0%,100% { opacity: 1; }
            50%      { opacity: .2; }
        }
        .live-info { flex: 1; }
        .live-label {
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255,255,255,.4);
            margin-bottom: .2rem;
        }
        .live-counter {
            font-size: 3.75rem;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            color: var(--green);
            line-height: 1;
            transition: transform .2s;
        }
        .live-counter.bump { transform: scale(1.18); }

        /* Dernier arrivé */
        .last-card {
            background: rgba(34,197,94,.07);
            border: 1px solid rgba(34,197,94,.18);
            border-radius: 18px;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            gap: .9rem;
            width: 100%;
            max-width: 320px;
            min-height: 68px;
        }
        .last-avatar {
            width: 42px; height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--green));
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        .last-sub  { font-size: .65rem; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .1em; }
        .last-name { font-size: .95rem; font-weight: 700; margin-top: .1rem; }
        .last-name.empty { color: rgba(255,255,255,.3); font-style: italic; font-size: .85rem; }

        /* ── Notification pop-up ── */
        .notif-popup {
            position: fixed;
            bottom: 1.5rem; right: 1.5rem;
            background: rgba(17,24,39,.92);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(34,197,94,.3);
            border-left: 4px solid var(--green);
            border-radius: 18px;
            padding: .9rem 1.5rem;
            display: flex; align-items: center; gap: .9rem;
            max-width: 300px;
            z-index: 500;
            transform: translateY(120px);
            opacity: 0;
            transition: transform .45s cubic-bezier(.34,1.56,.64,1), opacity .35s;
        }
        .notif-popup.show { transform: translateY(0); opacity: 1; }
        .notif-icon { font-size: 1.8rem; }
        .notif-sub  { font-size: .65rem; color: rgba(255,255,255,.4); text-transform: uppercase; letter-spacing: .1em; }
        .notif-name { font-size: 1rem; font-weight: 700; margin-top: .1rem; }
    </style>
</head>
<body>

<div class="bg-anim"></div>

<div class="layout">

    <!-- ══ PANNEAU GAUCHE : Affiche ══ -->
    <div class="panel-affiche">
        <?php if ($hasImage): ?>
            <div class="affiche-frame">
                <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>"
                     alt="<?php echo htmlspecialchars($event['name']); ?>">
                <div class="affiche-overlay">
                    <div class="affiche-title"><?php echo htmlspecialchars($event['name']); ?></div>
                </div>
            </div>
        <?php else: ?>
            <!-- Pas d'image : afficher le titre en grand -->
            <div class="affiche-placeholder">
                <div class="icon-evt">🎫</div>
                <h1><?php echo htmlspecialchars($event['name']); ?></h1>
            </div>
        <?php endif; ?>
    </div>

    <!-- ══ PANNEAU DROIT : QR + Live ══ -->
    <div class="panel-live">

        <span class="event-badge">🎯 Événement en direct</span>

        <?php if (!$hasImage): ?>
            <!-- Titre seulement si pas d'image (déjà affiché à gauche sinon) -->
        <?php endif; ?>

        <!-- QR Code -->
        <div class="qr-block">
            <div class="scan-arrow">
                <span>Scannez pour vous enregistrer</span>
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </div>
            <div class="qr-wrapper">
                <div id="qrcode"></div>
            </div>
            <div class="form-url" id="form-url-display"></div>
        </div>

        <!-- Compteur live -->
        <div class="live-block">
            <div class="live-dot"></div>
            <div class="live-info">
                <div class="live-label">Participants enregistrés</div>
                <div class="live-counter" id="live-counter">0</div>
            </div>
        </div>

        <!-- Dernier arrivé -->
        <div class="last-card" id="last-card">
            <div class="last-avatar">👤</div>
            <div>
                <div class="last-sub">Dernier arrivé</div>
                <div class="last-name empty" id="last-name">En attente…</div>
            </div>
        </div>

    </div><!-- /panel-live -->
</div><!-- /layout -->

<!-- Notification pop-up -->
<div class="notif-popup" id="notif-popup">
    <div class="notif-icon">👋</div>
    <div>
        <div class="notif-sub">Vient d'arriver</div>
        <div class="notif-name" id="notif-name">—</div>
    </div>
</div>

<script>
    const EVENT_TOKEN = "<?php echo htmlspecialchars($token, ENT_QUOTES); ?>";
    const CHECK_IN_URL = "<?php echo htmlspecialchars($checkInUrl, ENT_QUOTES); ?>";

    // ── Génération du QR Code ──────────────────────────────────────────────────
    document.getElementById('form-url-display').textContent = CHECK_IN_URL;

    new QRCode(document.getElementById('qrcode'), {
        text:         CHECK_IN_URL,
        width:        220,
        height:       220,
        colorDark:    '#111827',
        colorLight:   '#ffffff',
        correctLevel: QRCode.CorrectLevel.H
    });

    // ── Suivi en temps réel ────────────────────────────────────────────────────
    let lastPersonneId = 0;
    let notifTimer     = null;

    function fetchUpdates() {
        fetch(`api.php?token=${EVENT_TOKEN}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) return;

                // Mise à jour du compteur
                const counterEl = document.getElementById('live-counter');
                const oldCount  = parseInt(counterEl.textContent) || 0;
                counterEl.textContent = data.count;

                // Animation bump si nouveau participant
                if (data.count > oldCount) {
                    counterEl.classList.add('bump');
                    setTimeout(() => counterEl.classList.remove('bump'), 300);
                }

                // Dernier arrivé
                if (data.dernier_arrive) {
                    const p     = data.dernier_arrive;
                    const newId = parseInt(p.id);
                    const nameEl = document.getElementById('last-name');

                    nameEl.textContent = `${p.prenom} ${p.nom}`;
                    nameEl.classList.remove('empty');

                    // Notification pop-up uniquement si nouvelle arrivée
                    if (newId > lastPersonneId && lastPersonneId !== 0) {
                        showNotification(`${p.prenom} ${p.nom}`);
                    }
                    lastPersonneId = newId;
                }
            })
            .catch(err => console.error('Erreur API:', err));
    }

    function showNotification(fullName) {
        const popup = document.getElementById('notif-popup');
        document.getElementById('notif-name').textContent = fullName;

        if (notifTimer) clearTimeout(notifTimer);
        popup.classList.add('show');
        notifTimer = setTimeout(() => popup.classList.remove('show'), 5000);
    }

    // Première requête immédiate, puis toutes les 2 secondes
    fetchUpdates();
    setInterval(fetchUpdates, 2000);
</script>
</body>
</html>
