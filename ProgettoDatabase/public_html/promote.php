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
    
    // Determine the action type: Hiring (new_role) or Level Change (new_level)
    $newLevel = 0;
    $newRole = "";
    
    if (isset($_POST['new_role'])) {
        // HIRING FLOW
        $newRole = $_POST['new_role'];
        if ($newRole == 'Admin') {
            $newLevel = 3;
        } else {
            $newLevel = 2; // Tecnico, Magazziniere, Chimico
        }
    } elseif (isset($_POST['new_level'])) {
        // MANAGING EXISTING FLOW
        $newLevel = intval($_POST['new_level']);
        if ($newLevel == 3) $newRole = "Admin";
        elseif ($newLevel == 2) $newRole = "Dipendente"; // Default generic role for now
    }

    if($newLevel < 1 || $newLevel > 3) {
        header("Location: dashboard.php?error=livello_non_valido");
        exit();
    }

    // Spostiamo la transazione DENTRO l'IF del POST
    $conn->begin_transaction();

    try{
        // Fetch User Data
        $stmt = $conn->prepare("SELECT * FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $targetEmail);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userData){
            throw new Exception("Utente non trovato nel database.");
        }

        $idBadge = $userData['IdBadge'];
        $idUser = $userData['IdUser'];

        // 1. Update Badge Level
        $query = "UPDATE Badges SET BadgeLevel = ? WHERE IdBadge = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $newLevel, $idBadge);
        $stmt->execute();
        $stmt->close();

        // 2. Handle Employees Table
        if ($newLevel > 1) {
            
            // Map Role String to IdRole
            $idRole = 2; // Default Tecnico
            if ($newRole == 'Admin' || $newRole == 'Amministratore') $idRole = 1;
            elseif ($newRole == 'Tecnico') $idRole = 2;
            elseif ($newRole == 'Magazziniere') $idRole = 3;
            elseif ($newRole == 'Chimico') $idRole = 4;
            elseif ($newRole == 'Sicurezza') $idRole = 5;
            
            // Check if already in Employees
            $check = $conn->query("SELECT IdEmployee FROM Employees WHERE IdEmployee = $idUser");
            if ($check->num_rows > 0) {
                // Already employee, update role only if it's hiring flow (new_role is set and not 'Dipendente')
                if ($newRole != "Dipendente") {
                    $stmt = $conn->prepare("UPDATE Employees SET IdRole = ? WHERE IdEmployee = ?");
                    $stmt->bind_param("ii", $idRole, $idUser);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                // New Employee (Visitor -> Employee)
                $stmt = $conn->prepare("INSERT INTO Employees (IdEmployee, IdRole) VALUES (?, ?)");
                $stmt->bind_param("ii", $idUser, $idRole);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // Demoting to Visitor (Level 1)
            // Remove from Employees if exists
            $stmt = $conn->prepare("DELETE FROM Employees WHERE IdEmployee = ?");
            $stmt->bind_param("i", $idUser);
            $stmt->execute();
            $stmt->close();
        }

        //I log

        $descrizioneLog = "Admin " . $_SESSION['user'] . " ha modificato ruolo utente " . $targetEmail . " al livello " . $newLevel;
        $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$descrizioneLog', NOW())");
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