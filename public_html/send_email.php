<?php

// SISTEMARE LA PASSWORD E METTERE EFFETTIVAMENTE UNA MAIL 
// CONSIDERARE L'IMPLEMENTAZIONE DEI FILE .env 
// Richiesta di prolungare il badge per email
// Se l'accesso ai gate viene negato (scaduto), l'utente può il rinnovo del badge attraverso una mail automatica per gli amministratori

//Classi di PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

//Carichi i file della libreria, quindi il codice php
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';  
require 'PHPMailer/src/SMTP.php';

function SendVerificationEmail($recipientEmail, $token){
    $mail = new PHPMailer(true);

    try {
        //prendiamo da .env le informazioni che ci servono e se non le troviamo mettiamo quelle default
        $stmpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $smtpUser = getenv('SMTP_USER');
        $smtpPass = getenv('SMTP_PASSWORD');
        $smtpPort = getenv('SMTP_PORT') ?: 465;
        $baseUrl = getenv('BASE_URL') ?: 'http://localhost:8083';

        if(!$smtpUser || !$smtpPass){
            throw new Exception("Credenziali SMTP mancanti. Controlla il file .env e docker compose.");
        }


        $mail->isSMTP();
        $mail->Host = $stmpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $smtpPort;


        //SONO OPZIONI SSL CHE SERVONO MENTRE LAVORIAMO IN LOCALE
        $mail->SMTPOptions = array(
            'ssl' => array (
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
            );

        $mail->setFrom('$smtpUser','Gestionale Admin');
        $mail->addAddress($recipientEmail);
        
        $mail->isHTML(true);
        $mail->Subject = 'Verifica il tuo account.';

        $link = $baseUrl . "/verify.php?email=" . urlencode($recipientEmail) . "&token=" . $token;

        $mail->Body = "
            <h1>Benvenuto!</h1>
            <p>Grazie per esserti registrato.</p>
            <p>Per attivare il tuo account, clicca sul link qui sotto:</p>
            <p><a href='$link'>Clicca qui per verificare</a></p>
            <br>
            <small>Se non hai richiesto questa registrazione, ignora questa mail.</small>
        ";

        $mail->AltBody = "Per verificare il tuo account, visita questo link: $link";

        $mail->send();

        return true;
    }

    catch(Exception $e){ //DA CAMBIARE POI CON error_log() 
        error_log("ERRORE PHPMailer: {$mail->ErrorInfo}");
        return false;
    }
}

