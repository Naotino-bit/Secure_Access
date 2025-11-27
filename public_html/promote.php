<?php
require "db_connection.php";
session_start(); 

// 1. Controllo Login
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// 2. Controllo Permessi Admin
$adminEmail = $_SESSION['user'];
$queryAdmin = "
    SELECT B.BadgeLevel
    FROM Users U
    JOIN Badges B ON U.IdBadge = B.IdBadge
    WHERE U.Email = ?";
$stmt = $conn->prepare($queryAdmin);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$resAdmin = $stmt->get_result();
$rowAdmin = $resAdmin->fetch_assoc();

if(!$rowAdmin || $rowAdmin['BadgeLevel'] < 3) {
    die("ACCESSO NEGATO: Non hai i permessi per promuovere gli utenti");
}

// 3. Esecuzione Promozione
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    $targetEmail = $_POST['email_to_promote']; 
    $newLevel = intval($_POST['new_level']);

    if($newLevel < 1 || $newLevel > 3) {
        header("Location: dashboard.php?error=livello_non_valido");
        exit();
    }

    // Spostiamo la transazione DENTRO l'IF del POST
    $conn->begin_transaction();

    try{
        $isVis = false;
        $userData = NULL;

        $query = "SELECT * FROM Visitors WHERE Email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $targetEmail);
        $stmt->execute();
        $res = $stmt->get_result();

        if($userData = $res->fetch_assoc()){
            $isVis = true;
        }

        else{
            $stmt->close();
            $query = "SELECT * FROM Users WHERE Email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $targetEmail);
            $stmt->execute();
            $res = $stmt->get_result();
            $userData = $res->fetch_assoc();
        }
        $stmt->close();

        if (!$userData){
            throw new Exception("Utente non trovato nel database.");
        }

        $idBadge = $userData['IdBadge'];
        $query = "UPDATE Badges SET Badgelevel = ? WHERE IdBadge = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $newLevel, $idBadge);
        $stmt->execute();
        $stmt->close();

        if($isVis && $newLevel > 1){
            $role = ($newLevel == 2) ? "Dipendente" : "Admin";

            // Copia in Users
            $query = "INSERT INTO Users(IdBadge, Name, Surname, DateBirth, Email, Password, Role, is_verified) VALUES (?,?,?,?,?,?,?,1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssss", $idBadge, $userData['Name'], $userData['Surname'], $userData['DateBirth'], $userData['Email'], $userData['Password'], $role);
            $stmt->execute();
            $stmt->close();

            // Rimuovi da Visitors
            $query = "DELETE FROM Visitors WHERE Email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $targetEmail);
            $stmt->execute();
            $stmt->close();
        }

        elseif(!$isVis && $newLevel == 1){
            $query = "INSERT INTO Visitors(IdBadge, Name, Surname, DateBirth, Email, Password, Reason, is_verified) VALUES (?,?,?,?,?,?,'Ex-Dipendente',1)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssss", $idBadge, $userData['Name'], $userData['Surname'], $userData['DateBirth'], $userData['Email'], $userData['Password']);
            $stmt->execute();
            $stmt->close();

            $query = "DELETE FROM Users WHERE Email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $targetEmail);
            $stmt->execute();
            $stmt->close();
        }

        elseif (!$isVis && $newLevel > 1) { 
            $newRole = ($newLevel == 2) ? "Dipendente" : "Admin";
            $query = "UPDATE Users SET Role = ? WHERE Email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $newRole, $targetEmail);
            $stmt->execute();
            $stmt->close();
        }

        //I log

        $descrizioneLog = "Admin " . $_SESSION['user'] . " ha modificato ruolo utente " . $targetEmail . " al livello " . $newLevel;
        $conn->query("INSERT INTO SystemLogs (Description, Time) VALUES ('$descrizioneLog', NOW())");
        $conn->commit();
        header("Location: dashboard.php?success=ruolo_aggiornato");
        exit();


    } catch(Exception $e) {
        $conn->rollback();
        // Non fare echo prima dell'header, altrimenti il redirect si rompe.
        // Passiamo l'errore nell'URL per vederlo in dashboard (o loggalo)
        $errore = urlencode($e->getMessage());
        header("Location: dashboard.php?error=" . $errore);
        exit();
    }
} else {
    // Se provano ad aprire la pagina senza POST
    header("Location: dashboard.php");
    exit();
}
?>