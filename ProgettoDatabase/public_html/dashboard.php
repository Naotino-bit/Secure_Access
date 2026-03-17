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
        $badgeQuery = $conn->prepare("SELECT U.IdBadge, B.BadgeLevel, S.Role FROM Users U LEFT JOIN Badges B ON U.IdBadge = B.IdBadge LEFT JOIN Employees E ON U.IdUser = E.IdEmployee LEFT JOIN Shifts S ON S.IdRole = E.IdRole WHERE U.Email = ?");
        $badgeQuery->bind_param("s", $email);
        $badgeQuery->execute();
        $badgeResult = $badgeQuery->get_result();
        if ($bRow = $badgeResult->fetch_assoc()) {
            $idBadge = $bRow['IdBadge'];
            $bLevel = $bRow['BadgeLevel'];
            $role = $bRow['Role'];
            
            // Trova ultima posizione interattiva reale
            $posQuery = $conn->prepare("SELECT IdSectorTo FROM Accesses WHERE IdBadge = ? AND Result IN ('GRANTED', 'AUTO_EXIT') ORDER BY IdAccess DESC LIMIT 1");
            $posQuery->bind_param("i", $idBadge);
            $posQuery->execute();
            $posResult = $posQuery->get_result();
            if ($pRow = $posResult->fetch_assoc()) {
                $realLastPos = $pRow['IdSectorTo'];
            }
            $posQuery->close();
            
            // Inizializza admin_mode in sessione se manca
            if ($bLevel == 4) {
                $_SESSION['admin_mode'] = 'interactive'; // Admin maintains interactive map only
            } elseif ($role === 'Sorveglianza' && !isset($_SESSION['admin_mode'])) {
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
        SELECT U.IdUser, U.IdBadge, U.Name, U.Surname, E.IdRole, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue, S.Role
        FROM Users U
        JOIN Badges B ON U.IdBadge = B.IdBadge
        LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
        LEFT JOIN Shifts S ON E.IdRole = S.IdRole
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
    <title>Cruscotto Operativo | GATES</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.4);
            --cyan: #1bc2cc;
            --yellow: #e1b12c;
            --green: #00a859;
            --pink: #e64b7c;
            --unime-blue: #0072b8;
            --text-dark: #2c3e50;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }

        body {
            background: var(--bg-gradient);
            min-height: 100vh;
            color: #333;
            overflow-x: hidden;
        }

        /* Header Principale */
        .app-header {
            background-color: var(--unime-blue);
            color: white;
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-logo i { font-size: 2.8rem; text-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        .header-logo .logo-text { line-height: 1.2; }
        .header-logo .logo-text strong { font-size: 1.3rem; letter-spacing: 0.5px; }
        .header-logo .logo-text span { font-size: 0.95rem; opacity: 0.9; font-weight: 300;}
        
        .header-right { text-align: right; line-height: 1.1; display:flex; flex-direction:column; align-items:flex-end;}
        .header-right h2 { margin: 0; font-size: 1.8rem; font-weight: 700; letter-spacing: 1px; }
        .header-right span { font-size: 0.9rem; font-weight: 300; opacity: 0.9; }

        /* Struttura Dashboard */
        .dashboard-container {
            max-width: 1400px;
            margin: 30px auto;
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 25px;
            padding: 0 20px;
            align-items: start;
        }

        /* Stile Widget Base (Glassmorphism) */
        .widget {
            background: var(--glass-bg);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            margin-bottom: 25px;
        }
        .widget:last-child { margin-bottom: 0; }

        /* Header Widget */
        .widget-header {
            padding: 18px 20px;
            color: white;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: 0.3px;
        }
        .widget-header .header-text { display: flex; flex-direction: column; }
        .widget-header .header-text span { font-size: 0.75rem; font-weight: 400; opacity: 0.9; margin-top: 2px; text-transform: none; letter-spacing: 0; }
        .widget-header i.fa-3x, .widget-header i.fa-2x { font-size: 2.2rem; margin-right: 5px; opacity: 0.9;}

        .yellow-header { background-color: var(--yellow); }
        .cyan-header { background-color: var(--cyan); }
        .green-header { background-color: var(--green); }
        .pink-header { background-color: var(--pink); }
        .blue-header { background-color: var(--unime-blue); }

        /* Security Badge Design - Initials Style */
        .security-badge {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        .badge-header {
            height: 48px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Livelli Sicurezza Premium */
        .level-1-header { background: linear-gradient(135deg, #718093 0%, #2f3640 100%); } /* Steel */
        .level-2-header { background: linear-gradient(135deg, #0097e6 0%, #00a8ff 100%); } /* Ocean */
        .level-3-header { background: linear-gradient(135deg, #e1b12c 0%, #fbc531 100%); } /* Gold */
        .level-4-header { background: linear-gradient(135deg, #8c7ae6 0%, #9c88ff 100%); } /* Royal */

        .badge-content {
            padding: 25px 20px;
            text-align: center;
        }

        .profile-initials {
            width: 85px;
            height: 85px;
            background: #f5f6fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px auto;
            border: 3px solid #dcdde1;
            color: #2f3640;
            font-size: 2.2rem;
            font-weight: 800;
            text-transform: uppercase;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .badge-username {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .badge-role {
            display: inline-block;
            background: #f1f3f5;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }

        .badge-info-list {
            list-style: none;
            padding: 0;
            margin: 0 0 25px 0;
            text-align: left;
            border-top: 1px solid #f1f3f5;
            padding-top: 15px;
        }

        .badge-info-list li {
            padding: 8px 0;
            font-size: 0.9rem;
            color: #555;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px dashed #f1f3f5;
        }
        .badge-info-list li i { color: var(--unime-blue); width: 16px; text-align: center; }
        /*.badge-info-list li strong { color: #333; margin-left: auto; }*/

        .btn-logout-sidebar {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; border: 1px solid #ddd; background: #fff; 
            padding: 12px; border-radius: 8px; color: #e74c3c; 
            text-decoration: none; font-weight: 600; transition: all 0.2s;
        }
        .btn-logout-sidebar:hover { background: #feebeb; border-color: #f5c6cb; }

        /* Contenuto Main App Grid */
        .main-content {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .wide-widget {
            grid-column: 1 / -1;
        }

        .widget-apps-container {
            display: flex;
            flex-wrap: wrap;
            padding: 25px 20px;
            gap: 25px;
            justify-content: flex-start;
        }

        .app-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 130px;
            height: 130px;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.2s ease;
            text-align: center;
            border-radius: 12px;
            padding: 10px;
        }
        .app-icon:hover { 
            transform: translateY(-5px); 
            background: rgba(255,255,255,0.5);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .app-icon i { margin-bottom: 15px; font-size: 3rem; transition: color 0.3s;}
        .app-icon span { font-size: 0.9rem; line-height: 1.2; font-weight: 600;}

        /* Colori Isole/App */
        .text-cyan { color: var(--cyan); }
        .text-blue { color: var(--unime-blue); }
        .text-yellow { color: #f39c12; }
        .text-red { color: #e74c3c; }
        .text-green { color: var(--green); }
        .text-purple { color: #9b59b6; }

        /* Stato e Alert Integrati */
        .global-alerts { grid-column: 1 / -1; display: flex; flex-direction: column; gap: 10px;}
        .msg-box { padding: 15px 20px; border-radius: 10px; font-weight: 600; display: flex; align-items: center; gap: 10px; animation: slideDown 0.4s ease-out; }
        .msg-box.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg-box.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .panel-alert { padding: 15px 20px; margin: 0 20px 20px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: 500;}
        .panel-alert.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .panel-alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .panel-alert.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Chimico */
        .chem-form-wrapper { padding: 25px; text-align: center; }
        .chem-form { display: flex; align-items: center; justify-content: center; gap: 15px; margin-top: 15px;}
        .chem-form input { padding: 12px; border: 1px solid #ced4da; border-radius: 8px; width: 90px; text-align: center; outline: none; font-size: 1.1rem;}
        .chem-form input:focus { border-color: var(--pink); box-shadow: 0 0 8px rgba(230,75,124,0.3); }
        .btn-app-action { background: var(--pink); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; cursor: pointer; transition: background 0.2s; font-size:1rem;}
        .btn-app-action:hover { background: #d83a6b; box-shadow: 0 4px 15px rgba(230,75,124,0.4); }

        /* Mappa Container */
        .map-container-inner { padding: 0; margin-bottom: -5px;}
        .map-container-inner iframe { width: 100%; height: 800px; display: block; border: none; }
        
        /* Contenitore Pannello Admin che rimpiazza tabelle */
        .admin-widget-content { padding: 25px; } 

        /* Pulsanti Glass per admin_panel */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s ease; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .btn-purple { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); color: white; box-shadow: 0 4px 15px rgba(161, 140, 209, 0.4); }
        .btn-yellow { background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: white; box-shadow: 0 4px 15px rgba(246, 211, 101, 0.4); }
        .btn-red { background: linear-gradient(135deg, #ff0844 0%, #ffb199 100%); color: white; box-shadow: 0 4px 15px rgba(255, 8, 68, 0.4); }
        .btn-green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: #1e3c72; box-shadow: 0 4px 15px rgba(67, 233, 123, 0.4); }
        .btn-blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; box-shadow: 0 4px 15px rgba(79, 172, 254, 0.4); }

        @media (max-width: 992px) {
            .dashboard-container { grid-template-columns: 1fr; }
            .header-right { display: none; }
            .main-content { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="header-logo">
        <i class="fas fa-shield-alt"></i>
        <div class="logo-text">
            <strong>The Facility</strong><br>
            <span>Secure Access Control System</span>
        </div>
    </div>
    <div class="header-right">
        <h2>PORTALE</h2>
    </div>
</header>

<div class="dashboard-container">
    
    <!-- Area Sinistra: Security Badge (Take 3) -->
    <aside class="sidebar">
        <div class="security-badge">
            <!-- Header con Livello -->
            <div class="badge-header level-<?php echo $badgeLevel; ?>-header">
                SECURITY LEVEL <?php echo $badgeLevel; ?>
            </div>
            
            <div class="badge-content">
                <!-- Foto Profilo con Iniziali -->
                <div class="profile-initials">
                    <?php 
                        $initials = substr($currentUser['Name'], 0, 1) . substr($currentUser['Surname'], 0, 1);
                        echo htmlspecialchars(strtoupper($initials));
                    ?>
                </div>

                <h4 class="badge-username"><?php echo htmlspecialchars($currentUser['Name'] . " " . $currentUser['Surname']); ?></h4>
                <div class="badge-role">
                    <?php echo $currentUser['Role'] ? htmlspecialchars($currentUser['Role']) : 'Visitatore'; ?>
                </div>
                
                <ul class="badge-info-list">
                    <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email); ?></li>
                    <li><i class="far fa-calendar-alt"></i> Scadenza: <strong><?php echo $currentUser['ExpirationDate'] ? date("d/m/Y", strtotime($currentUser['ExpirationDate'])) : "Illimitata"; ?></strong></li>
                    <li><i class="fas fa-id-card-alt"></i> Identificativo: <strong>#<?php echo str_pad((string)($currentUser['IdUser'] ?? $currentUser['IdBadge']), 5, '0', STR_PAD_LEFT); ?></strong></li>
                </ul>
                
                <a href="logout.php" class="btn-logout-sidebar"><i class="fas fa-sign-out-alt"></i> Esegui Logout</a>
            </div>
        </div>
    </aside>

    <!-- Area Destra: Griglia Applicazioni -->
    <main class="main-content">
        
        <!-- Notifiche Globali -->
        <?php if (isset($_GET['success']) || isset($_GET['error'])): ?>
        <div class="global-alerts">
            <?php if (isset($_GET['success']) && $_GET['success'] !== ''): ?>
                <div class="msg-box success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="msg-box success"><i class="fas fa-check-circle"></i> Operazione riuscita!</div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="msg-box error"><i class="fas fa-exclamation-triangle"></i> Errore: <?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Isola 0: Utenti Rifiutati / Licenziati -->
        <?php if ($badgeLevel == 0): ?>
        <div class="widget wide-widget">
            <div class="widget-header red-header" style="background-color: var(--pink);">
                <i class="fas fa-user-times fa-2x"></i>
                <div class="header-text">
                    STATUS: CANDIDATURA RIFIUTATA / LICENZIATO
                    <span>Il tuo badge è inattivo. Non hai accesso a nessuna area della struttura.</span>
                </div>
            </div>
            
            <div class="admin-widget-content" style="text-align: center; padding: 40px 20px;">
                <h3 style="color: var(--text-dark); margin-bottom: 15px;">Vuoi riprovare?</h3>
                <p style="color: #555; margin-bottom: 25px; line-height: 1.6;">
                    La tua precedente collaborazione o candidatura è stata interrotta.<br> 
                    Se ritieni che ci siano i presupposti per una nuova valutazione da parte dell'amministrazione, puoi inviare una nuova richiesta.
                </p>
                
                <form id="form_reapply" action="reapply.php" method="POST">
                    <button type="button" class="btn btn-red" style="padding: 12px 30px; font-size: 1.1em;" onclick="openGenericConfirmModal('Sei sicuro di voler inviare una nuova candidatura?', 'form_reapply');">
                        <i class="fas fa-paper-plane"></i> Invia Nuova Candidatura
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Isola 1: Operazioni Struttura (Admin & Employees) -->
        <?php if ($badgeLevel == 4 || ($badgeLevel >= 2 && $currentUser['Role'] !== 'Chimico' && $currentUser['Role'] !== 'Sorveglianza')): ?>
        <div class="widget">
            <div class="widget-header cyan-header">
                <i class="fas fa-briefcase fa-2x"></i>
                <div class="header-text">
                    OPERAZIONI STRUTTURA
                    <span>Servizi per i dipendenti e task strutturali.</span>
                </div>
            </div>
            
            <div class="widget-apps-container">
                <?php if ($badgeLevel == 4): ?>
                    <a href="maintenance_dashboard.php" class="app-icon">
                        <i class="fas fa-tools text-yellow"></i>
                        <span>Manutenzione<br>Struttura</span>
                    </a>
                    <a href="manage_emergencies.php" class="app-icon">
                        <i class="fas fa-bell text-red"></i>
                        <span>Gestione<br>Emergenze</span>
                    </a>
                <?php endif; ?>
                
                <?php if ($badgeLevel >= 2 && $badgeLevel < 4 && $currentUser['Role'] !== 'Chimico'): ?>
                    <a href="employee_dashboard.php" class="app-icon">
                        <i class="fas fa-tasks text-green"></i>
                        <span>Task<br></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Isola 2: Pannello ICT Controllo Live (Admin solo) -->
        <?php if ($badgeLevel == 4 || $currentUser['Role'] === 'Sorveglianza'): ?>
        <div class="widget">
            <div class="widget-header yellow-header">
                <i class="fas fa-laptop-code fa-2x"></i>
                <div class="header-text">
                    CONTROL PANEL
                    <span>Controllo Live del traffico e monitoraggio accessi.</span>
                </div>
            </div>
            
            <div class="widget-apps-container">
                <?php if ($badgeLevel == 4 || $currentUser['Role'] === 'Sorveglianza'): ?>
                    <a href="logs.php" class="app-icon">
                        <i class="fas fa-history text-blue"></i>
                        <span>Accessi<br>Varchi</span>
                    </a>
                <?php endif; ?>

                <?php if ($badgeLevel == 4): ?>
                    <a href="admin_logs.php" class="app-icon">
                        <i class="fas fa-user-shield text-purple"></i>
                        <span>Azioni<br>Admin</span>
                    </a>
                <?php endif; ?>

                <?php if ($currentUser['Role'] === 'Sorveglianza'): ?>
                    <?php 
                    $currentMode = isset($_SESSION['admin_mode']) ? $_SESSION['admin_mode'] : 'monitor';
                    if ($currentMode === 'monitor') { 
                    ?>
                        <a href="?toggle_mode=1" class="app-icon">
                            <i class="fas fa-exchange-alt text-purple"></i>
                            <span>Mappa<br>Interattiva</span>
                        </a>
                    <?php } else { ?>
                        <a id="btn-monitor-toggle" href="?toggle_mode=1" class="app-icon" style="display: <?php echo ($realLastPos == 25) ? 'flex' : 'none'; ?>;">
                            <i class="fas fa-desktop text-blue"></i>
                            <span>Monitoraggio<br>Live</span>
                        </a>
                    <?php } ?>
                <?php endif; ?>
            </div>

            <?php if ($currentUser['Role'] === 'Sorveglianza' && isset($currentMode) && $currentMode === 'interactive'): ?>
            <div id="monitor-warning" class="panel-alert warning" style="display: <?php echo ($realLastPos != 25) ? 'block' : 'none'; ?>;">
                <i class="fas fa-info-circle"></i> <strong>Attenzione:</strong> Il Monitoraggio Live è accessibile dalla postazione del <strong>centro di monitoraggio</strong> (Stanza 25). Ti trovi nella <strong id="warning-room">Stanza <?php echo htmlspecialchars($realLastPos); ?></strong>.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Isola 3: Lavoro Chimico -->
        <?php if ($currentUser['Role'] === 'Chimico'): ?>
        <div class="widget">
            <div class="widget-header pink-header">
                <i class="fas fa-vial fa-2x"></i>
                <div class="header-text">
                    STRUMENTI – LABORATORIO
                    <span>Risorse e applicativi per sintesi chimica.</span>
                </div>
            </div>
            
            <?php $isLab = in_array($realLastPos, [7, 8, 11]); ?>
            <div class="chem-form-wrapper" id="chemist-work-form" style="display: <?php echo $isLab ? 'block' : 'none'; ?>;">
                <p style="color:#2ecc71; font-weight:600;"><i class="fas fa-check-circle"></i> Autorizzazione OK. Sei in Laboratorio.</p>
                <form action="chemist_work.php" method="POST" class="chem-form">
                    <label style="font-weight:600; color:#555;">Dosi:</label>
                    <input type="number" name="qty" id="qty" value="1" min="1" max="5">
                    <button type="submit" class="btn-app-action"><i class="fas fa-flask"></i> Avvia Sintesi</button>
                </form>
            </div>

            <div id="chemist-warning-msg" class="panel-alert error" style="margin-top:20px; display: <?php echo $isLab ? 'none' : 'block'; ?>;">
                <i class="fas fa-exclamation-triangle"></i> Operazione inibita. Spostati fisicamente all'interno di un Laboratorio. Sei nella stanza <strong id="chemist-current-room"><?php echo htmlspecialchars($realLastPos); ?></strong>.
            </div>
        </div>
        <?php endif; ?>

        <!-- Isola 4: Mappa Gates (Wide) -->
        <div class="widget wide-widget">
            <div class="widget-header cyan-header">
                <i class="fas fa-map-marked-alt fa-2x"></i>
                <div class="header-text">
                    PLANIMETRIA E ACCESSI FISICI
                    <span>Visualizzazione strutturale e spostamento utente.</span>
                </div>
            </div>
            <div class="map-container-inner" style="border-radius: 0 0 12px 12px; overflow:hidden;">
                <iframe src="gates.php" frameborder="0"></iframe>
            </div>
        </div>

        <!-- Isola 5: Pannello Amministrazione Personale (Wide) -->
        <?php if ($badgeLevel == 4): ?>
        <div class="widget wide-widget">
            <div class="widget-header green-header">
                <i class="fas fa-users-cog fa-2x"></i>
                <div class="header-text">
                    RISORSE E CONVENZIONI
                    <span>Area riservata per assunzioni, rinnovi badge e licenziamenti.</span>
                </div>
            </div>
            <div class="admin-widget-content">
                <?php include 'includes/admin_panel.php'; ?>
            </div>
        </div>
        <?php endif; ?>

    </main>
</div>

<!-- Generic Confirm Modal for Dashboard -->
<div id="genericConfirmModal" style="display:none; position:fixed; z-index:999999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); overflow:auto;">
    <div style="background-color:#fff; margin:10% auto; padding:0; border-radius:15px; width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out;">
        <div style="background: linear-gradient(135deg, #f39c12, #e67e22); padding:20px; border-radius:15px 15px 0 0; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2em;"><i class="fas fa-exclamation-triangle"></i> Conferma Azione</h3>
            <span onclick="closeGenericConfirmModal()" style="cursor:pointer; font-size:1.5em; font-weight:bold;">&times;</span>
        </div>
        
        <div style="padding:30px; text-align:center;">
            <p id="genericConfirmMessage" style="font-size:1.1em; color:#2c3e50; margin:0;">Sei sicuro di voler procedere?</p>
        </div>

        <div style="padding:20px; background:#f1f3f5; border-radius:0 0 15px 15px; text-align:right;">
            <button type="button" onclick="closeGenericConfirmModal()" class="btn" style="background:#95a5a6; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; margin-right:10px;"><i class="fas fa-times"></i> Annulla</button>
            <button type="button" id="confirmGenericBtn" class="btn" style="background:#e67e22; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; box-shadow:0 4px 6px rgba(230,126,34,0.3);"><i class="fas fa-check-circle"></i> Conferma</button>
        </div>
    </div>
</div>

<style>
@keyframes slideIn {
    from { transform: translateY(-30px); opacity:0; }
    to { transform: translateY(0); opacity:1; }
}
</style>

<script>
// Generic Confirm Modal Logic
let currentGenericFormId = null;

function openGenericConfirmModal(message, formId) {
    document.getElementById('genericConfirmMessage').innerText = message;
    currentGenericFormId = formId;
    document.getElementById('genericConfirmModal').style.display = 'block';
}

function closeGenericConfirmModal() {
    document.getElementById('genericConfirmModal').style.display = 'none';
    currentGenericFormId = null;
}

document.getElementById('confirmGenericBtn').addEventListener('click', function() {
    if(currentGenericFormId) {
        document.getElementById(currentGenericFormId).submit();
    }
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const genericModalEl = document.getElementById('genericConfirmModal');
    if (event.target == genericModalEl) {
        closeGenericConfirmModal();
    }
});
</script>

<!-- Scripts dinamici per Mappa -->
<?php if ($currentUser['Role'] === 'Sorveglianza' && isset($currentMode) && $currentMode === 'interactive'): ?>
<script>
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'roomMoved') {
        const newPos = event.data.newPos;
        const btnToggle = document.getElementById('btn-monitor-toggle');
        const warningDiv = document.getElementById('monitor-warning');
        const warningRoom = document.getElementById('warning-room');
        
        if (btnToggle && warningDiv) {
            if (newPos == 25) {
                btnToggle.style.display = 'flex';
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

<?php if ($currentUser['Role'] === 'Chimico'): ?>
<script>
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'roomMoved') {
        const newPos = parseInt(event.data.newPos);
        const validLabs = [7, 8, 11];
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
                if (currentRoomSpan) currentRoomSpan.innerText = newPos;
            }
        }
    }
});
</script>
<?php endif; ?>

</body>
</html>