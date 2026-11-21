<?php
/*
 * Authentication helpers. Everything here talks to the AUTH database only
 * (users, login_attempts, user_settings).
 */
require_once __DIR__ . '/bootstrap.php';

// A throw-away bcrypt hash so that logins for unknown usernames take as long as real ones.
const EW_DUMMY_HASH = '$2y$10$usesomesillystringforsaleuOQ4n1zVv2d1nQ3o8o3bEJd5S0r3m0Fy1G';

function password_policy_error(string $password, string $username = ''): ?string
{
    if (strlen($password) < 8) {
        return 'Your new password must be at least 8 characters long.';
    }
    if (strlen($password) > 72) {
        return 'Your new password is too long (72 characters maximum).';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Your new password must contain at least one letter and one number.';
    }
    if ($username !== '' && strcasecmp($password, $username) === 0) {
        return 'Your password cannot be the same as your username.';
    }
    return null;
}

/* ---------------------------------------------------------------- lockout */
function norm_username(string $u): string
{
    return mb_strtolower(mb_substr(trim($u), 0, 30));
}

/** Seconds left on the lock for this username (0 = not locked). */
function login_lock_remaining(string $username): int
{
    $cfg    = config()['lockout'];
    $max    = (int) $cfg['max_attempts'];
    $window = (int) $cfg['window_minutes'];
    $st = db_auth()->prepare(
        'SELECT UNIX_TIMESTAMP(attempted_at) AS ts, UNIX_TIMESTAMP() AS now_ts
           FROM login_attempts
          WHERE username = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? MINUTE)
          ORDER BY id DESC LIMIT ?'
    );
    $st->bindValue(1, $username);
    $st->bindValue(2, $window, PDO::PARAM_INT);
    $st->bindValue(3, $max, PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    if (count($rows) < $max) {
        return 0;
    }
    $oldest = (int) $rows[count($rows) - 1]['ts'];
    $now    = (int) $rows[0]['now_ts'];
    return max(0, $oldest + $window * 60 - $now);
}

function record_login_attempt(string $username, bool $success): void
{
    $db = db_auth();
    $db->prepare('INSERT INTO login_attempts (username, ip, success) VALUES (?, ?, ?)')
       ->execute([$username, client_ip(), $success ? 1 : 0]);
    if ($success) {
        $db->prepare('DELETE FROM login_attempts WHERE username = ? AND success = 0')->execute([$username]);
    }
    if (random_int(1, 50) === 1) { // occasional housekeeping
        $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}

/* ---------------------------------------------------------------- login / logout */
/**
 * Try to log in. Returns ['ok'=>true] or ['ok'=>false,'error'=>message].
 * The error text is deliberately the same for "no such user" and "wrong password".
 */
function attempt_login(string $usernameInput, string $password): array
{
    $username = norm_username($usernameInput);
    if ($username === '' || $password === '') {
        return ['ok' => false, 'code' => 'missing', 'error' => 'Please enter your ID/username and password.'];
    }

    $locked = login_lock_remaining($username);
    if ($locked > 0) {
        $mins = (int) ceil($locked / 60);
        return ['ok' => false, 'code' => 'locked', 'error' => "Too many failed attempts. Please wait about {$mins} minute(s) or use \"Forgot password?\"."];
    }

    $st = db_auth()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();

    $hash  = $user ? $user['password_hash'] : EW_DUMMY_HASH;
    $valid = password_verify($password, $hash);

    if (!$user || !$valid || !(int) $user['is_active']) {
        record_login_attempt($username, false);
        return ['ok' => false, 'code' => 'invalid', 'error' => 'Invalid ID/username or password.'];
    }

    record_login_attempt($username, true);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db_auth()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                 ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    db_auth()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    login_user($user);
    return ['ok' => true, 'user' => $user];
}

function load_settings(int $userId): array
{
    $st = db_auth()->prepare('SELECT theme, tone FROM user_settings WHERE user_id = ?');
    $st->execute([$userId]);
    $row = $st->fetch();
    return $row ?: ['theme' => 'light', 'tone' => 'formal'];
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $settings = load_settings((int) $user['id']);

    $_SESSION['uid']       = (int) $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['must_change'] = (bool) $user['must_change_password'];
    $_SESSION['theme']     = $settings['theme'];
    $_SESSION['tone']      = $settings['tone'];
    $_SESSION['last_seen'] = time();
}

function logout_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------------------------------------------------------------- session check */
/**
 * Look at the current session WITHOUT redirecting anywhere (the API answers in JSON instead).
 * Returns ['status' => 'ok', 'user' => [...]]   when someone valid is logged in, or
 *         ['status' => 'none' | 'timeout' | 'inactive'] when not.
 */
function session_check(): array
{
    if (empty($_SESSION['uid'])) {
        return ['status' => 'none'];
    }
    $idle = ((int) config()['session_idle_minutes']) * 60;
    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $idle) {
        logout_session();
        session_start();
        return ['status' => 'timeout'];
    }
    $_SESSION['last_seen'] = time();

    $st = db_auth()->prepare('SELECT role, is_active, must_change_password, full_name FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    $row = $st->fetch();
    if (!$row || !(int) $row['is_active']) {
        logout_session();
        session_start();
        return ['status' => 'inactive'];
    }
    $_SESSION['role']        = $row['role'];
    $_SESSION['full_name']   = $row['full_name'];
    $_SESSION['must_change'] = (bool) $row['must_change_password'];

    return [
        'status' => 'ok',
        'user'   => [
            'id'          => (int) $_SESSION['uid'],
            'username'    => $_SESSION['username'],
            'role'        => $_SESSION['role'],
            'full_name'   => $_SESSION['full_name'],
            'tone'        => $_SESSION['tone'] ?? 'formal',
            'theme'       => $_SESSION['theme'] ?? 'light',
            'must_change' => $_SESSION['must_change'],
        ],
    ];
}

/* ---------------------------------------------------------------- password change */
function set_user_password(int $userId, string $newPassword): void
{
    db_auth()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
             ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
}
