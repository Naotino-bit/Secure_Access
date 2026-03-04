<?php
    require "db_connection.php";
    session_start();

    // Eseguiamo il controllo automatico per far uscire i dipendenti fuori orario
    require_once "includes/auto_teleport.php";

    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit();
    }

    $email = $_SESSION['user']; 

    // Admin Mode Toggle (Dobbiamo controllare la posizione reale prima di permettere il cambio)

    $realLastPos = 1;
    if (isset($_SESSION['user'])) {
        // Recupera ID Badge per query accessi
        $badgeQuery = $conn->prepare("SELECT U.IdBadge, B.BadgeLevel FROM Users U LEFT JOIN Badges B ON U.IdBadge = B.IdBadge WHERE U.Email = ?");
        $badgeQuery->bind_param("s", $email);
        $badgeQuery->execute();
        $badgeResult = $badgeQuery->get_result();
        if ($bRow = $badgeResult->fetch_assoc()) {
            $idBadge = $bRow['IdBadge'];
            $bLevel = $bRow['BadgeLevel'];
            
            // Trova ultima posizione interattiva reale
            $posQuery = $conn->prepare("SELECT IdSectorTo FROM Accesses WHERE IdBadge = ? AND Result = 'GRANTED' ORDER BY IdAccess DESC LIMIT 1");
            $posQuery->bind_param("i", $idBadge);
            $posQuery->execute();
            $posResult = $posQuery->get_result();
            if ($pRow = $posResult->fetch_assoc()) {
                $realLastPos = $pRow['IdSectorTo'];
            }
            $posQuery->close();
            
            // Inizializza admin_mode in sessione se manca
            if ($bLevel == 4 && !isset($_SESSION['admin_mode'])) {
                $_SESSION['admin_mode'] = ($realLastPos == 25) ? 'monitor' : 'interactive';
            }
        }
        $badgeQuery->close();
    }

    if (isset($_GET['toggle_mode'])) {
        $currentAdminMode = isset($_SESSION['admin_mode']) ? $_SESSION['admin_mode'] : 'monitor';
        
        // Se stiamo passando da interactive a monitor, dobbiamo essere nella stanza 25
        if ($currentAdminMode === 'interactive') {
            if ($realLastPos == 25) {
                $_SESSION['admin_mode'] = 'monitor';
            } else {
                // Tentativo sventato, non fare niente o mostra errore
            }
        } 
        // Se stiamo passando da monitor a interactive, è sempre permesso
        else {
            $_SESSION['admin_mode'] = 'interactive';
        }

        header("Location: dashboard.php");
        exit();
    }

    // Recupero dati utente e LIVELLO BADGE

    $query = "
        SELECT U.Name, U.Surname, E.IdRole, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue, S.Role
        FROM Users U
        JOIN Badges B ON U.IdBadge = B.IdBadge
        LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
        JOIN Shifts S ON E.IdRole = S.IdRole
        WHERE U.Email = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $currentUser = null;
    $badgeLevel = 0;

    if($row = $result->fetch_assoc()) {
        $currentUser = $row; 
        $badgeLevel = $row['BadgeLevel'];
    } else {
        session_destroy();
        header("Location: index.php");
        exit();
    }
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <style>
        /* CSS Base */
        body { font-family: sans-serif; padding: 20px; background-color: #f4f6f9; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; }
        .msg-box { padding: 10px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Tabelle */
        table { width: 100%; border-collapse: collapse; margin-top: 15px; background: white; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #e0e0e0; text-align: left; }
        th { background-color: #f8f9fa; color: #495057; font-weight: 600; }
        .btn-update { background-color: #007bff; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-logout { background-color: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; float: right; font-weight: bold; }
        .role-label { padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .role-admin { background-color: #e8daef; color: #8e44ad; }
        .role-dip { background-color: #d6eaf8; color: #2980b9; }

        /* Mappa */
        .map-box { margin-top: 30px; border: 2px dashed #ccc; background: #eee; padding: 0px; text-align: center; border-radius: 10px; width:  100% ;}
    </style>
</head>
<body>

<div class="container">
    
    <?php if (isset($_GET['success'])): ?>
        <div class="msg-box success">Operazione riuscita!</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="msg-box error">Errore: <?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <a href="logout.php" class="btn-logout">Esci</a>
    <h1>Benvenuto, <?php echo htmlspecialchars($currentUser['Name']); ?></h1>
    
    <div style="border-left: 5px solid #007bff; padding: 15px; background: #f8f9fa; margin-bottom: 20px;">
        <h3>IL TUO RUOLO: 
            <?php 
                echo $currentUser['Role'];
            ?>
        </h3>
        <p>Scadenza: <?php echo $currentUser['ExpirationDate'] ? date("d/m/Y", strtotime($currentUser['ExpirationDate'])) : "Illimitata"; ?></p>
    </div>

    <div class="map-box" data-level="<?php echo $badgeLevel; ?>">
        <iframe src="gates.php" frameborder="0" width="900px" height="800px"></iframe>
    </div>

    <?php 
        // 1. SEI ADMIN (LIVELLO 4) -> Vedi tutto e puoi modificare
        if ($badgeLevel == 4) {
            $currentMode = isset($_SESSION['admin_mode']) ? $_SESSION['admin_mode'] : 'monitor';
            
            echo '<div style="text-align:center; margin-bottom:20px;">';
            
            // Mostra il pulsante solo se sei in monitor mode, o se sei in stanza 25 in interactive mode
            if ($currentMode === 'monitor') {
                echo '<a href="?toggle_mode=1" class="btn" style="background:#8e44ad; color:white; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px; margin-right: 10px;">
                        <i class="fas fa-exchange-alt"></i> PASSA ALLA MODALITÀ INTERATTIVA (Mappa Cliccabile)
                      </a>';
            } else {
                // In modalità interattiva, stampiamo il bottone ma controlliamo dinamicamente CSS
                $displayBtn = ($realLastPos == 25) ? 'inline-block' : 'none';
                echo '<a id="btn-monitor-toggle" href="?toggle_mode=1" class="btn" style="background:#2980b9; color:white; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px; margin-right: 10px; display: ' . $displayBtn . ';">
                        <i class="fas fa-desktop"></i> PASSA ALLA MODALITÀ MONITOR (Posizioni Live)
                      </a>';
            }
            
            echo '<a href="maintenance_dashboard.php" style="background:#d35400; color:white; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px; display: inline-block;">
                    VAI ALLA GESTIONE MANUTENZIONE (ADMIN)
                  </a>
                </div>';
            
            // Messaggio se sei interattivo
            if ($currentMode === 'interactive') {
                $displayWarning = ($realLastPos != 25) ? 'block' : 'none';
                echo '<div id="monitor-warning" style="text-align:center; color:#7f8c8d; font-size: 0.9em; margin-bottom: 15px; display: ' . $displayWarning . ';">
                        <em>Il Monitoraggio in Tempo Reale è accessibile solo dalla stanza <strong>Monitoring Center (25)</strong>. Attualmente ti trovi nella stanza <span id="warning-room">' . htmlspecialchars($realLastPos) . '</span>.</em>
                       </div>';
            }

            include 'includes/admin_panel.php';
        } 
        
        // LINK PER TUTTI I DIPENDENTI (Task Assegnate) tranne Chimico
        if ($badgeLevel >= 2 && $currentUser['Role'] !== 'Chimico') {
             echo '<div style="margin:20px 0; text-align:center;">
                    <a href="employee_dashboard.php" style="background:#27ae60; color:white; padding:12px 25px; text-decoration:none; font-weight:bold; border-radius:5px; font-size:1.1em;">
                        VEDI LE MIE TASK (OPERATIVO)
                    </a>
                  </div>';
        } 
        
        // SEZIONE CHIMICO
        if ($currentUser['Role'] === 'Chimico') {
            // Controlla se è in un laboratorio (7, 8, 11 - Laboratory 1, 2, 3)
            $isLab = in_array($realLastPos, [7, 8, 11]);
            $displayForm = $isLab ? 'block' : 'none';
            $displayWarning = $isLab ? 'none' : 'block';
            
            echo '<div style="margin:20px 0; text-align:center; border:2px solid #9b59b6; padding:20px; border-radius:8px; background:#f9ebff;">';
            echo '<h3 style="color:#8e44ad; margin-top:0;">Pannello Chimico</h3>';
            
            // Formulino per consumare sostanze (Visibile solo se in lab)
            echo '<div id="chemist-work-form" style="display: ' . $displayForm . ';">';
            echo '<p>Ti trovi in un Laboratorio. Puoi iniziare a lavorare.</p>';
            echo '<form action="chemist_work.php" method="POST">';
            echo '<label for="qty">Sostanze chimiche da consumare:</label> ';
            echo '<input type="number" name="qty" id="qty" value="1" min="1" max="5" style="padding:5px; width:60px; margin-right:10px;">';
            echo '<button type="submit" style="background:#8e44ad; color:white; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:5px; border:none; cursor:pointer;">
                    LAVORA
                  </button>';
            echo '</form>';
            echo '</div>';
            
            // Messaggio di avviso (Visibile solo se NON in lab)
            echo '<div id="chemist-warning-msg" style="display: ' . $displayWarning . ';">';
            echo '<p style="color:#d35400; font-weight:bold;">Per lavorare devi trovarti all\'interno di un Laboratorio.</p>';
            echo '<p>La tua posizione attuale è: Stanza <span id="chemist-current-room">' . htmlspecialchars($realLastPos) . '</span></p>';
            echo '</div>';

            echo '</div>';
        }
    ?>

    <br>

</div>

<?php 
// Se l'admin è in modalità interattiva, aggiungi lo script per ascoltare gli eventi della mappa
if ($badgeLevel == 4 && isset($currentMode) && $currentMode === 'interactive'): ?>
<script>
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'roomMoved') {
        const newPos = event.data.newPos;
        const btnToggle = document.getElementById('btn-monitor-toggle');
        const warningDiv = document.getElementById('monitor-warning');
        const warningRoom = document.getElementById('warning-room');
        
        if (btnToggle && warningDiv) {
            if (newPos == 25) {
                btnToggle.style.display = 'inline-block';
                warningDiv.style.display = 'none';
            } else {
                btnToggle.style.display = 'none';
                warningDiv.style.display = 'block';
                if (warningRoom) warningRoom.innerText = newPos;
            }
        }
    }
});
</script>
<?php endif; ?>

<?php 
// Add script for Chemist to dynamically change UI when moving rooms
if ($currentUser['Role'] === 'Chimico'): ?>
<script>
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'roomMoved') {
        const newPos = parseInt(event.data.newPos);
        const validLabs = [7, 8, 11]; // Laboratory 1, 2, 3
        const isLab = validLabs.includes(newPos);
        
        const workForm = document.getElementById('chemist-work-form');
        const warningMsg = document.getElementById('chemist-warning-msg');
        const currentRoomSpan = document.getElementById('chemist-current-room');
        
        if (workForm && warningMsg) {
            if (isLab) {
                workForm.style.display = 'block';
                warningMsg.style.display = 'none';
            } else {
                workForm.style.display = 'none';
                warningMsg.style.display = 'block';
                if (currentRoomSpan) {
                    currentRoomSpan.innerText = newPos;
                }
            }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>