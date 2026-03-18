<?php
require "db_connection.php";
session_start();

require_once "includes/auto_teleport.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit();
}

$email = $_SESSION['user'];

// Controllo se può sbirciare la mappa
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

$isAuthorized = ($me && ((int)$me['BadgeLevel'] === 4 || ($me['Role'] === 'Sorveglianza' && (int)$me['BadgeLevel'] === 3)));

if (!$isAuthorized) {
    http_response_code(403);
    exit();
}

// Sblocchiamo la sessione così non si pianta tutto
session_write_close();

// Prepariamo la linea per mandare i dati in tempo reale
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    @ob_end_flush();
}
@ob_implicit_flush(1);

$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Facciamo una pausa tra un controllo e l'altro per non appesantire tutto
$startedAt = time();
$maxSeconds = 60; // il browser riapre automaticamente EventSource

while (true) {
    if (connection_aborted()) {
        break;
    }
    if ((time() - $startedAt) > $maxSeconds) {
        // chiudiamo e facciamo ricollegare così puliamo tutto
        echo "event: session_terminated\n";
        echo "data: {}\n\n";
        @flush();
        break;
    }

    $sql = "
        SELECT
            A.IdAccess,
            A.Time,
            A.IdBadge,
            A.IdSectorTo,
            U.Name,
            U.Surname,
            COALESCE(S.Role, 'VISITATORE') as Role
        FROM Accesses A
        JOIN Badges B ON A.IdBadge = B.IdBadge
        JOIN Users U ON B.IdBadge = U.IdBadge
        LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
        LEFT JOIN Shifts S ON E.IdRole = S.IdRole
        WHERE A.Result IN ('GRANTED', 'AUTO_EXIT') AND A.IdAccess > ?
        ORDER BY A.IdAccess ASC
        LIMIT 50
    ";

    $q = $conn->prepare($sql);
    $q->bind_param("i", $lastId);
    $q->execute();
    $r = $q->get_result();

    $sentAny = false;
    while ($row = $r->fetch_assoc()) {
        $payload = [
            'id_access' => (int)$row['IdAccess'],
            'time' => $row['Time'],
            'id_badge' => (int)$row['IdBadge'],
            'id_sector' => (int)$row['IdSectorTo'],
            'name' => $row['Name'] ?? '',
            'surname' => $row['Surname'] ?? '',
            'role' => $row['Role'] ?? '',
        ];

        $lastId = (int)$row['IdAccess'];
        $sentAny = true;

        echo "event: move\n";
        echo "id: {$payload['id_access']}\n";
        echo "data: " . json_encode($payload) . "\n\n";
    }

    $q->close();

    if (!$sentAny) {
        // mandiamo un segnale ogni tanto per dire che siamo vivi
        echo "event: ping\n";
        echo "data: {\"t\":\"" . date('c') . "\"}\n\n";
    }

    @flush();
    usleep(2000000);
}
