<?php 

$mode = $_GET['mode'] ?? 'login'; // login di default
$error = $_GET['error']?? '';

session_start();
if(isset($_SESSION["user"])) {
        echo "<script type='text/javascript'>console.log('sei loggato');</script>";
        ?><a href="logout.php"><button>logout</button></a><?php
    } else {
    if(isset($_SESSION['error_message'])) {
        $error_message =  $_SESSION['error_message'];
        $error_html = '<p style="color: red; font-weight: bold; padding: 10px; border: 1px solid red; width:fit-content">' . htmlspecialchars($error_message) . '</p>';
        echo $error_html;
        unset($_SESSION['error_message']);
    }  if ($mode === 'login') {?>

        <form action="login.php" method="post">
            <label for="email">Email:</label>
            <input type="text" id="email" name="email" required><br>
            <label for="password">Password:</label>
            <input type="text" id="password" name="password" required><br>
            <input type="submit" value="Login">
        </form>

            <!-- BUTTON SWITCH -->
        <a href="index.php?mode=register">
            <button>Vai a Register</button>
        </a>
    <?php } else { if(isset($error)) {
        echo $error;
        } ?>
        
        <form action="register.php" method="post">
            <label for="name">Nome:</label>
            <input type="text" id="name" name="name" required><br>
            <label for="surname">Cognome:</label>
            <input type="text" id="surname" name="surname" required><br>
            <label for="dateBirth">Data di nascita:</label>
            <input type="date" id="dateBirth" name="dateBirth" required><br>
            <label for="email">Email:</label>
            <input type="text" id="email" name="email" required><br>
            <label for="password">Password:</label>
            <input type="text" id="password" name="password" required><br>
            <label for="reason">Reason</label>
            <select name="reason" id="reason" required>
                <option value=""></option>
                <option value="visita">Visita</option>
                <option value="appuntamento">Appuntamento</option>
            </select><br>

            <input type="submit" value="Register">
        </form>


            <!-- BUTTON SWITCH -->
        <a href="index.php?mode=login">
            <button>Vai a Login</button>
        </a>
<?php }
}?>














<!--
<table>
    <tr>
        <th>Id</th>
        <th>Ruolo</th>
        <th>Email</th>
        <th>Password</th>
        <th>Nome</th>
        <th>Cognome</th>
        <th>Data di nascita</th>
        <th>Id badge</th>
    </tr>



    <tr>
/*
    require 'db_connection.php';

    $query = "SELECT * FROM Users";
    $result = $conn->query($query);
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $entry =  "<tr><td>{$row['IdUser']}</td>";
            $entry .= "<td>{$row['Role']}</td>";
            $entry .= "<td>{$row['Email']}</td>";
            $entry .= "<td>{$row['Password']}</td>";
            $entry .= "<td>{$row['Nome']}</td>";
            $entry .= "<td>{$row['Cognome']}</td>";
            $entry .= "<td>{$row['DataNascita']}</td>";
            $entry .= "<td>{$row['IdBadge']}</td>";

            echo $entry;
        }
    }
?>
    </tr>
</table>