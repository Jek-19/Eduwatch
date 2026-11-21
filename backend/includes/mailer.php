<?php
/*
 * Sends the password-reset email.
 * Mode is set in config.php:  'screen' (demo, nothing is sent), 'log' (write to a file), 'smtp' (real email).
 * Returns ['sent' => bool, 'mode' => string].
 */
require_once __DIR__ . '/bootstrap.php';

function mail_send(string $to, string $subject, string $body): array
{
    $m    = config()['mail'];
    $mode = $m['mode'] ?? 'screen';

    if ($mode === 'log') {
        $line = '[' . date('Y-m-d H:i:s') . "] To: {$to}\nSubject: {$subject}\n{$body}\n" . str_repeat('-', 60) . "\n";
        file_put_contents(APP_ROOT . '/storage/mail_log.txt', $line, FILE_APPEND | LOCK_EX);
        return ['sent' => false, 'mode' => 'log'];
    }

    if ($mode === 'smtp') {
        require_once APP_ROOT . '/vendor/phpmailer/src/Exception.php';
        require_once APP_ROOT . '/vendor/phpmailer/src/PHPMailer.php';
        require_once APP_ROOT . '/vendor/phpmailer/src/SMTP.php';
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $m['host'];
            $mail->Port = (int) $m['port'];
            if (($m['username'] ?? '') !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $m['username'];
                $mail->Password = $m['password'];
            }
            $secure = $m['secure'] ?? '';
            if ($secure === 'tls') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($m['from'], $m['from_name'] ?? 'EduWatch');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->isHTML(false);
            $mail->send();
            return ['sent' => true, 'mode' => 'smtp'];
        } catch (Throwable $ex) {
            error_log('[EduWatch mail] ' . $ex->getMessage());
            return ['sent' => false, 'mode' => 'smtp'];
        }
    }

    return ['sent' => false, 'mode' => 'screen']; // demo mode: the page shows the link instead
}
