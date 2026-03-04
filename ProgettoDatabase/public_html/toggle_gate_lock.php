<?php
// Script per il toggle (Blocca/Sblocca) di un gate da parte dell'admin
header('Content-Type: application/json');

require("db_connection.php");

// Recupero dati JSON
$json_data = file_get_contents('php://input');
$request_data = json_decode($json_data, true);

if (!isset($request_data['gate_id']) || !isset($request_data['is_locked'])) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti.']);
    exit;
}

$gateId = (int)$request_data['gate_id'];
$isLocked = (int)$request_data['is_locked'] ? 1 : 0;

// Esegui update
$stmt = $conn->prepare("UPDATE Gates SET IsLocked = ? WHERE IdGate = ?");
$stmt->bind_param("ii", $isLocked, $gateId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
$conn->close();
?>
