<?php
    require "db_connection.php";
    session_start();

    // 1. Controllo Accesso
    if (!isset($_SESSION['user'])) {
        header("Location: index.php");
        exit();
    }

    $email = $_SESSION['user']; 

    // 2. QUERY DATI UTENTE LOGGATO
    // Ho aggiunto B.ExpirationDate e B.DateOfIssue alla SELECT
    // così possiamo mostrare quando scade il badge
    $query = "
        SELECT U.Name, U.Surname, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
        FROM Users U 
        JOIN Badges B ON U.IdBadge = B.IdBadge 
        WHERE U.Email = ?
        
        UNION
        
        SELECT V.Name, V.Surname, B.BadgeLevel, B.ExpirationDate, B.DateOfIssue
        FROM Visitors V 
        JOIN Badges B ON V.IdBadge = B.IdBadge 
        WHERE V.Email = ?
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) { die("Errore nella query: " . $conn->error); }
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    // Variabili per l'utente corrente
    $currentUser = null;
    $badgeLevel = 0;

    if($row = $result->fetch_assoc()) {
        $currentUser = $row; // Salviamo tutti i dati in un array
        $badgeLevel = $row['BadgeLevel'];
    } else {
        die("Errore: Utente non trovato.");
    }
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Gestionale</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        
        /* Stile per il Badge (Visitatore) */
        .badge-card {
            border: 2px solid #007bff; border-radius: 10px; padding: 20px; 
            max-width: 350px; background: linear-gradient(135deg, #fff 0%, #e6f2ff 100%);
            margin-top: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .badge-header { border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 10px; font-weight: bold; color: #007bff; }
        .badge-info p { margin: 5px 0; }
        .expired { color: red; font-weight: bold; }

        /* Stile per la Tabella (Admin) */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }

        .btn-logout { background-color: #dc3545; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 20px;}
    </style>
</head>
<body>

<div class="container">
    <h1>Benvenuto, <?php echo htmlspecialchars($currentUser['Name'] . " " . $currentUser['Surname']); ?></h1>
    
    <?php if ($badgeLevel == 1): ?>
        
        <h2>Il tuo Badge Digitale</h2>
        <div class="badge-card">
            <div class="badge-header">VISITATORE AUTORIZZATO</div>
            <div class="badge-info">
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($currentUser['Name']); ?></p>
                <p><strong>Cognome:</strong> <?php echo htmlspecialchars($currentUser['Surname']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <hr>
                <p><strong>Emesso il:</strong> <?php echo date("d/m/Y", strtotime($currentUser['DateOfIssue'])); ?></p>
                <p><strong>Scadenza:</strong> 
                    <?php 
                        $scadenza = strtotime($currentUser['ExpirationDate']);
                        // Se oggi è maggiore della scadenza, scrivi in rosso
                        if (time() > $scadenza) {
                            echo "<span class='expired'>" . date("d/m/Y", $scadenza) . " (SCADUTO)</span>";
                        } else {
                            echo date("d/m/Y", $scadenza);
                        }
                    ?>
                </p>
            </div>
        </div>

    <?php elseif ($badgeLevel >= 2): ?>

        <?php 
            $ruolo = ($badgeLevel == 3) ? "Amministratore" : "Dipendente";
            echo "<p style='color:green; font-weight:bold;'>Accesso Livello: $ruolo</p>"; 
        ?>

        <h3>Lista Visitatori Registrati</h3>
        
        <?php
            // NUOVA QUERY: Recuperiamo tutti i visitatori per mostrarli all'admin
            // Facciamo una JOIN per vedere anche quando scadono i loro badge
            $adminQuery = "
                SELECT V.Name, V.Surname, V.Email, V.Reason, V.is_verified, B.ExpirationDate 
                FROM Visitors V
                JOIN Badges B ON V.IdBadge = B.IdBadge
                ORDER BY B.ExpirationDate DESC
            ";
            $resultVisitatori = $conn->query($adminQuery);
        ?>

        <?php if ($resultVisitatori->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Motivo</th>
                        <th>Stato</th>
                        <th>Gestione Ruolo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($vis = $resultVisitatori->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($vis['Name'] . " " . $vis['Surname']); ?></td>
                            <td><?php echo htmlspecialchars($vis['Email']); ?></td>
                            <td><?php echo htmlspecialchars($vis['Reason']); ?></td>
                            <td>
                                <?php echo $vis['is_verified'] ? "<span style='color:green'>Verificato</span>" : "<span style='color:red'>Non Verificato</span>"; ?>
                            </td>
                            <td>
                                <form action="promote.php" method="POST" style="display:flex; gap:10px;">
                                    <input type="hidden" name="email_to_promote" value="<?php echo $vis['Email']; ?>">
                                    
                                    <select name="new_level">
                                        <option value="1" selected>Visitatore (Lvl 1)</option>
                                        <option value="2">Dipendente (Lvl 2)</option>
                                        <option value="3">Admin (Lvl 3)</option>
                                    </select>

                                    <button type="submit" onclick="return confirm('Sei sicuro di voler cambiare il ruolo a questo utente?');">
                                        Aggiorna
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessun visitatore registrato al momento.</p>
        <?php endif; ?>

    <?php endif; ?>

    <a href="logout.php" class="btn-logout">Logout</a>
</div>

</body>
</html>