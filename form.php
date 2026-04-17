<?php
// form.php — Formulaire d'enregistrement des participants (check-in)
require 'class.db.php';

$db = new EventManager();

// ── Mode AJAX : soumission silencieuse depuis JS ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_mode'])) {
    header('Content-Type: application/json; charset=utf-8');

    $nom     = trim($_POST['nom']     ?? '');
    $prenom  = trim($_POST['prenom']  ?? '');
    $eventId = (int) ($_POST['event_id'] ?? 0);

    // Validation basique
    if ($nom === '' || $prenom === '' || $eventId <= 0) {
        echo json_encode(['status' => 'erreur', 'message' => 'Données manquantes ou invalides.']);
        exit;
    }

    // markPresence retourne false si déjà enregistré
    $success = $db->markPresence($nom, $prenom, $eventId);

    if ($success) {
        echo json_encode(['status' => 'succes', 'nom_complet' => "$prenom $nom"]);
    } else {
        // Déjà enregistré : on affiche quand même l'écran de bienvenue
        echo json_encode(['status' => 'deja_enregistre', 'nom_complet' => "$prenom $nom"]);
    }
    exit;
}

// ── Mode standard : affichage de la page ─────────────────────────────────────
$token = trim($_GET['token'] ?? '');
$event = $token ? $db->getEventByToken($token) : false;

if (!$event) {
    http_response_code(404);
    die('Lien invalide ou événement introuvable.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrement — <?php echo htmlspecialchars($event['name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0d1117 0%, #161b22 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 24px;
            padding: 2.5rem 2rem;
            width: min(440px, 100%);
            box-shadow: 0 24px 60px rgba(0,0,0,.5);
            position: relative;
            overflow: hidden;
        }
        /* Accent glow */
        .card::before {
            content: '';
            position: absolute;
            top: -80px; left: 50%;
            transform: translateX(-50%);
            width: 300px; height: 200px;
            background: radial-gradient(ellipse, rgba(99,102,241,.25) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ── Écran : chargement automatique ── */
        #ecran-chargement {
            text-align: center;
            padding: 2rem 0;
        }
        .spinner {
            width: 48px; height: 48px;
            border: 4px solid #30363d;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin .9s linear infinite;
            margin: 0 auto 1.5rem;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .chargement-text { color: #8b949e; font-size: .95rem; font-weight: 500; }

        /* ── Écran : succès ── */
        #ecran-succes { display: none; text-align: center; padding: 1rem 0; }
        .check-circle {
            width: 80px; height: 80px;
            background: #052e16;
            border: 2px solid #22c55e;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pop .4s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes pop { 0% { transform: scale(0); } 100% { transform: scale(1); } }
        .check-circle svg { width: 40px; height: 40px; color: #22c55e; }
        #ecran-succes h1 { font-size: 1.6rem; font-weight: 800; color: #e6edf3; margin-bottom: .5rem; }
        #ecran-succes .nom-complet { color: #6366f1; font-size: 1.1rem; font-weight: 600; margin-top: .5rem; }
        #ecran-succes .sous-texte  { color: #8b949e; font-size: .85rem; margin-top: 1rem; }

        /* ── Écran : formulaire manuel ── */
        #ecran-formulaire { display: none; }
        .form-header { text-align: center; margin-bottom: 1.75rem; }
        .form-header .event-title { font-size: 1.2rem; font-weight: 800; color: #e6edf3; margin-bottom: .35rem; }
        .form-header p { color: #8b949e; font-size: .85rem; }

        .field { margin-bottom: 1.1rem; }
        .field label { display: block; font-size: .8rem; font-weight: 600; color: #8b949e; margin-bottom: .45rem; text-transform: uppercase; letter-spacing: .06em; }
        .field input {
            width: 100%;
            padding: .75rem 1rem;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 12px;
            color: #e6edf3;
            font-size: .95rem;
            font-family: inherit;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .field input::placeholder { color: #4b5563; }
        .field input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99,102,241,.15);
        }

        .btn-submit {
            width: 100%;
            padding: .85rem;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            margin-top: .5rem;
            transition: background .2s, transform .1s;
        }
        .btn-submit:hover   { background: #5254cc; }
        .btn-submit:active  { transform: scale(.98); }
        .btn-submit:disabled { background: #374151; cursor: not-allowed; }

        /* ── Écran : erreur ── */
        #ecran-erreur { display: none; text-align: center; padding: 1rem 0; }
        #ecran-erreur p { color: #f87171; margin-bottom: 1rem; }
        .btn-retry {
            background: #1f2937; color: #e6edf3;
            border: 1px solid #30363d; border-radius: 10px;
            padding: .6rem 1.4rem; font-size: .875rem;
            font-family: inherit; cursor: pointer;
        }

        .event-poster {
            max-width: 100%;
            max-height: 30vh;
            width: auto;
            height: auto;
            border-radius: 14px;
            object-fit: contain;
            border: 1px solid #30363d;
            margin: 0 auto 1.5rem auto;
            display: block;
        }
    </style>
</head>
<body>
<div class="card">

    <!-- Chargement / identification automatique -->
    <div id="ecran-chargement">
        <div class="spinner"></div>
        <p class="chargement-text">Identification en cours…</p>
    </div>

    <!-- Succès -->
    <div id="ecran-succes">
        <?php if (!empty($event['image'])): ?>
            <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>"
                 alt="<?php echo htmlspecialchars($event['name']); ?>"
                 class="event-poster">
        <?php endif; ?>
        <div class="check-circle">
            <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1>Bienvenue !</h1>
        <div class="nom-complet" id="nom-complet-affiche"></div>
        <p class="sous-texte">Votre présence a bien été enregistrée.<br>Merci de votre participation 🎉</p>
    </div>

    <!-- Formulaire manuel -->
    <div id="ecran-formulaire">
        <div class="form-header">
            <?php if (!empty($event['image'])): ?>
                <img src="uploads/<?php echo htmlspecialchars($event['image']); ?>"
                     alt="<?php echo htmlspecialchars($event['name']); ?>"
                     class="event-poster">
            <?php endif; ?>
            <div class="event-title"><?php echo htmlspecialchars($event['name']); ?></div>
            <p>Veuillez saisir vos informations pour vous enregistrer.</p>
        </div>
        <div class="field">
            <label for="input-nom">Nom</label>
            <input type="text" id="input-nom" placeholder="Votre nom de famille" autocomplete="family-name">
        </div>
        <div class="field">
            <label for="input-prenom">Prénom</label>
            <input type="text" id="input-prenom" placeholder="Votre prénom" autocomplete="given-name">
        </div>
        <button class="btn-submit" id="btn-enregistrer" onclick="soumettreFormulaire()">
            ✅ M'enregistrer
        </button>
    </div>

    <!-- Erreur réseau -->
    <div id="ecran-erreur">
        <p>⚠️ Une erreur réseau est survenue. Veuillez réessayer.</p>
        <button class="btn-retry" onclick="afficherFormulaire()">Réessayer manuellement</button>
    </div>

</div>

<script>
    const EVENT_ID = <?php echo (int) $event['id']; ?>;

    // ── Au chargement : afficher le formulaire ──────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
        afficherFormulaire();
    });

    // ── Appel API d'enregistrement ──────────────────────────────────────────────
    function effectuerCheckIn(nom, prenom) {
        afficherEcran('ecran-chargement');

        const formData = new FormData();
        formData.append('ajax_mode', '1');
        formData.append('event_id',  EVENT_ID);
        formData.append('nom',       nom);
        formData.append('prenom',    prenom);

        fetch('form.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'succes' || data.status === 'deja_enregistre') {
                    afficherSucces(data.nom_complet);
                } else {
                    afficherFormulaire();
                    alert('Erreur : ' + (data.message || 'Veuillez réessayer.'));
                }
            })
            .catch(() => afficherEcran('ecran-erreur'));
    }

    // ── Soumission manuelle ─────────────────────────────────────────────────────
    function soumettreFormulaire() {
        const nom    = document.getElementById('input-nom').value.trim();
        const prenom = document.getElementById('input-prenom').value.trim();

        if (!nom || !prenom) {
            alert('Veuillez remplir votre nom ET votre prénom.');
            return;
        }

        document.getElementById('btn-enregistrer').disabled = true;
        effectuerCheckIn(nom, prenom);
    }

    // ── Helpers d'affichage ─────────────────────────────────────────────────────
    function afficherEcran(id) {
        ['ecran-chargement', 'ecran-succes', 'ecran-formulaire', 'ecran-erreur']
            .forEach(e => document.getElementById(e).style.display = 'none');
        document.getElementById(id).style.display = 'block';
    }

    function afficherFormulaire() {
        afficherEcran('ecran-formulaire');
        document.getElementById('btn-enregistrer').disabled = false;
    }

    function afficherSucces(nomComplet) {
        document.getElementById('nom-complet-affiche').textContent = nomComplet;
        afficherEcran('ecran-succes');
    }

    // Soumettre avec Entrée
    document.addEventListener('keydown', e => {
        if (e.key === 'Enter' && document.getElementById('ecran-formulaire').style.display === 'block') {
            soumettreFormulaire();
        }
    });
</script>
</body>
</html>
