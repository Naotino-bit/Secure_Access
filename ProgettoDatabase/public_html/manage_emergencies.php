<?php
require "db_connection.php";
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user'];

// Solo chi comanda può venire qui
$query = "SELECT U.IdBadge, B.BadgeLevel 
          FROM Users U 
          JOIN Badges B ON U.IdBadge = B.IdBadge 
          WHERE U.Email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res->fetch_assoc();
$stmt->close();

if (!$userData || $userData['BadgeLevel'] != 4) {
    header("Location: dashboard.php?error=" . urlencode("Accesso negato: Solo gli amministratori possono gestire le emergenze."));
    exit();
}

// Controlliamo cosa ha premuto l'admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $type = $_POST['type'];
            $sector = (int)$_POST['sector'];
            $durationMinutes = (int)$_POST['duration'];

            if ($durationMinutes < 1) $durationMinutes = 60;

            $insertStmt = $conn->prepare("INSERT INTO EmergencyEvents (StartTime, EndTime, Type, IdSector) VALUES (NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)");
            $insertStmt->bind_param("isi", $durationMinutes, $type, $sector);
            if ($insertStmt->execute()) {
                $msg = urlencode("Emergenza creata con successo!");
                header("Location: manage_emergencies.php?success=$msg");
                exit();
            } else {
                $msg = urlencode("Errore nella creazione dell'emergenza.");
                header("Location: manage_emergencies.php?error=$msg");
                exit();
            }
        } elseif ($_POST['action'] === 'resolve') {
            $eventId = (int)$_POST['event_id'];
            // Risolviamo l'emergenza subito
            $resolveStmt = $conn->prepare("UPDATE EmergencyEvents SET EndTime = NOW() WHERE IdEvent = ?");
            $resolveStmt->bind_param("i", $eventId);
            if ($resolveStmt->execute()) {
                $msg = urlencode("Emergenza risolta.");
                header("Location: manage_emergencies.php?success=$msg");
                exit();
            } else {
                $msg = urlencode("Errore nella risoluzione dell'emergenza.");
                header("Location: manage_emergencies.php?error=$msg");
                exit();
            }
        }
    }
}

// Prendiamo le info per riempire la lista e cerchiamo le emergenze ancora attive
$activeEmergenciesQ = $conn->query("
    SELECT E.*, S.Description as SectorName 
    FROM EmergencyEvents E 
    LEFT JOIN Sectors S ON E.IdSector = S.IdSector 
    WHERE NOW() BETWEEN E.StartTime AND E.EndTime
    ORDER BY E.StartTime DESC
");

// Prendiamo tutte le stanze per farle scegliere
$sectorsQ = $conn->query("SELECT * FROM Sectors ORDER BY Description, IdSector");

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Gestione Emergenze</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%234facfe' d='M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C25.5 92 16 110.1 16 130.1c0 231 161.4 336.8 226.7 372.4a47.79 47.79 0 0 0 46.5 0C354.6 466.9 512 361.1 512 130.1c0-20-9.5-38.1-26.6-46.4z'/%3E%3C/svg%3E">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; padding: 40px 20px; color: #333; }
        .container { 
            max-width: 900px; margin: 0 auto; 
            background: rgba(255, 255, 255, 0.85); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4); border-radius: 20px; 
            padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
        }
        h2 { text-align: center; color: #e74c3c; font-weight: 700; margin-bottom: 25px; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 2.2rem; border-bottom: none; }
        h3 { color: #2c3e50; margin-top: 30px; margin-bottom: 15px; font-size: 1.5em; display: flex; align-items: center; gap: 8px; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border: none; border-radius: 8px; color: white; cursor: pointer; text-decoration: none; font-size: 1em; font-weight: bold; transition: transform 0.2s, box-shadow 0.2s; }
        .btn-danger { background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); box-shadow: 0 4px 15px rgba(255, 8, 68, 0.4); width: 100%; margin-top: 15px; padding: 15px; font-size: 1.1em; }
        .btn-danger:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255, 8, 68, 0.6); }
        .btn-primary { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 242, 254, 0.6); }
        .btn-secondary { background: rgba(52, 152, 219, 0.1); color: #3498db; box-shadow: none; border: 1px solid rgba(52, 152, 219, 0.3); }
        .btn-secondary:hover { background: rgba(52, 152, 219, 0.2); transform: translateY(-2px); }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 25px; text-align: left; font-weight: 600; animation: slideDown 0.4s ease-out; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        table { width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 10px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        th { background-color: #f8f9fa; padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6; }
        td { padding: 15px; border-bottom: 1px solid #e9ecef; }
        tr:hover { background-color: #f1f3f5; transition: background 0.3s; }

        .form-panel { background: rgba(255, 255, 255, 0.6); padding: 25px; border: 1px solid rgba(0,0,0,0.05); border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border-left: 5px solid #e74c3c; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; color: #555; margin-bottom: 8px; font-size: 0.95em; }
        .form-group select, .form-group input { width: 100%; padding: 12px; box-sizing: border-box; border: 1px solid #ced4da; border-radius: 8px; background: rgba(255,255,255,0.9); outline:none; transition: border-color 0.3s, box-shadow 0.3s; font-size: 1em; }
        .form-group select:focus, .form-group input:focus { border-color: #e74c3c; box-shadow: 0 0 8px rgba(231, 76, 60, 0.3); }
        
        .empty-state { text-align: center; padding: 30px; color: #7f8c8d; font-style: italic; background: rgba(255,255,255,0.5); border-radius: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="btn btn-secondary" style="margin-bottom: 25px;"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
        <h2><i class="fas fa-exclamation-triangle"></i> Gestione Emergenze</h2>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <h3><i class="fas fa-bell"></i> Emergenze Attive</h3>
        <?php if ($activeEmergenciesQ->num_rows > 0): ?>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tipo</th>
                            <th>Stanza Coinvolta</th>
                            <th><i class="far fa-clock"></i> Inizio</th>
                            <th><i class="far fa-clock"></i> Fine Prevista</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $activeEmergenciesQ->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['IdEvent'] ?></strong></td>
                                <td style="font-weight: bold; color: <?= $row['Type'] == 'Incendio' ? '#e74c3c' : '#f39c12' ?>;">
                                    <i class="fas <?= $row['Type'] == 'Incendio' ? 'fa-fire' : 'fa-wind' ?>"></i> <?= htmlspecialchars($row['Type']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['SectorName'] ?? 'Tutte/Sconosciuta') ?> (ID: <?= $row['IdSector'] ?>)
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['StartTime'])) ?></td>
                                <td style="color:#e74c3c; font-weight:600;"><?= date('d/m/Y H:i', strtotime($row['EndTime'])) ?></td>
                                <td>
                                    <form id="form_resolve_<?= $row['IdEvent'] ?>" method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="resolve">
                                        <input type="hidden" name="event_id" value="<?= $row['IdEvent'] ?>">
                                        <button type="button" class="btn btn-primary" onclick="openGenericConfirmModal('Sei sicuro di voler risolvere questa emergenza?', 'form_resolve_<?= $row['IdEvent'] ?>');"><i class="fas fa-shield-alt"></i> Risolvi Ora</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-leaf" style="font-size: 2em; color: #2ecc71; margin-bottom: 10px; display: block;"></i>
                Non ci sono emergenze attive al momento. Tutto tranquillo!
            </div>
        <?php endif; ?>

        <h3><i class="fas fa-power-off" style="color:#e74c3c;"></i> Scatena Nuova Emergenza (Test/Manuale)</h3>
        <div class="form-panel">
            <form id="form_create_emergency" method="POST">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label>Tipo di Emergenza:</label>
                    <select name="type" required>
                        <option value="Fuga di gas">Fuga di gas (Blocca l'ingresso, permette l'uscita)</option>
                        <option value="Incendio">Incendio (Apre TUTTI i varchi della struttura)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Settore/Stanza (Rilevante soprattutto per la Fuga di gas):</label>
                    <select name="sector" required>
                        <?php while ($sec = $sectorsQ->fetch_assoc()): ?>
                            <option value="<?= $sec['IdSector'] ?>">Stanza <?= $sec['IdSector'] ?> - <?= htmlspecialchars($sec['Description']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Durata (in Minuti):</label>
                    <input type="number" name="duration" value="60" min="1" required>
                </div>

                <button type="button" class="btn btn-danger" onclick="openGenericConfirmModal('Attenzione! Questa azione modificherà l\'accesso ai varchi e allarmerà il sistema. Procedere?', 'form_create_emergency');"><i class="fas fa-broadcast-tower"></i> Genera Emergenza</button>
            </form>
        </div>

    <!-- Messaggio di conferma per sicurezza -->
    <div id="genericConfirmModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); overflow:auto;">
        <div style="background-color:#fff; margin:10% auto; padding:0; border-radius:15px; width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out;">
            <div style="background: linear-gradient(135deg, #f39c12, #e67e22); padding:20px; border-radius:15px 15px 0 0; color:white; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;"><i class="fas fa-exclamation-triangle"></i> Conferma Azione</h3>
                <span onclick="closeGenericConfirmModal()" style="cursor:pointer; font-size:1.5em; font-weight:bold;">&times;</span>
            </div>
            
            <div style="padding:30px; text-align:center;">
                <p id="genericConfirmMessage" style="font-size:1.2em; color:#2c3e50; margin:0;">Sei sicuro di voler procedere?</p>
            </div>

            <div style="padding:20px; background:#f1f3f5; border-radius:0 0 15px 15px; text-align:right;">
                <button type="button" onclick="closeGenericConfirmModal()" class="btn" style="background:#95a5a6; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; margin-right:10px; box-shadow:none;"><i class="fas fa-times"></i> Annulla</button>
                <button type="button" id="confirmGenericBtn" class="btn" style="background:#e67e22; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; box-shadow:0 4px 6px rgba(230,126,34,0.3);"><i class="fas fa-check-circle"></i> Conferma</button>
            </div>
        </div>
    </div>

    <style>
    @keyframes slideIn {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    </style>

    <script>
    // Gestiamo i messaggi di "Sei sicuro?"
    if (typeof currentGenericFormId === 'undefined') {
        window.currentGenericFormId = null;

        window.openGenericConfirmModal = function(message, formId) {
            document.getElementById('genericConfirmMessage').innerText = message;
            window.currentGenericFormId = formId;
            document.getElementById('genericConfirmModal').style.display = 'block';
        }

        window.closeGenericConfirmModal = function() {
            document.getElementById('genericConfirmModal').style.display = 'none';
            window.currentGenericFormId = null;
        }

        document.getElementById('confirmGenericBtn').addEventListener('click', function() {
            if(window.currentGenericFormId) {
                document.getElementById(window.currentGenericFormId).submit();
            }
        });

        // Se clicchi fuori dal box, lui si chiude
        document.addEventListener('click', function(event) {
            const genericModalEl = document.getElementById('genericConfirmModal');
            if (event.target == genericModalEl) {
                closeGenericConfirmModal();
            }
        });
    }
    </script>
</body>
</html>
