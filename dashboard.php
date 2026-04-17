<?php
// dashboard.php — Tableau de bord de gestion des événements
require 'class.db.php';

$db = new EventManager();

// ── Créer un nouvel événement ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $name = trim($_POST['event_name'] ?? '');
    if ($name !== '') {
        $imageName = null;

        // Traitement de l'image uploadée
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $tmpPath  = $_FILES['event_image']['tmp_name'];
            $origName = $_FILES['event_image']['name'];
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            // Vérifier l'extension (jpg, jpeg, png uniquement)
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                // Vérifier le type MIME réel
                $mime = mime_content_type($tmpPath);
                if (in_array($mime, ['image/jpeg', 'image/png'])) {
                    $imageName = 'evt_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    move_uploaded_file($tmpPath, __DIR__ . '/uploads/' . $imageName);
                }
            }
        }

        $db->createEvent($name, $imageName);
    }
    header('Location: dashboard.php');
    exit;
}

// ── Exporter la liste des participants en CSV ─────────────────────────────────
if (isset($_GET['export_event'])) {
    $eventId      = (int) $_GET['export_event']; // cast int pour la sécurité
    $participants = $db->getParticipants($eventId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="presence_' . $eventId . '.csv"');

    $output = fopen('php://output', 'w');
    // BOM UTF-8 pour Excel
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Nom', 'Prénom', 'Heure d\'arrivée']);
    foreach ($participants as $p) {
        fputcsv($output, [$p['id'], $p['nom'], $p['prenom'], $p['check_in_time']]);
    }
    fclose($output);
    exit;
}

$events = $db->getEvents();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord — Gestion des Événements</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d1117;
            --surface:   #161b22;
            --surface2:  #1f2937;
            --border:    #30363d;
            --accent:    #6366f1;
            --accent2:   #818cf8;
            --green:     #22c55e;
            --red:       #ef4444;
            --text:      #e6edf3;
            --muted:     #8b949e;
            --radius:    14px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Header ── */
        header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-logo {
            display: flex;
            align-items: center;
            gap: .75rem;
            font-weight: 700;
            font-size: 1.2rem;
        }
        .header-logo span { font-size: 1.5rem; }
        .header-nav a {
            color: var(--muted);
            text-decoration: none;
            font-size: .875rem;
            padding: .4rem .9rem;
            border-radius: 8px;
            transition: background .2s, color .2s;
        }
        .header-nav a:hover { background: var(--surface2); color: var(--text); }

        /* ── Main layout ── */
        main { max-width: 1100px; margin: 0 auto; padding: 2.5rem 1.5rem; }

        /* ── Page title ── */
        .page-title { font-size: 1.75rem; font-weight: 800; margin-bottom: .5rem; }
        .page-sub   { color: var(--muted); font-size: .9rem; margin-bottom: 2rem; }

        /* ── Stats ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2.5rem;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
        }
        .stat-label { font-size: .75rem; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .5rem; }
        .stat-value { font-size: 2rem; font-weight: 800; color: var(--accent2); }

        /* ── Create form ── */
        .create-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2.5rem;
        }
        .create-card h2 { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; }
        .create-form { display: flex; gap: .75rem; flex-wrap: wrap; }
        .create-form input[type="text"] {
            flex: 1;
            min-width: 220px;
            padding: .65rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: .9rem;
            font-family: inherit;
            outline: none;
            transition: border-color .2s;
        }
        .create-form input[type="text"]::placeholder { color: var(--muted); }
        .create-form input[type="text"]:focus { border-color: var(--accent); }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .65rem 1.2rem;
            border-radius: 10px;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .2s, transform .1s;
        }
        .btn:active { transform: scale(.97); }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { opacity: .88; }
        .btn-green   { background: #166534; color: #4ade80; }
        .btn-green:hover { background: #14532d; }
        .btn-indigo  { background: #312e81; color: var(--accent2); }
        .btn-indigo:hover { background: #1e1b4b; }
        .btn-yellow  { background: #ca8a04; color: #fff; }
        .btn-yellow:hover { background: #a16207; }
        .btn-gray    { background: var(--surface2); color: var(--muted); }
        .btn-gray:hover { background: var(--border); color: var(--text); }

        /* ── Events table ── */
        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }
        .table-card table { width: 100%; border-collapse: collapse; }
        .table-card th {
            background: var(--surface2);
            padding: .85rem 1.25rem;
            text-align: left;
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--muted);
            border-bottom: 1px solid var(--border);
        }
        .table-card td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            font-size: .875rem;
            vertical-align: middle;
        }
        .table-card tr:last-child td { border-bottom: none; }
        .table-card tbody tr { transition: background .15s; }
        .table-card tbody tr:hover { background: rgba(99,102,241,.04); }

        .event-name { font-weight: 600; color: var(--text); }
        .event-date { color: var(--muted); font-size: .8rem; }

        .event-thumb {
            width: 44px; height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid var(--border);
            vertical-align: middle;
            margin-right: .75rem;
        }
        .event-no-img {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: var(--surface2);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            vertical-align: middle;
            margin-right: .75rem;
            flex-shrink: 0;
        }
        .event-cell { display: flex; align-items: center; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .25rem .65rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 600;
        }
        .badge-green { background: #052e16; color: #4ade80; }

        .actions { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--muted);
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.65);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity .25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            width: min(480px, 90vw);
            transform: translateY(30px) scale(.97);
            transition: transform .25s;
        }
        .modal-overlay.open .modal-box { transform: translateY(0) scale(1); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 700; }
        .modal-close {
            background: var(--surface2); border: none; cursor: pointer;
            color: var(--muted); border-radius: 8px; padding: .35rem .55rem;
            font-size: 1rem; transition: background .2s;
        }
        .modal-close:hover { background: var(--border); color: var(--text); }

        .modal-input {
            width: 100%;
            padding: .65rem 1rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text);
            font-size: .9rem;
            font-family: inherit;
            outline: none;
            margin-bottom: 1rem;
            transition: border-color .2s;
        }
        .modal-input:focus { border-color: var(--accent); }

        .modal-label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .5rem;
        }

        .file-upload-zone {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            margin-bottom: 1rem;
            position: relative;
        }
        .file-upload-zone:hover { border-color: var(--accent); background: rgba(99,102,241,.05); }
        .file-upload-zone input[type="file"] {
            position: absolute; inset: 0;
            opacity: 0; cursor: pointer;
        }
        .file-upload-zone .icon { font-size: 1.5rem; margin-bottom: .4rem; }
        .file-upload-zone .text { font-size: .8rem; color: var(--muted); }
        .file-upload-zone .text strong { color: var(--accent2); }

        .img-preview {
            max-width: 100%;
            max-height: 140px;
            border-radius: 10px;
            margin-bottom: 1rem;
            display: none;
            border: 1px solid var(--border);
        }
    </style>
</head>
<body>

<header>
    <div class="header-logo">
        <span>🎫</span>
        GestionÉvénements
    </div>
    <nav class="header-nav"></nav>
</header>

<main>
    <h1 class="page-title">Tableau de bord</h1>
    <p class="page-sub">Gérez vos événements et suivez les présences en temps réel.</p>

    <!-- Statistiques rapides -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Événements</div>
            <div class="stat-value"><?php echo count($events); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Total Participants</div>
            <div class="stat-value">
                <?php echo array_sum(array_column($events, 'nb_participants')); ?>
            </div>
        </div>
    </div>

    <!-- Créer un événement (bouton ouvre modal) -->
    <div class="create-card">
        <h2>➕ Nouvel Événement</h2>
        <div class="create-form">
            <input type="text" id="quick-name"
                   placeholder="Nom de l'événement (ex : Congrès Médical 2026)"
                   onkeydown="if(event.key==='Enter') openCreateModal()">
            <button class="btn btn-primary" onclick="openCreateModal()">
                Créer l'événement
            </button>
        </div>
    </div>

    <!-- Liste des événements -->
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Nom de l'événement</th>
                    <th>Date de création</th>
                    <th>Participants</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                <tr>
                    <td colspan="4">
                        <div class="empty-state">
                            <div class="icon">📋</div>
                            <p>Aucun événement créé pour l'instant.</p>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($events as $evt): ?>
                <tr>
                    <td>
                        <div class="event-cell">
                            <?php if (!empty($evt['image'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($evt['image']); ?>" alt="" class="event-thumb">
                            <?php else: ?>
                                <span class="event-no-img">🎫</span>
                            <?php endif; ?>
                            <div class="event-name"><?php echo htmlspecialchars($evt['name']); ?></div>
                        </div>
                    </td>
                    <td>
                        <div class="event-date">
                            <?php
                            $date = new DateTime($evt['created_at']);
                            echo $date->format('d/m/Y à H:i');
                            ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-green">
                            ✅ <?php echo (int) $evt['nb_participants']; ?> présents
                        </span>
                    </td>
                    <td>
                        <div class="actions">
                            <!-- Afficher l'affiche de l'événement (QR + live) -->
                            <a href="affiche.php?token=<?php echo urlencode($evt['token']); ?>"
                               target="_blank"
                               class="btn btn-indigo"
                               title="Afficher l'affiche de l'événement">
                                🖼️ To Display
                            </a>
                            <!-- Ouvrir la page en direct (sans affiche) -->
                            <a href="index.php?token=<?php echo urlencode($evt['token']); ?>"
                               target="_blank"
                               class="btn btn-green"
                               title="Suivi en direct">
                                📡 Test
                            </a>
                            <!-- Générer PDF -->
                            <a href="gen_pdf.php?event_id=<?php echo (int) $evt['id']; ?>"
                               target="_blank"
                               class="btn btn-yellow"
                               title="Générer les attestations PDF">
                                📄 PDF
                            </a>
                            <!-- Exporter CSV -->
                            <a href="dashboard.php?export_event=<?php echo (int) $evt['id']; ?>"
                               class="btn btn-gray"
                               title="Exporter la liste en CSV">
                                ⬇️ CSV
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Modal de création -->
<div class="modal-overlay" id="create-modal" onclick="closeModalOutside(event)">
    <div class="modal-box">
        <div class="modal-header">
            <h3>🎉 Créer un nouvel événement</h3>
            <button class="modal-close" onclick="closeCreateModal()">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="return validateCreate()">
            <label class="modal-label">Nom de l'événement</label>
            <input type="text" class="modal-input" name="event_name" id="modal-name"
                   placeholder="Ex : Congrès Médical 2026" required autocomplete="off">

            <label class="modal-label">Affiche de l'événement (optionnel)</label>
            <div class="file-upload-zone" id="drop-zone">
                <div class="icon">🖼️</div>
                <div class="text">Cliquez ou glissez une image <strong>JPG / PNG</strong></div>
                <input type="file" name="event_image" id="modal-image" accept=".jpg,.jpeg,.png">
            </div>
            <img id="img-preview" class="img-preview" alt="Aperçu">

            <button type="submit" name="create_event" class="btn btn-primary" style="width:100%; justify-content:center">
                ✅ Créer l'événement
            </button>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        const name = document.getElementById('quick-name').value.trim();
        document.getElementById('modal-name').value = name;
        document.getElementById('create-modal').classList.add('open');
        setTimeout(() => document.getElementById('modal-name').focus(), 100);
    }

    function closeCreateModal() {
        document.getElementById('create-modal').classList.remove('open');
        // Réinitialiser l'aperçu
        document.getElementById('img-preview').style.display = 'none';
        document.getElementById('modal-image').value = '';
    }

    function closeModalOutside(e) {
        if (e.target === document.getElementById('create-modal')) {
            closeCreateModal();
        }
    }

    function validateCreate() {
        const name = document.getElementById('modal-name').value.trim();
        if (!name) {
            document.getElementById('modal-name').focus();
            return false;
        }
        return true;
    }

    // Aperçu de l'image sélectionnée
    document.getElementById('modal-image').addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('img-preview');
        if (file && file.type.match('image.*')) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });

    // Fermer la modal avec Échap
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeCreateModal();
    });
</script>
</body>
</html>