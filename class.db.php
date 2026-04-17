<?php
// class.db.php — Gestionnaire de la base de données
require_once 'config.php';

class EventManager {

    public PDO $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE,    PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Erreur de connexion: ' . $e->getMessage()]));
        }
    }

    /**
     * Crée un nouvel événement et retourne son token unique.
     * @param string      $name  Nom de l'événement
     * @param string|null $image Nom du fichier image (optionnel)
     */
    public function createEvent(string $name, ?string $image = null): string {
        $token = bin2hex(random_bytes(8));
        $stmt  = $this->pdo->prepare("INSERT INTO events (name, token, image) VALUES (?, ?, ?)");
        $stmt->execute([$name, $token, $image]);
        return $token;
    }

    /**
     * Retourne tous les événements avec leur nombre de participants.
     */
    public function getEvents(): array {
        $stmt = $this->pdo->query(
            "SELECT e.*, 
                    (SELECT COUNT(*) FROM participants p WHERE p.event_id = e.id) AS nb_participants
             FROM events e 
             ORDER BY e.created_at DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Retourne un événement par son token.
     */
    public function getEventByToken(string $token): array|false {
        $stmt = $this->pdo->prepare("SELECT * FROM events WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }

    /**
     * Enregistre la présence d'un participant.
     * Retourne false si déjà enregistré (anti-doublon).
     */
    public function markPresence(string $nom, string $prenom, int $eventId): bool {
        // Vérification anti-doublon
        $check = $this->pdo->prepare(
            "SELECT id FROM participants WHERE event_id = ? AND nom = ? AND prenom = ?"
        );
        $check->execute([$eventId, $nom, $prenom]);
        if ($check->rowCount() > 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO participants (event_id, nom, prenom) VALUES (?, ?, ?)"
        );
        return $stmt->execute([$eventId, $nom, $prenom]);
    }

    /**
     * Retourne la liste des participants d'un événement.
     */
    public function getParticipants(int $eventId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM participants WHERE event_id = ? ORDER BY check_in_time DESC"
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /**
     * Retourne le nombre total de participants d'un événement.
     */
    public function countParticipants(int $eventId): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM participants WHERE event_id = ?"
        );
        $stmt->execute([$eventId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retourne le dernier participant enregistré pour un événement.
     */
    public function getLastParticipant(int $eventId): array|false {
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom FROM participants WHERE event_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$eventId]);
        return $stmt->fetch();
    }
}
?>