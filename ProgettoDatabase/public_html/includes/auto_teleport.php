<?php
// auto_teleport.php
// Questo script viene incluso in cima alle pagine principali (dashboard.php, gates.php...) 
// per teletrasportare automaticamente gli utenti nella Hall (Stanza 1) 
// se sono passati +30 minuti dalla fine del loro turno e non sono ancora usciti.

if (isset($conn)) {
    // Trova l'ultima posizione di tutti gli utenti attualmente NON nella Hall (IdSectorTo != 1)
    $teleportQuery = "
        SELECT U.IdBadge, A.IdSectorTo, S.Start, S.End 
        FROM Users U
        JOIN Employees E ON U.IdUser = E.IdEmployee
        JOIN Shifts S ON E.IdRole = S.IdRole
        JOIN (
            -- Trova l'ultimo accesso GRANTED per ogni IdBadge
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
            // Aggiungi 30 minuti alla fine del turno
            $shiftEndRaw = strtotime($row['End']);
            $graceEnd = $shiftEndRaw + (30 * 60); 
            
            $isWorkingHours = false;
            
            // Caso 1: Turno nello stesso giorno (es. 09:00 - 17:00, GraceEnd = 17:30)
            if ($shiftStart <= $shiftEndRaw) {
                // E.g. se sono le 08:00, isWorkingHours = false
                // Se sono le 17:15, isWorkingHours = true
                // Se sono le 18:00, isWorkingHours = false
                if ($currentTime >= $shiftStart && $currentTime <= $graceEnd) {
                    $isWorkingHours = true;
                }
            } 
            // Caso 2: Turno a cavallo della mezzanotte (es. 22:00 - 06:00, GraceEnd = 06:30)
            else {
                // E.g. se sono le 23:00, currentTime >= 22:00 -> true
                // Se sono le 05:00, currentTime <= 06:30 -> true
                // Se sono le 12:00, false
                if ($currentTime >= $shiftStart || $currentTime <= $graceEnd) {
                    $isWorkingHours = true;
                }
            }

            // Se l'utente non è nel suo orario consentito, teleportalo alla Hall
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
