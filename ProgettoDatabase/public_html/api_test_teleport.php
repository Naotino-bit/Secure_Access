<?php
require("db_connection.php");
// debug output of the teleport query
$teleportQuery = "
    SELECT U.IdBadge, A.IdSectorTo, S.End, CURRENT_TIME() as now_time, ADDTIME(S.End, '00:30:00') as threshold
    FROM Users U
    JOIN Employees E ON U.IdUser = E.IdEmployee
    JOIN Shifts S ON E.IdRole = S.IdRole
    JOIN (
        SELECT IdBadge, MAX(IdAccess) as MaxIdAccess
        FROM Accesses
        WHERE Result IN ('GRANTED', 'AUTO_EXIT')
        GROUP BY IdBadge
    ) LatestAccess ON U.IdBadge = LatestAccess.IdBadge
    JOIN Accesses A ON LatestAccess.MaxIdAccess = A.IdAccess
    WHERE A.IdSectorTo != 1;
";
$teleportRes = $conn->query($teleportQuery);
$arr = [];
if ($teleportRes) {
    while($r = $teleportRes->fetch_assoc()) $arr[] = $r;
}

file_put_contents("/tmp/teleport_debug.json", json_encode($arr, JSON_PRETTY_PRINT));
echo "Done.";
?>
