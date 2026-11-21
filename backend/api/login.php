<?php
/* POST {username, password} -> logs in. The password is still required even when the ID came from a QR code. */
require_once __DIR__ . '/../includes/api.php';
$body = api_start(['POST']);

$username = body_str($body, 'username', 30);
$password = body_raw($body, 'password');

$result = attempt_login($username, $password);
if (!$result['ok']) {
    $status = ['missing' => 400, 'locked' => 429][$result['code'] ?? ''] ?? 401;
    api_fail($result['error'], $status, $result['code'] ?? 'invalid');
}

api_out([
    'role'        => $result['user']['role'],
    'must_change' => !empty($_SESSION['must_change']),
    'theme'       => $_SESSION['theme'] ?? 'light',
    'csrf'        => csrf_token(), // the session id was renewed, so the old token is gone
]);
