<div style="margin-top: 20px;">
    <h2><i class="fas fa-users-cog" style="color:#8e44ad;"></i> Pannello di Controllo</h2>

    <div class="admin-section" style="background: rgba(255,255,255,0.6); border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #e74c3c; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
        <h3 style="color:#c0392b; margin-top:0;"><i class="fas fa-user-clock"></i> Utenti in Attesa di Assunzione</h3>
        <?php
            $adminQuery = "SELECT U.Name, U.Surname, U.Email, U.is_verified, B.ExpirationDate 
                           FROM Users U JOIN Badges B ON U.IdBadge = B.IdBadge WHERE B.BadgeLevel = 1 ORDER BY B.ExpirationDate DESC";
            $resultVisitatori = $conn->query($adminQuery);
        ?>

        <?php if ($resultVisitatori->num_rows > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Candidato</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Email</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Stato</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Gestisci</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($vis = $resultVisitatori->fetch_assoc()): ?>
                            <tr style="transition: background 0.3s;" onmouseover="this.style.background='#f1f3f5'" onmouseout="this.style.background='white'">
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($vis['Name'] . " " . $vis['Surname']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($vis['Email']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo $vis['is_verified'] ? "<span style='background:#d4edda; color:#155724; padding:5px 10px; border-radius:12px; font-size:0.85em; font-weight:bold; white-space: nowrap;'><i class='fas fa-check'></i> Verificato</span>" : "<span style='background:#fff3cd; color:#856404; padding:5px 10px; border-radius:12px; font-size:0.85em; font-weight:bold; white-space: nowrap;'><i class='fas fa-envelope-open-text'></i> Mail non conf.</span>"; ?>
                                </td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                    <form id="form_hire_<?php echo md5($vis['Email']); ?>" action="promote.php" method="POST" style="display:flex; gap:10px; align-items:center; margin:0;" onsubmit="openHiringModal(event, '<?php echo $vis['Email']; ?>', 'form_hire_<?php echo md5($vis['Email']); ?>', 'role_<?php echo md5($vis['Email']); ?>');">
                                        <input type="hidden" name="email_to_promote" value="<?php echo $vis['Email']; ?>">
                                        <select name="new_role" id="role_<?php echo md5($vis['Email']); ?>" class="custom-select" required>
                                            <option value="" disabled selected>-- Seleziona Ruolo --</option>
                                            <option value="Rifiutato" style="color:#e74c3c; font-weight:bold;">Rifiuta Candidatura</option>
                                            <option value="Tecnico">Tecnico</option>
                                            <option value="Magazziniere">Magazziniere</option>
                                            <option value="Chimico">Chimico</option>
                                            <option value="Sorveglianza">Sorveglianza</option>
                                            <option value="Admin">Amministratore</option>
                                        </select>
                                        <button type="submit" class="btn btn-green" style="padding: 8px 15px; font-size:0.9em;"><i class="fas fa-user-plus"></i> Modifica</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#7f8c8d; font-style:italic; padding: 10px 0;"><i class="fas fa-info-circle"></i> Nessun visitatore presente.</p>
        <?php endif; ?>
    </div>


    <div class="admin-section" style="background: rgba(255,255,255,0.6); border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #f39c12; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
        <h3 style="color: #d35400; margin-top:0;"><i class="fas fa-id-card-alt"></i> Badge Scaduti</h3>
        <?php
            // Chi ha il badge scaduto ma lavora ancora qui?
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
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Dipendente</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Email</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Scaduto Il</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Azione</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($exp = $resultExpired->fetch_assoc()): ?>
                            <tr style="transition: background 0.3s;" onmouseover="this.style.background='#f1f3f5'" onmouseout="this.style.background='white'">
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($exp['Name'] . " " . $exp['Surname']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($exp['Email']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef; color:#e74c3c; font-weight:bold;">
                                    <i class="fas fa-calendar-times"></i> <?php echo date('d/m/Y H:i', strtotime($exp['ExpirationDate'])); ?>
                                </td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                    <form id="form_renew_<?php echo md5($exp['Email']); ?>" action="renew_badge.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="email_to_renew" value="<?php echo $exp['Email']; ?>">
                                        <button type="button" class="btn btn-blue" style="padding: 8px 15px; font-size:0.9em;" onclick="openGenericConfirmModal('Sicuro di voler rinnovare il badge per 1 anno?', 'form_renew_<?php echo md5($exp['Email']); ?>');"><i class="fas fa-sync-alt"></i> Rinnova</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#7f8c8d; font-style:italic; padding: 10px 0;"><i class="fas fa-check-circle" style="color:#2ecc71;"></i> Nessun badge da rinnovare.</p>
        <?php endif; $stmtExpired->close(); ?>
    </div>


    <div class="admin-section" style="background: rgba(255,255,255,0.6); border-radius: 15px; padding: 25px; margin-bottom: 30px; border-left: 5px solid #3498db; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
        <h3 style="color: #2980b9; margin-top:0;"><i class="fas fa-users"></i> Gestione Personale</h3>
        <?php
            // Una lista di tutti i colleghi (noi esclusi)
            $usersQuery = "SELECT U.Name, U.Surname, U.Email, E.IdRole, B.BadgeLevel, S.Role 
                           FROM Users U 
                           JOIN Employees E ON U.IdUser = E.IdEmployee
                           JOIN Badges B ON U.IdBadge = B.IdBadge 
                           JOIN Shifts S ON E.IdRole = S.IdRole
                           WHERE U.Email != ? ORDER BY U.Surname ASC";
            $stmtUsers = $conn->prepare($usersQuery);
            $stmtUsers->bind_param("s", $email);
            $stmtUsers->execute();
            $resultUsers = $stmtUsers->get_result();
        ?>

        <?php if ($resultUsers->num_rows > 0): ?>
            <div style="overflow-x:auto;">
                <table style="width: 100%; border-collapse: separate; border-spacing: 0; margin-top: 15px; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Dipendente</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Email</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Ruolo</th>
                            <th style="padding: 15px; text-align: left; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">Gestisci Ruolo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($usr = $resultUsers->fetch_assoc()): ?>
                            <tr style="transition: background 0.3s;" onmouseover="this.style.background='#f1f3f5'" onmouseout="this.style.background='white'">
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($usr['Name'] . " " . $usr['Surname']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($usr['Email']); ?></td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                    <?php 
                                        if($usr['BadgeLevel'] == 4) echo "<span style='background:#e8daef; color:#8e44ad; padding:5px 10px; border-radius:12px; font-size:0.85em; font-weight:bold;'><i class='fas fa-user-shield'></i> ADMIN</span>";
                                        else echo "<span style='background:#d6eaf8; color:#2980b9; padding:5px 10px; border-radius:12px; font-size:0.85em; font-weight:bold;'><i class='fas fa-id-badge'></i> {$usr['Role']}</span>";
                                    ?>
                                </td>
                                <td style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                                    <form id="form_manage_<?php echo md5($usr['Email']); ?>" action="promote.php" method="POST" style="display:flex; gap:10px; align-items:center; margin:0;">
                                        <input type="hidden" name="email_to_promote" value="<?php echo $usr['Email']; ?>">
                                        <select name="new_role" class="custom-select">
                                            <option value="Licenziato" style="color:#e74c3c; font-weight:bold;">Licenzia</option>
                                            <option value="Tecnico" <?php if($usr['Role']=='Tecnico') echo 'selected'; ?>>Tecnico</option>
                                            <option value="Magazziniere" <?php if($usr['Role']=='Magazziniere') echo 'selected'; ?>>Magazziniere</option>
                                            <option value="Chimico" <?php if($usr['Role']=='Chimico') echo 'selected'; ?>>Chimico</option>
                                            <option value="Sorveglianza" <?php if($usr['Role']=='Sorveglianza') echo 'selected'; ?>>Sorveglianza</option>
                                            <option value="Amministratore" <?php if($usr['Role']=='Amministratore') echo 'selected'; ?>>Amministratore</option>
                                        </select>
                                        <button type="button" class="btn btn-yellow" style="padding: 8px 15px; font-size:0.9em; box-shadow:none; color:#333;" onclick="openGenericConfirmModal('Sicuro di applicare le modifiche?', 'form_manage_<?php echo md5($usr['Email']); ?>');"><i class="fas fa-edit"></i> Modifica</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#7f8c8d; font-style:italic; padding: 10px 0;"><i class="fas fa-info-circle"></i> Nessun altro dipendente.</p>
        <?php endif; $stmtUsers->close(); ?>
    </div>
</div>

<!-- Finestra per dare il benvenuto ai nuovi -->
<div id="hiringModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); overflow:auto;">
    <div style="background-color:#fff; margin:5% auto; padding:0; border-radius:15px; width:90%; max-width:800px; box-shadow:0 10px 25px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out;">
        <div style="background: linear-gradient(135deg, #27ae60, #2ecc71); padding:20px; border-radius:15px 15px 0 0; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;"><i class="fas fa-user-tie"></i> Riepilogo Personale</h3>
            <span onclick="closeHiringModal()" style="cursor:pointer; font-size:1.5em; font-weight:bold;">&times;</span>
        </div>
        
        <div style="padding:30px; display:flex; flex-wrap:wrap; gap:30px;">
            <!-- Dati personali di chi vogliamo assumere -->
            <div style="flex:1; min-width:300px; background:#f8f9fa; padding:20px; border-radius:12px; border-left:5px solid #2ecc71;">
                <h4 style="color:#2c3e50; margin-top:0; border-bottom:2px solid #e9ecef; padding-bottom:10px;"><i class="fas fa-id-card"></i> Anagrafica Candidato</h4>
                <div id="hiringCandidateDetails" style="font-size:1.05em; line-height:1.6; color:#34495e;">
                    <p><i class="fas fa-spinner fa-spin"></i> Caricamento in corso...</p>
                </div>
            </div>

            <!-- Vediamo chi fa già lo stesso lavoro -->
            <div id="hiringEmployeesSection" style="flex:1; min-width:300px; background:#f8f9fa; padding:20px; border-radius:12px; border-left:5px solid #3498db;">
                <h4 style="color:#2c3e50; margin-top:0; border-bottom:2px solid #e9ecef; padding-bottom:10px;"><i class="fas fa-users"></i> Dipendenti Attuali (<span id="hiringRoleName">Ruolo</span>)</h4>
                <div id="hiringRoleEmployees" style="max-height:200px; overflow-y:auto; padding-right:10px;">
                    <p><i class="fas fa-spinner fa-spin"></i> Caricamento in corso...</p>
                </div>
            </div>
        </div>

        <div style="padding:20px; background:#f1f3f5; border-radius:0 0 15px 15px; text-align:right;">
            <button type="button" onclick="closeHiringModal()" class="btn" style="background:#95a5a6; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; margin-right:10px;"><i class="fas fa-times"></i> Annulla</button>
            <button type="button" id="confirmHiringBtn" class="btn" style="background:#27ae60; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; box-shadow:0 4px 6px rgba(39,174,96,0.3);"><i class="fas fa-check-circle"></i> Conferma</button>
        </div>
    </div>
</div>

<!-- Finestra di conferma per sicurezza -->
<div id="genericConfirmModal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); overflow:auto;">
    <div style="background-color:#fff; margin:10% auto; padding:0; border-radius:15px; width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.2); animation: slideIn 0.3s ease-out;">
        <div style="background: linear-gradient(135deg, #f39c12, #e67e22); padding:20px; border-radius:15px 15px 0 0; color:white; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;"><i class="fas fa-exclamation-triangle"></i> Conferma Azione</h3>
            <span onclick="closeGenericConfirmModal()" style="cursor:pointer; font-size:1.5em; font-weight:bold;">&times;</span>
        </div>
        
        <div style="padding:30px; text-align:center;">
            <p id="genericConfirmMessage" style="font-size:1.2em; color:#2c3e50; margin:0;">Sei sicuro di voler procedere?</p>
        </div>

        <div style="padding:20px; background:#f1f3f5; border-radius:0 0 15px 15px; text-align:right;">
            <button type="button" onclick="closeGenericConfirmModal()" class="btn" style="background:#95a5a6; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; margin-right:10px;"><i class="fas fa-times"></i> Annulla</button>
            <button type="button" id="confirmGenericBtn" class="btn" style="background:#e67e22; color:white; padding:10px 20px; border-radius:8px; border:none; cursor:pointer; font-weight:bold; box-shadow:0 4px 6px rgba(230,126,34,0.3);"><i class="fas fa-check-circle"></i> Conferma</button>
        </div>
    </div>
</div>

<style>
.custom-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: #fff;
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23131C23%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat, repeat;
    background-position: right .7em top 50%, 0 0;
    background-size: .65em auto, 100%;
    padding: 10px 30px 10px 15px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95em;
    color: #374151;
    cursor: pointer;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-weight: 500;
}
.custom-select:focus {
    border-color: #3498db;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
}
.custom-select option {
    font-weight: normal;
}

@keyframes slideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
#hiringRoleEmployees::-webkit-scrollbar { width: 6px; }
#hiringRoleEmployees::-webkit-scrollbar-track { background: #e9ecef; border-radius: 10px; }
#hiringRoleEmployees::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 10px; }
#hiringRoleEmployees::-webkit-scrollbar-thumb:hover { background: #6c757d; }
</style>

<script>
let currentHiringFormId = null;

function openHiringModal(event, email, formId, roleSelectId) {
    event.preventDefault(); // Aspettiamo prima di mandare i dati
    
    const roleSelect = document.getElementById(roleSelectId);
    const selectedRole = roleSelect.value;
    
    if(!selectedRole) {
        alert("Seleziona un ruolo prima di continuare.");
        return;
    }

    currentHiringFormId = formId;
    document.getElementById('hiringModal').style.display = 'block';
    
    // Toggle visibility based on Role
    const empSection = document.getElementById('hiringEmployeesSection');
    if (selectedRole === 'Rifiutato') {
        empSection.style.display = 'none';
    } else {
        empSection.style.display = 'block';
    }

    // Reset contents
    document.getElementById('hiringCandidateDetails').innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Caricamento in corso...</p>';
    document.getElementById('hiringRoleEmployees').innerHTML = '<p><i class="fas fa-spinner fa-spin"></i> Caricamento in corso...</p>';
    document.getElementById('hiringRoleName').innerText = selectedRole;

    // Fetch details
    fetch('api_hiring_info.php?email=' + encodeURIComponent(email) + '&role=' + encodeURIComponent(selectedRole))
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                // Inseriamo i dati personali
                const dob = new Date(data.candidate.DateBirth).toLocaleDateString('it-IT');
                document.getElementById('hiringCandidateDetails').innerHTML = `
                    <p><strong><i class="fas fa-user"></i> Nome:</strong> ${data.candidate.Name} ${data.candidate.Surname}</p>
                    <p><strong><i class="fas fa-envelope"></i> Email:</strong> <a href="mailto:${data.candidate.Email}" style="color:#2980b9;">${data.candidate.Email}</a></p>
                    <p><strong><i class="fas fa-calendar-alt"></i> Data di Nascita:</strong> ${dob}</p>
                `;

                // Facciamo vedere gli altri dipendenti
                let employeesHtml = '';
                if(data.employees && data.employees.length > 0) {
                    data.employees.forEach(emp => {
                        employeesHtml += `
                            <div style="background:white; padding:10px 15px; margin-bottom:10px; border-radius:8px; border:1px solid #ced4da; display:flex; align-items:center; gap:10px;">
                                <div style="background:#e8f4f8; color:#2980b9; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                                    ${emp.Name.charAt(0)}${emp.Surname.charAt(0)}
                                </div>
                                <div style="flex:1;">
                                    <div style="font-weight:bold; color:#2c3e50;">${emp.Name} ${emp.Surname}</div>
                                    <div style="font-size:0.85em; color:#7f8c8d;">${emp.Email}</div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    employeesHtml = '<p style="color:#7f8c8d; font-style:italic;"><i class="fas fa-info-circle"></i> Nessun dipendente attualmente in questo ruolo.</p>';
                }
                
                document.getElementById('hiringRoleEmployees').innerHTML = employeesHtml;

            } else {
                alert("Errore nel caricamento dei dati: " + (data.error || "Sconosciuto"));
                closeHiringModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert("Errore di connessione al server.");
            closeHiringModal();
        });
}

function closeHiringModal() {
    document.getElementById('hiringModal').style.display = 'none';
    currentHiringFormId = null;
}

document.getElementById('confirmHiringBtn').addEventListener('click', function() {
    if(currentHiringFormId) {
        document.getElementById(currentHiringFormId).submit();
    }
});

// Generic Confirm Modal Logic
let currentGenericFormId = null;

function openGenericConfirmModal(message, formId) {
    document.getElementById('genericConfirmMessage').innerText = message;
    currentGenericFormId = formId;
    document.getElementById('genericConfirmModal').style.display = 'block';
}

function closeGenericConfirmModal() {
    document.getElementById('genericConfirmModal').style.display = 'none';
    currentGenericFormId = null;
}

document.getElementById('confirmGenericBtn').addEventListener('click', function() {
    if(currentGenericFormId) {
        document.getElementById(currentGenericFormId).submit();
    }
});

// Se clicchi fuori dalla finestra, lei si chiude
window.onclick = function(event) {
    const hiringModalEl = document.getElementById('hiringModal');
    const genericModalEl = document.getElementById('genericConfirmModal');
    
    if (event.target == hiringModalEl) {
        closeHiringModal();
    }
    if (event.target == genericModalEl) {
        closeGenericConfirmModal();
    }
}
</script>