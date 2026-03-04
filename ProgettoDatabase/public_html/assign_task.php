<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

// 1. INPUT
$taskType = $_POST['task_type'] ?? '';
$idEmployee = $_POST['id_employee'];
$startTime = $_POST['start_time'];
$endTime = $_POST['end_time'];
$now = new DateTime();

$msg = "";
$error = "";

$conn->begin_transaction();

try {
    $start = date('Y-m-d H:i:s', strtotime($startTime));
    $end = date('Y-m-d H:i:s', strtotime($endTime));

    if (empty($taskType) || empty($idEmployee) || empty($start) || empty($end)) {
        throw new Exception("Compila tutti i campi obbligatori.");
    }

    // 2. CREATE PARENT TASK (Status Default = Pending)
    $typeStr = ($taskType == 'maintenance') ? 'Maintenance' : 'Restock';
    
    // CHECK IF MAX QUANITY REACHED
    if ($taskType == 'restock') {
        $idItem = $_POST['target_item_id'];
        $qty = intval($_POST['restock_quantity']);
        
        $stmt = $conn->prepare("SELECT Quantity FROM Inventory WHERE IdItem = ?");
        $stmt->bind_param("i", $idItem);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if ($row['Quantity'] + $qty > 100) {
            throw new Exception("Quantità massima raggiunta.");
        }
    }
    // CHECK IF ASSIGNED TASK ALREADY EXPIRED
    if (strtotime($endTime) <= time()) {
        throw new Exception("Impossibile pianificare un Task già scaduto.");
    }
    if (strtotime($startTime) >= strtotime($endTime)) {
        throw new Exception("L'orario di inizio deve essere precedente all'orario di fine.");
    }

    $stmt = $conn->prepare("INSERT INTO Tasks (Type, StartTime, EndTime, IdEmployee, Status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("sssi", $typeStr, $start, $end, $idEmployee);
    if (!$stmt->execute()) throw new Exception("Errore creazione Task: " . $stmt->error);
    $idTask = $stmt->insert_id;
    $stmt->close();

    // 3. BRANCHING 
    if ($taskType == 'maintenance') {
        // --- MAINTENANCE ---
        $idRequest = $_POST['target_gate_req_id'];
        $idItemToConsume = $_POST['consume_item_id'] ?? null; // Optionally store this if needed? For now we just focus on Gate.

        if (empty($idRequest)) throw new Exception("Seleziona il Gate.");

        // IdRequest here IS IdGate per previous logic
        $idGate = $idRequest;

        $stmt = $conn->prepare("INSERT INTO MaintenanceTasks (IdTask, IdGate) VALUES (?, ?)");
        $stmt->bind_param("ii", $idTask, $idGate);
        $stmt->execute();
        $stmt->close();

        // Update Request to 'Assigned' so it doesn't show up in the list anymore
        // BUT Gate Wear is NOT reset yet.
        $conn->query("UPDATE MaintenanceRequests SET Status = 'Assigned' WHERE IdGate = $idGate AND Status = 'Pending'");
        
        $msg = "Manutenzione pianificata (Task $idTask). Il tecnico dovrà eseguirla nella fascia oraria indicata.";

    } elseif ($taskType == 'restock') {
        // --- RESTOCK ---
        $idItem = $_POST['target_item_id'];
        $qty = intval($_POST['restock_quantity']);

        if (empty($idItem) || $qty <= 0) throw new Exception("Dati rifornimento non validi.");

        // Insert into RestockTasks (Deferred Execution)
        // Table: IdTask, IdItem, Quantity
        $stmt = $conn->prepare("INSERT INTO RestockTasks (IdTask, IdItem, Quantity) VALUES (?, ?, ?)");
        $stmt->bind_param("isi", $idTask, $idItem, $qty);
        $stmt->execute();
        $stmt->close();

        $msg = "Rifornimento pianificato (Task $idTask). Il magazziniere dovrà eseguirlo nella fascia oraria indicata.";
    }

    // 4. LOG
    $desc = "Pianificato Task ($typeStr) ID $idTask per Dipendente $idEmployee ($start - $end)";
    $conn->query("INSERT INTO AdminLogs (Description, DateTime) VALUES ('$desc', NOW())");

    $conn->commit();
    header("Location: maintenance_dashboard.php?msg=" . urlencode($msg));

} catch (Exception $e) {
    if ($conn) $conn->rollback();
    header("Location: maintenance_dashboard.php?error=" . urlencode("Errore: " . $e->getMessage()));
}
?>
