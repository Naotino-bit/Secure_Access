<?php
require "db_connection.php";
session_start();

$message = "";
$access_granted = null;


if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user']; 

// Recupero dati utente e LIVELLO BADGE
$query = "
    SELECT U.Name, U.Surname, U.IdBadge, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
    FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge WHERE U.Email = ?
    UNION
    SELECT V.Name, V.Surname, V.IdBadge, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
    FROM Visitors V JOIN Badges B ON V.IdBadge = B.IdBadge WHERE V.Email = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $email, $email);
$stmt->execute();
$result = $stmt->get_result();
$badge = $result->fetch_assoc();

$currentUser = null;
$badgeLevel = 0;

$stmt->close();


//2. Troviamo l'ultima posizione dell'utente se non esite è fuori dalla struttura
$LastPositionQuery = $conn->prepare("SELECT * FROM Accesses WHERE IdBadge = ? AND RESULT = 'GRANTED' ORDER BY IdAccess DESC;");
$LastPositionQuery->bind_param("i", $badge['IdBadge']);
$LastPositionQuery->execute();
$LastPositionResult = $LastPositionQuery->get_result();
if (!$LastPositionResult) { 
    echo "Appena entrato nella struttura, sei nella hall";
    $LastPos = 1;
} else {
    $row = $LastPositionResult->fetch_assoc();
    $LastPos = $row["IdSectorTo"];
    //  echo "Ultima Pos: $LastPos";
}

$LastPositionQuery->close();


//3. Recupero tutte le posizioni dove l'utente può andare e le mostro in una lista
// Con due join recupero anche i nomi delle stanze
$PossiblePositionQuery = $conn->prepare("SELECT g.IdGate, g.SecurityLevel, g.IdSectorA, sa.Description 
                                                AS SectorA_Description, g.IdSectorB, sb.Description 
                                                AS SectorB_Description 
                                                FROM Gates g 
                                                LEFT JOIN Sectors sa ON g.IdSectorA = sa.IdSector 
                                                LEFT JOIN Sectors sb ON g.IdSectorB = sb.IdSector 
                                                WHERE (IdSectorA = ? OR IdSectorB = ?) AND SecurityLevel <= ?;");
$PossiblePositionQuery->bind_param("iii", $LastPos, $LastPos, $badge['BadgeLevel']);
$PossiblePositionQuery->execute();
$PossiblePositionResult = $PossiblePositionQuery->get_result();


if($_SERVER['REQUEST_METHOD'] == 'POST') {


    $data = json_decode($_POST['sector_id'], true);

    $SectorId = $data['sector'];
    $GateId   = $data['gate'];

    // LOGICA DI CONTROLLO
    if (!$badge) {
        $message = "ERRORE: Badge non esistente!";
        $access_granted = false;
        $esito = "NOT_FOUND";
    //} elseif (!$gate) {
    //    $message = "ERRORE: Gate non trovato nel sistema!";
    //    $access_granted = false;
    //    $esito = "ERROR";
    } elseif ($badge['ExpirationDate'] != NULL && new DateTime() > new DateTime($badge['ExpirationDate'])) {
        $message = "ACCESSO NEGATO: Badge Scaduto!";
        $access_granted = false;
        $esito = "EXPIRED";
    } elseif ($badge['BadgeLevel']) {
        // SE IL LIVELLO DEL BADGE È UGUALE O SUPERIORE AL LIVELLO DEL GATE
        //$message = "ACCESSO CONSENTITO: Benvenuto in " . htmlspecialchars($gate['Type']);
        $access_granted = true;
        $esito = "GRANTED";
    } else {
        $message = "ACCESSO NEGATO: Livello insufficiente per questa zona.";
        $access_granted = false;
        $esito = "LOW_LEVEL";
    }

        $timestamp = date("Y-m-d H:i:s");
        $logStmt = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge, IdSectorTo) VALUES (?, ?, ?, ?, ?)");
        $logStmt->bind_param("ssiii", $timestamp, $esito, $GateId, $badge['IdBadge'], $SectorId);
        $logStmt->execute();
        $logStmt->close();

        header("Location: gates.php");
        exit();
}







// 3. REGISTRIAMO IL LOG NEL DB
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
        body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #333; color: white; }
        .scanner-box { background: #444; padding: 30px; border-radius: 10px; display: inline-block; width: 400px; }
        select, input { width: 90%; padding: 10px; margin: 10px 0; font-size: 1.1em; border-radius: 5px; border: none; }
        button { width: 95%; padding: 10px; font-size: 1.2em; cursor: pointer; background-color: #007bff; color: white; border: none; margin-top: 15px; border-radius: 5px; }
        .result { margin-top: 20px; padding: 20px; font-size: 1.5em; border-radius: 5px; }
        .success { background-color: #28a745; }
        .error { background-color: #dc3545; }
        a { color: #ccc; text-decoration: none; display: block; margin-top: 20px; }
    </style>
</head>
<body>

    <h1>Simulazione Controllo Accessi</h1>
    <p>Ti trovi nella stanza <?php echo $LastPos ?></p>
    <form method="POST">
        <label>Seleziona Varco:</label>
                <select name="sector_id" required>
                    <?php
                    if ($PossiblePositionResult->num_rows > 0) {
                        while($row = $PossiblePositionResult->fetch_assoc()): 
                            if ($LastPos == $row['IdSectorA']){
                                $IdSector = $row['IdSectorB']; 
                                $Description = $row['SectorB_Description'];
                            } else {
                                $IdSector = $row['IdSectorA'];
                                $Description = $row['SectorA_Description'];
                            }

                        ?>
                            <option value='<?php echo json_encode(["sector" => $IdSector, "gate" => $row["IdGate"]]); ?>'>
                                <?php echo "Stanza:$IdSector $Description  Porta:{$row['IdGate']}"; ?>
                            </option>
                        <?php endwhile; 
                    } else {
                        echo "<option value=''>Nessun Gate nel Database</option>";
                    }
                    ?>
                </select>
        <button type="submit">PASSA</button>
    </form>
    <a href="logs.php">Visualizza Storico Accessi &rarr;</a>
    <a href="dashboard.php">Torna alla Dashboard</a>
</body>
</html>
