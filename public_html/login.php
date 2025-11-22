<?php
    require "db_connection.php"; 

    session_start();
    

    if(isset($_SESSION["user"])) {
        //echo "<script type='text/javascript'>alert('sei loggato');</script>";
    }



    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    
    $stmt = $conn->prepare("SELECT * FROM Users WHERE Email = (?) and Password = (?)");
    $stmt->bind_param("ss", $email, $password);
    $stmt->execute();

    $result= $stmt->get_result();
    //print($result->num_rows);
    //echo "<script type='text/javascript'>console.log('$result->num_rows');</script>";
    if ($result->num_rows > 0) {
        $_SESSION['user'] = $_POST["email"];
        header("Location: http://localhost:8083/dashboard.php");
    } else {
        $stmt = $conn->prepare("SELECT * FROM Visitors WHERE Email = (?) and Password = (?)");
        $stmt->bind_param("ss", $email, $password);
        $stmt->execute();
        $result= $stmt->get_result();
        if ($result->num_rows > 0) {
            $_SESSION['user'] = $_POST["email"];
            header("Location: http://localhost:8083/dashboard.php");
        } else {
            $_SESSION["error_message"] = "Credenziali non valide";
            header("Location: http://localhost:8083");
        }
    }


    
        
    
?>
