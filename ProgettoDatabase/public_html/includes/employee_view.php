<h2 style="margin-top: 50px;">Database Aziendale (Sola Lettura)</h2>
<p style="color: gray;">Il tuo livello di accesso ti permette di consultare i dati ma non di modificarli.</p>

<h3>Visitatori Attuali</h3>
<?php
    // Usiamo la stessa logica dell'Admin per coerenza, ma mostriamo solo i dati
    $readQuery1 = "
        SELECT U.Name, U.Surname, U.Email, U.is_verified, B.ExpirationDate 
        FROM Users U
        JOIN Badges B ON U.IdBadge = B.IdBadge
        WHERE B.BadgeLevel = 1
        ORDER BY B.ExpirationDate DESC
    ";
    $resRead1 = $conn->query($readQuery1);
?>

<?php if ($resRead1->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Nome e Cognome</th>
                <th>Email</th>
                <th>Motivo Visita</th>
                <th>Stato</th>
                <th>Scadenza Badge</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resRead1->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Name'] . " " . $row['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($row['Email']); ?></td>
                    <td>-</td>
                    <td>
                        <?php echo $row['is_verified'] ? "<span style='color:green; font-weight:bold;'>Verificato</span>" : "<span style='color:orange;'>In attesa</span>"; ?>
                    </td>
                    <td>
                        <?php echo $row['ExpirationDate'] ? date("d/m/Y", strtotime($row['ExpirationDate'])) : "N/D"; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nessun visitatore presente nel sistema.</p>
<?php endif; ?>


<h3 style="margin-top: 40px;">Colleghi e Staff</h3>
<?php
    // Query per vedere gli altri dipendenti (escluso sé stesso)
    $readQuery2 = "
        SELECT U.Name, U.Surname, U.Email, E.Role, B.BadgeLevel 
        FROM Users U 
        JOIN Employees E ON U.IdUser = E.IdEmployee
        JOIN Badges B ON U.IdBadge = B.IdBadge 
        WHERE U.Email != ? 
        ORDER BY U.Surname ASC
    ";
    
    $stmtRead2 = $conn->prepare($readQuery2);
    $stmtRead2->bind_param("s", $email); // $email arriva dalla dashboard principale
    $stmtRead2->execute();
    $resRead2 = $stmtRead2->get_result();
?>

<?php if ($resRead2->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Nome Collega</th>
                <th>Email Aziendale</th>
                <th>Ruolo</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resRead2->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['Name'] . " " . $row['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($row['Email']); ?></td>
                    <td>
                        <?php 
                            // Etichette colorate solo per bellezza visiva
                            if($row['BadgeLevel'] == 3) echo "<span class='role-label role-admin'>ADMIN</span>";
                            else echo "<span class='role-label role-dip'>DIPENDENTE</span>";
                        ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Non ci sono altri dipendenti registrati.</p>
<?php endif; $stmtRead2->close(); ?>