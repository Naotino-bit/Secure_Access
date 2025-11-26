<?php
require "db_connection.php";
session_start();

$message = "";
$access_granted = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $badgeId = intval($_POST['badge_id']);
    $gateId = 1; // Simuliamo che sia il Tornello n.1 (Ingresso Principale)
    
    // 1. Controlliamo se il Badge esiste ed è valido
    $stmt = $conn->prepare("SELECT BadgeLevel, ExpirationDate FROM Badges WHERE IdBadge = ?");
    $stmt->bind_param("i", $badgeId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $timestamp = date("Y-m-d H:i:s");
    $esito = "DENIED"; // Di base è negato

    if ($row = $result->fetch_assoc()) {
        $scadenza = $row['ExpirationDate'];
        
        // Controllo Scadenza
        if ($scadenza != NULL && new DateTime() > new DateTime($scadenza)) {
            $message = "ACCESSO NEGATO: Badge Scaduto!";
            $access_granted = false;
            $esito = "EXPIRED";
        } else {
            // Controllo Livello (Esempio: serve almeno livello 1)
            if ($row['BadgeLevel'] >= 1) {
                $message = "ACCESSO CONSENTITO: Benvenuto!";
                $access_granted = true;
                $esito = "GRANTED";
            } else {
                $message = "ACCESSO NEGATO: Livello insufficiente.";
                $access_granted = false;
                $esito = "LOW_LEVEL";
            }
        }
    } else {
        $message = "ACCESSO NEGATO: Badge non trovato.";
        $access_granted = false;
        $esito = "NOT_FOUND";
    }
    $stmt->close();

    // 2. REGISTRAZIONE LOG NELLA TABELLA ACCESSES
    // Nota: Salviamo IdBadge, non IdUser/IdVisitor!
    $logStmt = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge) VALUES (?, ?, ?, ?)");
    $logStmt->bind_param("ssii", $timestamp, $esito, $gateId, $badgeId);
    $logStmt->execute();
    $logStmt->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulazione Tornello</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #333; color: white; }
        .scanner-box { background: #444; padding: 30px; border-radius: 10px; display: inline-block; }
        input[type="number"] { padding: 10px; font-size: 1.2em; width: 100px; text-align: center; }
        button { padding: 10px 20px; font-size: 1.2em; cursor: pointer; background-color: #007bff; color: white; border: none; }
        
        .result { margin-top: 20px; padding: 20px; font-size: 1.5em; border-radius: 5px; }
        .success { background-color: #28a745; }
        .error { background-color: #dc3545; }
    </style>
</head>
<body>

    <h1>Simulazione Gate #1</h1>
    
    <div class="scanner-box">
        <form method="POST">
            <label>Scansiona Badge (Inserisci ID):</label><br><br>
            <input type="number" name="badge_id" required autofocus placeholder="ID">
            <button type="submit">ENTRA</button>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="result <?php echo $access_granted ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <br><br>
    <a href="dashboard.php" style="color: #ccc;">Torna alla Dashboard Admin</a>

</body>
</html>


