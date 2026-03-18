<?php
// Riportiamo tutti all'ingresso se il turno è finito da un pezzo

if (isset($conn)) {
    // Chi è ancora nelle stanze anche se dovrebbe essere a casa?
    $teleportQuery = "
        SELECT U.IdBadge, A.IdSectorTo, S.Start, S.End 
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
        WHERE A.IdSectorTo != 1
          AND S.End != '23:59:59'
    ";

    $teleportRes = $conn->query($teleportQuery);
    
    if ($teleportRes && $teleportRes->num_rows > 0) {
        $insertQuery = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge, IdSectorTo) VALUES (NOW(), 'AUTO_EXIT', 5, ?, 1)");
        $warningQuery = $conn->prepare("INSERT INTO Warnings (Reason, IdAccess) VALUES ('Uscita Automatica: +30min dalla fine del turno', ?)");
        
        $currentTimeStr = date('H:i:s');
        $currentTime = strtotime($currentTimeStr);
        
        while ($row = $teleportRes->fetch_assoc()) {
            $shiftStart = strtotime($row['Start']);
            // Diamo mezz'ora di tempo dopo la fine del turno
            $shiftEndRaw = strtotime($row['End']);
            $graceEnd = $shiftEndRaw + (30 * 60); 
            
            $isWorkingHours = false;
            
            // Se il turno inizia e finisce nello stesso giorno
            if ($shiftStart <= $shiftEndRaw) {
                if ($currentTime >= $shiftStart && $currentTime <= $graceEnd) {
                    $isWorkingHours = true;
                }
            } 
            // Se il turno finisce il giorno dopo
            else {
                // E.g. se sono le 23:00, currentTime >= 22:00 -> true
                // Se sono le 05:00, currentTime <= 06:30 -> true
                // Se sono le 12:00, false
                if ($currentTime >= $shiftStart || $currentTime <= $graceEnd) {
                    $isWorkingHours = true;
                }
            }

            // Se è fuori orario, portiamolo all'ingresso
            if (!$isWorkingHours) {
                $insertQuery->bind_param("i", $row['IdBadge']);
                if ($insertQuery->execute()) {
                    $newAccessId = $insertQuery->insert_id;
                    $warningQuery->bind_param("i", $newAccessId);
                    $warningQuery->execute();
                }
            }
        }
        $insertQuery->close();
        $warningQuery->close();
    }
}
?>
