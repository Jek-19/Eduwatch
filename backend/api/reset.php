<?php
/*
 * Forgot Password - step 3.
 *   GET  ?token=...                                   -> is the emailed one-time link still valid?
 *   POST {token, password, password2}                 -> save the new password
 */
require_once __DIR__ . '/../includes/api.php';
$body = api_start(['GET', 'POST']);

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$token  = $isPost ? body_str($body, 'token', 64) : get_str('token', 64);
$reset  = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $st = db_auth()->prepare(
        'SELECT r.id, r.user_id, u.username, u.full_name
           FROM password_resets r JOIN users u ON u.id = r.user_id
          WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1
          LIMIT 1'
    );
    $st->execute([hash('sha256', $token)]);
    $reset = $st->fetch() ?: null;
}

if (!$reset) {
    if ($isPost) {
        api_fail('This reset link is invalid, already used, or has expired.', 400, 'invalid_token');
    }
    api_out(['valid' => false]);
}

if (!$isPost) {
    api_out(['valid' => true, 'username' => $reset['username']]);
}

$pw1 = body_raw($body, 'password');
$pw2 = body_raw($body, 'password2');
if ($pw1 !== $pw2) {
    api_fail('The two passwords do not match.', 400, 'mismatch');
}
if (($msg = password_policy_error($pw1, $reset['username'])) !== null) {
    api_fail($msg, 400, 'weak_password');
}

$db = db_auth();
$db->beginTransaction();
try {
    set_user_password((int) $reset['user_id'], $pw1);
    $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
       ->execute([$reset['user_id']]);
    // A successful reset also clears any "too many attempts" lock.
    $db->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([mb_strtolower($reset['username'])]);
    $db->commit();
} catch (Throwable $ex) {
    $db->rollBack();
    throw $ex;
}
api_out();
