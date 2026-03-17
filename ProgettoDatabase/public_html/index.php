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
    <title><?php echo ucfirst($mode); ?> - The Facility</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Sfondo Animato */
        body::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 10%, transparent 20%);
            background-size: 20px 20px;
            animation: rotateBg 60s linear infinite;
            z-index: 0;
        }
        
        @keyframes rotateBg {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .auth-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            text-align: center;
            color: white;
            z-index: 10;
            animation: fadeIn 0.8s ease-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .auth-header {
            margin-bottom: 30px;
        }

        .auth-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .auth-header p {
            font-weight: 300;
            opacity: 0.8;
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
            text-align: left;
        }

        .form-group i {
            position: absolute;
            top: 40px;
            left: 15px;
            color: rgba(255,255,255,0.7);
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.9);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-group input::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .form-group input:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: #61dafb;
            box-shadow: 0 0 10px rgba(97, 218, 251, 0.5);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(90deg, #4facfe 0%, #00f2fe 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(0, 242, 254, 0.4);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 242, 254, 0.6);
        }

        .switch-link {
            margin-top: 25px;
            font-size: 0.9rem;
        }

        .switch-link p { margin-bottom: 5px; opacity: 0.8;}
        
        .switch-link a {
            color: #4facfe;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .switch-link a:hover {
            color: #fff;
            text-decoration: underline;
        }

        /* Error Toast Styled for Glassmorphism */
        .error-toast {
            background: rgba(220, 53, 69, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }

    </style>
</head>
<body>

    <div class="auth-container">
        
        <div class="auth-header">
            <i class="fas fa-shield-alt fa-3x" style="margin-bottom:15px; color:#4facfe; filter: drop-shadow(0 0 10px rgba(79, 172, 254, 0.6));"></i>
            <h1>The Facility</h1>
            <p>Secure Access Control System</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-toast">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>


        <?php if ($mode === 'login'): ?>
            
            <form action="login.php" method="post">
                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Inserisci la tua mail" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                
                <button type="submit" class="btn-submit">Accedi al Sistema</button>
            </form>

            <div class="switch-link">
                <p>Ospite o Nuovo Dipendente?</p>
                <a href="index.php?mode=register">Richiedi un Badge qui</a>
            </div>

        <?php else: ?>

            <form action="register.php" method="post">
                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex:1;">
                        <label for="name">Nome</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="name" name="name" placeholder="Mario" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label for="surname">Cognome</label>
                        <i class="fas fa-user"></i>
                        <input type="text" id="surname" name="surname" placeholder="Rossi" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dateBirth">Data di Nascita</label>
                    <i class="fas fa-calendar-alt"></i>
                    <input type="date" id="dateBirth" name="dateBirth" required>
                </div>

                <div class="form-group">
                    <label for="email">E-Mail</label>
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" required placeholder="m.rossi@thefacility.com">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" required
                           pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           title="Logica Complessa Obbligatoria: 8+ caratteri, maiuscola, numero, speciale.">
                </div>

                <button type="submit" class="btn-submit">Registrati</button>
            </form>

            <div class="switch-link">
                <p>Hai già un Badge assegnato?</p>
                <a href="index.php?mode=login">Torna al Login</a>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>