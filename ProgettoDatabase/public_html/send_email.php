<?php

// Gestiamo le mail che partono dal sistema

// Portiamo dentro PHPMailer per far funzionare tutto
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ci servono questi file per parlare con i server di posta
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';  
require 'PHPMailer/src/SMTP.php';

function SendVerificationEmail($recipientEmail, $token){
    $mail = new PHPMailer(true);

    try {
        // Leggiamo i segreti dal file .env
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


        // Queste servono per farlo funzionare mentre facciamo i test
        $mail->SMTPOptions = array(
            'ssl' => array (
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
            );

        $mail->setFrom($smtpUser,'Gestionale Admin');
        $mail->addAddress($recipientEmail);
        
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = 'Verifica il tuo account.';

        $link = $baseUrl . "/verify.php?email=" . urlencode($recipientEmail) . "&token=" . $token;

        $mail->Body = "
            <div style='font-family: \"Segoe UI\", Helvetica, Arial, sans-serif; background-color: #f4f7f6; padding: 40px 0; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    <div style='background: linear-gradient(135deg, #4FACFE, #00F2FE); padding: 30px; text-align: center; color: #ffffff;'>
                        <h1 style='margin: 0; font-size: 24px; letter-spacing: 1px; text-transform: uppercase;'>The Facility</h1>
                        <p style='margin: 5px 0 0; opacity: 0.8; font-size: 14px;'>Secure Access Control System</p>
                    </div>

                    <div style='padding: 40px; line-height: 1.6;'>
                        <h2 style='color: #2c3e50; margin-top: 0;'>Benvenuto a Bordo!</h2>
                        <p>Grazie per aver completato la registrazione su <strong>The Facility</strong>.</p>
                        <p>Il tuo account è quasi pronto. Per attivarlo e procedere con la tua candidatura ai varchi della struttura, conferma il tuo indirizzo email cliccando sul pulsante sottostante:</p>
                        
                        <div style='text-align: center; margin: 35px 0;'>
                            <a href='$link' style='background-color: #3498db; color: #ffffff; padding: 15px 35px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(52, 152, 219, 0.3);'>Verifica Account</a>
                        </div>

                        <p style='font-size: 14px; color: #7f8c8d;'>Se il pulsante non funziona, puoi copiare e incollare il seguente link nel tuo browser:</p>
                        <p style='font-size: 12px; word-break: break-all;'><a href='$link' style='color: #3498db;'>$link</a></p>
                    </div>

                    <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #eeeeee; font-size: 12px; color: #95a5a6;'>
                        <p style='margin: 0;'>Questo è un messaggio automatico, si prega di non rispondere.</p>
                        <p style='margin: 5px 0 0;'>Se non hai richiesto tu questa iscrizione, ignora semplicemente questa email.</p>
                        <p style='margin: 15px 0 0; font-weight: bold;'>&copy; " . date('Y') . " The Facility - Secure Access Control System</p>
                    </div>
                </div>
            </div>
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

