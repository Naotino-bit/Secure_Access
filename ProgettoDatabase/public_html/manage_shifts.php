<?php
require "db_connection.php";
session_start();

// Vediamo se chi entra è un admin
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

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

if (!$rowAdmin || $rowAdmin['BadgeLevel'] < 3) {
    die("ACCESSO NEGATO: Non hai i permessi per gestire i turni.");
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Orari Turni | GATES</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%234facfe' d='M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C25.5 92 16 110.1 16 130.1c0 231 161.4 336.8 226.7 372.4a47.79 47.79 0 0 0 46.5 0C354.6 466.9 512 361.1 512 130.1c0-20-9.5-38.1-26.6-46.4z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        
        .admin-section { 
            background: white; 
            padding: 30px; 
            border-radius: 15px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            border-top: 5px solid #2ecc71; 
            margin-bottom: 30px;
        }

        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background-color: #f8f9fa; color: #495057; font-weight: 600; }
        tr:hover { background-color: #f1f3f5; }

        .custom-select {
            appearance: none; -webkit-appearance: none; -moz-appearance: none;
            background-color: #fff;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95em;
            color: #374151;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            font-weight: 500;
        }
        .custom-select:focus { border-color: #3498db; box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2); }

        .btn { 
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; 
            text-decoration: none; font-weight: 600; border-radius: 8px; border: none; 
            cursor: pointer; transition: all 0.3s ease; padding: 10px 20px;
        }
        .btn-secondary { background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); margin-bottom: 25px; }
        .btn-secondary:hover { background: rgba(52, 152, 219, 0.2); transform: translateY(-2px); }
        .btn-green { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4); }
        .btn-green:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4); }

        /* Modal simple style */
        #genericConfirmModal {
            display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; 
            background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px);
        }
        .modal-content {
            background-color:#fff; margin:10% auto; padding:0; border-radius:15px; 
            width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        /* Alerts */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
        
        <h1><i class="far fa-clock" style="color: #2ecc71;"></i> Gestione Orari Turni</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> Operazione completata con successo!</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Errore: <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="admin-section">
            <?php
                $shiftsQuery = "SELECT IdRole, Role, Start, End FROM Shifts ORDER BY IdRole ASC";
                $resultShifts = $conn->query($shiftsQuery);
            ?>

            <?php if ($resultShifts->num_rows > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Ruolo</th>
                                <th>Inizio Turno</th>
                                <th>Fine Turno</th>
                                <th>Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($shift = $resultShifts->fetch_assoc()): ?>
                                <tr>
                                    <td style="font-weight:bold; color:#2c3e50;"><?php echo htmlspecialchars($shift['Role']); ?></td>
                                    <td>
                                        <form id="form_shift_<?php echo $shift['IdRole']; ?>" action="update_shift.php" method="POST" style="display:flex; gap:10px; align-items:center; margin:0;">
                                            <input type="hidden" name="id_role" value="<?php echo $shift['IdRole']; ?>">
                                            <input type="time" name="start_time" id="start_<?php echo $shift['IdRole']; ?>" value="<?php echo date('H:i', strtotime($shift['Start'])); ?>" class="custom-select" style="width:130px;" required>
                                    </td>
                                    <td>
                                            <input type="time" name="end_time" id="end_<?php echo $shift['IdRole']; ?>" value="<?php echo date('H:i', strtotime($shift['End'])); ?>" class="custom-select" style="width:130px;" required>
                                    </td>
                                    <td>
                                            <button type="button" class="btn btn-green" style="padding: 8px 15px; font-size:0.9em;" onclick="const s = document.getElementById('start_<?php echo $shift['IdRole']; ?>').value; const e = document.getElementById('end_<?php echo $shift['IdRole']; ?>').value; openConfirm('Confermi l\'aggiornamento degli orari per il ruolo <?php echo addslashes($shift['Role']); ?> (' + s + ' - ' + e + ')?', 'form_shift_<?php echo $shift['IdRole']; ?>');"><i class="fas fa-save"></i> Salva</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="color:#7f8c8d; font-style:italic; padding: 10px 0;"><i class="fas fa-info-circle"></i> Nessun turno configurato nel database.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Conferma -->
    <div id="genericConfirmModal">
        <div class="modal-content">
            <div style="background: linear-gradient(135deg, #f39c12, #e67e22); padding:20px; border-radius:15px 15px 0 0; color:white; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><i class="fas fa-exclamation-triangle"></i> Conferma Azione</h3>
                <span onclick="closeConfirm()" style="cursor:pointer; font-size:1.5em; font-weight:bold;">&times;</span>
            </div>
            <div style="padding:30px; text-align:center;">
                <p id="confirmMessage" style="font-size:1.1em; color:#2c3e50; margin:0;">Sei sicuro di voler procedere?</p>
            </div>
            <div style="padding:20px; background:#f1f3f5; border-radius:0 0 15px 15px; text-align:right;">
                <button type="button" onclick="closeConfirm()" class="btn" style="background:#95a5a6; color:white; margin-right:10px;">Annulla</button>
                <button type="button" id="confirmBtn" class="btn" style="background:#e67e22; color:white;">Conferma</button>
            </div>
        </div>
    </div>

    <script>
        let currentFormId = null;
        function openConfirm(msg, formId) {
            document.getElementById('confirmMessage').innerText = msg;
            currentFormId = formId;
            document.getElementById('genericConfirmModal').style.display = 'block';
        }
        function closeConfirm() {
            document.getElementById('genericConfirmModal').style.display = 'none';
        }
        document.getElementById('confirmBtn').onclick = function() {
            if(currentFormId) document.getElementById(currentFormId).submit();
        };
        window.onclick = function(event) {
            if (event.target == document.getElementById('genericConfirmModal')) closeConfirm();
        }
    </script>
</body>
</html>
