<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

$email = $_SESSION['user'];

// 1. Validate User Role and Position
$query = "
    SELECT U.IdBadge, S.Role 
    FROM Users U
    JOIN Employees E ON U.IdUser = E.IdEmployee
    JOIN Shifts S ON E.IdRole = S.IdRole
    WHERE U.Email = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    header("Location: dashboard.php?error=" . urlencode("Utente non autorizzato."));
    exit();
}
$userData = $res->fetch_assoc();
$role = $userData['Role'];
$idBadge = $userData['IdBadge'];
$stmt->close();

if ($role !== 'Chimico') {
    header("Location: dashboard.php?error=" . urlencode("Solo i chimici possono lavorare in laboratorio."));
    exit();
}

// 2. Validate Position
$realLastPos = 1;
$posQuery = $conn->prepare("SELECT IdSectorTo FROM Accesses WHERE IdBadge = ? AND Result IN ('GRANTED', 'AUTO_EXIT') ORDER BY IdAccess DESC LIMIT 1");
$posQuery->bind_param("i", $idBadge);
$posQuery->execute();
$posResult = $posQuery->get_result();
if ($pRow = $posResult->fetch_assoc()) {
    $realLastPos = $pRow['IdSectorTo'];
}
$posQuery->close();

$validLabs = [7, 8, 11];
if (!in_array($realLastPos, $validLabs)) {
    header("Location: dashboard.php?error=" . urlencode("Devi trovarti in un laboratorio per lavorare."));
    exit();
}

// 3. Process Quantity
$qtyToConsume = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
if ($qtyToConsume < 1 || $qtyToConsume > 5) {
    header("Location: dashboard.php?error=" . urlencode("Quantità non valida (da 1 a 5)."));
    exit();
}

// 4. Update Inventory for "Sostanze chimiche" (Assuming IdItem = 2)
$chemicalItemId = 2; // Verify this matches the database
$checkInv = $conn->prepare("SELECT Quantity, Description FROM Inventory WHERE IdItem = ?");
$checkInv->bind_param("i", $chemicalItemId);
$checkInv->execute();
$resInv = $checkInv->get_result();

if ($resInv->num_rows === 0) {
    header("Location: dashboard.php?error=" . urlencode("Elemento chimico non trovato in inventario."));
    exit();
}

$invData = $resInv->fetch_assoc();
$currentQty = $invData['Quantity'];
$checkInv->close();

if ($currentQty < $qtyToConsume) {
    header("Location: dashboard.php?error=" . urlencode("Sostanze chimiche insufficienti in inventario (Restanti: $currentQty)."));
    exit();
}

// Consume
$newQty = $currentQty - $qtyToConsume;
$updateInv = $conn->prepare("UPDATE Inventory SET Quantity = ? WHERE IdItem = ?");
$updateInv->bind_param("ii", $newQty, $chemicalItemId);
if ($updateInv->execute()) {
    $successMsg = "Hai lavorato con successo! Consumate $qtyToConsume unità di Sostanze chimiche.";

    // 5. Emergency Event Generation (5% chance)
    $randNum = rand(1, 100);
    if ($randNum <= 5) {
        $eventType = (rand(0, 1) === 0) ? 'Incendio' : 'Fuga di gas';
        $insertEvent = $conn->prepare("INSERT INTO EmergencyEvents (StartTime, EndTime, Type, IdSector) VALUES (NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), ?, ?)");
        $insertEvent->bind_param("si", $eventType, $realLastPos);
        if ($insertEvent->execute()) {
            $successMsg .= " ATTENZIONE: Si è verificato un evento imprevisto: $eventType!";
        }
        $insertEvent->close();
    }

    header("Location: dashboard.php?success=" . urlencode($successMsg));
} else {
    header("Location: dashboard.php?error=" . urlencode("Errore durante l'aggiornamento dell'inventario."));
}
$updateInv->close();
?>
