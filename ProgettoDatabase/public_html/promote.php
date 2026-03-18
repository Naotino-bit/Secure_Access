<?php
require "db_connection.php";
session_start(); 

// Vediamo se l'utente è dentro
if(!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Controlliamo se ha la mostrina da admin sulla spalla
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

// Vediamo che futuro ha scelto per questo utente
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    $targetEmail = $_POST['email_to_promote']; 
    
    // Determine the action type: Hiring (new_role) or Level Change (new_level)
    $newLevel = 0;
    $newRole = "";
    
    if (isset($_POST['new_role'])) {
        // Se stiamo assumendo o cambiando ruolo
        $newRole = $_POST['new_role'];
        if ($newRole == 'Admin' || $newRole == 'Amministratore') {
            $newLevel = 4; // Admin uses BadgeLevel 4
        } elseif ($newRole == 'Sorveglianza') {
            $newLevel = 3; // Security requires BadgeLevel 3
        } elseif ($newRole == 'Licenziato' || $newRole == 'Rifiutato') {
            $newLevel = 0;
        } else {
            $newLevel = 2; // Tecnico, Magazziniere, Chimico
        }
    } elseif (isset($_POST['new_level'])) {
        $newLevel = intval($_POST['new_level']);
        if ($newLevel == 3) $newRole = "Admin";
        elseif ($newLevel == 2) $newRole = "Dipendente"; // Default generic role for now
    }

    if($newLevel < 0 || $newLevel > 3) {
        header("Location: dashboard.php?error=livello_non_valido");
        exit();
    }

    // Applichiamo i nuovi gradi sul faldone digitale
    $conn->begin_transaction();

    try{
        // Prendiamo i dati dell'utente
        $stmt = $conn->prepare("SELECT * FROM Users WHERE Email = ?");
        $stmt->bind_param("s", $targetEmail);
        $stmt->execute();
        $userData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userData){
            throw new Exception("Utente non trovato nel database.");
        }

        if ($newLevel > 1 && $userData['is_verified'] == 0) {
            throw new Exception("Impossibile assumere: l'utente non ha ancora verificato la sua email.");
        }

        $idBadge = $userData['IdBadge'];
        $idUser = $userData['IdUser'];

        if ($newLevel == 0) {
            // Rifiuto Candidatura o Licenziamento: Settiamo BadgeLevel a 0.
            // In questo modo i dati dell'utente rimangono nel DB ma l'utente non può accedere a nulla.
            $query = "UPDATE Badges SET BadgeLevel = 0 WHERE IdBadge = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $idBadge);
            $stmt->execute();
            $stmt->close();
            
            // Rimuoviamo da Employees se presente
            $stmt = $conn->prepare("DELETE FROM Employees WHERE IdEmployee = ?");
            $stmt->bind_param("i", $idUser);
            $stmt->execute();
            $stmt->close();

            // Teleport the user to the Hall (Sector 1) if they are fired
            // We insert an AUTO_EXIT access to Sector 1 for this user using their Badge
            $teleportDesc = 'AUTO_EXIT';
            $gateId = 5; // Gate connecting Hall and outside
            $sectorId = 1; // Hall
            $stmtPort = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge, IdSectorTo) VALUES (NOW(), ?, ?, ?, ?)");
            $stmtPort->bind_param("siii", $teleportDesc, $gateId, $idBadge, $sectorId);
            $stmtPort->execute();
            $stmtPort->close();

            if ($newRole == 'Rifiutato') {
                $descrizioneLog = "Admin " . $_SESSION['user'] . " ha rifiutato la candidatura di " . $targetEmail;
            } else {
                $descrizioneLog = "Admin " . $_SESSION['user'] . " ha licenziato l'utente " . $targetEmail;
            }
            
        } else {
            // Cambiamo il livello del badge
            $query = "UPDATE Badges SET BadgeLevel = ? WHERE IdBadge = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $newLevel, $idBadge);
            $stmt->execute();
            $stmt->close();

            // Aggiorniamo la tabella dei dipendenti
            if ($newLevel > 1) {
                
                // Map Role String to IdRole
                $idRole = 2; // Default Tecnico
                if ($newRole == 'Admin' || $newRole == 'Amministratore') $idRole = 1;
                elseif ($newRole == 'Tecnico') $idRole = 2;
                elseif ($newRole == 'Magazziniere') $idRole = 3;
                elseif ($newRole == 'Chimico') $idRole = 4;
                elseif ($newRole == 'Sorveglianza') $idRole = 5;
                
                // Vediamo se lavora già qui
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
                $descrizioneLog = "Admin " . $_SESSION['user'] . " ha assegnato il ruolo di " . $newRole . " a " . $targetEmail;
            } else {
                // Se torna a essere un semplice visitatore
                // Remove from Employees if exists
                $stmt = $conn->prepare("DELETE FROM Employees WHERE IdEmployee = ?");
                $stmt->bind_param("i", $idUser);
                $stmt->execute();
                $stmt->close();
                $descrizioneLog = "Admin " . $_SESSION['user'] . " ha degradato l'utente " . $targetEmail . " a Visitatore";
            }
        }

        // Segnamo tutto nel registro admin
        $stmtLog = $conn->prepare("INSERT INTO AdminLogs (Description, DateTime) VALUES (?, NOW())");
        $stmtLog->bind_param("s", $descrizioneLog);
        $stmtLog->execute();
        $stmtLog->close();
        
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