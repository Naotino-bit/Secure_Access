<?php
    require "db_connection.php";
    session_start();

    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit();
    }

    $email = $_SESSION['user']; 

    // Recupero dati utente e LIVELLO BADGE
    $query = "
        SELECT U.Name, U.Surname, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
        FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge WHERE U.Email = ?
        UNION
        SELECT V.Name, V.Surname, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
        FROM Visitors V JOIN Badges B ON V.IdBadge = B.IdBadge WHERE V.Email = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $email);
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
        .map-box { margin-top: 30px; border: 2px dashed #ccc; background: #eee; padding: 40px; text-align: center; border-radius: 10px; }
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
        <h3>IL TUO BADGE: 
            <?php 
                if($badgeLevel == 1) echo "VISITATORE";
                elseif($badgeLevel == 2) echo "DIPENDENTE";
                elseif($badgeLevel == 3) echo "AMMINISTRATORE";
            ?>
        </h3>
        <p>Scadenza: <?php echo $currentUser['ExpirationDate'] ? date("d/m/Y", strtotime($currentUser['ExpirationDate'])) : "Illimitata"; ?></p>
    </div>

    <div class="map-box" data-level="<?php echo $badgeLevel; ?>">
        <h2>🗺️ Mappa Edificio</h2>
        <p>Livello Accesso: <?php echo $badgeLevel; ?></p>
        <p>[Qui comparirà la mappa interattiva]</p>
    </div>

    <?php 
        // 1. SEI ADMIN (LIVELLO 3) -> Vedi tutto e puoi modificare
        if ($badgeLevel == 3) {
            include 'includes/admin_panel.php';
        } 
        // 2. SEI DIPENDENTE (LIVELLO 2) -> Vedi solo tabelle sola lettura
        elseif ($badgeLevel == 2) {
            include 'includes/employee_view.php';
        } 
        // 3. SEI VISITATORE (LIVELLO 1) -> Non vedi nulla sotto la mappa
    ?>

    <br>
    <a href="gates.php" style="color:#666; text-decoration:none;">&larr; Vai al Simulatore Gate</a>

</div>
</body>
</html>