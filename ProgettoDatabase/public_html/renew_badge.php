<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email_admin = $_SESSION['user'];

// Controllo privilegi (Solo Admin = 4)
$queryAdmin = "SELECT B.BadgeLevel FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge WHERE U.Email = ?";
$stmtA = $conn->prepare($queryAdmin);
$stmtA->bind_param("s", $email_admin);
$stmtA->execute();
$resA = $stmtA->get_result();
$adminData = $resA->fetch_assoc();
$stmtA->close();

if (!$adminData || $adminData['BadgeLevel'] != 4) {
    $_SESSION['error_message'] = "Non hai i permessi per eseguire questa operazione.";
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email_to_renew'])) {
    $email_to_renew = $_POST['email_to_renew'];

    // Ottieni ID Badge dell'utente da rinnovare
    $qBadge = "SELECT IdBadge FROM Users WHERE Email = ?";
    $stmtB = $conn->prepare($qBadge);
    $stmtB->bind_param("s", $email_to_renew);
    $stmtB->execute();
    $resB = $stmtB->get_result();
    
    if ($row = $resB->fetch_assoc()) {
        $idBadge = $row['IdBadge'];
        
        // Rinnovo di 1 anno dalla data attuale
        $newExpiration = date('Y-m-d H:i:s', strtotime('+1 year'));
        
        $updateQuery = "UPDATE Badges SET ExpirationDate = ? WHERE IdBadge = ?";
        $stmtU = $conn->prepare($updateQuery);
        $stmtU->bind_param("si", $newExpiration, $idBadge);
        
        if ($stmtU->execute()) {
            $_SESSION['success_message'] = "Badge per l'utente $email_to_renew rinnovato con successo fino a scadenza: " . date('d/m/Y', strtotime($newExpiration)) . ".";
        } else {
            $_SESSION['error_message'] = "Errore durante il rinnovo del badge.";
        }
        $stmtU->close();
    } else {
        $_SESSION['error_message'] = "Utente non trovato.";
    }
    $stmtB->close();
}

header("Location: dashboard.php");
exit();
?>
