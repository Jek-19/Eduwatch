<?php
/*
 * Forgot Password - steps 1 and 2:  POST {email} -> a one-time link (valid for a short time) is emailed.
 * Step 3 (choose a new password) is api/reset.php. Passwords are stored hashed, so they can only be reset, never retrieved.
 */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/mailer.php';
$body = api_start(['POST']);

$email = mb_strtolower(body_str($body, 'email', 120));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    api_fail('Please enter a valid email address.', 400, 'invalid_email');
}

$demoLink = '';
$db = db_auth();
$st = $db->prepare('SELECT id, username, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
$st->execute([$email]);
$user = $st->fetch();

if ($user) {
    // Limit how many links one account can request per hour.
    $cnt = $db->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $cnt->execute([$user['id']]);
    if ((int) $cnt->fetchColumn() < 5) {
        $minutes = (int) config()['reset_token_minutes'];
        $token   = bin2hex(random_bytes(32));

        // Only the HASH of the token is stored, so a database leak cannot be used to reset passwords.
        $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
           ->execute([$user['id']]);
        $ins = $db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, requested_ip)
             VALUES (?, ?, (NOW() + INTERVAL ? MINUTE), ?)'
        );
        $ins->bindValue(1, $user['id'], PDO::PARAM_INT);
        $ins->bindValue(2, hash('sha256', $token));
        $ins->bindValue(3, $minutes, PDO::PARAM_INT);
        $ins->bindValue(4, client_ip());
        $ins->execute();

        $link = frontend_url() . '/reset.html?token=' . $token;
        $mailBody = "Hello {$user['full_name']},\n\n"
                  . "We received a request to reset your EduWatch password.\n"
                  . "Open this link to choose a new password (it works once and expires in {$minutes} minutes):\n\n"
                  . "{$link}\n\n"
                  . "If you did not ask for this, you can ignore this email - your password stays the same.\n";
        $res = mail_send($email, 'Reset your EduWatch password', $mailBody);

        if ($res['mode'] === 'screen') {
            $demoLink = $link; // demo mode only (see config.php)
        }
    }
}

// Same answer whether or not the email exists, so nobody can probe which emails are registered.
$out = ['minutes' => (int) config()['reset_token_minutes']];
if ($demoLink !== '') {
    $out['demo_link'] = $demoLink;
}
api_out($out);
