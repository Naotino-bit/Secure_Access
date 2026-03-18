<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Vediamo chi è l'utente partendo dall'email
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

// Vediamo cosa deve fare oggi questo dipendente
// Recuperiamo tutti i dettagli dei lavori da fare
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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%234facfe' d='M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C25.5 92 16 110.1 16 130.1c0 231 161.4 336.8 226.7 372.4a47.79 47.79 0 0 0 46.5 0C354.6 466.9 512 361.1 512 130.1c0-20-9.5-38.1-26.6-46.4z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding: 40px 20px; color: #333; }
        .container { 
            max-width: 850px; margin: 0 auto; 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; 
            padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        h1 { text-align: center; color: #2c3e50; font-weight: 700; margin-bottom: 30px; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 2.2rem; }
        .back-link { text-decoration: none; color: #3498db; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 20px; transition: color 0.3s; }
        .back-link:hover { color: #2980b9; }

        .task-card { 
            background: rgba(255, 255, 255, 0.6); 
            border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; 
            padding: 25px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            border-left: 5px solid #ccc; transition: transform 0.2s;
        }
        .task-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.05); }
        .task-maint { border-left-color: #e67e22; }
        .task-restock { border-left-color: #2980b9; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px; }
        .header h3 { color: #2c3e50; font-size: 1.25em; display: flex; align-items: center; gap: 8px; }
        .time-box { font-size: 0.9em; color: #555; background: rgba(0,0,0,0.05); padding: 5px 12px; border-radius: 20px; font-weight: 600; display: flex; align-items: center; gap: 5px; }
        
        .task-content { margin-bottom: 20px; font-size: 1.05em; color: #444; line-height: 1.6; }
        .task-content strong { color: #2c3e50; }

        .btn-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 20px; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; cursor: pointer; border: none; font-size: 1em; transition: transform 0.2s, box-shadow 0.2s; }
        .btn-enabled { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4); }
        .btn-enabled:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 242, 254, 0.6); }
        .btn-disabled { background: #95a5a6; cursor: not-allowed; box-shadow: none; }
        .btn-expire { background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); box-shadow: 0 4px 15px rgba(255, 8, 68, 0.4); }
        .btn-expire:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 8, 68, 0.6); }
        
        .form-actions { display: flex; gap: 10px; flex-wrap: wrap; }

        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; text-align: left; font-weight: 600; animation: slideDown 0.4s ease-out; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .inventory-section { margin-top: 40px; background: rgba(255,255,255,0.6); padding: 25px; border-radius: 12px; border-left: 5px solid #27ae60; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .inventory-section h2 { color: #27ae60; margin-bottom: 15px; margin-top: 0; display: flex; align-items: center; gap: 8px; font-size: 1.4em; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        th { background-color: #f8f9fa; padding: 12px 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px 15px; border-bottom: 1px solid #e9ecef; }
        tr:hover { background-color: #f1f3f5; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Home</a>
    <h1><i class="fas fa-clipboard-list" style="color: #3498db;"></i> Le Mie Task Assegnate</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (count($tasks) == 0): ?>
        <p>Nessuna task in programma.</p>
    <?php else: ?>
        <?php foreach ($tasks as $task): ?>
            <?php 
                $start = new DateTime($task['StartTime']);
                $end = new DateTime($task['EndTime']);
                $isTime = ($now >= $start && $now <= $end);

                // Scegliamo il colore giusto in base al lavoro
                $class = ($task['Type'] == 'Maintenance') ? 'task-maint' : 'task-restock';
                $title = ($task['Type'] == 'Maintenance') ? "Manutenzione Gate {$task['IdGate']}" : "Rifornimento Magazzino";
            ?>
            <div class="task-card <?php echo $class; ?>">
                <div class="header">
                    <h3 style="margin:0;">
                        <?php if($task['Type'] == 'Maintenance'): ?>
                            <i class="fas fa-tools" style="color:#e67e22;"></i>
                        <?php else: ?>
                            <i class="fas fa-box-open" style="color:#2980b9;"></i>
                        <?php endif; ?>
                        <?php echo $title; ?>
                    </h3>
                    <div class="time-box">
                        <i class="far fa-clock"></i> <?php echo $start->format('d/m H:i'); ?> - <?php echo $end->format('d/m H:i'); ?>
                    </div>
                </div>
                
                <div class="task-content">
                    <?php if ($task['Type'] == 'Maintenance'): ?>
                        <p><i class="fas fa-map-marker-alt" style="color:#7f8c8d; width:20px;"></i> Settore: <strong><?php echo htmlspecialchars($task['SectorDesc']); ?></strong></p>
                        <p><i class="fas fa-chart-pie" style="color:#7f8c8d; width:20px;"></i> Usura attuale: <strong><?php echo $task['Wear']; ?>%</strong></p>
                    <?php else: ?>
                        <p><i class="fas fa-barcode" style="color:#7f8c8d; width:20px;"></i> Articolo ID: <strong><?php echo htmlspecialchars($task['IdItem']); ?></strong></p>
                        <p><i class="fas fa-plus-circle" style="color:#7f8c8d; width:20px;"></i> Quantità da aggiungere: <strong><?php echo $task['RestockQty']; ?></strong></p>
                    <?php endif; ?>
                </div>

                <form action="complete_task.php" method="POST" class="form-actions">
                    <input type="hidden" name="id_task" value="<?php echo $task['IdTask']; ?>">
                    <?php if ($isTime): ?>
                        <button type="submit" name="action" value="complete" class="btn-action btn-enabled"><i class="fas fa-check"></i> COMPLETA ORA</button>
                    <?php else: ?>
                        <button type="button" class="btn-action btn-disabled" title="Puoi completare la task solo nell'orario indicato">
                            <i class="fas fa-ban"></i> <?php echo ($now < $start) ? "Non ancora disponibile" : "Scaduta"; ?>
                        </button>
                        <?php if (!($now < $start)): ?>
                            <button type="submit" name="action" value="expire" class="btn-action btn-expire" title="Chiedi di riassegnare la task">
                                <i class="fas fa-redo"></i> Riassegna task 
                            </button>    
                        <?php endif; ?>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Tabella inventario -->
    <?php 
    // Controlliamo il magazzino
    $queryInv = "SELECT * FROM Inventory";
    $resInv = $conn->query($queryInv);
    $inventory = [];
    while($row = $resInv->fetch_assoc()) $inventory[] = $row;
    
    // Facciamo vedere il magazzino solo a chi serve
    $isMagazziniere = (strpos(strtolower($userData['Role']), 'magazziniere') !== false);
    
    // Controlliamo se ci sono box da caricare
    $hasRestockTask = false;
    foreach($tasks as $t) {
        if ($t['Type'] == 'Restock') {
            $hasRestockTask = true;
            break;
        }
    }

    if ($isMagazziniere || $hasRestockTask): ?>
        <div class="inventory-section">
            <h2><i class="fas fa-boxes"></i> Stato Inventario</h2>
            <div style="overflow-x:auto;">
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
                                <td><?php echo htmlspecialchars($i['Description']); ?></td>
                                <td><strong><?php echo $i['Quantity']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
