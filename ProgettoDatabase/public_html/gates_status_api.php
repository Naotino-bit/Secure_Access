<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$email = $_SESSION['user'];

// Vediamo come sono messi tutti i varchi
$allGatesQuery = $conn->query("SELECT IdGate, SecurityLevel, IdSectorA, IdSectorB, Wear, IsLocked FROM Gates");
$gatesData = [];
while ($gRow = $allGatesQuery->fetch_assoc()) {
    $gatesData[$gRow['IdGate']] = $gRow;
}

// Vediamo dove può andare l'utente adesso
$accessibleSectors = [];

$query = "
    SELECT U.IdBadge, B.BadgeLevel 
    FROM Users U 
    JOIN Badges B ON U.IdBadge = B.IdBadge 
    WHERE U.Email = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$badge = $result->fetch_assoc();
$stmt->close();

if ($badge) {
    // Cerchiamo l'ultima stanza dove è stato visto
    $LastPos = 1;
    $posQuery = $conn->prepare("SELECT IdSectorTo FROM Accesses WHERE IdBadge = ? AND Result IN ('GRANTED', 'AUTO_EXIT') ORDER BY IdAccess DESC LIMIT 1");
    $posQuery->bind_param("i", $badge['IdBadge']);
    $posQuery->execute();
    $posResult = $posQuery->get_result();
    if ($pRow = $posResult->fetch_assoc()) {
        $LastPos = $pRow['IdSectorTo'];
    }
    $posQuery->close();

    // Vediamo se ci sono allarmi attivi
    $emergencyQuery = $conn->query("SELECT * FROM EmergencyEvents WHERE NOW() BETWEEN StartTime AND EndTime");
    $isFireActive = false;
    $gasLeakRooms = [];
    while ($ev = $emergencyQuery->fetch_assoc()) {
        if ($ev['Type'] === 'Incendio') $isFireActive = true;
        if ($ev['Type'] === 'Fuga di gas') $gasLeakRooms[] = $ev['IdSector'];
    }

    // Quali varchi ci sono qui intorno?
    $PossiblePosQuery = $conn->prepare("SELECT g.IdGate, g.SecurityLevel, g.IdSectorA, g.IdSectorB, g.Wear, g.IsLocked 
                                         FROM Gates g 
                                         WHERE (IdSectorA = ? OR IdSectorB = ?)");
    $PossiblePosQuery->bind_param("ii", $LastPos, $LastPos);
    $PossiblePosQuery->execute();
    $possibleRes = $PossiblePosQuery->get_result();

    while($row = $possibleRes->fetch_assoc()) {
        $IdSectorTo = ($LastPos == $row['IdSectorA']) ? $row['IdSectorB'] : $row['IdSectorA'];
        
        $isAccessible = ($badge['BadgeLevel'] >= $row['SecurityLevel'] && $row['Wear'] < 100 && $row['IsLocked'] == 0);
        
        // Se è un nuovo arrivato non lo facciamo muovere
        if ($badge['BadgeLevel'] == 1 && $LastPos == 1 && $IdSectorTo != 1) {
            $isAccessible = false;
        }

        // Se c'è un'emergenza cambiamo le regole
        if ($isFireActive) {
            // Con l'incendio si aprono tutti, se non sono rotti del tutto
            if ($row['Wear'] < 100) {
                $isAccessible = true;
            } else {
                $isAccessible = false;
            }
        } elseif (in_array($IdSectorTo, $gasLeakRooms)) {
            $isAccessible = false; // Meglio non entrare se c'è puzza di gas
        } elseif (in_array($LastPos, $gasLeakRooms)) {
            // Se sei già dentro il gas, scappa finché puoi
            if ($row['Wear'] < 100) {
                $isAccessible = true;
            } else {
                $isAccessible = false;
            }
        }

        if ($isAccessible) {
            $accessibleSectors[] = (string)$IdSectorTo;
        }
    }
    $PossiblePosQuery->close();
}

$activeEmergencies = [];
$emgQ = $conn->query("SELECT Type, IdSector FROM EmergencyEvents WHERE NOW() BETWEEN StartTime AND EndTime");
while ($e = $emgQ->fetch_assoc()) {
    $activeEmergencies[] = $e;
}

$response = [
    'success' => true,
    'gates' => $gatesData,
    'accessible_sectors' => $accessibleSectors,
    'emergencies' => $activeEmergencies
];

header('Content-Type: application/json');
echo json_encode($response);
exit();
