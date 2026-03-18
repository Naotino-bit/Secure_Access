<?php
    require "db_connection.php"; 

    session_start();
    

    if(isset($_SESSION["user"])) {
        header("Location: dashboard.php");
        exit();
    }

    // Se qualcuno prova a entrare via URL lo rimbalziamo
    // torna al punto di partenza
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Location: index.php");
        exit();
    }
    
    // cerchiamo di prendere l'email e la pass, altrimenti le lasciamo vuote
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $_SESSION["error_message"] = "Formato email non valido";
        header("Location: index.php");
        exit();
    }
    

    // controllo di sicurezza, non si sa mai
    if (empty($email) || empty($password)) {
        $_SESSION["error_message"] = "Inserire email e password";
        header("Location: index.php");
        exit();
    }
    // Vediamo se l'utente esiste e se la pass è corretta


    $query= "SELECT Email, Password, is_verified FROM Users WHERE Email = (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result= $stmt->get_result();

    if($row = $result->fetch_assoc()){
        if (password_verify($password, $row['Password'])) {

            if(isset($row['is_verified']) && $row['is_verified'] == 0) {
                $_SESSION['error_message'] = "Devi confermare la tua email prima di accedere.";
                header("Location: index.php");
                exit();
            }

            session_regenerate_id(true); // cambiamo id sessione per sicurezza

            $_SESSION['user'] = $row['Email'];
            header("Location: dashboard.php");
            exit();
        }
    }
    $stmt->close();

    // Se siamo arrivati qui vuol dire che non l'abbiamo trovato

    $_SESSION["error_message"] = "Credenziali non valide";
    header("Location: index.php");
    exit();
?>
