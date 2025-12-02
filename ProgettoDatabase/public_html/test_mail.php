<?php
// serve solo a noi per fare le mail senza iscriverci ogni volta
require_once 'send_email.php';

$destinatario = ''; 
$token_prova = '1234567890abcdef';

echo "<h2> Tentativo di invio a: $destinatario</h2>";

$esito = SendVerificationEmail($destinatario, $token_prova);

if ($esito) {
    echo "<h3 style='color: green;'>✅ Email inviata con successo! Controlla la posta.</h3>";
} else {
    echo "<h3 style='color: red;'>❌ Errore durante l'invio.</h3>";
}
?>