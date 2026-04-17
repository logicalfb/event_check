<?php
// index.php — Premium Cinematic Live Display
require 'class.db.php';

$db    = new EventManager();
$token = trim($_GET['token'] ?? '');
$event = $token ? $db->getEventByToken($token) : false;

if (!$event) {
    http_response_code(404);
    die('Événement introuvable ou lien invalide.');
}

$hasImage   = !empty($event['image']);
$imagePath  = $hasImage ? 'uploads/' . htmlspecialchars($event['image']) : '';
$checkInUrl = rtrim(BASE_URL, '/') . '/form.php?token=' . urlencode($token);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live — <?php echo htmlspecialchars($event['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.5);
            --secondary: #06b6d4;
            --secondary-glow: rgba(6, 182, 212, 0.5);
            --dark-bg: #0b0f19;
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --success: #10b981;
        }

        html, body {
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-main);
            overflow: hidden;
        }

        /* ── Dynamic Ambient Background ── */
        .ambient-bg {
            position: fixed; inset: -10%; z-index: 0;
            background-size: cover;
            background-position: center;
            filter: blur(120px) saturate(150%) opacity(0.35);
            pointer-events: none;
            transition: opacity 1s ease;
        }
        .ambient-overlay {
            position: fixed; inset: 0; z-index: 0;
            background: linear-gradient(135deg, rgba(11,15,25,0.85) 0%, rgba(11,15,25,0.95) 100%);
            pointer-events: none;
        }

        /* Ambient Orbs */
        .orb {
            position: fixed; border-radius: 50%; pointer-events: none; z-index: 0;
            filter: blur(90px); opacity: 0.4;
            animation: float 15s ease-in-out infinite alternate;
        }
        .orb-1 { width: 40vw; height: 40vw; background: var(--primary); top: -10%; right: -5%; }
        .orb-2 { width: 35vw; height: 35vw; background: var(--secondary); bottom: -10%; left: -5%; animation-delay: -5s; }
        
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(-50px, 30px) scale(1.1); }
        }

        /* ── Main Layout ── */
        .layout-container {
            position: relative; z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(380px, 450px);
            height: 100vh;
            gap: 4rem;
            padding: 3rem 4rem;
            max-width: 1920px;
            margin: 0 auto;
        }

        @media (max-width: 1024px) {
            .layout-container {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto;
                height: auto;
                overflow-y: auto;
                padding: 2rem;
                gap: 2.5rem;
            }
            html, body { overflow: auto; }
        }

        /* ═══════════════════════════════════════════
           LEFT: Title / Poster
        ═══════════════════════════════════════════ */
        .presentation-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }

        /* Cinematic Poster Wrapper */
        .poster-wrapper {
            position: relative;
            border-radius: 24px;
            padding: 1px; /* for gradient border */
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%);
            box-shadow: 
                0 30px 60px rgba(0,0,0,0.4),
                0 0 80px rgba(0,0,0,0.5) inset;
            backdrop-filter: blur(10px);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0; transform: translateY(30px);
            max-height: 85vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        @keyframes slideUp {
            to { opacity: 1; transform: translateY(0); }
        }

        .poster-wrapper img {
            border-radius: 23px;
            max-width: 100%;
            max-height: calc(85vh - 2px);
            object-fit: contain;
            display: block;
            box-shadow: 0 0 40px rgba(0,0,0,0.6) inset;
        }

        /* Text Display if no poster */
        .text-hero {
            text-align: left;
            animation: fadeLeft 0.8s ease forwards;
            opacity: 0; transform: translateX(-30px);
        }
        @keyframes fadeLeft { to { opacity: 1; transform: translateX(0); } }

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 100px;
            color: var(--success);
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 24px;
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
        }
        .live-badge .dot {
            width: 8px; height: 8px;
            background: var(--success);
            border-radius: 50%;
            animation: blink 1.5s ease-in-out infinite;
        }

        .hero-title {
            font-size: clamp(3rem, 5vw, 5.5rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #fff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }

        /* ═══════════════════════════════════════════
           RIGHT: Interaction & Stats Panel
        ═══════════════════════════════════════════ */
        .interaction-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 2rem;
            height: 100%;
            animation: fadeRight 0.8s ease backwards;
            animation-delay: 0.2s;
        }
        @keyframes fadeRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        /* Glassmorphic Panel Base */
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 2.5rem;
            box-shadow: 0 24px 40px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        /* Top Edge Highlight */
        .glass-panel::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
        }

        /* When Poster exists, show Title above the QR code panel */
        .panel-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .panel-title {
            font-size: 1.8rem;
            font-weight: 800;
            line-height: 1.2;
            color: #fff;
            margin-top: 0.5rem;
        }

        /* ── QR Code Section ── */
        .qr-section {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .qr-instruction {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .qr-instruction svg { width: 20px; height: 20px; color: var(--primary); }

        .qr-frame {
            position: relative;
            padding: 1.2rem;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.1), 0 20px 40px rgba(0,0,0,0.5);
            margin-bottom: 1rem;
        }
        /* Animated Gradient Halo around QR */
        .qr-frame::after {
            content: '';
            position: absolute; inset: -4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary), var(--primary));
            background-size: 200% 200%;
            border-radius: 32px;
            z-index: -1;
            animation: gradientMove 3s linear infinite;
            filter: blur(8px);
            opacity: 0.6;
        }
        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* ── Live Counter Section ── */
        .counter-section {
            text-align: center;
            margin-top: 1rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        .counter-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .counter-display {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .counter-number {
            font-size: 6rem;
            font-weight: 900;
            line-height: 1;
            font-variant-numeric: tabular-nums;
            background: linear-gradient(to bottom, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            will-change: transform;
        }
        .counter-number.bump {
            transform: scale(1.15) translateY(-5px);
            filter: drop-shadow(0 0 20px var(--success));
        }

        .pulse-ring {
            position: relative;
            width: 24px; height: 24px;
        }
        .pulse-ring::before {
            content: ''; position: absolute; inset: 0;
            background: var(--success);
            border-radius: 50%;
        }
        .pulse-ring::after {
            content: ''; position: absolute; inset: -10px;
            border: 2px solid var(--success);
            border-radius: 50%;
            animation: radarPulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes radarPulse {
            0% { transform: scale(0.5); opacity: 0; }
            50% { opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        @keyframes blink {
            50% { opacity: 0.3; }
        }

        /* ── Dynamic Toasts (Recent Checkins) ── */
        .toast-container {
            position: fixed;
            bottom: 2rem; right: 2rem;
            display: flex;
            flex-direction: column-reverse; /* Newest at bottom */
            gap: 1rem;
            z-index: 100;
            pointer-events: none;
        }

        .toast {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.1);
            border-left: 4px solid var(--success);
            padding: 1rem 1.25rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            min-width: 280px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            
            /* Entry Animation */
            transform: translateX(120%) scale(0.9);
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .toast.show {
            transform: translateX(0) scale(1);
            opacity: 1;
        }

        .toast-icon {
            width: 40px; height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--success), #059669);
            display: flex; justify-content: center; align-items: center;
            font-size: 1.2rem;
        }
        .toast-content { flex: 1; }
        .toast-sub {
            font-size: 0.7rem; font-weight: 600; color: var(--text-muted);
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .toast-title {
            font-size: 1rem; font-weight: 700; color: #fff; margin-top: 2px;
        }

    </style>
</head>
<body>

<?php if ($hasImage): ?>
    <div class="ambient-bg" style="background-image: url('<?php echo $imagePath; ?>');"></div>
<?php endif; ?>
<div class="ambient-overlay"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="layout-container">
    
    <!-- LEFT PANEL: Image or Large Text -->
    <div class="presentation-panel">
        <?php if ($hasImage): ?>
            <div class="poster-wrapper">
                <img src="<?php echo $imagePath; ?>" alt="Event Poster">
            </div>
        <?php else: ?>
            <div class="text-hero">
                <div class="live-badge">
                    <span class="dot"></span> Événement en cours
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($event['name']); ?></h1>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT PANEL: Stats & Interaction -->
    <div class="interaction-panel">
        
        <div class="glass-panel">
            <?php if ($hasImage): ?>
                <div class="panel-header">
                    <div class="live-badge" style="margin-bottom: 0.5rem;">
                        <span class="dot"></span> En Cours
                    </div>
                    <h2 class="panel-title"><?php echo htmlspecialchars($event['name']); ?></h2>
                </div>
            <?php endif; ?>

            <div class="qr-section">
                <div class="qr-instruction">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    Scannez pour rejoindre
                </div>
                <div class="qr-frame">
                    <div id="qrcode"></div>
                </div>
            </div>

            <div class="counter-section">
                <div class="counter-label">Participants Connectés</div>
                <div class="counter-display">
                    <div class="pulse-ring"></div>
                    <div class="counter-number" id="live-counter">0</div>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<script>
    const EVENT_TOKEN = "<?php echo htmlspecialchars($token, ENT_QUOTES); ?>";
    const CHECK_IN_URL = "<?php echo htmlspecialchars($checkInUrl, ENT_QUOTES); ?>";

    // Initialize QR Code
    new QRCode(document.getElementById('qrcode'), {
        text: CHECK_IN_URL,
        width: 240,
        height: 240,
        colorDark: "#111827",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    // Real-Time Polling Logic
    let lastPersonneId = 0;
    let isFirstLoad = true;

    function createToast(fullName) {
        const container = document.getElementById('toast-container');
        
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.innerHTML = `
            <div class="toast-icon">👋</div>
            <div class="toast-content">
                <div class="toast-sub">Nouveau Participant</div>
                <div class="toast-title">${fullName}</div>
            </div>
        `;
        
        container.appendChild(toast);
        
        // Trigger reflow & animate in
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Remove after delay
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 500);
        }, 5000);
    }

    function fetchUpdates() {
        const cacheBuster = Date.now();
        fetch(`api.php?token=${EVENT_TOKEN}&_t=${cacheBuster}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) return;

                // Update Counter
                const counterEl = document.getElementById('live-counter');
                const oldCount = parseInt(counterEl.textContent, 10) || 0;
                counterEl.textContent = data.count;

                // Animate if count increased
                if (data.count > oldCount && !isFirstLoad) {
                    counterEl.classList.remove('bump');
                    void counterEl.offsetWidth; // trigger reflow
                    counterEl.classList.add('bump');
                }

                // Check for new arrivals
                if (data.dernier_arrive) {
                    const p = data.dernier_arrive;
                    const newId = parseInt(p.id, 10);

                    if (isFirstLoad) {
                        lastPersonneId = newId;
                        isFirstLoad = false;
                        return;
                    }

                    // Show toast if ID is newer than what we saw last
                    if (newId > lastPersonneId) {
                        createToast(`${p.prenom} ${p.nom}`);
                        lastPersonneId = newId;
                    }
                } else if (isFirstLoad) {
                    isFirstLoad = false;
                }
            })
            .catch(err => console.error('Erreur API Live:', err));
    }

    fetchUpdates();
    setInterval(fetchUpdates, 2000);

</script>
</body>
</html>