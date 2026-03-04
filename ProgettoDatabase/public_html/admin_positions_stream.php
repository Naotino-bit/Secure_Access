<?php
require "db_connection.php";
session_start();

require_once "includes/auto_teleport.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    exit();
}

$email = $_SESSION['user'];

// Verifica admin (BadgeLevel = 4)
$authQuery = "
    SELECT B.BadgeLevel
    FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge
    WHERE U.Email = ?
";
$stmt = $conn->prepare($authQuery);
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$me = $res->fetch_assoc();
$stmt->close();

if (!$me || (int)$me['BadgeLevel'] !== 4) {
    http_response_code(403);
    exit();
}

// Release session lock so other requests (like page reload) are not blocked
session_write_close();

// SSE headers
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

// Loop breve: in ambiente LAMP spesso PHP viene killato -> teniamolo leggero
$startedAt = time();
$maxSeconds = 60; // il browser riapre automaticamente EventSource

while (true) {
    if (connection_aborted()) {
        break;
    }
    if ((time() - $startedAt) > $maxSeconds) {
        // forziamo una reconnessione pulita
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
        JOIN Shifts S ON E.IdRole = S.IdRole
        WHERE A.Result = 'GRANTED' AND A.IdAccess > ?
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
        // keepalive ogni ~2s
        echo "event: ping\n";
        echo "data: {\"t\":\"" . date('c') . "\"}\n\n";
    }

    @flush();
    usleep(2000000);
}
