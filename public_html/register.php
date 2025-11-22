<?php
    require "db_connection.php"; 

    session_start();
    date_default_timezone_set('Europe/Rome');
    $date = date("Y-m-d H:i:s");

    if(isset($_SESSION["user"])) {
        //echo "<script type='text/javascript'>alert('sei loggato');</script>";
    }

    $name = trim($_POST["name"]);
    $surname = trim($_POST["surname"]);
    $dateBirth = trim($_POST["dateBirth"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $reason = trim($_POST["reason"]);

    $query = "SELECT Email FROM Users WHERE Email = ? UNION SELECT Email FROM Visitors WHERE Email = ?" ; 
    $stmt = $conn->prepare($query) ;
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute() ;
    $result = $stmt->get_result();
    if($result->num_rows > 0) {
        header("Location:http://localhost:8083?mode=register&error=credenziali_non_valide");
        die();
    }
    
    $stmt = $conn->prepare("INSERT INTO Badges (DateOfIssue, ExpirationDate, BadgeLevel) VALUES (?,?,1)");
    $stmt->bind_param("ss", $date, $date);
    $stmt->execute();

    $query = "SELECT MAX(IdBadge) FROM Badges";
    $result = $conn->query($query);
    $row = $result->fetch_row();
    $badge = $row[0];
    
    $stmt = $conn->prepare("INSERT INTO Visitors (IdBadge, Name, Surname,DateBirth,Email,Password, Reason) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssss", $badge,$name,$surname, $dateBirth, $email, $password, $reason);
    $stmt->execute();

    //print($result->num_rows);
    //echo "<script type='text/javascript'>console.log('$result->num_rows');</script>";
    if($stmt->affected_rows > 0){
        header("Location:http://localhost:8083?mode=login");
    }


    
        
    
?>
