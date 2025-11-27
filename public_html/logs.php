<?php
require "db_connection.php";
session_start();

// Controllo se Admin o Dipendente (opzionale, per ora lasciamo libero per test)
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Registro Accessi</title>
    <style>
        body { font-family: sans-serif; padding: 20px; background-color: #f4f4f4; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        th { background-color: #34495e; color: white; }
        tr:hover { background-color: #f1f1f1; }
        .status-granted { color: green; font-weight: bold; }
        .status-denied { color: red; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; padding: 10px 15px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>

    <a href="gates.php" class="back-btn">&larr; Torna al Simulatore</a>
    <h1>Registro Accessi (Logs)</h1>

    <table>
        <thead>
            <tr>
                <th>Data e Ora</th>
                <th>Varco (Gate)</th>
                <th>Nome e Cognome</th>
                <th>Ruolo</th>
                <th>Esito</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // QUERY AVANZATA: Unisce Accessi + Gate + (Users O Visitors)
            $sql = "
                SELECT A.Time, A.Result, G.Type as GateName,
                       COALESCE(U.Name, V.Name) as Nome,
                       COALESCE(U.Surname, V.Surname) as Cognome,
                       CASE 
                           WHEN U.IdUser IS NOT NULL THEN U.Role
                           ELSE 'VISITATORE' 
                       END as RuoloIdentificato
                FROM Accesses A
                JOIN Gates G ON A.IdGate = G.IdGate
                JOIN Badges B ON A.IdBadge = B.IdBadge
                LEFT JOIN Users U ON B.IdBadge = U.IdBadge
                LEFT JOIN Visitors V ON B.IdBadge = V.IdBadge
                ORDER BY A.Time DESC
            ";
            
            $result = $conn->query($sql);

            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $esitoClass = ($row['Result'] === 'GRANTED') ? 'status-granted' : 'status-denied';
                    $nomeCompleto = $row['Nome'] ? htmlspecialchars($row['Nome'] . " " . $row['Cognome']) : "Badge Sconosciuto";
                    
                    echo "<tr>";
                    echo "<td>" . $row['Time'] . "</td>";
                    echo "<td>" . htmlspecialchars($row['GateName']) . "</td>";
                    echo "<td>" . $nomeCompleto . "</td>";
                    echo "<td>" . htmlspecialchars($row['RuoloIdentificato']) . "</td>";
                    echo "<td class='$esitoClass'>" . htmlspecialchars($row['Result']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5' style='text-align:center'>Nessun accesso registrato.</td></tr>";
            }
            ?>
        </tbody>
    </table>

</body>
</html>