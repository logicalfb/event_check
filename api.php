<?php
// api.php — Endpoint JSON pour les mises à jour en temps réel
require 'class.db.php';

header('Content-Type: application/json; charset=utf-8');

$db    = new EventManager();
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    echo json_encode(['error' => 'Token manquant']);
    exit;
}

$event = $db->getEventByToken($token);

if (!$event) {
    echo json_encode(['error' => 'Événement introuvable']);
    exit;
}

$eventId      = (int) $event['id'];
$count        = $db->countParticipants($eventId);
$lastPersonne = $db->getLastParticipant($eventId);

echo json_encode([
    'count'          => $count,
    'dernier_arrive' => $lastPersonne ?: null
]);
?>