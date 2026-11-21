<?php
/*
 * Create a staff (teacher) account from the command line.
 *
 *   php tools/create_teacher.php <username> "<Full name>" <email> [password]
 *
 * Example:  php tools/create_teacher.php prof.santos "Prof. Ana Santos" ana.santos@school.edu
 * If you leave out the password, a random one is generated and printed once.
 * Giving every teacher their own account keeps the audit log meaningful (it shows who changed which grade).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool can only be run from the command line.');
}
require_once __DIR__ . '/../includes/auth.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/create_teacher.php <username> \"<Full name>\" <email> [password]\n");
    exit(1);
}
$username = norm_username($argv[1]);
$fullName = trim($argv[2]);
$email    = mb_strtolower(trim($argv[3]));
$password = $argv[4] ?? '';

if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
    fwrite(STDERR, "Username must be 3-30 characters: letters, numbers, dot, dash or underscore.\n");
    exit(1);
}
if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Please give a full name and a valid email address.\n");
    exit(1);
}
$generated = false;
if ($password === '') {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = 'A1';
    for ($i = 0; $i < 10; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $generated = true;
}
if (($msg = password_policy_error($password, $username)) !== null) {
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

$db = db_auth();
$chk = $db->prepare('SELECT 1 FROM users WHERE username = ? OR email = ?');
$chk->execute([$username, $email]);
if ($chk->fetch()) {
    fwrite(STDERR, "That username or email is already in use.\n");
    exit(1);
}
$db->prepare("INSERT INTO users (username, email, password_hash, role, full_name, must_change_password) VALUES (?, ?, ?, 'teacher', ?, ?)")
   ->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $fullName, $generated ? 1 : 0]);
$db->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([(int) $db->lastInsertId()]);

echo "Staff account created.\n  Username: {$username}\n";
if ($generated) {
    echo "  Temporary password: {$password}   (they must change it at first login)\n";
}
