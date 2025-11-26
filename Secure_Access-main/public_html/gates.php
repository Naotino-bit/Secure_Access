<?php
require "db_connection.php";
session_start();

$message = "";
$access_granted = null;

// SE ABBIAMO INVIATO IL FORM (Tentativo di accesso)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $badgeId = intval($_POST['badge_id']);
    $gateId = intval($_POST['gate_id']); // Ora prendiamo l'ID scelto dal menu
    
    // 1. Recuperiamo info sul Badge
    $stmtBadge = $conn->prepare("SELECT BadgeLevel, ExpirationDate FROM Badges WHERE IdBadge = ?");
    $stmtBadge->bind_param("i", $badgeId);
    $stmtBadge->execute();
    $resBadge = $stmtBadge->get_result();
    $badge = $resBadge->fetch_assoc();
    $stmtBadge->close();

    // 2. Recuperiamo info sul Gate (Livello richiesto)
    $stmtGate = $conn->prepare("SELECT SecurityLevel, Type FROM Gates WHERE IdGate = ?");
    $stmtGate->bind_param("i", $gateId);
    $stmtGate->execute();
    $resGate = $stmtGate->get_result();
    $gate = $resGate->fetch_assoc();
    $stmtGate->close();

    $timestamp = date("Y-m-d H:i:s");
    $esito = "DENIED"; 

    // LOGICA DI CONTROLLO
    if (!$badge) {
        $message = "ERRORE: Badge non esistente!";
        $access_granted = false;
        $esito = "NOT_FOUND";
    } elseif (!$gate) {
        $message = "ERRORE: Gate non trovato nel sistema!";
        $access_granted = false;
        $esito = "ERROR";
    } elseif ($badge['ExpirationDate'] != NULL && new DateTime() > new DateTime($badge['ExpirationDate'])) {
        $message = "ACCESSO NEGATO: Badge Scaduto!";
        $access_granted = false;
        $esito = "EXPIRED";
    } elseif ($badge['BadgeLevel'] >= $gate['SecurityLevel']) {
        // SE IL LIVELLO DEL BADGE È UGUALE O SUPERIORE AL LIVELLO DEL GATE
        $message = "ACCESSO CONSENTITO: Benvenuto in " . htmlspecialchars($gate['Type']);
        $access_granted = true;
        $esito = "GRANTED";
    } else {
        $message = "ACCESSO NEGATO: Livello insufficiente per questa zona.";
        $access_granted = false;
        $esito = "LOW_LEVEL";
    }

    // 3. REGISTRIAMO IL LOG NEL DB
    $logStmt = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge) VALUES (?, ?, ?, ?)");
    $logStmt->bind_param("ssii", $timestamp, $esito, $gateId, $badgeId);
    $logStmt->execute();
    $logStmt->close();
}

// Recuperiamo la lista dei Gates per popolare il menu a tendina
$gatesList = $conn->query("SELECT * FROM Gates");
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
    
    <div class="scanner-box">
        <form method="POST">
            <label>Seleziona Varco:</label>
            <select name="gate_id" required>
                <?php 
                if ($gatesList && $gatesList->num_rows > 0) {
                    while($row = $gatesList->fetch_assoc()): ?>
                        <option value="<?php echo $row['IdGate']; ?>">
                            <?php echo htmlspecialchars($row['Type']); ?> (Liv. <?php echo $row['SecurityLevel']; ?>)
                        </option>
                    <?php endwhile; 
                } else {
                    echo "<option value=''>Nessun Gate nel Database</option>";
                }
                ?>
            </select>

            <label>ID Badge (Simula NFC):</label>
            <input type="number" name="badge_id" required placeholder="Es. 14" autofocus>

            <button type="submit">SCANSIONA</button>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="result <?php echo $access_granted ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <a href="logs.php">Visualizza Storico Accessi &rarr;</a>
    <a href="dashboard.php">Torna alla Dashboard</a>

</body>
</html>