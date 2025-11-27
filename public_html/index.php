<?php 
session_start();

// 1. Controllo se l'utente è già loggato
if(isset($_SESSION["user"])) {
    header("Location: dashboard.php");
    exit(); 
}

// 2. Gestione Errori
$error_message = '';

if(isset($_SESSION['error_message'])) { // se c'è un errore
    $error_message = $_SESSION['error_message']; // me lo segno
    unset($_SESSION['error_message']); // lo rimuovo
} elseif (isset($_GET['error'])) {
    $error_message = $_GET['error']; // prende l'errore dall'URL
}

$mode = $_GET['mode'] ?? 'login'; // login di default se non specificato

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($mode); ?> - Gestionale</title>
    <style>
        /* Stile di base per rendere la pagina leggibile */
        body { font-family: sans-serif; padding: 20px; max-width: 600px; margin: 0 auto; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ccc; border-radius: 5px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        input[type="submit"] { margin-top: 20px; cursor: pointer; background-color: #007bff; color: white; border: none; }
        .error-box { color: red; font-weight: bold; padding: 10px; border: 1px solid red; background-color: #ffeeee; margin-bottom: 15px; }
        .switch-btn { margin-top: 15px; display: block; text-align: center; }
    </style>
</head>
<body>

    <h1>Benvenuto</h1>

    <?php if (!empty($error_message)): ?>
        <div class="error-box">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>


    <?php if ($mode === 'login'): ?>
        
        <form action="login.php" method="post">
            <h2>Accedi</h2>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            
            <input type="submit" value="Login">
        </form>

        <div class="switch-btn">
            <p>Non hai un account?</p>
            <a href="index.php?mode=register">
                <button type="button">Vai a Register</button>
            </a>
        </div>

    <?php else: ?>

        <form action="register.php" method="post">
            <h2>Registrazione</h2>
            <label for="name">Nome:</label>
            <input type="text" id="name" name="name" required>

            <label for="surname">Cognome:</label>
            <input type="text" id="surname" name="surname" required>

            <label for="dateBirth">Data di nascita:</label>
            <input type="date" id="dateBirth" name="dateBirth" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required placeholder = "esempio@mail.com">

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required
                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                   title="La password deve essere di almeno 8 caratteri, contenere una maiuscola, un numero e un carattere speciale.">

            <label for="reason">Motivo:</label>
            <select name="reason" id="reason" required>
                <option value="" disabled selected>Seleziona un motivo</option>
                <option value="visita">Visita</option>
                <option value="appuntamento">Appuntamento</option>
            </select>

            <input type="submit" value="Register">
        </form>

        <div class="switch-btn">
            <p>Hai già un account?</p>
            <a href="index.php?mode=login">
                <button type="button">Vai a Login</button>
            </a>
        </div>

    <?php endif; ?>

</body>
</html>