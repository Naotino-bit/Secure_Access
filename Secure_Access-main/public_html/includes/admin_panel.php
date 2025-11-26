<h2 style="margin-top: 50px;">Pannello di Controllo (ADMIN)</h2>

<h3>Attivazione Visitatori</h3>
<?php
    $adminQuery = "SELECT V.Name, V.Surname, V.Email, V.Reason, V.is_verified, B.ExpirationDate 
                   FROM Visitors V JOIN Badges B ON V.IdBadge = B.IdBadge ORDER BY B.ExpirationDate DESC";
    $resultVisitatori = $conn->query($adminQuery);
?>

<?php if ($resultVisitatori->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Utente</th>
                <th>Email</th>
                <th>Motivo</th>
                <th>Stato</th>
                <th>Azione</th>
            </tr>
        </thead>
        <tbody>
            <?php while($vis = $resultVisitatori->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($vis['Name'] . " " . $vis['Surname']); ?></td>
                    <td><?php echo htmlspecialchars($vis['Email']); ?></td>
                    <td><?php echo htmlspecialchars($vis['Reason']); ?></td>
                    <td><?php echo $vis['is_verified'] ? "<span style='color:green'>Verificato</span>" : "<span style='color:orange'>In attesa</span>"; ?></td>
                    <td>
                        <form action="promote.php" method="POST" style="display:flex; gap:5px;">
                            <input type="hidden" name="email_to_promote" value="<?php echo $vis['Email']; ?>">
                            <select name="new_level">
                                <option value="1">Visitatore</option>
                                <option value="2">Dipendente</option>
                                <option value="3">Admin</option>
                            </select>
                            <button type="submit" class="btn-update">Salva</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>Nessun visitatore presente.</p>
<?php endif; ?>


<h3 style="color: #d35400;">Gestione Personale</h3>
<?php
    // Escludiamo l'utente loggato
    $usersQuery = "SELECT U.Name, U.Surname, U.Email, U.Role, B.BadgeLevel 
                   FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge 
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
                            if($usr['BadgeLevel'] == 3) echo "<span class='role-label role-admin'>ADMIN</span>";
                            elseif($usr['BadgeLevel'] == 2) echo "<span class='role-label role-dip'>DIPENDENTE</span>";
                            else echo $usr['Role'];
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