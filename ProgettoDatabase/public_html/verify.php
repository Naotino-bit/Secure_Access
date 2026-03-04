<?php

require "db_connection.php";

if(isset($_GET['email']) && isset($_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];


    $query = "SELECT * FROM Users WHERE Email = ? AND token = ? AND is_verified = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0){
        $stmt->close(); //chiudo se troviamo l'utente
        $UpdateQuery = "UPDATE Users SET is_verified = 1, token = NULL WHERE Email = ?";
        $update = $conn->prepare($UpdateQuery);
        $update->bind_param("s", $email);

        if ($update->execute()){
            $update->close();
            header("Location: index.php?mode=login&success=account_attivato");
            exit();
        }
        else{
            $update->close();
            header("Location: index.php?error=errore_attivazione");
        }
    }
    else{
        $stmt->close();
        header("Location: index.php?error=link_non_valido_o_scaduto");
        exit();
    }
}

else{
    header("Location: index.php");
    exit();
}
?>