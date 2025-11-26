<?php
require "db_connection.php";
session_start(); // CORRETTO: era sessiom_start

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
    WHERE U.Email = ?"; // CORRETTO: era Emain
$stmt = $conn->prepare($queryAdmin);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$resAdmin = $stmt->get_result();
$rowAdmin = $resAdmin->fetch_assoc(); // CORRETTO: era fetch_assoch

if(!$rowAdmin || $rowAdmin['BadgeLevel'] < 3) {
    die("ACCESSO NEGATO: Non hai i permessi per promuovere gli utenti");
}

// 3. Esecuzione Promozione
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // CORRETTO: Uniformato il nome variabile (era targhetEmail)
    $targetEmail = $_POST['email_to_promote']; 
    $newLevel = intval($_POST['new_level']);

    if($newLevel <= 1) {
        header("Location: dashboard.php?msg=livello_invariato");
        exit();
    }

    // Spostiamo la transazione DENTRO l'IF del POST
    $conn->begin_transaction();

    try {
        // A. Recupero dati visitatore
        $sqlSelect = "SELECT * FROM Visitors WHERE Email = ?";
        $stmt = $conn->prepare($sqlSelect);
        $stmt->bind_param("s", $targetEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        $visitorData = $res->fetch_assoc(); // CORRETTO: era fetch_result che non esiste

        if(!$visitorData){
            throw new Exception("Visitatore non trovato.");
        }

        $idBadge = $visitorData['IdBadge']; 

        // B. Aggiornamento Livello Badge
        $sqlUpdateBadge = "UPDATE Badges SET BadgeLevel = ? WHERE IdBadge = ?";
        $stmt = $conn->prepare($sqlUpdateBadge); // CORRETTO: mancava il $ davanti a conn
        $stmt->bind_param("ii", $newLevel, $idBadge);
        if(!$stmt->execute()) {
            throw new Exception("Errore aggiornamento Badge.");
        }

        // C. Inserimento in Users
        // Nota: Assicurati che le colonne corrispondano esattamente al tuo DB
        $role = ($newLevel ==2) ? "Dipendente" : "Admin";
        $sqlInsertUser="INSERT INTO Users(IdBadge, Name, Surname, DateBirth, Email, Password, Role, is_verified) VALUES (?,?,?,?,?,?,?,1)";
        $stmt = $conn->prepare($sqlInsertUser); // CORRETTO: mancava il $ davanti a conn
        $stmt->bind_param(
            "issssss",
            $idBadge,
            $visitorData['Name'],
            $visitorData['Surname'],
            $visitorData['DateBirth'],
            $visitorData['Email'],
            $visitorData['Password'],
            $role
        );
        if(!$stmt->execute()){
            throw new Exception("Errore inserimento in Users.");
        }

        // D. CANCELLAZIONE DA VISITORS (Mancava completamente!)
        $sqlDelete = "DELETE FROM Visitors WHERE Email = ?";
        $stmt = $conn->prepare($sqlDelete);
        $stmt->bind_param("s", $targetEmail);
        if(!$stmt->execute()){
            throw new Exception("Errore cancellazione da Visitors.");
        }

        // E. Conferma tutto
        $conn->commit();
        header("Location: dashboard.php?success=utente_promosso");
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