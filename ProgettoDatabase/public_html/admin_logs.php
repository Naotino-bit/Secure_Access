<?php
require "db_connection.php";
session_start();

// Vediamo se chi entra è un admin
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$adminEmail = $_SESSION['user'];
$queryAdmin = "
    SELECT B.BadgeLevel
    FROM Users U
    JOIN Badges B ON U.IdBadge = B.IdBadge
    WHERE U.Email = ?";
$stmt = $conn->prepare($queryAdmin);
$stmt->bind_param("s", $adminEmail);
$stmt->execute();
$resAdmin = $stmt->get_result();
$rowAdmin = $resAdmin->fetch_assoc();

if (!$rowAdmin || $rowAdmin['BadgeLevel'] < 3) {
    die("ACCESSO NEGATO: Non hai i permessi per visualizzare i log admin.");
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registro Azioni Admin (Logs)</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 512 512'%3E%3Cpath fill='%234facfe' d='M466.5 83.7l-192-80a48.15 48.15 0 0 0-36.9 0l-192 80C25.5 92 16 110.1 16 130.1c0 231 161.4 336.8 226.7 372.4a47.79 47.79 0 0 0 46.5 0C354.6 466.9 512 361.1 512 130.1c0-20-9.5-38.1-26.6-46.4z'/%3E%3C/svg%3E">
    <!-- Carichiamo le icone -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Un bel calendario per le date -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; margin: 0; padding: 0; color: #333; }
        .container { max-width: 1000px; margin: 40px auto; padding: 20px; }
        h1 { color: #2c3e50; text-align: center; margin-bottom: 30px; }
        
        .filter-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; margin-bottom: 30px; border-top: 5px solid #8e44ad; }
        .filter-card input[type="date"] { padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; }
        .filter-card input[type="text"].date-picker-custom { padding: 10px 35px 10px 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; width: 150px; text-align: center; background-color: #fff; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%238e44ad' width='18px' height='18px'%3E%3Cpath d='M0 0h24v24H0z' fill='none'/%3E%3Cpath d='M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; }
        .filter-card .btn { padding: 10px 20px; background: #8e44ad; color: white; border: none; border-radius: 6px; font-size: 1em; cursor: pointer; transition: background 0.3s; margin-left: 10px; }
        .filter-card .btn:hover { background: #732d91; }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background-color: #f1f3f5; color: #495057; font-weight: 600; }
        tr:hover { background-color: #f8f9fa; }
        
        .log-date { color: #7f8c8d; font-family: monospace; font-size: 1.1em; }
        
        .btn-secondary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-size: 1em; font-weight: bold; transition: transform 0.2s, background 0.2s; background: rgba(52, 152, 219, 0.1); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); margin-bottom: 25px; }
        .btn-secondary:hover { background: rgba(52, 152, 219, 0.2); transform: translateY(-2px); }
    </style>
</head>
<body>

    <div class="container">
        <a href="dashboard.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Torna alla Dashboard</a>
        
        <h1><i class="fas fa-user-shield" style="color: #8e44ad;"></i> Registro Azioni Amministratore</h1>

        <!-- Scegliamo il giorno -->
        <div class="filter-card">
            <form method="GET" action="admin_logs.php" style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="filter_date" style="font-weight: bold; color: #2c3e50;"><i class="fas fa-calendar-alt"></i> Giorno:</label>
                    <?php $currentDate = $_GET['filter_date'] ?? date('Y-m-d'); ?>
                    <input type="text" id="filter_date" class="date-picker-custom" name="filter_date" value="<?php echo htmlspecialchars($currentDate); ?>" placeholder="gg/mm/aaaa">
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="search_query" style="font-weight: bold; color: #2c3e50;"><i class="fas fa-search"></i> Cerca:</label>
                    <?php $searchQuery = $_GET['search'] ?? ''; ?>
                    <input type="text" id="search_query" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Azione, email, ruolo..." style="padding: 10px; border: 1px solid #ced4da; border-radius: 6px; font-size: 1em; outline: none; width: 250px;">
                </div>

                <button type="submit" class="btn"><i class="fas fa-filter"></i> Applica Filtri</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 200px;"><i class="fas fa-clock"></i> Data e Ora</th>
                        <th><i class="fas fa-clipboard-list"></i> Descrizione Azione</th>
                    </tr>
                </thead>
                <tbody>
            <?php
            // Mostriamo cosa hanno combinato gli admin
            $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
            $searchParam = "%$searchTerm%";

            $sql = "SELECT DateTime, Description FROM AdminLogs WHERE DATE(DateTime) = ? AND Description LIKE ? ORDER BY DateTime DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $currentDate, $searchParam);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td class='log-date'>" . date('d/m/Y H:i:s', strtotime($row['DateTime'])) . "</td>";
                    
                    // Cambiamo colore in base al tipo di azione
                    $desc = htmlspecialchars($row['Description']);
                    if (strpos($desc, 'livello 1') !== false || strpos(strtolower($desc), 'licenzia') !== false) {
                        $desc = "<span style='color: #c0392b; font-weight:bold;'><i class='fas fa-user-minus'></i> " . $desc . "</span>";
                    } elseif (strpos($desc, 'livello 3') !== false) {
                        $desc = "<span style='color: #8e44ad; font-weight:bold;'><i class='fas fa-shield-alt'></i> " . $desc . "</span>";
                    } elseif (strpos(strtolower($desc), 'livello') !== false) {
                        $desc = "<span style='color: #27ae60; font-weight:bold;'><i class='fas fa-user-plus'></i> " . $desc . "</span>";
                    } else if (strpos(strtolower($desc), 'rinnov') !== false) {
                        $desc = "<span style='color: #2980b9; font-weight:bold;'><i class='fas fa-sync-alt'></i> " . $desc . "</span>";
                    } else {
                        $desc = "<i class='fas fa-info-circle' style='color:#bdc3c7;'></i> " . $desc;
                    }

                    echo "<td>" . $desc . "</td>";
                    echo "</tr>";
                }
            } else {
                $searchSuffix = $searchTerm ? " e ricerca '<strong>" . htmlspecialchars($searchTerm) . "</strong>'" : "";
                echo "<tr><td colspan='2' style='text-align:center; padding: 30px; color: #7f8c8d;'><i class='fas fa-folder-open' style='font-size:3em; display:block; margin-bottom:10px; color:#bdc3c7;'></i> Nessuna azione registrata per il giorno <strong>" . htmlspecialchars(date('d/m/Y', strtotime($currentDate))) . "</strong>{$searchSuffix}.</td></tr>";
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
