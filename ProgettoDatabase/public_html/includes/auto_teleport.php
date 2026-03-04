<?php
// auto_teleport.php
// Questo script viene incluso in cima alle pagine principali (dashboard.php, gates.php...) 
// per teletrasportare automaticamente gli utenti nella Hall (Stanza 1) 
// se sono passati +30 minuti dalla fine del loro turno e non sono ancora usciti.

if (isset($conn)) {
    // 1. Troviamo l'ultima posizione di tutti gli utenti attualmente NON nella Hall (IdSectorTo != 1)
    // 2. Uniamo con Shifts per controllare l'orario di fine
    // 3. Facciamo il check: NOW > ADDTIME(Shifts.End, '00:30:00')
    
    // Siccome l'utente in `Accesses` ha molti log, dobbiamo interrogare solo l'ULTIMO accesso di ogni badge.
    // Usiamo una subquery per trovare l'ultimo IdAccess per ogni Badge che sia stato GRANTED.
    $teleportQuery = "
        SELECT U.IdBadge, A.IdSectorTo, S.End 
        FROM Users U
        JOIN Employees E ON U.IdUser = E.IdEmployee
        JOIN Shifts S ON E.IdRole = S.IdRole
        JOIN (
            -- Trova l'ultimo accesso GRANTED per ogni IdBadge
            SELECT IdBadge, MAX(IdAccess) as MaxIdAccess
            FROM Accesses
            WHERE Result = 'GRANTED'
            GROUP BY IdBadge
        ) LatestAccess ON U.IdBadge = LatestAccess.IdBadge
        JOIN Accesses A ON LatestAccess.MaxIdAccess = A.IdAccess
        WHERE A.IdSectorTo != 1
          AND S.End != '23:59:59'
          AND (
              -- CASO 1: Turno nello stesso giorno (es. 09:00 - 18:00)
              -- Se l'orario attuale è superiore all'orario di fine + 30 minuti, scatta l'uscita automatica.
              (S.Start <= S.End AND CURRENT_TIME() > ADDTIME(S.End, '00:30:00'))
              OR 
              -- CASO 2: Turno scavalca la mezzanotte (es. 22:00 - 06:00)
              -- In questo caso la fine turno + 30m è al mattino (es 06:30). 
              -- Scatta l'uscita se (00:00 <= NOW <= Start) e (NOW > 06:30)
              (S.Start > S.End AND CURRENT_TIME() > ADDTIME(S.End, '00:30:00') AND CURRENT_TIME() < S.Start)
          )
    ";

    $teleportRes = $conn->query($teleportQuery);
    if ($teleportRes && $teleportRes->num_rows > 0) {
        $insertQuery = $conn->prepare("INSERT INTO Accesses (Time, Result, IdGate, IdBadge, IdSectorTo) VALUES (NOW(), 'GRANTED', 5, ?, 1)");
        $warningQuery = $conn->prepare("INSERT INTO Warnings (Reason, IdAccess) VALUES ('Uscita Automatica: +30min dalla fine del turno', ?)");
        
        while ($row = $teleportRes->fetch_assoc()) {
            $insertQuery->bind_param("i", $row['IdBadge']);
            if ($insertQuery->execute()) {
                $newAccessId = $insertQuery->insert_id;
                $warningQuery->bind_param("i", $newAccessId);
                $warningQuery->execute();
            }
        }
        $insertQuery->close();
        $warningQuery->close();
    }
}
?>
