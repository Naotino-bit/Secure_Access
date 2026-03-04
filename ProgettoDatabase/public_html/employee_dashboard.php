<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Get User ID from Email
$email = $_SESSION['user'];
$resUser = $conn->query("
    SELECT IdUser, Role 
    FROM Users 
    JOIN Employees ON Users.IdUser = Employees.IdEmployee 
    JOIN Shifts ON Employees.IdRole = Shifts.IdRole
    WHERE Email = '$email'");
if ($resUser->num_rows == 0) {
    die("Utente non trovato o non dipendente.");
}
$userData = $resUser->fetch_assoc();
$userId = $userData['IdUser'];

// Fetch Tasks Assigned to this Employee (Pending or In Progress)
// We join with MaintenanceTasks and RestockTasks to get details.
// Note: A task is either Maintenance OR Restock.
    $query = "
    SELECT T.IdTask, T.Type, T.StartTime, T.EndTime, T.Status,
           G.IdGate, G.Wear, S.Description as SectorDesc,
           I.IdItem, RT.Quantity as RestockQty
    FROM Tasks T
    LEFT JOIN MaintenanceTasks MT ON T.IdTask = MT.IdTask
    LEFT JOIN Gates G ON MT.IdGate = G.IdGate
    LEFT JOIN Sectors S ON G.IdSectorA = S.IdSector
    LEFT JOIN RestockTasks RT ON T.IdTask = RT.IdTask
    LEFT JOIN Inventory I ON RT.IdItem = I.IdItem
    WHERE T.IdEmployee = $userId AND T.Status != 'Completed' AND T.Status != 'Expired' AND T.Status != 'Cancelled'
    ORDER BY T.StartTime ASC
";

$result = $conn->query($query);
$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

$now = new DateTime();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Le Mie Task</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .task-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px; background: #fff; border-left: 5px solid #ccc; }
        .task-maint { border-left-color: #e67e22; }
        .task-restock { border-left-color: #2980b9; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .time-box { font-size: 0.9em; color: #555; background: #eee; padding: 4px 8px; border-radius: 4px; }
        
        .btn-action { display: inline-block; padding: 10px 20px; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; cursor: pointer; border: none; }
        .btn-enabled { background: #27ae60; }
        .btn-enabled:hover { background: #219150; }
        .btn-disabled { background: #95a5a6; cursor: not-allowed; }
        
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" style="text-decoration:none; color:#3498db;">&larr; Home</a>
    <h1>Le Mie Task Assegnate</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (count($tasks) == 0): ?>
        <p>Nessuna task in programma.</p>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <?php 
                $start = new DateTime($task['StartTime']);
                $end = new DateTime($task['EndTime']);
                $isTime = ($now >= $start && $now <= $end);

                // Determine visuals
                $class = ($task['Type'] == 'Maintenance') ? 'task-maint' : 'task-restock';
                $title = ($task['Type'] == 'Maintenance') ? "Manutenzione Gate {$task['IdGate']}" : "Rifornimento Magazzino";
            ?>
            <div class="task-card <?php echo $class; ?>">
                <div class="header">
                    <h3 style="margin:0;"><?php echo $title; ?></h3>
                    <div class="time-box">
                        <?php echo $start->format('d/m H:i'); ?> - <?php echo $end->format('d/m H:i'); ?>
                    </div>
                </div>
                
                <p>
                    <?php if ($task['Type'] == 'Maintenance'): ?>
                        Settore: <strong><?php echo htmlspecialchars($task['SectorDesc']); ?></strong><br>
                        Usura attuale: <?php echo $task['Wear']; ?>%
                    <?php else: ?>
                        Articolo ID: <strong><?php echo htmlspecialchars($task['IdItem']); ?></strong><br>
                        Quantità da aggiungere: <strong><?php echo $task['RestockQty']; ?></strong>
                    <?php endif; ?>
                </p>

                <form action="complete_task.php" method="POST">
                    <input type="hidden" name="id_task" value="<?php echo $task['IdTask']; ?>">
                    <?php if ($isTime): ?>
                        <button type="submit" name="action" value="complete" class="btn-action btn-enabled">COMPLETA ORA</button>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-disabled" title="Puoi completare la task solo nell'orario indicato">
                            <?php echo ($now < $start) ? "Non ancora disponibile" : "Scaduta"; ?>
                        </button>
                        <?php if (!($now < $start)): ?>
                            <button type="submit" name="action" value="expire" class="btn-action btn-enabled" title="Chiedi di riassegnare la task">
                                Riassegna task 
                            </button>    
                        <?php endif; ?>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Tabella inventario -->
    <?php 
    // 3. Fetch Inventory
    $queryInv = "SELECT * FROM Inventory";
    $resInv = $conn->query($queryInv);
    $inventory = [];
    while($row = $resInv->fetch_assoc()) $inventory[] = $row;
    
    // Show inventory if user is Magazziniere or has Restock tasks
    $isMagazziniere = (strpos(strtolower($userData['Role']), 'magazziniere') !== false);
    
    // Check if any of the fetched tasks is Restock
    $hasRestockTask = false;
    foreach($tasks as $t) {
        if ($t['Type'] == 'Restock') {
            $hasRestockTask = true;
            break;
        }
    }

    if ($isMagazziniere || $hasRestockTask): ?>
        <div>
            <table>
                <thead>
                    <tr>
                        <th>IdItem</th>
                        <th>Description</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($inventory as $i): ?>
                        <tr>
                            <td><?php echo $i['IdItem']; ?></td>
                            <td><?php echo $i['Description']; ?></td>
                            <td><?php echo $i['Quantity']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
