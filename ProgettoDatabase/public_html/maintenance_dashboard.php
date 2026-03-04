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
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h1 { text-align: center; color: #2c3e50; margin-bottom: 30px; }
        .back-link { text-decoration: none; color: #3498db; font-weight: 500; display: inline-block; margin-bottom: 20px; }
        
        .panel { border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin-bottom: 20px; background: #fff; }
        .panel h2 { margin-top: 0; color: #2980b9; font-size: 1.2em; border-bottom: 2px solid #f1f1f1; padding-bottom: 10px; margin-bottom: 20px; }

        .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
        .form-group { flex: 1; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #555; font-size: 0.9em; }
        select, input[type="datetime-local"], input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 1em; background: #fafafa; }
        select:focus, input:focus { border-color: #3498db; outline: none; background: #fff; }
        
        button.btn-primary { width: 100%; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 6px; font-size: 1.1em; font-weight: bold; cursor: pointer; transition: background 0.3s; margin-top: 10px; }
        button.btn-primary:hover { background: #219150; }

        /* Status Messages */
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; text-align: center; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .hidden { display: none; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-link">&larr; Torna alla Dashboard</a>
    <h1>Gestione Operativa</h1>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['msg']); ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
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
    <div class="panel" style="border-left: 5px solid #e74c3c; background-color: #fdeaea;">
        <h2 style="color: #c0392b;">⚠️ Task Scadute da Riassegnare</h2>
        <p>Riassegna subito queste task definendo i nuovi orari.</p>
        <table style="width:100%; border-collapse: collapse; margin-top:10px;">
            <thead>
                <tr style="text-align:left; background:#eee;">
                    <th style="padding:8px;">Task ID</th>
                    <th style="padding:8px;">Vecchio Assegnatario</th>
                    <th style="padding:8px;">Tipo</th>
                    <th style="padding:8px;">Nuovo Orario Inizio</th>
                    <th style="padding:8px;">Nuovo Orario Fine</th>
                    <th style="padding:8px;">Gestione</th>
                </tr>
            </thead>
            <tbody>
                <?php while($crow = $resExpired->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #ddd;">
                    <td style="padding:8px;">#<?php echo $crow['IdTask']; ?></td>
                    <td style="padding:8px;"><?php echo htmlspecialchars($crow['Name'] . ' ' . $crow['Surname']); ?></td>
                    <td style="padding:8px;"><?php echo $crow['Type']; ?></td>
                    
                    <form action="reset_task.php" method="POST" style="margin:0;">
                        <input type="hidden" name="id_task" value="<?php echo $crow['IdTask']; ?>">
                        <input type="hidden" name="old_employee" value="<?php echo $crow['Name'] . ' ' . $crow['Surname']; ?>">
                        
                        <td style="padding:8px;">
                            <input type="datetime-local" name="new_start_time" required 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" style="padding:5px;">
                        </td>
                        <td style="padding:8px;">
                            <input type="datetime-local" name="new_end_time" required 
                                   value="<?php echo date('Y-m-d\TH:i', strtotime('+1 hour')); ?>" style="padding:5px;">
                        </td>
                        <td style="padding:8px;">
                            <!-- Optional: Select new employee. For simplicity, we can default to 'Current' or allow pick from list. 
                                 Let's add a small select. -->
                            <!-- Reusing $technicians or $restockers depending on Type -->
                            <?php 
                                $empList = ($crow['Type'] == 'Maintenance') ? $technicians : $restockers;
                            ?>
                            <select name="new_id_employee" style="padding:5px; margin-bottom:5px; width:100%;">
                                <?php foreach($empList as $emp): ?>
                                    <option value="<?php echo $emp['IdEmployee']; ?>" <?php echo ($emp['IdEmployee'] == $crow['IdEmployee'] ?? '') ? 'selected' : ''; ?>>
                                        <?php echo $emp['Name'] . ' ' . $emp['Surname']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <br>
                            <button type="submit" style="background:#e74c3c; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer; width:100%;">RIASSEGNA</button>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <form action="assign_task.php" method="POST">
        
        <!-- SEZIONE 1: TIPO DI INTERVENTO -->
        <div class="panel">
            <h2>1. Tipologia Intervento e Personale</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="task_type">Tipo di Dipendente / Intervento</label>
                    <select name="task_type" id="task_type" onchange="updateUI()" required>
                        <option value="" disabled selected>-- Seleziona --</option>
                        <option value="maintenance">Tecnico (Riparazione Gate)</option>
                        <option value="restock">Magazziniere (Rifornimento)</option>
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
            <h2>2. Pianificazione Temporale</h2>
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
            <h2 id="target_title">3. Dettagli Operazione</h2>
            
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

        <button type="submit" class="btn-primary">CONFERMA ASSEGNAZIONE</button>
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
