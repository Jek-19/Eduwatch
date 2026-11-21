<?php
/* POST {current, password, password2} -> change the logged-in user's password (also the forced first-login change). */
require_once __DIR__ . '/../includes/api.php';
$body = api_start(['POST']);
$user = api_require_login(true);

$current = body_raw($body, 'current');
$pw1     = body_raw($body, 'password');
$pw2     = body_raw($body, 'password2');

$st = db_auth()->prepare('SELECT password_hash FROM users WHERE id = ?');
$st->execute([$user['id']]);
$hash = (string) $st->fetchColumn();

if (!password_verify($current, $hash)) {
    api_fail('Your current password is not correct.', 400, 'wrong_current');
}
if ($pw1 !== $pw2) {
    api_fail('The two new passwords do not match.', 400, 'mismatch');
}
if (hash_equals($current, $pw1)) {
    api_fail('Your new password must be different from the current one.', 400, 'same_password');
}
if (($msg = password_policy_error($pw1, $user['username'])) !== null) {
    api_fail($msg, 400, 'weak_password');
}

set_user_password($user['id'], $pw1);
$_SESSION['must_change'] = false;
session_regenerate_id(true);
api_out(['message' => 'Your password was changed.', 'role' => $user['role']]);
