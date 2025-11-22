<?php
    require "db_connection.php";

    session_start();
    $email = trim($_SESSION['user']);
    echo "<h1>Ciao {$email}</h1>";

    $query = "SELECT * FROM Users WHERE Email = (?) UNION SELECT * FROM Visitors WHERE Email = (?)" ; 
    $stmt = $conn->prepare($query) ;
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute() ;
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $Id = $row['Email'];
    print($Id);

?>
