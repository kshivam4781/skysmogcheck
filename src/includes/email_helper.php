<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

function sendEmail($to, $subject, $message, $isHTML = false, $attachments = [], $cc = []) {
    global $conn;
    
    // Get SMTP configuration
    $stmt = $conn->prepare("SELECT * FROM mailsender WHERE idmailsender = 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $smtpConfig = $result->fetch_assoc();

    if (!$smtpConfig) {
        error_log("SMTP configuration not found");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpConfig['smtp'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtpConfig['email'];
        $mail->Password = $smtpConfig['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpConfig['port'];

        // Recipients
        $mail->setFrom($smtpConfig['email'], 'Sky Smoke Check LLC');
        $mail->addAddress($to);

        // Add CC recipients if any
        if (!empty($cc)) {
            foreach ($cc as $ccEmail) {
                if (!empty($ccEmail)) {
                    $mail->addCC($ccEmail);
                }
            }
        }

        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        
        if ($isHTML) {
            $mail->Body = $message;
        } else {
            // For plain text emails, ensure proper line breaks
            $mail->Body = $message;
            $mail->AltBody = $message;
            // Set content type to text/plain
            $mail->ContentType = 'text/plain';
            // Set character encoding
            $mail->CharSet = 'UTF-8';
            // Force line breaks
            $mail->WordWrap = 80;
        }

        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
?> 