<?php
require "db_connection.php";
session_start();

// Eseguiamo il controllo automatico per far uscire i dipendenti fuori orario
require_once "includes/auto_teleport.php";

$message = "";
$access_granted = null;

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user']; 

// Recupero dati utente, LIVELLO BADGE e ORARI TURNO
$query = "
    SELECT U.Name, U.Surname, U.IdBadge, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue, S.Start, S.End, S.Role
    FROM Users U 
    JOIN Badges B ON U.IdBadge = B.IdBadge 
    LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
    LEFT JOIN Shifts S ON E.IdRole = S.IdRole
    WHERE U.Email = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$badge = $result->fetch_assoc();

$currentUser = null;
$badgeLevel = 0;

$stmt->close();

$isSurveillance = isset($badge['Role']) && $badge['Role'] === 'Sorveglianza';

// Se l'admin_mode non è settato nella sessione, lo inizializziamo in base all'ultima posizione
if ($isSurveillance && !isset($_SESSION['admin_mode'])) {
    $LastPosTempQuery = $conn->prepare("SELECT IdSectorTo FROM Accesses WHERE IdBadge = ? AND Result IN ('GRANTED', 'AUTO_EXIT') ORDER BY IdAccess DESC LIMIT 1;");
    $LastPosTempQuery->bind_param("i", $badge['IdBadge']);
    $LastPosTempQuery->execute();
    $resTmp = $LastPosTempQuery->get_result();
    $rowTmp = $resTmp->fetch_assoc();
    $realPosTemp = $rowTmp ? $rowTmp['IdSectorTo'] : 1;
    $LastPosTempQuery->close();
    
    $_SESSION['admin_mode'] = ($realPosTemp == 25) ? 'monitor' : 'interactive';
}

$isMonitorMode = $isSurveillance && (isset($_SESSION['admin_mode']) && $_SESSION['admin_mode'] === 'monitor');

// Per l'ADMIN (o Sicurezza) in sola visualizzazione (richiesta GET) evitiamo query pesanti sulla tabella Accesses
if ($isMonitorMode && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $LastPos = 1;              // Valore neutro, la posizione admin non è rilevante
    $LastPositionResult = null;
} else {
    // 2. Troviamo l'ultima posizione dell'utente; se non esiste è fuori dalla struttura
    $LastPositionQuery = $conn->prepare("SELECT IdSectorTo 
                                         FROM Accesses 
                                         WHERE IdBadge = ? AND RESULT IN ('GRANTED', 'AUTO_EXIT') 
                                         ORDER BY IdAccess DESC 
                                         LIMIT 1;");
    $LastPositionQuery->bind_param("i", $badge['IdBadge']);
    $LastPositionQuery->execute();
    $LastPositionResult = $LastPositionQuery->get_result();
    $row = $LastPositionResult->fetch_assoc();
    if (!$row) { 
        // Appena entrato nella struttura, sei nella hall
        $LastPos = 1;
    } else {
        $LastPos = $row["IdSectorTo"];
    }

    $LastPositionQuery->close();
}

// Recupero tutte le info dei gate per l'interfaccia JS (mappa)
$allGatesQuery = $conn->query("SELECT IdGate, SecurityLevel, IdSectorA, IdSectorB, Wear, IsLocked FROM Gates");
$allGatesData = [];
if ($allGatesQuery) {
    while ($gRow = $allGatesQuery->fetch_assoc()) {
        $allGatesData[$gRow['IdGate']] = $gRow;
    }
}
$userBadgeLevel = isset($badge['BadgeLevel']) ? (int)$badge['BadgeLevel'] : 0;

// 3. Recupero tutte le posizioni dove l'utente può andare (solo se non è admin viewer)
$accessibleSectors = [];
if (!($isMonitorMode && $_SERVER['REQUEST_METHOD'] !== 'POST')) {
    // === GESTIONE EMERGENZE FRONTEND ==============================
    $emergencyQuery = $conn->query("SELECT * FROM EmergencyEvents WHERE NOW() BETWEEN StartTime AND EndTime");
    $isFireActive = false;
    $gasLeakRooms = [];
    while ($ev = $emergencyQuery->fetch_assoc()) {
        if ($ev['Type'] === 'Incendio') $isFireActive = true;
        if ($ev['Type'] === 'Fuga di gas') $gasLeakRooms[] = $ev['IdSector'];
    }

    // Troviamo tutte le porte collegate
    $PossiblePositionQuery = $conn->prepare("SELECT g.IdGate, g.SecurityLevel, g.IdSectorA, g.IdSectorB, g.Wear, g.IsLocked 
                                             FROM Gates g 
                                             WHERE (IdSectorA = ? OR IdSectorB = ?)");
    $PossiblePositionQuery->bind_param("ii", $LastPos, $LastPos);
    $PossiblePositionQuery->execute();
    $PossiblePositionResult = $PossiblePositionQuery->get_result();

    while($row = $PossiblePositionResult->fetch_assoc()) {
        $IdSectorTo = ($LastPos == $row['IdSectorA']) ? $row['IdSectorB'] : $row['IdSectorA'];
        
        $isAccessible = (isset($badge['BadgeLevel']) && $badge['BadgeLevel'] >= $row['SecurityLevel'] && $row['Wear'] < 100 && $row['IsLocked'] == 0);
        
        // Regola utente pending
        if (isset($badge['BadgeLevel']) && $badge['BadgeLevel'] == 1 && $LastPos == 1 && $IdSectorTo != 1) {
            $isAccessible = false;
        }

        // Sovrascrittura per Emergenze
        if ($isFireActive) {
            $isAccessible = true;
        } elseif (in_array($IdSectorTo, $gasLeakRooms)) {
            $isAccessible = false; // NON entrare dove c'è fuga di gas
        } elseif (in_array($LastPos, $gasLeakRooms)) {
            $isAccessible = true; // SCAPPA se sei nella stanza con fuga di gas
        }

        if ($isAccessible) {
            $accessibleSectors[] = (string)$IdSectorTo;
        }
    }
    $PossiblePositionQuery->close();
} else {
    $PossiblePositionResult = null;
}

// Assumiamo che $conn, $badge, $LastPos siano già definiti sopra; per $PossiblePositionResult
// vale solo per le richieste non-admin / POST.

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // === 1. GESTIONE JSON INVECE DI POST STANDARD ===
    $json_data = file_get_contents('php://input');
    $request_data = json_decode($json_data, true);
    
    // I dati sono inviati da JS come: { sector_id : room.id }
    $clicked_sector_id = $request_data['sector_id'] ?? null;
    
    // Preparo la risposta JSON di default
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Errore sconosciuto.'];
    
    // === 2. TROVA IL GATE ASSOCIATO ALLA STANZA CLICCATA ===
    // Dobbiamo trovare TUTTI i gate collegati alla posizione corrente, INDIPENDENTEMENTE dal livello del badge
    // per poter distinguere tra "Non esiste passaggio" (Not Reachable) e "Passaggio esiste ma livello basso" (LOW_LEVEL).
    
    $AllGatesQuery = $conn->prepare("SELECT g.IdGate, g.SecurityLevel, g.IdSectorA, g.IdSectorB, g.Wear, g.IsLocked
                                     FROM Gates g 
                                     WHERE (IdSectorA = ? OR IdSectorB = ?)");
    $AllGatesQuery->bind_param("ii", $LastPos, $LastPos);
    $AllGatesQuery->execute();
    $AllGatesResult = $AllGatesQuery->get_result();

    $PossibleGates = [];
    while($row = $AllGatesResult->fetch_assoc()) {
        if ($LastPos == $row['IdSectorA']){
            $IdSectorTo = $row['IdSectorB']; 
        } else {
            $IdSectorTo = $row['IdSectorA'];
        }
        if (!isset($PossibleGates[$IdSectorTo])) {
            $PossibleGates[$IdSectorTo] = [];
        }
        $PossibleGates[$IdSectorTo][] = [
            'gate' => $row['IdGate'], 
            'level' => $row['SecurityLevel'],
            'wear' => $row['Wear'],
            'isLocked' => $row['IsLocked']
        ];
    }
    $AllGatesQuery->close();


    // === 3. ESECUZIONE LOGICA DI CONTROLLO ===

    if (isset($PossibleGates[$clicked_sector_id])) {
        $sectorGates = $PossibleGates[$clicked_sector_id];
        $SectorId = $clicked_sector_id; // Questo è il settore di destinazione
        $timestamp = date("Y-m-d H:i:s");
        
        // === GESTIONE EMERGENZE ===============================================
        $emergencyQuery = $conn->query("SELECT * FROM EmergencyEvents WHERE NOW() BETWEEN StartTime AND EndTime");
        $isFireActive = false;
        $isGasLeakCurrentRoom = false;
        $isGasLeakDestRoom = false;

        while ($ev = $emergencyQuery->fetch_assoc()) {
            if ($ev['Type'] === 'Incendio') {
                $isFireActive = true;
                break;
            }
            if ($ev['Type'] === 'Fuga di gas') {
                if ($ev['IdSector'] == $LastPos) $isGasLeakCurrentRoom = true;
                if ($ev['IdSector'] == $SectorId) $isGasLeakDestRoom = true;
            }
        }
        // ======================================================================

        // *** Esegui la tua logica di controllo (Badge Scaduto, Livello Insufficiente, etc.) ***

        if ($isFireActive) {
            // Find a gate that is NOT worn out (Wear < 100)
            $validFireGate = null;
            foreach ($sectorGates as $GateInfo) {
                if ($GateInfo['wear'] < 100) {
                    $validFireGate = $GateInfo['gate'];
                    break;
                }
            }

            if ($validFireGate) {
                $response['status'] = 'success';
                $response['message'] = "EMERGENZA INCENDIO: Tutti i varchi aperti. Evacuazione in corso!";
                $esito = "GRANTED";
                $GateId = $validFireGate;
            } else {
                $response['status'] = 'error';
                $response['message'] = "ACCESSO NEGATO: Tutte le porte verso questa stanza sono fuori uso (Manutenzione Richiesta).";
                $esito = "REQUIRED_MAINTENANCE";
                $GateId = $sectorGates[0]['gate'];
            }
        } elseif ($isGasLeakDestRoom) {
            $response['status'] = 'error';
            $response['message'] = "ACCESSO NEGATO: Fuga di gas rilevata nella Stanza $SectorId!";
            $esito = "DENIED";
            $GateId = $sectorGates[0]['gate'];
        } elseif ($isGasLeakCurrentRoom) {
            $validGasGate = null;
            foreach ($sectorGates as $GateInfo) {
                if ($GateInfo['wear'] < 100) {
                    $validGasGate = $GateInfo['gate'];
                    break;
                }
            }

            if ($validGasGate) {
                $response['status'] = 'success';
                $response['message'] = "FUGA DI GAS: Evacuazione d'emergenza consentita verso la Stanza $SectorId.";
                $esito = "GRANTED";
                $GateId = $validGasGate;
            } else {
                $response['status'] = 'error';
                $response['message'] = "ACCESSO NEGATO: Tutte le porte verso questa stanza sono fuori uso (Manutenzione Richiesta).";
                $esito = "REQUIRED_MAINTENANCE";
                $GateId = $sectorGates[0]['gate'];
            }
        } elseif (!$badge) {
            $response['message'] = "ERRORE: Badge non esistente!";
            $esito = "NOT_FOUND";
            $GateId = $sectorGates[0]['gate'];
        } elseif ($badge['ExpirationDate'] != NULL && new DateTime() > new DateTime($badge['ExpirationDate'])) {
            $response['message'] = "ACCESSO NEGATO: Badge Scaduto!";
            $esito = "EXPIRED";
            $GateId = $sectorGates[0]['gate'];
        } elseif ($badge['BadgeLevel'] == 1 && $LastPos == 1 && $SectorId != 1) {
            // BLOCCO UTENTI PENDING (LEVEL 1) NELLA HALL
            $response['message'] = "ACCESSO NEGATO: Utente in attesa di assunzione. Sei confinato nella Hall fino all'assegnazione di un ruolo.";
            $esito = "LOW_LEVEL"; 
            $GateId = $sectorGates[0]['gate'];
        } else {
            // Check Shifts (Orario Lavorativo) for users with level > 1
            $isWorkingHours = true;
            if ($badge['BadgeLevel'] > 1 && isset($badge['Start']) && isset($badge['End'])) {
                $currentTime = date('H:i:s');
                if ($badge['Start'] <= $badge['End']) {
                    $isWorkingHours = ($currentTime >= $badge['Start'] && $currentTime <= $badge['End']);
                } else {
                    $isWorkingHours = ($currentTime >= $badge['Start'] || $currentTime <= $badge['End']);
                }
            }

            if (!$isWorkingHours) {
                $response['message'] = "ACCESSO NEGATO: Fuori dall'orario di lavoro.";
                $esito = "OFF_HOURS";
                $GateId = $sectorGates[0]['gate'];
            } else {
                // Logica multi-porta per trovare un gate valido
                $grantedGate = null;
                $bestEsito = "LOW_LEVEL";
                $bestMessage = "ACCESSO NEGATO: Livello insufficiente per questa zona.";
                $fallbackGateId = $sectorGates[0]['gate'];

                foreach ($sectorGates as $GateInfo) {
                    $GateLevel = $GateInfo['level'];
                    $GateLocked = $GateInfo['isLocked'];
                    $currentGateId = $GateInfo['gate'];

                    // 1. Check Usura
                    $GateWearStatus = $conn->query("SELECT Wear FROM Gates WHERE IdGate = $currentGateId")->fetch_assoc();
                    if ($GateWearStatus['Wear'] >= 100) {
                        $bestEsito = "REQUIRED_MAINTENANCE";
                        $bestMessage = "ACCESSO NEGATO: Manutenzione richiesta su questa porta.";
                        continue;
                    }

                    // 2. Check Porta Bloccata
                    if ($GateLocked == 1) {
                        $bestEsito = "DENIED";
                        $bestMessage = "ACCESSO NEGATO: Questo varco è momentaneamente BLOCCATO dalla sorveglianza.";
                        continue;
                    }

                    // 3. Check Livello di Sicurezza
                    if ($badge['BadgeLevel'] >= $GateLevel) {
                        $grantedGate = $currentGateId;
                        break; 
                    } else {
                        $bestEsito = "LOW_LEVEL";
                        $bestMessage = "ACCESSO NEGATO: Livello insufficiente per questa zona (Gate Level: $GateLevel).";
                    }
                }

                if ($grantedGate) {
                    $response['status'] = 'success';
                    $response['message'] = "ACCESSO CONSENTITO: Benvenuto nella Stanza $SectorId.";
                    $esito = "GRANTED";
                    $GateId = $grantedGate;
                } else {
                    $response['status'] = 'error';
                    $response['message'] = $bestMessage;
                    $esito = $bestEsito;
                    $GateId = $fallbackGateId;
                }
            }
        }
        
        // === 4. LOG DEGLI ACCESSI & BUSINESS LOGIC ===
        if (isset($GateId) && isset($SectorId)) {
            $timestamp = date("Y-m-d H:i:s");
            // Nota: I controlli di usura/blocco/permesso sono stati eseguiti sopra e l'esito finale definito
            
            $logStmt = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge, IdSectorTo) VALUES (?, ?, ?, ?, ?)");
            $logStmt->bind_param("ssiii", $timestamp, $esito, $GateId, $badge['IdBadge'], $SectorId);
            $logStmt->execute();
            $idAccess = $logStmt->insert_id; // Recupero ID dell'accesso appena creato
            $logStmt->close();

            // A. GESTIONE WARNINGS (Tentativo non autorizzato)
            if ($esito == "LOW_LEVEL") {
                $warnStmt = $conn->prepare("INSERT INTO Warnings (Reason, IdAccess) VALUES ('Tentativo accesso non autorizzato: Livello insufficiente', ?)");
                $warnStmt->bind_param("i", $idAccess);
                $warnStmt->execute();
                $warnStmt->close();
            } elseif ($esito == "OFF_HOURS") {
                $warnStmt = $conn->prepare("INSERT INTO Warnings (Reason, IdAccess) VALUES ('Tentativo accesso non autorizzato: Fuori orario di lavoro', ?)");
                $warnStmt->bind_param("i", $idAccess);
                $warnStmt->execute();
                $warnStmt->close();
            }

            // B. GESTIONE USURA & MANUTENZIONE (Solo su accesso riuscito)
            if ($esito == "GRANTED") {
                // 1. Incrementa USURA
                $wearStmt = $conn->prepare("UPDATE Gates SET Wear = Wear + 1 WHERE IdGate = ?");
                $wearStmt->bind_param("i", $GateId);
                $wearStmt->execute();
                $wearStmt->close();

                // 2. Controllo Soglia Manutenzione (es. ogni 100 utilizzi)
                // Recupero il nuovo valore di Wear
                $checkWear = $conn->query("SELECT Wear FROM Gates WHERE IdGate = $GateId")->fetch_assoc();
                $currentWear = $checkWear['Wear'];

                if ($currentWear >= 100) {
                    // FIX DUPLICATI: Controlla Pending o Assigned
                    $checkReq = $conn->query("SELECT IdRequest FROM MaintenanceRequests WHERE IdGate = $GateId AND Status IN ('Pending', 'Assigned')");
                    
                    if ($checkReq->num_rows == 0) {
                        // Inseriamo richiesta se usura è alta
                        // STATUS = Pending (come richiesto)
                        // PRIORITY = High
                        // NON RESETTIAMO USURA QUI! (Il reset avverrà alla riparazione)
                        
                        $maintStmt = $conn->prepare("INSERT INTO MaintenanceRequests (Priority, Status, CreatedAt, IdGate) VALUES ('High', 'Pending', NOW(), ?)");
                        $maintStmt->bind_param("i", $GateId);
                        $maintStmt->execute();
                        $maintStmt->close();
                    } else {
                        $maintStmt = $conn->prepare("UPDATE MaintenanceRequests SET Priority = 'High', CreatedAt = NOW() WHERE IdGate = ? AND Status = 'Pending'");
                        $maintStmt->bind_param("i", $GateId);
                        $maintStmt->execute();
                        $maintStmt->close();
                    }
                } elseif ($currentWear >= 66) {
                    // FIX DUPLICATI: Controlla Pending o Assigned
                    $checkReq = $conn->query("SELECT IdRequest FROM MaintenanceRequests WHERE IdGate = $GateId AND Status IN ('Pending', 'Assigned')");
                    
                    if ($checkReq->num_rows == 0) {
                        // Inseriamo richiesta se usura è alta
                        // STATUS = Pending (come richiesto)
                        // PRIORITY = High
                        // NON RESETTIAMO USURA QUI! (Il reset avverrà alla riparazione)
                        
                        $maintStmt = $conn->prepare("INSERT INTO MaintenanceRequests (Priority, Status, CreatedAt, IdGate) VALUES ('Medium', 'Pending', NOW(), ?)");
                        $maintStmt->bind_param("i", $GateId);
                        $maintStmt->execute();
                        $maintStmt->close();
                    } else {
                        $maintStmt = $conn->prepare("UPDATE MaintenanceRequests SET Priority = 'Medium', CreatedAt = NOW() WHERE IdGate = ? AND Status = 'Pending'");
                        $maintStmt->bind_param("i", $GateId);
                        $maintStmt->execute();
                        $maintStmt->close();
                    }
                } elseif ($currentWear >= 33) {
                    // FIX DUPLICATI: Controlla Pending o Assigned
                    $checkReq = $conn->query("SELECT IdRequest FROM MaintenanceRequests WHERE IdGate = $GateId AND Status IN ('Pending', 'Assigned')");
                    
                    if ($checkReq->num_rows == 0) {
                        // Inseriamo richiesta se usura è alta
                        // STATUS = Pending (come richiesto)
                        // PRIORITY = High
                        // NON RESETTIAMO USURA QUI! (Il reset avverrà alla riparazione)
                        
                        $maintStmt = $conn->prepare("INSERT INTO MaintenanceRequests (Priority, Status, CreatedAt, IdGate) VALUES ('Low', 'Pending', NOW(), ?)");
                        $maintStmt->bind_param("i", $GateId);
                        $maintStmt->execute();
                        $maintStmt->close();
                    }
                }
                
            }
        }
        
        // Se l'accesso è GRANTED, aggiorna la posizione dell'utente (opzionale, ma consigliato)
        // Se vuoi aggiornare la posizione e ricaricare la pagina, puoi farlo qui.
        // Ma per una risposta AJAX, generalmente si aggiorna la UI tramite JS.
        if ($esito == "GRANTED") {
            // Invia al client la nuova posizione per aggiornare la UI
            $response['new_pos'] = $SectorId;

            // Calcola le nuove stanze raggiungibili dalla nuova posizione (senza ricaricare la pagina)
            $nextAccessible = [];
            $nextQuery = $conn->prepare("SELECT g.IdGate, g.SecurityLevel, g.IdSectorA, g.IdSectorB, g.Wear, g.IsLocked 
                                         FROM Gates g 
                                         WHERE (IdSectorA = ? OR IdSectorB = ?)");
            $nextQuery->bind_param("ii", $SectorId, $SectorId);
            $nextQuery->execute();
            $nextResult = $nextQuery->get_result();
            while ($nrow = $nextResult->fetch_assoc()) {
                if ($SectorId == $nrow['IdSectorA']) {
                    $to = $nrow['IdSectorB'];
                } else {
                    $to = $nrow['IdSectorA'];
                }

                $isAcc = (isset($badge['BadgeLevel']) && $badge['BadgeLevel'] >= $nrow['SecurityLevel'] && $nrow['Wear'] < 100 && $nrow['IsLocked'] == 0);
                
                if (isset($badge['BadgeLevel']) && $badge['BadgeLevel'] == 1 && $SectorId == 1 && $to != 1) {
                    $isAcc = false;
                }

                if ($isFireActive) {
                    $isAcc = true;
                } elseif (in_array($to, $gasLeakRooms)) {
                    $isAcc = false;
                } elseif (in_array($SectorId, $gasLeakRooms)) {
                    $isAcc = true;
                }

                if ($isAcc) {
                    $nextAccessible[] = (string)$to;
                }
            }
            $nextQuery->close();

            $response['accessible_sectors'] = $nextAccessible;
        }


    } else {
        $response['message'] = "ERRORE: La stanza $clicked_sector_id non è raggiungibile dalla tua posizione attuale ($LastPos) o non esiste un gate associato.";
    }

    // === 5. RITORNA LA RISPOSTA JSON E TERMINA ===
    echo json_encode($response);
    exit(); // Termina lo script PHP per non stampare il resto dell'HTML
}


// 3. REGISTRIAMO IL LOG NEL DB (esempio commentato)
//$logStmt = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge) VALUES (?, ?, ?, ?)");
//$logStmt->bind_param("ssii", $timestamp, $esito, $gateId, $badgeId);
//$logStmt->execute();
//$logStmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Simulazione Varco</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #e8feffff; color: white; }
        .scanner-box { background: #444; padding: 30px; border-radius: 10px; display: inline-block; width: 400px; }
        select, input { width: 90%; padding: 10px; margin: 10px 0; font-size: 1.1em; border-radius: 5px; border: none; }
        button { width: 95%; padding: 10px; font-size: 1.2em; cursor: pointer; background-color: #007bff; color: white; border: none; margin-top: 15px; border-radius: 5px; }
        /* Stile Toast Notification per Messaggi Accesso */
        .result { 
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100000;
            padding: 15px 30px; 
            font-size: 1.2em; 
            font-weight: bold;
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            color: white;
            opacity: 0;
            visibility: hidden;
            /* Assicura che visibility diventi hidden SOLO ALLA FINE dell'animazione (0.2s di ritardo) */
            transition: opacity 0.2s ease-in, top 0.2s ease-in, visibility 0s linear 0.2s;
        }
        .result.show {
            opacity: 1;
            visibility: visible;
            top: 40px;
            /* Quando entra (show), la classe diventa visible istantaneamente */
            transition: opacity 0.2s ease-out, top 0.2s ease-out, visibility 0s linear 0s;
        }
        .success { background-color: #28a745; }
        .error { background-color: #dc3545; }
        a { color: #ccc; text-decoration: none; display: block; margin-top: 20px; }


        /* 1. Contenitore principale: Fissato e centrato nel viewport */
        .fixed-centered-overlay {
            /* Posizionamento fisso: rimane fermo durante lo scroll e lo zoom */
            position: fixed; 
            
            /* Sposta il punto d'inizio nell'angolo centrale (50% x 50%) */
            top: 50%;
            left: 50%;
            
            /* Sposta l'elemento indietro della metà della sua dimensione: centramento perfetto */
            transform: translate(-50%, -50%);
            
            /* Assicura che l'elemento sia sempre sopra il resto del contenuto */
            z-index: 9999; 
            
            /* Opzionale: per vedere l'area del contenitore */
            /* border: 1px solid blue; */
        }

        .fixed-centered-overlay svg path:hover {
            fill: red;
            fill-opacity: 0.3;
        }

        /* 2. Elementi interni: Sovrapposti con precisione */
        .overlay-element {
            /* Rimuove gli spazi extra che il browser potrebbe inserire */
            display: block; 
            
            /* Permette a entrambi gli elementi di sovrapporsi con precisione */
            position: absolute;
            
            /* Centramento perfetto rispetto al contenitore .fixed-centered-overlay */
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* 3. Stratificazione */
        .base-layer {
            z-index: 10; /* Livello inferiore */
            width: 1250px; /* Esempio: imposta la dimensione del tuo PNG */
            height: auto;
        }

        .top-layer {
            z-index: 20; /* Livello superiore (apparirà sopra il PNG) */
            width: 640px; /* Esempio: imposta la dimensione del tuo SVG */
            height: auto;
            top: -7px;
        }

        /* Stili dinamici aggiunti da JS */
        .room.accessible:hover {
            fill: #28a745; /* Verde per accesso consentito */
            fill-opacity: 0.5;
        }

        .room.inaccessible:hover {
            fill: #dc3545; /* Rosso per accesso negato */
            fill-opacity: 0.5;
        }

        /* NUOVA CLASSE: Stile della STANZA CORRENTE */
        .room.current-position {
            fill: gold; /* Colore giallo fisso per la posizione attuale */
            fill-opacity: 0.4;
            cursor: default; /* Non sembra cliccabile */
        }
        /* Override per impedire all'hover di cambiare la stanza corrente */
        .room.current-position:hover {
            fill: gold; 
            fill-opacity: 0.4;
        }

        .gate {
            transition: fill 0.3s, opacity 0.3s;
        }

        .gate-popup {
            position: absolute;
            background: rgba(0,0,0,0.85);
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 10000;
            display: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.5);
            pointer-events: auto; /* Permette il click sugli elementi interni */
            text-align: left;
            transform: translate(-50%, -100%);
            margin-top: -10px;
        }


    </style>
</head>
<body>

    <div id="access-message" class="result"></div>
    <?php if (!$isMonitorMode): ?>
    <div id="current-pos-badge">
        <i class="fas fa-map-marker-alt"></i>
        <span id="current-pos-text" data-current-pos="<?php echo $LastPos; ?>">
            Ti trovi nella Stanza: <strong><?php echo $LastPos;?></strong>
        </span>
    </div>
    <?php endif; ?>

    <!-- FontAwesome per l'icona (se non è già importato altrove, lo aggiungiamo qui) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Stile moderno per l'indicatore di posizione */
        #current-pos-badge {
            position: fixed;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(44, 62, 80, 0.9);
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 1.1em;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            z-index: 100000;
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }
        #current-pos-badge i {
            color: #f1c40f;
            font-size: 1.2em;
            animation: pulse-marker 2s infinite;
        }
        #current-pos-badge strong {
            color: #f1c40f;
            font-size: 1.2em;
        }

        @keyframes pulse-marker {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
    <div class="fixed-centered-overlay">
        <img src="img/piantina2.png" class="overlay-element base-layer" alt=""> 
        
        <svg class="overlay-element top-layer" width="2097" height="2171" viewBox="0 0 2097 2171" fill="none" xmlns="http://www.w3.org/2000/svg" style="padding-left: 3px; overflow: visible;">
            <path class="room" id="1" fill-opacity="0" d="M845.713 1459.5L643.713 1812.5L744.713 1984.5L907.713 1886.5H987.713V2163.5H1109.71V1897.5H1173.71V1601.5H1338.71L1253.71 1459.5H845.713Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="2" fill-opacity="0" d="M915.213 1895L748.213 1992.5L847.213 2162H987.713V1895H915.213Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="3" fill-opacity="0" d="M1178.71 1901H1114.71V2170H1260.1L1466.21 1813H1178.71V1901Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="4" fill-opacity="0" d="M1464.71 1810H1179.71V1609.08H1348.71L1464.71 1810Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="5" fill-opacity="0" d="M290.253 1451L704.213 1690L765.33 1583L541.896 1454.5L765.33 1325.5L701.213 1214.45L438.713 1366L479.713 1091.5H356.213L290.253 1451Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="6" fill-opacity="0" d="M632.72 1812L701.713 1692.5L549.456 1602L428.213 1812H632.72Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="7" fill-opacity="0" d="M426.713 1811.94L548.213 1601.5L287.713 1448.5H2.3783L212.213 1811.94H426.713Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="8" fill-opacity="0" d="M210.482 1085.5L3.21289 1444.5H290.713L357.213 1085.5H210.482Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="9" fill-opacity="0" d="M628.713 1092.5H482.213L441.713 1360.46L697.995 1212.5L628.713 1092.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="10" fill-opacity="0" d="M768.713 1326.33L546.713 1454.5L762.276 1576.5L837.713 1445.84L768.713 1326.33Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="11" fill-opacity="0" d="M483.213 573.5H89.2129L0.578125 725.5L208.713 1086H632.713L758.713 861.5L534.213 752V696.445L483.213 667V573.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="12" fill-opacity="0" d="M211.436 362L92.2129 568.5H488.713V362H211.436Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="13" fill-opacity="0" d="M632.713 362.5H490.213V665.793L540.713 694.95L753.668 572L632.713 362.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="14" fill-opacity="0" d="M839.213 721.358L754.713 575L536.713 696.5V751.5L759.457 859.5L839.213 721.358Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="15" fill-opacity="0" d="M912.713 595H775.713L844.713 724H1257.23L1331.71 595H1092.71V362.5H912.713V595Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="16" fill-opacity="0" d="M1096.21 420V591.5H1333.2L1432.21 420H1096.21Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="17" fill-opacity="0" d="M912.713 360H640.213L772.713 593.5H912.713V360Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="18" fill-opacity="0" d="M1092.21 357.5H808.213V249.269L723.743 200.5L839.213 0.5H1092.21V357.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="19" fill-opacity="0" d="M634.878 358L723.213 205L806.713 250.5V358H634.878Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="20" fill-opacity="0" d="M1434.21 416.5H1095.71V1.5H1261.01L1467.41 359L1434.21 416.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="21" fill-opacity="0" d="M1427.21 784.5V444L1265.39 723.5L1408.86 972L1471.21 936V889H1818.71V577.5H1765.21V784.5H1427.21Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="22" fill-opacity="0" d="M1474.71 362.424L1430.21 439.5V781H1761.21V362.424H1474.71Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="23" fill-opacity="0" d="M2009.71 575H1765.21V362.824H1887.21L2009.71 575Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="24" fill-opacity="0" d="M2010.71 578.5H1821.71L1820.71 891.151H1998.21L2094.72 724L2010.71 578.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="25" fill-opacity="0" d="M1888.21 1086.5H1773.71V894.242H1999.21L1888.21 1086.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="26" fill-opacity="0" d="M1472.21 941.318L1405.21 980L1472.21 1084H1770.71V894.5H1472.21V941.318Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="27" fill-opacity="0" d="M1397.31 1213.5L1262.21 1447.5L1357.71 1605H1814.21V1283.3H1518.21L1397.31 1213.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="28" fill-opacity="0" d="M1469.21 1089.76L1399.21 1211L1520.21 1280H1723.71V1089.76H1469.21Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="29" fill-opacity="0" d="M1998.21 1280.5H1725.71V1091H1888.81L1998.21 1280.5Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="30" fill-opacity="0" d="M2001.21 1287H1817.71V1609H2004.68L2095.9 1451L2001.21 1287Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="31" fill-opacity="0" d="M2004.21 1610H1677.71V1812.65H1887.21L2004.21 1610Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="32" fill-opacity="0" d="M1675.71 1610H1359.21L1472.71 1811.5H1675.71V1610Z" fill="#D9D9D9" stroke="black" />
            <path class="room" id="33" fill-opacity="0" d="M1254.71 1452.5H844.713L637.866 1088.77L844.713 730.5H1257.87L1464.71 1088.77L1254.71 1452.5Z" fill="#D9D9D9" stroke="black" />
            <path class="gate" id="-1" d="M993.713 1893.5V1939.5H976.713V1893.5H993.713Z" fill="#FF0000" />
            <path class="gate" id="-2" d="M1125.71 1909.5H1103.71V1969.5H1125.71V1909.5Z" fill="#FF0000" />
            <path class="gate" id="-3" d="M1307.71 1595.5H1255.71V1617.5H1307.71V1595.5Z" fill="#FF0000" />
            <path class="gate" id="-4" d="M702.713 1666.5L742.213 1598.5L760.213 1609.5L721.213 1677.5L702.713 1666.5Z" fill="#FF0000" />
            <path class="gate" id="-5" d="M1093.71 1462.5H1005.71V1434.5H1093.71V1462.5Z" fill="#FF0000" />
            <path class="gate" id="-6" d="M1341.51 1568.5L1297.21 1493.5L1279.21 1504L1322.71 1579.36L1341.51 1568.5Z" fill="#FF0000" />
            <path class="gate" id="-7" d="M654.713 1648.5L609.213 1623.5L601.213 1637L645.713 1662L654.713 1648.5Z" fill="#FF0000" />
            <path class="gate" id="-8" d="M518.713 1571L484.713 1552L476.713 1564.5L509.713 1583.5L518.713 1571Z" fill="#FF0000" />
            <path class="gate" id="-9" d="M347.713 1213H324.213L316.713 1249H341.213L347.713 1213Z" fill="#FF0000" />
            <path class="gate" id="-10" d="M474.713 1220.5H451.213L445.213 1258.5H468.713L474.713 1220.5Z" fill="#FF0000" />
            <path class="gate" id="-11" d="M582.952 1268L545.713 1289.5L558.974 1308L596.213 1286.5L582.952 1268Z" fill="#FF0000" />
            <path class="gate" id="-12" d="M633.438 1393.5L667.213 1374C668.468 1376.38 674.58 1385.57 675.008 1385.77C675.025 1385.76 675.032 1385.76 675.029 1385.76C675.026 1385.77 675.019 1385.77 675.008 1385.77C674.228 1386.06 652.641 1398.62 641.713 1405L633.438 1393.5Z" fill="#FF0000" />
            <path class="gate" id="-13" d="M674.213 1518L642.713 1500.5L632.213 1514L662.713 1531.5L674.213 1518Z" fill="#FF0000" />
            <path class="gate" id="-14" d="M731.213 1219.5L698.713 1237.88L742.713 1315L775.607 1296.39L731.213 1219.5Z" fill="#FF0000" />
            <path class="gate" id="-15" d="M451.713 1071H374.713V1103H451.713V1071Z" fill="#FF0000" />
            <path class="gate" id="-16" d="M207.713 560.5H156.713V585.5H207.713V560.5Z" fill="#FF0000" />
            <path class="gate" id="-17" d="M446.213 562.5H393.713V584.5H446.213V562.5Z" fill="#FF0000" />
            <path class="gate" id="-18" d="M532.148 683.728L493.648 661.5L482.713 676.728L521.148 700.728L532.148 683.728Z" fill="#FF0000" />
            <path class="gate" id="-19" d="M686.213 817L649.713 799L638.832 815L676.213 833.5L686.213 817Z" fill="#FA0000" />
            <path class="gate" id="-20" d="M737.713 955.5L696.713 933.075L740.213 857L781.529 879.608L737.713 955.5Z" fill="#FF0000" />
            <path class="gate" id="-21" d="M1093.96 708.5H1005.96V740.5H1093.96V708.5Z" fill="#FF0000" />
            <path class="gate" id="-22" d="M1143.71 584.5H1108.71V607.5H1143.71V584.5Z" fill="#FF0000" />
            <path class="gate" id="-23" d="M921.713 434H907.713V395.5H921.713V434Z" fill="#FF0000" />
            <path class="gate" id="-24" d="M1059.71 374.5H1013.71V350.5H1059.71V374.5Z" fill="#FF0000" />
            <path class="gate" id="-25" d="M817.713 335.5H801.713V304.5H817.713V335.5Z" fill="#FF0000" />
            <path class="gate" id="-26" d="M1103.71 408.5H1082.71V373.5H1103.71V408.5Z" fill="#FF0000" />
            <path class="gate" id="-27" d="M1355.4 860.479L1326.21 875.5L1368.71 950.991L1398.71 935.5L1355.4 860.479Z" fill="#FF0000" />
            <path class="gate" id="-28" d="M1521.71 799.5H1456.71V775.5H1521.71V799.5Z" fill="#FF0000" />
            <path class="gate" id="-29" d="M1737.71 798.5H1671.71V775.5H1737.71V798.5Z" fill="#FF0000" />
            <path class="gate" id="-30" d="M1810.21 584.5H1776.21V569.5H1810.21V584.5Z" fill="#FF0000" />
            <path class="gate" id="-31" d="M1830.71 634.5H1809.71V601.5H1830.71V634.5Z" fill="#FF0000" />
            <path class="gate" id="-32" d="M1829.71 882.5H1810.71V849.5H1829.71V882.5Z" fill="#FF0000" />
            <path class="gate" id="-33" d="M1817.71 900.5H1783.71V883.5H1817.71V900.5Z" fill="#FF0000" />
            <path class="gate" id="-34" d="M1765.71 883.5V900.5H1731.71V883.5H1765.71Z" fill="#FF0000" />
            <path class="gate" id="-35" d="M1357.62 1313L1327.21 1296.5L1370.21 1220.66L1401.21 1237.5L1357.62 1313Z" fill="#FF0000" />
            <path class="gate" id="-36" d="M1713.71 1292.5H1680.71V1273.5H1713.71V1292.5Z" fill="#FF0000" />
            <path class="gate" id="-37" d="M1769.71 1292.5H1736.71V1273.5H1769.71V1292.5Z" fill="#FF0000" />
            <path class="gate" id="-38" d="M1830.71 1595.5H1803.71V1562.5H1830.71V1595.5Z" fill="#FF0000" />
            <path class="gate" id="-39" d="M1725.46 1620.5H1691.46V1596.5H1725.46V1620.5Z" fill="#FF0000" />
            <path class="gate" id="-40" d="M1665.46 1620.5H1631.46V1596.5H1665.46V1620.5Z" fill="#FF0404" />
        </svg>




        

    </div>

    <!--    SCRIPT MAPPA        -->

    <script>
    const allGatesData = <?php echo json_encode($allGatesData); ?>;
    const userBadgeLevel = <?php echo $userBadgeLevel; ?>;
    
    let gatePopup = null;
    function showGatePopup(x, y, info) {
        if (!gatePopup) {
            gatePopup = document.createElement('div');
            gatePopup.className = 'gate-popup';
            document.body.appendChild(gatePopup);
            
            // Nasconde il popup al click fuori
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.gate') && !e.target.closest('.gate-popup')) {
                    gatePopup.style.display = 'none';
                }
            });
        }
        
        const lockedStatus = info.IsLocked == 1 ? "<span style='color:red;font-weight:bold'>BLOCCATO</span>" : "<span style='color:green;font-weight:bold'>SBLOCCATO</span>";
        const lockButtonText = info.IsLocked == 1 ? "Sblocca Porta" : "Blocca Porta";
        
        gatePopup.innerHTML = `
            <strong>Gate ID:</strong> ${info.IdGate}<br>
            <strong>Stato:</strong> ${lockedStatus}<br>
            <strong>Collega:</strong> Stanza ${info.IdSectorA} e Stanza ${info.IdSectorB}<br>
            <strong>Livello Sicurezza:</strong> Livello ${info.SecurityLevel}<br>
            <strong>Usura:</strong> ${info.Wear}% <span style='color:yellow;font-weight:bold'>${info.Wear >= 33 ? 'MANUTENZIONE RICHIESTA' : ''}</span>
            <br><button onclick="toggleGateLock(${info.IdGate}, ${info.IsLocked})" style="margin-top:8px; padding:4px 8px; cursor:pointer;">${lockButtonText}</button>
        `;
        gatePopup.style.left = x + 'px';
        gatePopup.style.top = y + 'px';
        gatePopup.style.display = 'block';
    }

    // 1. Variabile per i settori accessibili
    let accessibleSectors = <?php echo json_encode($accessibleSectors); ?>;
    
    // Funzione per inizializzare gli eventi (chiamata al caricamento e dopo l'accesso GRANTED)
    function initializeMapEvents(currentPos) {
        
        // 1. Aggiornamento vista dei Gate in base alla posizione (per utente normale)
        document.querySelectorAll('.gate').forEach(gate => {
            const gateId = Math.abs(parseInt(gate.id));
            const gateInfo = allGatesData[gateId];
            
            gate.style.pointerEvents = 'none'; // Nessun click per l'utente sui gate

            if (gateInfo) {
                const isBordering = (gateInfo.IdSectorA == currentPos || gateInfo.IdSectorB == currentPos);
                
                if (isBordering) {
                    gate.style.opacity = '1';
                    
                    let destSector = (gateInfo.IdSectorA == currentPos) ? gateInfo.IdSectorB : gateInfo.IdSectorA;
                    let isAccessible = (userBadgeLevel >= gateInfo.SecurityLevel) && (gateInfo.Wear < 100);
                    
                    // regola utente pending: non può uscire dalla hall
                    if (userBadgeLevel === 1 && currentPos == 1 && destSector != 1) {
                        isAccessible = false;
                    }
                    
                    // Regola porta bloccata
                    if (gateInfo.IsLocked == 1) {
                        isAccessible = false;
                    }

                    if (isAccessible) {
                        gate.style.fill = '#28a745'; // Verde
                    } else {
                        gate.style.fill = '#dc3545'; // Rosso
                    }
                } else {
                    gate.style.fill = '#888888';
                    gate.style.opacity = '0'; // Invisibile se non confinante
                }
            }
        });

        // Prima di tutto, marca la posizione corrente
        document.querySelectorAll('.room').forEach(room => {
            room.classList.remove('current-position'); // Rimuovi la classe da tutti
            if (room.id == currentPos) {
                room.classList.add('current-position'); // Aggiungi la classe alla posizione attuale
            }
        });


        document.querySelectorAll('.room').forEach(room => {
            const roomId = room.id;

            // Rimuovi vecchi listener per evitare duplicati (necessario quando si re-inizializza)
            room.removeEventListener('mouseover', handleMouseOver);
            room.removeEventListener('mouseout', handleMouseOut);
            room.removeEventListener('click', handleClick); 
            
            // Aggiungi i nuovi listener

            // MOUSEOVER (usiamo una funzione esterna per poterla rimuovere)
            room.addEventListener('mouseover', handleMouseOver);

            // MOUSEOUT
            room.addEventListener('mouseout', handleMouseOut);
            
            // CLICK
            room.addEventListener('click', handleClick);
        });
    }


    // Funzione MOUSEOVER (Logica Hover)
    function handleMouseOver() {
        // Recupera la posizione corrente dal DOM (la fonte aggiornata)
        const currentPos = document.getElementById('current-pos-text').dataset.currentPos;
        const roomId = this.id; // 'this' è l'elemento room (path) cliccato

        // NON TOCCARE LA STANZA CORRENTE!
        if (roomId == currentPos) {
            return; 
        }
        
        // CONTROLLO DI ACCESSO (usa l'array 'accessibleSectors' aggiornato)
        if (accessibleSectors.includes(roomId)) {
            this.classList.add('accessible');
            this.classList.remove('inaccessible');
        } else {
            this.classList.add('inaccessible');
            this.classList.remove('accessible');
        }
    }


    // Funzione MOUSEOUT (Ripulisce l'Hover)
    function handleMouseOut() {
        // Rimuove le classi dinamiche, ripristinando lo stato base (trasparente o current-position)
        this.classList.remove('accessible', 'inaccessible');
    }
    
    
    // Funzione CLICK (Logica AJAX)
    function handleClick() {
        const clickedId = this.id;
        const currentPos = document.getElementById('current-pos-text').dataset.currentPos;
        
        // Se si clicca sulla stanza corrente (il click su inaccessibile ora è permesso per generare Warning)
        if (clickedId == currentPos) {
            return;
        }

        // Effettua la chiamata AJAX
        fetch('gates.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sector_id : clickedId }) 
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Errore di rete o del server.');
            }
            return response.json();
        })
        .then(data => {
            
            // 1. Mostra il messaggio di stato come TOAST
            const resultElement = document.getElementById('access-message'); 
            resultElement.textContent = data.message;
            resultElement.className = data.status === 'success' ? 'result success show' : 'result error show';
            
            // Nascondi automaticamente dopo 4 secondi
            if(window.accessMessageTimeout) clearTimeout(window.accessMessageTimeout);
            window.accessMessageTimeout = setTimeout(() => {
                resultElement.classList.remove('show');
            }, 4000);

            // 2. GESTIONE ACCESSO CONSENTITO (Aggiornamento Mappa Senza Ricarica)
            if (data.status === 'success' && data.new_pos) {
                
                // a) AGGIORNA LO STATO GLOBALE NELL'HTML
                const posElement = document.getElementById('current-pos-text');
                posElement.innerHTML = `Ti trovi nella Stanza: <strong>${data.new_pos}</strong>`;
                posElement.dataset.currentPos = data.new_pos; // *** ESSENZIALE: Aggiorna il data attribute! ***
                
                // b) Rimuovi il messaggio di "Hall" se ci si sposta dalla Hall (Stanza 1)
                if (data.new_pos != '1') {
                    // Puoi rifare la logica PHP qui per il messaggio hall se necessario.
                }

                // c) Aggiorna le posizioni possibili lato client (senza ricaricare tutta la pagina)
                if (Array.isArray(data.accessible_sectors)) {
                    accessibleSectors = data.accessible_sectors.map(String);
                } else {
                    accessibleSectors = [];
                }

                // d) Riesegui il wiring degli eventi con la nuova posizione corrente
                initializeMapEvents(String(data.new_pos));
                
                // e) Avvisa il parent (dashboard) per aggiornare la UI dinamicamente
                if (window.parent) {
                    window.parent.postMessage({ type: 'roomMoved', newPos: data.new_pos }, '*');
                }
            }
        })
        .catch(error => {
            console.error('Si è verificato un errore:', error);
            document.getElementById('access-message').textContent = 'Errore di comunicazione con il server.';
            document.getElementById('access-message').className = 'result error';
        });
    }

    // Avvia gli eventi al caricamento della pagina solo per gli utenti NON admin monitor
    const isMonitorMode = <?php echo $isMonitorMode ? 'true' : 'false'; ?>;
    if (!isMonitorMode) {
        initializeMapEvents(<?php echo json_encode($LastPos); ?>);
        
        // --- POLLING PER AGGIORNAMENTI IN TEMPO REALE PER UTENTI NORMALI ---
        setInterval(function() {
            // Chiedi al server lo stato di tutti i gate e delle emergenze
            fetch('gates_status_api.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Aggiorna i dati globali
                    Object.keys(data.gates).forEach(id => {
                        if (allGatesData[id]) {
                            allGatesData[id].Wear = data.gates[id].Wear;
                            allGatesData[id].IsLocked = data.gates[id].IsLocked;
                        }
                    });
                    
                    // Ricalcoliamo accessibilità base (il server ci restituisce i nuovi accessible_sectors)
                    if (Array.isArray(data.accessible_sectors)) {
                        accessibleSectors = data.accessible_sectors.map(String);
                    }
                    
                    // Ridisegnamo la ui con i nuovi dati
                    const currentPos = document.getElementById('current-pos-text').dataset.currentPos;
                    initializeMapEvents(currentPos);
                }
            })
            .catch(err => console.error("Errore di polling gates:", err));
        }, 3000); // 3 secondi
    }

    // === REALTIME ADMIN OVERLAY (BadgeLevel 4, Modalità Monitor) ===
    if (isMonitorMode) {
        // Disabilita i click per le stanze dell'admin
        document.querySelectorAll('.room').forEach(r => r.style.pointerEvents = 'none');
        
        // Abilita i click per i Gate
        document.querySelectorAll('.gate').forEach(gate => {
            gate.style.pointerEvents = 'auto';
            gate.style.cursor = 'pointer';
            
            const gateId = Math.abs(parseInt(gate.id));
            const info = allGatesData[gateId];
            
            // Colora porta a seconda dello stato di blocco
            if (info) {
                if (info.Wear >= 33) {
                    gate.style.fill = '#ffc107'; // Giallo se richiede riparazione
                } else if (info.IsLocked == 1) {
                    gate.style.fill = '#dc3545'; // Rosso se bloccata
                } else {
                    gate.style.fill = '#28a745'; // Verde se sbloccata
                }
            }
            
            gate.addEventListener('click', function(e) {
                e.stopPropagation(); // Evita che il click si propaghi se necessario
                if (info) {
                    showGatePopup(e.pageX, e.pageY, info);
                }
            });
        });

        // Funzione JS per bloccare/sbloccare
        window.toggleGateLock = function(gateId, currentLocked) {
            const newLockedState = currentLocked == 1 ? 0 : 1;
            fetch('toggle_gate_lock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ gate_id: gateId, is_locked: newLockedState })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    allGatesData[gateId].IsLocked = newLockedState; // update local data
                    gatePopup.style.display = 'none'; // hide popup to force redraw next click
                    
                    // Aggiorna visivamente il colore del path
                    const gateElement = document.getElementById("-" + gateId);
                    if (gateElement) {
                        if (allGatesData[gateId].Wear >= 33) {
                            gateElement.style.fill = '#ffc107'; // Rimane giallo se rotto
                        } else if (newLockedState == 1) {
                            gateElement.style.fill = '#dc3545';
                        } else {
                            gateElement.style.fill = '#28a745';
                        }
                    }
                    
                    //alert(newLockedState ? "Porta bloccata con successo." : "Porta sbloccata con successo.");
                } else {
                    alert("Errore nell'aggiornamento della porta: " + (data.error || ""));
                }
            })
            .catch(err => {
                console.error(err);
                alert("Errore di rete.");
            });
        };

        const svg = document.querySelector('svg.top-layer');
        const overlayGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        overlayGroup.setAttribute('id', 'admin-live-overlay');
        svg.appendChild(overlayGroup);

        // id_badge -> { id_sector, label, color, el }
        const people = new Map();
        // id_sector -> [id_badge, id_badge, ...] (ordine usato per lo stacking verticale)
        const sectorStacks = new Map();
        let lastEventId = 0;

        const palette = [
            '#2563eb', '#16a34a', '#dc2626', '#9333ea', '#ea580c', '#fcf807ff',
            '#4f46e5', '#65a30d', '#be123c', '#7c3aed', '#b45309', '#a8b80dff'
        ];
        function colorFor(badgeId) {
            return palette[Math.abs(parseInt(badgeId, 10)) % palette.length];
        }

        // Dizionario per le coordinate personalizzate del centro (es. per corridoi a L o forme non convesse)
        // Se si nota un'icona fuori posto, basta aggiungere l'ID della stanza qui con le coordinate desiderate.
        // BBox Center spesso finisce fuori dalla stanza per forme a U o L (es. corridoi lunghi).
        const customCenters = {
            '21': { x: 1385.0, y: 845.1 },
        };

        function roomCenter(roomId) {
            const strId = String(roomId);
            
            // 1. Controlla se abbiamo forzato un centro manuale
            if (customCenters[strId]) {
                return customCenters[strId];
            }

            // 2. Fallback sul Bounding Box (che fallisce per i poligoni non convessi/a U/a L)
            const room = document.getElementById(strId);
            if (!room || typeof room.getBBox !== 'function') return null;
            const b = room.getBBox();
            return { x: b.x + b.width / 2, y: b.y + b.height / 2 };
        }

        function upsertMarker(p) {
            const center = roomCenter(p.id_sector);
            if (!center) return;

            const key = String(p.id_badge);
            const existing = people.get(key);
            const color = existing?.color || colorFor(p.id_badge);
            const label = `${p.name ?? ''} ${p.surname ?? ''}`.trim() || `Badge ${p.id_badge}`;

            // Aggiorna gli stack per stanza
            const prevSector = existing?.id_sector;
            let needsPrevUpdate = false;
            if (prevSector != null && prevSector !== p.id_sector) {
                const prevArr = sectorStacks.get(String(prevSector));
                if (prevArr) {
                    sectorStacks.set(
                        String(prevSector),
                        prevArr.filter(id => id !== key)
                    );
                    needsPrevUpdate = true;
                }
            }

            const stackKey = String(p.id_sector);
            let stack = sectorStacks.get(stackKey);
            if (!stack) {
                stack = [];
                sectorStacks.set(stackKey, stack);
            }
            if (!stack.includes(key)) {
                stack.push(key);
            }

            let g = existing?.el;
            if (!g) {
                g = document.createElementNS('http://www.w3.org/2000/svg', 'g');

                // Piccolo "pin" tondo colorato (sempre visibile)
                const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                circle.setAttribute('r', '14');
                circle.setAttribute('cx', '0');
                circle.setAttribute('cy', '0');
                circle.setAttribute('fill', color);
                circle.setAttribute('fill-opacity', '0.98');
                circle.setAttribute('stroke', '#111827');
                circle.setAttribute('stroke-width', '2.5');

                // Etichetta tipo "pill" (visibile SOLO in hover)
                const bg = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                bg.setAttribute('x', '20');
                bg.setAttribute('y', '-40');
                bg.setAttribute('rx', '12');
                bg.setAttribute('ry', '12');
                // width dinamica calcolata dopo in base alla larghezza del testo
                bg.setAttribute('height', '60');
                bg.setAttribute('fill', '#f9fafb');
                bg.setAttribute('fill-opacity', '0.96');
                bg.setAttribute('stroke', '#111827');
                bg.setAttribute('stroke-width', '1.8');
                bg.setAttribute('opacity', '0');

                const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                text.setAttribute('x', '32');
                text.setAttribute('y', '6');
                text.setAttribute('font-size', '32');
                text.setAttribute('font-family', 'system-ui, -apple-system, BlinkMacSystemFont, \"Segoe UI\", sans-serif');
                text.setAttribute('fill', '#111827');
                text.setAttribute('font-weight', '700');
                text.setAttribute('opacity', '0');
                text.textContent = label;

                // Hover: mostra/nasconde label
                g.addEventListener('mouseenter', () => {
                    bg.setAttribute('opacity', '1');
                    text.setAttribute('opacity', '1');
                });
                g.addEventListener('mouseleave', () => {
                    bg.setAttribute('opacity', '0');
                    text.setAttribute('opacity', '0');
                });

                g.appendChild(circle);
                g.appendChild(bg);
                g.appendChild(text);
                overlayGroup.appendChild(g);

                // Imposta larghezza dinamica dell'etichetta in base al testo
                try {
                    const textLen = text.getComputedTextLength();
                    const paddingX = 28;
                    bg.setAttribute('width', String(textLen + paddingX * 2));
                } catch (e) {
                    // fallback: larghezza fissa ragionevole
                    bg.setAttribute('width', '360');
                }
            } else {
                // aggiorna label se cambia (es: snapshot vuota -> stream completa)
                const text = g.querySelector('text');
                const bg = g.querySelector('rect');
                if (text && text.textContent !== label) {
                    text.textContent = label;
                }
                if (text && bg) {
                    try {
                        const textLen = text.getComputedTextLength();
                        const paddingX = 28;
                        bg.setAttribute('width', String(textLen + paddingX * 2));
                    } catch (e) {
                        // lascia dimensioni correnti
                    }
                }
            }

            // Salvo o aggiorno i dati nel dizionario globale
            people.set(key, { ...p, label, color, el: g });

            // Funzione helper per ricalcolare le posizioni verticali di TUTTI gli utenti in un dato settore
            function redrawSectorStack(sectorId) {
                const sKey = String(sectorId);
                const sStack = sectorStacks.get(sKey);
                if (!sStack || sStack.length === 0) return;
                
                const sCenter = roomCenter(sectorId);
                if (!sCenter) return;

                const n = sStack.length;
                const spacing = 40;
                
                sStack.forEach((badgeStr, index) => {
                    const personData = people.get(badgeStr);
                    if (personData && personData.el) {
                        const offsetY = (index - (n - 1) / 2) * spacing;
                        personData.el.setAttribute('transform', `translate(${sCenter.x}, ${sCenter.y + offsetY})`);
                        // Portiamo l'elemento in primo piano per evitare sovrapposizioni strane
                        const parent = personData.el.parentNode;
                        if (parent) {
                            parent.appendChild(personData.el);
                        }
                    }
                });
            }

            // Ricalcola il settore corrente
            redrawSectorStack(stackKey);
            
            // Se l'utente si è spostato da un altro settore, ricalcola anche il settore precedente
            if (needsPrevUpdate) {
                redrawSectorStack(prevSector);
            }
        }

        function applySnapshot(list) {
            (list || []).forEach(p => {
                lastEventId = Math.max(lastEventId, p.id_access || 0);
                upsertMarker(p);
            });
        }

        // 1) Snapshot iniziale (tutti)
        fetch('admin_positions_snapshot.php')
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(data => applySnapshot(data.positions))
            .catch(() => {
                // niente: se fallisce, continueremo con lo stream
            })
            .finally(() => {
                // 2) Stream realtime (EventSource) con last_id per non perdere eventi
                const es = new EventSource(`admin_positions_stream.php?last_id=${encodeURIComponent(lastEventId)}`);

                es.addEventListener('move', (ev) => {
                    try {
                        const payload = JSON.parse(ev.data);
                        if (payload?.id_access) lastEventId = Math.max(lastEventId, payload.id_access);
                        upsertMarker(payload);
                    } catch (e) {
                        // ignore
                    }
                });

            });
    }
</script>

</body>
</html>
