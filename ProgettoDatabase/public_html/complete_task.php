<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

$idTask = $_POST['id_task'];
if (empty($idTask)) {
    die("ID Task mancante.");
}

$conn->begin_transaction();

try {
    // 1. Fetch Task Info & Validate Time
    $stmt = $conn->prepare("SELECT Type, StartTime, EndTime, Status FROM Tasks WHERE IdTask = ? FOR UPDATE");
    $stmt->bind_param("i", $idTask);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 0) throw new Exception("Task non trovata.");
    $task = $res->fetch_assoc();
    $stmt->close();


    $now = new DateTime();
    $start = new DateTime($task['StartTime']);
    $end = new DateTime($task['EndTime']);

    // --- CHECK SHIFT (ORARIO LAVORATIVO) ---
    $stmtShift = $conn->prepare("
        SELECT S.Start, S.End 
        FROM Users U 
        JOIN Employees E ON U.IdUser = E.IdEmployee 
        JOIN Shifts S ON E.IdRole = S.IdRole 
        WHERE U.Email = ?
    ");
    $stmtShift->bind_param("s", $_SESSION['user']);
    $stmtShift->execute();
    $resShift = $stmtShift->get_result();
    $shift = $resShift->fetch_assoc();
    $stmtShift->close();

    if ($shift && isset($shift['Start']) && isset($shift['End'])) {
        $currentTime = date('H:i:s');
        $isWorkingHours = false;
        if ($shift['Start'] <= $shift['End']) {
            $isWorkingHours = ($currentTime >= $shift['Start'] && $currentTime <= $shift['End']);
        } else {
            // Turno a cavallo della mezzanotte
            $isWorkingHours = ($currentTime >= $shift['Start'] || $currentTime <= $shift['End']);
        }
        
        if (!$isWorkingHours) {
            throw new Exception("Operazione negata: sei fuori dal tuo orario di lavoro.");
        }
    }

    // 2. Determine Action
    $action = $_POST['action'] ?? 'complete';

    if ($action == 'expire') {
        // --- EXPIRE LOGIC ---
        // Validate it is actually expired or at least past start time? 
        // Logic says "if not available yet" it's disabled, so user only sees this if expired.
        // We double check strictly if $now > $end to be safe, or just trust the button availability?
        // Let's rely on the check: if it's NOT time, and we are asking to expire.
        
        // Actually, if it's just "past end time", it is expired.
        if ($now <= $end) {
             throw new Exception("La task non è ancora scaduta, impossibile segnalarla come tale.");
        }

        // Update status
        $conn->query("UPDATE Tasks SET Status = 'Expired' WHERE IdTask = $idTask");
        
        $logDesc = "Task $idTask segnalata come SCADUTA dal dipendente {$_SESSION['user']}.";
        $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$logDesc', NOW())");
        
        $msg = "Task segnalata come Scaduta. In attesa di riassegnazione.";

    } else {
        // --- COMPLETE LOGIC ---
        
        if ($task['Status'] == 'Completed') {
            throw new Exception("Task già completata.");
        }

        if ($now < $start || $now > $end) {
            throw new Exception("Task non eseguibile in questo momento (Fuori orario).");
        }

        // Execute Logic Based on Type
        if ($task['Type'] == 'Maintenance') {
            // Find the Gate associated with this task
            // We link via MaintenanceTasks
            $resM = $conn->query("SELECT IdGate FROM MaintenanceTasks WHERE IdTask = $idTask");
            if ($resM->num_rows == 0) throw new Exception("Dettagli manutenzione mancanti.");
            $mTask = $resM->fetch_assoc();
            $idGate = $mTask['IdGate'];

            // Reset Wear
            $conn->query("UPDATE Gates SET Wear = 0 WHERE IdGate = $idGate");

            // Check inventory items
            $resItems = $conn->query("SELECT IdItem, Description, Quantity FROM Inventory WHERE IdItem = 1");
            $item = $resItems->fetch_assoc();
            if ($item['Quantity'] == 0) {
                throw new Exception("Componenti mancanti.");
            } elseif ($item['Quantity'] > 0) {
                // Update inventory items
                $conn->query("UPDATE Inventory SET Quantity = Quantity - 1 WHERE IdItem = 1");
                
                // Update Request Status
                $conn->query("UPDATE MaintenanceRequests SET Status = 'Completed' WHERE IdGate = $idGate AND Status = 'Assigned'");
                
                $msg = "Manutenzione completata. Gate riparato.";
            }

        } elseif ($task['Type'] == 'Restock') {
            // Find Restock Details
            $resR = $conn->query("SELECT IdItem, Quantity FROM RestockTasks WHERE IdTask = $idTask");
            if ($resR->num_rows == 0) throw new Exception("Dettagli rifornimento mancanti.");
            $rTask = $resR->fetch_assoc();
            $idItem = $rTask['IdItem'];
            $qty = $rTask['Quantity'];

            // Update Inventory
            $stmtInv = $conn->prepare("UPDATE Inventory SET Quantity = Quantity + ? WHERE IdItem = ?");
            $stmtInv->bind_param("is", $qty, $idItem);
            $stmtInv->execute();
            $stmtInv->close();

            $msg = "Rifornimento completato. Magazzino aggiornato.";
        }

        // Mark Task as Completed
        $conn->query("UPDATE Tasks SET Status = 'Completed' WHERE IdTask = $idTask");

        // Log
        $logDesc = "Task $idTask ({$task['Type']}) completata da utente.";
        $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$logDesc', NOW())");
    }

    $conn->commit();
    header("Location: employee_dashboard.php?msg=" . urlencode($msg));

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    header("Location: employee_dashboard.php?error=" . urlencode("Errore: " . $e->getMessage()));
}
?>
