<?php
require "db_connection.php";
session_start();

// Vediamo se l'utente è dentro
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user'];

// Prendiamo i dati dell'utente per vedere chi è
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
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData) {
    header("Location: dashboard.php?error=utente_non_trovato");
    exit();
}

$badgeLevel = (int)$userData['BadgeLevel'];
$idBadge = $userData['IdBadge'];

// Solo chi è stato "bocciato" (livello 0) può riprovare
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($badgeLevel === 0) {
        
        $conn->begin_transaction();
        try {
            // Lo rimettiamo come visitatore in attesa
            $updateQuery = "UPDATE Badges SET BadgeLevel = 1 WHERE IdBadge = ?";
            $updStmt = $conn->prepare($updateQuery);
            $updStmt->bind_param("i", $idBadge);
            $updStmt->execute();
            $updStmt->close();
            
            $conn->commit();
            header("Location: dashboard.php?success=candidatura_inviata_con_successo");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            header("Location: dashboard.php?error=errore_di_sistema");
            exit();
        }
    } else {
        header("Location: dashboard.php?error=azione_non_permessa");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>
