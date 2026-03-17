<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user'];

// 1. Fetch Maintenance Requests (Status = Pending)
//    These are the targets for Technicians.
$queryReq = "
    SELECT MR.IdRequest, MR.Priority, MR.Status, MR.CreatedAt, G.IdGate, G.Wear, S.Description as SectorDesc
    FROM MaintenanceRequests MR
    JOIN Gates G ON MR.IdGate = G.IdGate
    LEFT JOIN Sectors S ON G.IdSectorA = S.IdSector 
    WHERE MR.Status != 'Completed' AND MR.Status != 'Assigned'
    ORDER BY MR.Priority DESC, MR.CreatedAt ASC
";
$resReq = $conn->query($queryReq);
$requests = [];
while($row = $resReq->fetch_assoc()) $requests[] = $row;


// 2. Fetch Technicians AND Warehouse Workers (Magazziniere)
//    We fetch all employees and will filter by role in JS or PHP.
//    Assuming Role in Employees table: 'Manutentore', 'Tecnico', 'Magazziniere', 'Admin'.
$queryEmp = "
    SELECT E.IdEmployee, E.IdRole, U.Name, U.Surname, S.Role 
    FROM Employees E 
    JOIN Users U ON E.IdEmployee = U.IdUser
    JOIN Shifts S ON E.IdRole = S.IdRole
    ORDER BY U.Surname ASC
";
$resEmp = $conn->query($queryEmp);
$technicians = [];
$restockers = [];

while($row = $resEmp->fetch_assoc()) {
    // Categorize based on Role string. Adjust based on your actual DB values.
    // Spec says: "tipo di Dipendente (tecnico o magazziniere)"
    // Let's assume generic matching.
    $role = strtolower($row['Role']);
    
    // Technicians
    if (strpos($role, 'tecnico') !== false || strpos($role, 'manutentore') !== false || strpos($role, 'admin') !== false) {
        $technicians[] = $row;
    }
    
    // Restockers
    if (strpos($role, 'magazziniere') !== false || strpos($role, 'admin') !== false) {
        $restockers[] = $row;
    }
}


// 3. Fetch Inventory
$queryInv = "SELECT * FROM Inventory";
$resInv = $conn->query($queryInv);
$inventory = [];
while($row = $resInv->fetch_assoc()) $inventory[] = $row;
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Manutenzione & Rifornimenti UI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 30px 15px;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.85); /* Glassmorphism light */
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            position: relative;
        }
        h1 { text-align: center; color: #2c3e50; font-weight: 700; margin-bottom: 30px; font-size: 2.2rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 1em; font-weight: bold; transition: transform 0.2s, background 0.2s; background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); margin-bottom: 25px; }
        .btn-secondary:hover { background: rgba(52, 152, 219, 0.2); transform: translateY(-2px); }
        
        .panel { 
            background: rgba(255, 255, 255, 0.6); 
            border: 1px solid rgba(0,0,0,0.05); 
            border-radius: 12px; 
            padding: 25px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
            border-left: 5px solid #3498db;
        }
        .panel h2 { margin-top: 0; color: #2980b9; font-size: 1.3em; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

        .form-row { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .form-group { flex: 1; min-width: 250px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.95em; }
        select, input[type="datetime-local"], input[type="number"] { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 8px; font-size: 1em; background: rgba(255,255,255,0.9); outline:none; transition: border-color 0.3s, box-shadow 0.3s; }
        select:focus, input:focus { border-color: #3498db; box-shadow: 0 0 8px rgba(52, 152, 219, 0.3); }
        
        button.btn-primary { 
            width: 100%; padding: 15px; 
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
            color: white; border: none; border-radius: 8px; font-size: 1.1em; font-weight: bold; 
            cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; margin-top: 10px; 
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4);
        }
        button.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 242, 254, 0.6); }

        /* Status Messages */
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; text-align: left; font-weight: 600; animation: slideDown 0.4s ease-out; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hidden { display: none !important; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        th { background-color: #f8f9fa; padding: 12px 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        td { padding: 12px 15px; border-bottom: 1px solid #e9ecef; }
        tr:hover { background-color: #f1f3f5; }

    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
    <h1><i class="fas fa-cogs" style="color:#f39c12;"></i> Gestione Operativa</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <!-- SEZIONE ALERT: TASK SCADUTE -->
    <?php
    $queryExpired = "
        SELECT T.IdTask, T.Type, T.StartTime, T.EndTime, T.IdEmployee, U.Name, U.Surname, T.Status
        FROM Tasks T
        JOIN Employees E ON T.IdEmployee = E.IdEmployee
        JOIN Users U ON E.IdEmployee = U.IdUser
        WHERE T.Status = 'Expired'
    ";
    $resExpired = $conn->query($queryExpired);
    if ($resExpired->num_rows > 0):
    ?>
    <div class="panel" style="border-left: 5px solid #e74c3c; background-color: rgba(253, 234, 234, 0.8);">
        <h2 style="color: #c0392b;"><i class="fas fa-exclamation-triangle"></i> Task Scadute da Riassegnare</h2>
        <p style="margin-bottom:15px; color:#555;">Riassegna subito queste task definendo i nuovi orari.</p>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Task ID</th>
                        <th>Vecchio Assegnatario</th>
                        <th>Tipo</th>
                        <th>Nuovo Orario Inizio</th>
                        <th>Nuovo Orario Fine</th>
                        <th>Gestione</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($crow = $resExpired->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?php echo $crow['IdTask']; ?></strong></td>
                        <td><?php echo htmlspecialchars($crow['Name'] . ' ' . $crow['Surname']); ?></td>
                        <td><span style="background:#e0e0e0; padding:3px 8px; border-radius:12px; font-size:0.85em; font-weight:bold;"><?php echo $crow['Type']; ?></span></td>
                        
                        <form action="reset_task.php" method="POST" style="margin:0;">
                            <input type="hidden" name="id_task" value="<?php echo $crow['IdTask']; ?>">
                            <input type="hidden" name="old_employee" value="<?php echo $crow['Name'] . ' ' . $crow['Surname']; ?>">
                            
                            <td>
                                <input type="datetime-local" name="new_start_time" required 
                                       value="<?php echo date('Y-m-d\TH:i'); ?>" style="padding:8px;">
                            </td>
                            <td>
                                <input type="datetime-local" name="new_end_time" required 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>" style="padding:8px;">
                            </td>
                            <td>
                                <?php 
                                    $empList = ($crow['Type'] == 'Maintenance') ? $technicians : $restockers;
                                ?>
                                <select name="new_id_employee" style="padding:8px; margin-bottom:8px; width:100%;">
                                    <?php foreach($empList as $emp): ?>
                                        <option value="<?php echo $emp['IdEmployee']; ?>" <?php echo ($emp['IdEmployee'] == $crow['IdEmployee'] ?? '') ? 'selected' : ''; ?>>
                                            <?php echo $emp['Name'] . ' ' . $emp['Surname']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <br>
                                <button type="submit" style="background:linear-gradient(135deg, #ff0844 0%, #ffb199 100%); color:white; border:none; padding:8px 10px; border-radius:6px; cursor:pointer; width:100%; font-weight:bold; box-shadow: 0 4px 10px rgba(255, 8, 68, 0.4);"><i class="fas fa-redo-alt"></i> Riassegna</button>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <form action="assign_task.php" method="POST">
        
        <!-- SEZIONE 1: TIPO DI INTERVENTO -->
        <div class="panel">
            <h2><i class="fas fa-users-cog"></i> 1. Tipologia Intervento e Personale</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="task_type">Tipo di Dipendente / Intervento</label>
                    <select name="task_type" id="task_type" onchange="updateUI()" required>
                        <option value="" disabled selected>-- Seleziona --</option>
                        <option value="maintenance">🛠 Tecnico (Riparazione Gate)</option>
                        <option value="restock">📦 Magazziniere (Rifornimento)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_employee">Assegna a Dipendente</label>
                    <select name="id_employee" id="id_employee" required>
                        <option value="" disabled selected>-- Prima seleziona il tipo --</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- SEZIONE 2: PIANIFICAZIONE -->
        <div class="panel">
            <h2><i class="fas fa-calendar-alt"></i> 2. Pianificazione Temporale</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Data e Ora Inizio</label>
                    <input type="datetime-local" name="start_time" required>
                </div>
                <div class="form-group">
                    <label>Data e Ora Fine (Stimata)</label>
                    <input type="datetime-local" name="end_time" required>
                </div>
            </div>
        </div>

        <!-- SEZIONE 3: OBIETTIVO (DINAMICO) -->
        <div class="panel" id="target_panel">
            <h2 id="target_title"><i class="fas fa-bullseye"></i> 3. Dettagli Operazione</h2>
            
            <!-- CASO MANUTENZIONE: SCEGLI PORTA -->
            <div id="maintenance_target" class="hidden">
                 <div class="form-group">
                    <label>Seleziona Porta da Riparare (Richieste Pendenti)</label>
                    <select name="target_gate_req_id" id="target_gate_req_id">
                        <option value="">-- Seleziona Richiesta --</option>
                        <?php foreach($requests as $r): ?>
                            <option value="<?php echo $r['IdGate']; // Inviamo IdGate come target ?>">
                                [Gate <?php echo $r['IdGate']; ?>] 
                                Priorità: <?php echo $r['Priority']; ?> 
                                (Usura: <?php echo $r['Wear']; ?>%) 
                                - <?php echo $r['SectorDesc']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- CASO RIFORNIMENTO: SCEGLI ITEM -->
            <div id="restock_target" class="hidden">
                <div class="form-row">
                    <div class="form-group">
                        <label>Articolo da Rifornire</label>
                        <select name="target_item_id" id="target_item_id">
                            <option value="">-- Seleziona Articolo --</option>
                            <?php foreach($inventory as $i): ?>
                                <option value="<?php echo $i['IdItem']; ?>">
                                    <?php echo htmlspecialchars($i['Description']); ?> 
                                    (Attuale: <?php echo $i['Quantity']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantità da Aggiungere</label>
                        <input type="number" name="restock_quantity" value="10" min="1">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabella inventario -->
        <div class="panel" style="border-left: 5px solid #27ae60;">
            <h2 style="color:#27ae60; margin-bottom: 10px;"><i class="fas fa-boxes"></i> Stato Inventario Attuale</h2>
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

        <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> CONFERMA ASSEGNAZIONE</button>
    </form>
</div>

<script>
    // Dati passati da PHP a JS
    const technicians = <?php echo json_encode($technicians); ?>;
    const restockers = <?php echo json_encode($restockers); ?>;

    function updateUI() {
        const type = document.getElementById('task_type').value;
        const employeeSelect = document.getElementById('id_employee');
        const maintDiv = document.getElementById('maintenance_target');
        const restockDiv = document.getElementById('restock_target');
        
        // 1. Update Employee List
        employeeSelect.innerHTML = '<option value="">-- Seleziona --</option>';
        let list = [];
        if (type === 'maintenance') list = technicians;
        else if (type === 'restock') list = restockers;

        list.forEach(emp => {
            const opt = document.createElement('option');
            opt.value = emp.IdEmployee;
            opt.textContent = `${emp.Name} ${emp.Surname} (${emp.Role})`;
            employeeSelect.appendChild(opt);
        });

        // 2. Toggle Target Section
        if (type === 'maintenance') {
            maintDiv.classList.remove('hidden');
            restockDiv.classList.add('hidden');
            // Required logic
            document.getElementById('target_gate_req_id').required = true;
            document.getElementById('target_item_id').required = false;
        } else if (type === 'restock') {
            maintDiv.classList.add('hidden');
            restockDiv.classList.remove('hidden');
            // Required logic
            document.getElementById('target_gate_req_id').required = false;
            document.getElementById('target_item_id').required = true;
        }
    }
</script>

</body>
</html>
