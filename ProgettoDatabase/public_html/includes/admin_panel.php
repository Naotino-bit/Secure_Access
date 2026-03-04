<h2 style="margin-top: 50px;">Pannello di Controllo (ADMIN)</h2>

<h3 style="color:red;">Dipendenti in Attesa di Assunzione (Hall Locked)</h3>
<?php
    $adminQuery = "SELECT U.Name, U.Surname, U.Email, U.is_verified, B.ExpirationDate 
                   FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge WHERE B.BadgeLevel = 1 ORDER BY B.ExpirationDate DESC";
    $resultVisitatori = $conn->query($adminQuery);
?>

<?php if ($resultVisitatori->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Candidato</th>
                <th>Email</th>
                <th>Stato</th>
                <th>Assumi come...</th>
            </tr>
        </thead>
        <tbody>
            <?php while($vis = $resultVisitatori->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($vis['Name'] . " " . $vis['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($vis['Email']); ?></td>
                    <td><?php echo $vis['is_verified'] ? "<span style='color:green'>Verificato</span>" : "<span style='color:orange'>Mail non conf.</span>"; ?></td>
                    <td>
                        <form action="promote.php" method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="email_to_promote" value="<?php echo $vis['Email']; ?>">
                            <select name="new_role" required>
                                <option value="" disabled selected>-- Seleziona Ruolo --</option>
                                <option value="Tecnico">Tecnico</option>
                                <option value="Magazziniere">Magazziniere</option>
                                <option value="Chimico">Chimico</option>
                                <option value="Admin">Amministratore</option>
                            </select>
                            <button type="submit" class="btn-update" style="background:#27ae60;">Assumi</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nessun visitatore presente.</p>
<?php endif; ?>

<h3 style="color: #c0392b;">Badge Scaduti (Da Rinnovare)</h3>
<?php
    // Cerca badge scaduti (Escludendo livello 1 che sono in attesa assunzione)
    // E escludendo l'admin stesso per sicurezza
    $expiredQuery = "SELECT U.Name, U.Surname, U.Email, B.ExpirationDate, B.BadgeLevel 
                   FROM Users U 
                   JOIN Badges B ON U.IdBadge = B.IdBadge 
                   WHERE B.ExpirationDate < NOW() AND B.BadgeLevel > 1 AND U.Email != ?
                   ORDER BY B.ExpirationDate ASC";
    $stmtExpired = $conn->prepare($expiredQuery);
    $stmtExpired->bind_param("s", $email);
    $stmtExpired->execute();
    $resultExpired = $stmtExpired->get_result();
?>

<?php if ($resultExpired->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Dipendente</th>
                <th>Email</th>
                <th>Scaduto Il</th>
                <th>Azione</th>
            </tr>
        </thead>
        <tbody>
            <?php while($exp = $resultExpired->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($exp['Name'] . " " . $exp['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($exp['Email']); ?></td>
                    <td style="color:red; font-weight:bold;">
                        <?php echo date('d/m/Y H:i', strtotime($exp['ExpirationDate'])); ?>
                    </td>
                    <td>
                        <form action="renew_badge.php" method="POST" style="margin:0;">
                            <input type="hidden" name="email_to_renew" value="<?php echo $exp['Email']; ?>">
                            <button type="submit" class="btn-update" style="background-color: #2980b9;" onclick="return confirm('Sicuro di voler rinnovare il badge per 1 anno?');">Rinnova (1 Anno)</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nessun badge scaduto da rinnovare.</p>
<?php endif; $stmtExpired->close(); ?>

<h3 style="color: #d35400;">Gestione Personale</h3>
<?php
    // Escludiamo l'utente loggato
    $usersQuery = "SELECT U.Name, U.Surname, U.Email, E.IdRole, B.BadgeLevel, S.Role 
                   FROM Users U 
                   JOIN Employees E ON U.IdUser = E.IdEmployee
                   JOIN Badges B ON U.IdBadge = B.IdBadge 
                   JOIN Shifts S ON E.IdRole = S.IdRole
                   WHERE U.Email != ? ORDER BY U.Surname ASC";
    $stmtUsers = $conn->prepare($usersQuery);
    $stmtUsers->bind_param("s", $email); // $email viene ereditata da dashboard.php
    $stmtUsers->execute();
    $resultUsers = $stmtUsers->get_result();
?>

<?php if ($resultUsers->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Dipendente</th>
                <th>Email</th>
                <th>Ruolo</th>
                <th>Modifica / Licenzia</th>
            </tr>
        </thead>
        <tbody>
            <?php while($usr = $resultUsers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($usr['Name'] . " " . $usr['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($usr['Email']); ?></td>
                    <td>
                        <?php 
                            if($usr['BadgeLevel'] == 4) echo "<span class='role-label role-admin'>ADMIN</span>";
                            else echo "<span class='role-label role-dip'>{$usr['Role']}</span>";
                        ?>
                    </td>
                    <td>
                        <form action="promote.php" method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="email_to_promote" value="<?php echo $usr['Email']; ?>">
                            <select name="new_level">
                                <option value="1" style="color:red; font-weight:bold;">Degrada</option>
                                <option value="2" <?php if($usr['BadgeLevel']==2) echo 'selected'; ?>>Dipendente</option>
                                <option value="3" <?php if($usr['BadgeLevel']==3) echo 'selected'; ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn-update" style="background-color: #e67e22;" onclick="return confirm('Sicuro di modificare il ruolo?');">Modifica</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nessun altro dipendente.</p>
<?php endif; $stmtUsers->close(); ?>