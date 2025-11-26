<?php
    require "db_connection.php"; 

    session_start();
    

    if(isset($_SESSION["user"])) {
        header("Location: dashboard.php");
        exit();
    }

    // Se NON è una richiesta POST (cioè l'utente ha scritto l'url a mano)
    // dritto al login
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        header("Location: index.php");
        exit();
    }
    
    // mio amico caro ricorda che se c'è ?? vuol dire che noi proviamo a prenderci il valore a sinistra
    // ma se non lo dovessimo avere mettiamo email e pass uguali a ''
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        $_SESSION["error_message"] = "Formato email non valido";
        header("Location: index.php");
        exit();
    }
    

    //solito controllo in più, non c'è modo che l'utente arrivi a questo ma non si è mai troppo sicuri
    if (empty($email) || empty($password)) {
        $_SESSION["error_message"] = "Inserire email e password";
        header("Location: index.php");
        exit();
    }
    //RICERCA IN USERS E VISITORS ATTRAVERSO L'EMAIL E VERIFICA DELLA PASSWORD


    $query= "SELECT Email, Password, 1 as is_verified FROM Users WHERE Email = (?)
             UNION 
             SELECT Email, Password, is_verified FROM Visitors WHERE Email = (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $result= $stmt->get_result();

    if($row = $result->fetch_assoc()){
        if (password_verify($password, $row['Password'])) {

            if(isset($row['is_verified']) && $row['is_verified'] == 0) {
                $_SESSION['error_message'] = "Devi confermare la tua email prima di accedere.";
                header("Location: index.php");
                exit();
            }

            session_regenerate_id(true); //rigenero l'id della sessione per evitare Session Fixation

            $_SESSION['user'] = $row['Email'];
            header("Location: dashboard.php");
            exit();
        }
    }
    $stmt->close();

    //NON E' STATO TROVATO DA NESSUNA PARTE, ALLORA

    $_SESSION["error_message"] = "Credenziali non valide";
    header("Location: index.php");
    exit();
?>


<!-- 
print($result->num_rows);
echo "<script type='text/javascript'>console.log('$result->num_rows');</script>"; -->