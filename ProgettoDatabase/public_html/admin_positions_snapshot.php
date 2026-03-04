<?php
require "db_connection.php";
session_start();

require_once "includes/auto_teleport.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Non autenticato']);
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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Permessi insufficienti']);
    exit();
}

// Snapshot: ultima posizione (GRANTED) per ogni badge + nome
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
    INNER JOIN (
        SELECT IdBadge, MAX(IdAccess) AS MaxIdAccess
        FROM Accesses
        WHERE Result = 'GRANTED'
        GROUP BY IdBadge
    ) LastA ON LastA.IdBadge = A.IdBadge AND LastA.MaxIdAccess = A.IdAccess
    JOIN Badges B ON A.IdBadge = B.IdBadge
    JOIN Users U ON B.IdBadge = U.IdBadge
    LEFT JOIN Employees E ON U.IdUser = E.IdEmployee
    LEFT JOIN Shifts S ON E.IdRole = S.IdRole
    ORDER BY A.IdAccess ASC
";

$result = $conn->query($sql);
$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id_access' => (int)$row['IdAccess'],
            'time' => $row['Time'],
            'id_badge' => (int)$row['IdBadge'],
            'id_sector' => (int)$row['IdSectorTo'],
            'name' => $row['Name'] ?? '',
            'surname' => $row['Surname'] ?? '',
            'role' => $row['Role'] ?? '',
        ];
    }
}

header('Content-Type: application/json');
echo json_encode(['positions' => $rows]);
