<?php
// gen_pdf.php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!file_exists('fpdf/fpdf.php')) {
    ob_end_clean();
    die("Error: FPDF library missing. Please check your 'fpdf' folder.");
}
require('fpdf/fpdf.php');
require 'class.db.php';

$db = new EventManager();

$eventId = $_GET['event_id'] ?? null;
if (!$eventId) { ob_end_clean(); die("Error: Missing Event ID."); }

try {
    $stmt = $db->pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) { ob_end_clean(); die("Error: Event not found."); }
    
    $attendees = $db->getParticipants($event['id']);
    if (empty($attendees)) { ob_end_clean(); die("Error: No attendees found for this event."); }

} catch (Exception $e) {
    ob_end_clean();
    die("Database Error: " . $e->getMessage());
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(false);

foreach ($attendees as $person) {
    $pdf->AddPage();

    $brown = [168, 86, 28];
    $dark = [22, 58, 70];
    $light = [230, 203, 183];

    $pdf->SetDrawColor(168, 86, 28);
    $pdf->SetLineWidth(1.5);
    $pdf->Rect(8, 8, 281, 194);

    $pdf->SetFillColor(168, 86, 28);
    $pdf->Rect(8, 8, 30, 30, 'F');
    $pdf->Rect(259, 8, 30, 30, 'F');
    $pdf->Rect(8, 172, 30, 30, 'F');
    $pdf->Rect(259, 172, 30, 30, 'F');

    $pdf->SetY(40);
    $pdf->SetFont('Times', 'B', 36);
    $pdf->SetTextColor(...$dark);
    $pdf->Cell(0, 14, 'Attestation', 0, 1, 'C');

    $pdf->SetFont('Times', '', 28);
    $pdf->Cell(0, 12, 'de Participation', 0, 1, 'C');

    $pdf->SetY(62);
    $pdf->SetFillColor(...$light);
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(60, 10, 'Decernee a :', 0, 1, 'C', true);

    $prenom = iconv('UTF-8', 'windows-1252//TRANSLIT', $person['prenom']);
    $nom = iconv('UTF-8', 'windows-1252//TRANSLIT', $person['nom']);
    $fullName = strtoupper($prenom . ' ' . $nom);

    $pdf->SetY(80);
    $pdf->SetFont('Times', 'B', 44);
    $pdf->SetTextColor(...$brown);
    $pdf->Cell(0, 18, $fullName, 0, 1, 'C');

    $pdf->SetY(110);
    $pdf->SetFont('Arial', '', 14);
    $pdf->SetTextColor(...$dark);

    $text = iconv('UTF-8', 'windows-1252//TRANSLIT', "En reconnaissance de sa participation à l'événement :");
    $pdf->MultiCell(0, 8, $text, 0, 'C');

    $pdf->SetFont('Arial', 'B', 16);
    $title = iconv('UTF-8', 'windows-1252//TRANSLIT', "« " . mb_strtoupper($event['name'], 'UTF-8') . " »");
    $pdf->MultiCell(0, 9, $title, 0, 'C');

    $pdf->SetFont('Arial', '', 14);
    $dateText = iconv('UTF-8', 'windows-1252//TRANSLIT', "organisé le " . date('d/m/Y', strtotime($event['created_at'])) . ".");
    $pdf->MultiCell(0, 8, $dateText, 0, 'C');

    $pdf->SetY(160);
    $pdf->SetTextColor(...$brown);
    $pdf->SetFont('Arial', '', 14);

    $pdf->SetX(40);
    $pdf->Cell(80, 8, 'M. X', 0, 0, 'C');

    $pdf->SetX(180);
    $pdf->Cell(80, 8, 'M. Y', 0, 0, 'C');
}

ob_end_clean();
$pdf->Output('I', 'Attestations_' . $event['id'] . '.pdf');
?>
