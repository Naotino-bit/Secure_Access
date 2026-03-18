<?php
require "db_connection.php";
session_start(); 

// Check if user is logged in
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Check admin permissions
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
    die("ACCESSO NEGATO: Non hai i permessi per modificare gli orari");
}

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $idRole = intval($_POST['id_role']);
    $startTime = $_POST['start_time'];
    $endTime = $_POST['end_time'];

    // Basic validation for time format (HH:MM or HH:MM:SS)
    if (!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', $startTime) ||
        !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', $endTime)) {
        header("Location: dashboard.php?error=formato_orario_non_valido");
        exit();
    }

    $conn->begin_transaction();

    try {
        // Fetch role name for logging
        $stmtRole = $conn->prepare("SELECT Role FROM Shifts WHERE IdRole = ?");
        $stmtRole->bind_param("i", $idRole);
        $stmtRole->execute();
        $roleData = $stmtRole->get_result()->fetch_assoc();
        $stmtRole->close();

        if (!$roleData) {
            throw new Exception("Ruolo non trovato.");
        }

        $roleName = $roleData['Role'];

        // Update shift
        $stmtUpdate = $conn->prepare("UPDATE Shifts SET Start = ?, End = ? WHERE IdRole = ?");
        $stmtUpdate->bind_param("ssi", $startTime, $endTime, $idRole);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // Log the change
        $descrizioneLog = "Admin " . $_SESSION['user'] . " ha modificato gli orari del ruolo " . $roleName . " ($startTime - $endTime)";
        $stmtLog = $conn->prepare("INSERT INTO AdminLogs (Description, DateTime) VALUES (?, NOW())");
        $stmtLog->bind_param("s", $descrizioneLog);
        $stmtLog->execute();
        $stmtLog->close();

        $conn->commit();
        header("Location: manage_shifts.php?success=orario_aggiornato");
        exit();

    } catch(Exception $e) {
        $conn->rollback();
        $errore = urlencode($e->getMessage());
        header("Location: manage_shifts.php?error=" . $errore);
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>
