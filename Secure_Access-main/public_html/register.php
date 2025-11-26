<?php
    require "db_connection.php"; 
    require "send_email.php";
    session_start();

    // Impostazioni Data e Timezone
    date_default_timezone_set('Europe/Rome'); 
    $date = date("Y-m-d H:i:s");
    $expiration = date("Y-m-d H:i:s", strtotime("+1 year")); // Scade tra un anno

    // 1. Controllo se l'utente è già loggato
    if(isset($_SESSION["user"])) { 
        header("Location: dashboard.php");
        exit();
    }

    // 2. Recupero Dati dal Form
    $name = trim($_POST["name"] ?? '');
    $surname = trim($_POST["surname"] ?? '');
    $dateBirth = trim($_POST["dateBirth"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $reason = trim($_POST["reason"] ?? '');

    // --- INIZIO CONTROLLI DI SICUREZZA ---

    // Check validità formato email
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        header("Location: index.php?mode=register&error=email_non_valida");
        exit();
    }

    // Check esistenza dominio email (MX Record)
    $domain = substr(strrchr($email, "@"), 1); 
    if (!checkdnsrr($domain, "MX")){ 
        header("Location: index.php?mode=register&error=dominio_inesistente");
        exit();
    }

    // Check complessità password
    $passwordRegex = "/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/";
    if(!preg_match($passwordRegex, $password)){ 
        header("Location: index.php?mode=register&error=password_debole");
        exit();
    }

    // --- FINE CONTROLLI DI SICUREZZA ---


    // 3. Controllo se l'email esiste già (in Users o Visitors)
    $query = "SELECT Email FROM Users WHERE Email = ? UNION SELECT Email FROM Visitors WHERE Email = ?" ; 
    $stmt = $conn->prepare($query) ;
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) { 
        $stmt->close();
        header("Location: index.php?mode=register&error=email_gia_registrata");
        exit();
    }
    $stmt->close();
    

    // 4. REGISTRAZIONE CON TRANSAZIONE (Atomica)
    // Se qualcosa fallisce (DB o Mail), si annulla tutto automaticamente.

    $conn->begin_transaction();

    try {
        // A. Creazione Badge (Livello 1 - Visitatore)
        $stmt = $conn->prepare("INSERT INTO Badges (DateOfIssue, ExpirationDate, BadgeLevel) VALUES (?,?,1)");
        $stmt->bind_param("ss", $date, $expiration);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore durante la creazione del Badge.");
        }
        $badgeId = $conn->insert_id; // Recuperiamo l'ID appena creato
        $stmt->close();

        // B. Preparazione Dati Utente
        $token = bin2hex(random_bytes(16)); // Token per la mail
        $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Password cifrata

        // C. Inserimento Visitatore
        $stmt = $conn->prepare("INSERT INTO Visitors (IdBadge, Name, Surname, DateBirth, Email, Password, Reason, token, is_verified) VALUES (?,?,?,?,?,?,?,?,0)");
        $stmt->bind_param("isssssss", $badgeId, $name, $surname, $dateBirth, $email, $hashed_password, $reason, $token);
        
        if (!$stmt->execute()) {
            throw new Exception("Errore durante l'inserimento del Visitatore.");
        }
        $stmt->close();

        // D. Invio Email di Verifica
        if (SendVerificationEmail($email, $token)){
            
            // SE LA MAIL PARTE -> CONFERMIAMO LE MODIFICHE AL DB
            $conn->commit(); 
            
            // Output di successo
            ?>
            <!DOCTYPE html>
            <html lang="it">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Registrazione Completata</title>
                <style>
                    body { font-family: sans-serif; text-align: center; padding: 50px; }
                    button { padding: 10px 20px; cursor: pointer; background-color: #007bff; color: white; border: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <h1>Registrazione quasi completata!</h1>
                <p>Abbiamo inviato una mail a <strong><?php echo htmlspecialchars($email); ?></strong>.</p>
                <p>Controlla la tua casella di posta e clicca sul link per attivare l'account.</p>
                <br>
                <a href='index.php'><button>Torna alla Home</button></a>
            </body>
            </html>
            <?php
            exit();

        } else {
            // SE LA MAIL NON PARTE -> Lanciamo un errore per attivare il catch
            throw new Exception("Impossibile inviare la mail di verifica.");
        }

    } catch (Exception $e) {
        // E. GESTIONE ERRORE -> ROLLBACK
        // Annulla tutte le query fatte dall'inizio della transazione (rimuove Badge e Visitatore)
        $conn->rollback();

        // Log dell'errore (opzionale, utile per debug)
        error_log("Errore Registrazione: " . $e->getMessage());

        // Redirect con messaggio di errore generico
        header("Location: index.php?mode=register&error=errore_invio_mail_riprova");
        exit();
    }
?>