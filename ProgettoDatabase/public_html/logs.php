<?php
require "db_connection.php";
session_start();

// Solo i "pezzi grossi" o la sorveglianza vedono tutto
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['user'];
$authQuery = "
    SELECT B.BadgeLevel, S.Role 
    FROM Users U 
    JOIN Badges B ON U.IdBadge = B.IdBadge 
    LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
    LEFT JOIN Shifts S ON E.IdRole = S.IdRole
    WHERE U.Email = ?
";
$stmt = $conn->prepare($authQuery);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc();
$stmt->close();

$isAuthorized = ($me && ((int)$me['BadgeLevel'] === 4 || $me['Role'] === 'Sorveglianza'));

if (!$isAuthorized) {
    header("Location: dashboard.php?error=Accesso+negato+ai+log");
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registro Accessi - Seleziona Data</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%234facfe' d='M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C25.5 92 16 110.1 16 130.1c0 231 161.4 336.8 226.7 372.4a47.79 47.79 0 0 0 46.5 0C354.6 466.9 512 361.1 512 130.1c0-20-9.5-38.1-26.6-46.4z'/%3E%3C/svg%3E">
    <!-- Le solite icone -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Calendario per le date -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        
        .filter-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; margin-bottom: 30px; border-top: 5px solid #3498db; }
        .filter-card input[type="date"] { padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; }
        .filter-card input[type="text"].date-picker-custom { padding: 10px 35px 10px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; width: 150px; text-align: center; background-color: #fff; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db' width='18px' height='18px'%3E%3Cpath d='M0 0h24v24H0z' fill='none'/%3E%3Cpath d='M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
        .filter-card .btn { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 6px; font-size: 1em; cursor: pointer; transition: background 0.3s; margin-left: 10px; }
        .filter-card .btn:hover { background: #2980b9; }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background-color: #f1f3f5; color: #495057; font-weight: 600; }
        tr:hover { background-color: #f8f9fa; }
        
        .status-granted { display: inline-block; padding: 5px 10px; background-color: #d4edda; color: #155724; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .status-denied { display: inline-block; padding: 5px 10px; background-color: #f8d7da; color: #721c24; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .status-warning { display: inline-block; padding: 5px 10px; background-color: #fff3cd; color: #856404; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        .status-info { display: inline-block; padding: 5px 10px; background-color: #d1ecf1; color: #0c5460; border-radius: 12px; font-size: 0.85em; font-weight: bold; }
        
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 1em; font-weight: bold; transition: transform 0.2s, background 0.2s; background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); margin-bottom: 25px; }
        .btn-secondary:hover { background: rgba(52, 152, 219, 0.2); transform: translateY(-2px); }
    </style>
</head>
<body>

    <div class="container">
        <a href="dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
        
        <h1><i class="fas fa-history"></i> Registro Accessi Varchi</h1>

        <!-- Pannello per filtrare i risultati -->
        <div class="filter-card">
            <form method="GET" action="logs.php" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="filter_date" style="font-weight: bold; color: #2c3e50;"><i class="fas fa-calendar-alt"></i> Giorno:</label>
                    <?php $currentDate = $_GET['filter_date'] ?? date('Y-m-d'); ?>
                    <input type="text" id="filter_date" class="date-picker-custom" name="filter_date" value="<?php echo htmlspecialchars($currentDate); ?>" placeholder="gg/mm/aaaa">
                </div>
                
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="search_query" style="font-weight: bold; color: #2c3e50;"><i class="fas fa-search"></i> Cerca:</label>
                    <?php $searchQuery = $_GET['search'] ?? ''; ?>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Nome, ruolo, esito..." style="padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; width: 200px;">
                </div>

                <button type="submit" class="btn"><i class="fas fa-filter"></i> Applica Filtri</button>
            </form>
        </div>



        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fas fa-clock"></i> Data e Ora</th>
                        <th><i class="fas fa-door-open"></i> Varco</th>
                        <th><i class="fas fa-user"></i> Utente</th>
                        <th><i class="fas fa-id-badge"></i> Ruolo</th>
                        <th><i class="fas fa-info-circle"></i> Esito</th>
                    </tr>
                </thead>
                <tbody>
            <?php
            // Applichiamo i filtri alla ricerca
            $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
            $searchParam = "%$searchTerm%";

            $sql = "
                SELECT A.Time, A.Result, G.IdGate as GateName,
                       U.Name as Nome,
                       U.Surname as Cognome,
                       COALESCE(S.Role, 'VISITATORE') as RuoloIdentificato,
                       GROUP_CONCAT(W.Reason SEPARATOR ', ') as WarningReason
                FROM Accesses A
                JOIN Gates G ON A.IdGate = G.IdGate
                JOIN Badges B ON A.IdBadge = B.IdBadge
                JOIN Users U ON B.IdBadge = U.IdBadge
                LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
                LEFT JOIN Shifts S ON E.IdRole = S.IdRole
                LEFT JOIN Warnings W ON A.IdAccess = W.IdAccess
                WHERE DATE(A.Time) = ?
                AND (
                    U.Name LIKE ? OR 
                    U.Surname LIKE ? OR 
                    CONCAT(U.Name, ' ', U.Surname) LIKE ? OR
                    S.Role LIKE ? OR 
                    A.Result LIKE ? OR
                    G.IdGate LIKE ?
                )
                GROUP BY A.IdAccess, A.Time, A.Result, G.IdGate, U.Name, U.Surname, S.Role
                ORDER BY A.Time DESC
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssss", $currentDate, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    // Scegliamo colore e icona per l'esito
                    switch($row['Result']) {
                        case 'GRANTED': 
                            $esitoText = '<i class="fas fa-check-circle"></i> ACCESSO CONSENTITO'; 
                            $esitoClass = 'status-granted'; 
                            break;
                        case 'AUTO_EXIT': 
                            $esitoText = '<i class="fas fa-sign-out-alt"></i> USCITA AUTOMATICA'; 
                            $esitoClass = 'status-warning'; 
                            break;
                        case 'DENIED_EXPIRED': 
                            $esitoText = '<i class="fas fa-id-card"></i> NEGATO (Badge Scaduto)'; 
                            $esitoClass = 'status-denied'; 
                            break;
                        case 'DENIED_NO_PERMISSION': 
                            $esitoText = '<i class="fas fa-user-lock"></i> NEGATO (No Permessi)'; 
                            $esitoClass = 'status-denied'; 
                            break;
                        case 'DENIED_GATE_LOCKED': 
                            $esitoText = '<i class="fas fa-lock"></i> NEGATO (Varco Bloccato)'; 
                            $esitoClass = 'status-denied'; 
                            break;
                        case 'DENIED_MAINTENANCE': 
                            $esitoText = '<i class="fas fa-tools"></i> NEGATO (Manutenzione)'; 
                            $esitoClass = 'status-denied'; 
                            break;
                        case 'DENIED_NO_SHIFT': 
                            $esitoText = '<i class="fas fa-calendar-times"></i> NEGATO (Fuori Turno)'; 
                            $esitoClass = 'status-warning'; 
                            break;
                        case 'DENIED_ALREADY_INSIDE':
                            $esitoText = '<i class="fas fa-walking"></i> NEGATO (Già all\'interno)'; 
                            $esitoClass = 'status-warning'; 
                            break;
                        case 'DENIED_OUTSIDE_EXPECTED':
                            $esitoText = '<i class="fas fa-map-marker-alt"></i> NEGATO (Direzione Errata)'; 
                            $esitoClass = 'status-warning'; 
                            break;
                        default:
                            if (strpos($row['Result'], 'DENIED') !== false) {
                                $esitoText = '<i class="fas fa-times-circle"></i> ' . str_replace('_', ' ', $row['Result']);
                                $esitoClass = 'status-denied';
                            } else {
                                $esitoText = '<i class="fas fa-info-circle"></i> ' . $row['Result'];
                                $esitoClass = 'status-info';
                            }
                    }

                    // Se c'è un avviso particolare facciamo vedere quello
                    if (!empty($row['WarningReason']) && trim((string)$row['WarningReason']) !== '') {
                        // Sostituiamo il testo generico con la vera motivazione loggata dal trigger/codice
                        $esitoText = '<i class="fas fa-exclamation-triangle"></i> ' . htmlspecialchars($row['WarningReason']);
                        // Se era status-warning lo lasciamo tale, sennò status-denied di default x i warnings
                        if ($esitoClass !== 'status-warning') {
                            $esitoClass = 'status-denied';
                        }
                    }

                    $nomeCompleto = $row['Nome'] ? htmlspecialchars($row['Nome'] . " " . $row['Cognome']) : "Badge Sconosciuto";
                    
                    echo "<tr>";
                    echo "<td>" . date('d/m/Y H:i:s', strtotime($row['Time'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['GateName']) . "</td>";
                    echo "<td>" . $nomeCompleto . "</td>";
                    echo "<td>" . htmlspecialchars($row['RuoloIdentificato']) . "</td>";
                    echo "<td><span class='$esitoClass'>" . $esitoText . "</span></td>";
                    echo "</tr>";
                }
            } else {
                $searchSuffix = $searchTerm ? " e ricerca '<strong>" . htmlspecialchars($searchTerm) . "</strong>'" : "";
                echo "<tr><td colspan='5' style='text-align:center; padding: 30px; color: #7f8c8d;'><i class='fas fa-folder-open' style='font-size:3em; display:block; margin-bottom:10px; color:#bdc3c7;'></i> Nessun accesso registrato per il giorno <strong>" . htmlspecialchars(date('d/m/Y', strtotime($currentDate))) . "</strong>{$searchSuffix}.</td></tr>";
            }
            $stmt->close();
            ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Script per il calendario -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/it.js"></script>
    <script>
        flatpickr("#filter_date", {
            dateFormat: "Y-m-d",        // Valore inviato al server
            altInput: true,             // Mostra un input alternativo all'utente
            altFormat: "d/m/Y",         // Formato italiano a video
            locale: "it",               // Lingua italiana
            disableMobile: "true"       // Previene la UI di default nei mobile browser (opzionale)
        });
    </script>
</body>
</html>