<?php
/*
 * EduWatch configuration
 * ----------------------
 * Edit the values below to match your XAMPP / MySQL setup.
 * On a default XAMPP install the MySQL user is "root" with an EMPTY password.
 *
 * The system uses TWO separate databases:
 *   - eduwatch_auth      -> logins, password resets, settings, login attempts
 *   - eduwatch_academic  -> students, subjects, grades, risk results, audit log
 * For better security you can give each database its own MySQL user
 * (see database/00_optional_db_users.sql) and put those users below.
 */

// Lets the settings below be overridden by environment variables (handy for testing/hosting).
$env = static function (string $key, $default) {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
};

return [
    'app_name'  => 'EduWatch',
    'debug'     => false,            // true = show PHP errors on screen (development only!)
    'timezone'  => 'Asia/Manila',

    // Address of the EduWatch folder (the one that contains frontend/ and backend/), used inside password-reset emails.
    // Example: 'http://localhost/eduwatch'   (leave '' to auto-detect)
    'base_url'  => $env('EDUWATCH_BASE_URL', ''),

    // Grading scale: 1.00 = highest, 5.00 = lowest. A subject average ABOVE passing_grade (a worse number) is "failing".
    'passing_grade'        => 3.00,
    // An average within this many grade points of passing (e.g. 2.51 to 3.00) counts as borderline.
    'borderline_margin'    => 0.50,
    'session_idle_minutes' => 30,    // auto log-out after this much inactivity

    // Account lock after too many wrong passwords (per username)
    'lockout' => [
        'max_attempts'   => 5,
        'window_minutes' => 15,
    ],

    // How long a password-reset link stays valid
    'reset_token_minutes' => 30,

    'db_auth' => [
        'host' => $env('EDUWATCH_AUTH_HOST', '127.0.0.1'),
        'port' => (int) $env('EDUWATCH_AUTH_PORT', 3306),
        'name' => $env('EDUWATCH_AUTH_NAME', 'eduwatch_auth'),
        'user' => $env('EDUWATCH_AUTH_USER', 'root'),
        'pass' => $env('EDUWATCH_AUTH_PASS', ''),
    ],
    'db_academic' => [
        'host' => $env('EDUWATCH_ACAD_HOST', '127.0.0.1'),
        'port' => (int) $env('EDUWATCH_ACAD_PORT', 3306),
        'name' => $env('EDUWATCH_ACAD_NAME', 'eduwatch_academic'),
        'user' => $env('EDUWATCH_ACAD_USER', 'root'),
        'pass' => $env('EDUWATCH_ACAD_PASS', ''),
    ],

    /*
     * Email for the "Forgot password" flow.
     *   mode 'screen' : DEMO MODE. No email is sent; the reset link is shown on the page.
     *                   (Great for demos without internet. Do NOT use for real users.)
     *   mode 'log'    : No email is sent; the message is written to storage/mail_log.txt.
     *   mode 'smtp'   : Sends real email through your SMTP server using PHPMailer.
     *                   Gmail example: host smtp.gmail.com, port 587, secure 'tls',
     *                   username = your Gmail, password = a Google "App Password".
     */
    'mail' => [
        'mode'      => $env('EDUWATCH_MAIL_MODE', 'screen'),
        'from'      => $env('EDUWATCH_MAIL_FROM', 'no-reply@eduwatch.local'),
        'from_name' => 'EduWatch',
        'host'      => $env('EDUWATCH_SMTP_HOST', ''),
        'port'      => (int) $env('EDUWATCH_SMTP_PORT', 587),
        'secure'    => ($env('EDUWATCH_SMTP_SECURE', 'tls') === 'none') ? '' : $env('EDUWATCH_SMTP_SECURE', 'tls'),   // 'tls', 'ssl' or '' / 'none' (no encryption)
        'username'  => $env('EDUWATCH_SMTP_USER', ''),
        'password'  => $env('EDUWATCH_SMTP_PASS', ''),
    ],
];
