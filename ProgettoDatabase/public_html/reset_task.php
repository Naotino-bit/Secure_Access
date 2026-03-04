<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: maintenance_dashboard.php");
    exit();
}

$idTask = $_POST['id_task'] ?? null;
$newStart = $_POST['new_start_time'] ?? null;
$newEnd = $_POST['new_end_time'] ?? null;
$newEmployee = $_POST['new_id_employee'] ?? null;

if (!$idTask || !$newStart || !$newEnd || !$newEmployee) {
    header("Location: maintenance_dashboard.php?error=" . urlencode("Dati mancanti per la riassegnazione."));
    exit();
}

$conn->begin_transaction();

try {
    // 1. Get Old Task Info
    $stmt = $conn->prepare("
        SELECT * FROM Tasks 
        WHERE IdTask = ? AND Status = 'Expired' 
        FOR UPDATE
    ");
    $stmt->bind_param("i", $idTask);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) {
        throw new Exception("Task non trovata o non scaduta.");
    }
    
    $oldTask = $res->fetch_assoc();
    $stmt->close();
    
    // Time Validation
    $startDT = new DateTime($newStart);
    $endDT = new DateTime($newEnd);
    $now = new DateTime();
    
    if ($startDT >= $endDT) {
        throw new Exception("L'orario di inizio deve essere precedente alla fine.");
    }
    
    // 2. Archive Old Task
    $conn->query("UPDATE Tasks SET Status = 'Cancelled' WHERE IdTask = $idTask");
    
    // 3. Create New Task
    $type = $oldTask['Type'];
    $sqlNew = "INSERT INTO Tasks (Type, StartTime, EndTime, IdEmployee, Status) VALUES (?, ?, ?, ?, 'Pending')";
    $stmtNew = $conn->prepare($sqlNew);
    $sStr = $startDT->format('Y-m-d H:i:s');
    $eStr = $endDT->format('Y-m-d H:i:s');
    $stmtNew->bind_param("sssi", $type, $sStr, $eStr, $newEmployee);
    
    if (!$stmtNew->execute()) {
        throw new Exception("Errore creazione nuova task: " . $stmtNew->error);
    }
    $newIdTask = $stmtNew->insert_id;
    $stmtNew->close();
    
    // 4. Link Details based on Type
    if ($type === 'Maintenance') {
        // Get linked Gate ID from OLD task
        $resM = $conn->query("SELECT IdGate FROM MaintenanceTasks WHERE IdTask = $idTask");
        if ($rowM = $resM->fetch_assoc()) {
            $idGate = $rowM['IdGate'];
            
            // Link New Task to Gate
            $conn->query("INSERT INTO MaintenanceTasks (IdTask, IdGate) VALUES ($newIdTask, $idGate)");
            
            // Important: Logic for Request Status
            // The Request was 'Assigned' to the OLD task.
            // We want it 'Assigned' to the NEW task.
            // Actually the status 'Assigned' works for both. We just leave it as is.
            // BUT if we implemented the "Reset to Pending" logic before, we should assume it is currently Assigned.
            // Just double check:
            $conn->query("UPDATE MaintenanceRequests SET Status = 'Assigned' WHERE IdGate = $idGate AND Status = 'Assigned'");
        } else {
            throw new Exception("Dettagli manutenzione persi.");
        }
    } elseif ($type === 'Restock') {
        // Get linked Item from OLD task
        $resR = $conn->query("SELECT IdItem, Quantity FROM RestockTasks WHERE IdTask = $idTask");
        if ($rowR = $resR->fetch_assoc()) {
            $idItem = $rowR['IdItem'];
            $qty = $rowR['Quantity'];
            
            // Link New Task
            $conn->query("INSERT INTO RestockTasks (IdTask, IdItem, Quantity) VALUES ($newIdTask, '$idItem', $qty)");
        }
    }

    // 5. Log
    $logDesc = "Admin ha riassegnato Task Scaduta $idTask -> Nuova Task $newIdTask a Emp $newEmployee";
    $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$logDesc', NOW())");

    $conn->commit();
    header("Location: maintenance_dashboard.php?msg=" . urlencode("Task riassegnata con successo (ID: $newIdTask)."));

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    header("Location: maintenance_dashboard.php?error=" . urlencode("Errore: " . $e->getMessage()));
}
?>
