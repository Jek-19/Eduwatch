<?php
/* GET: who is logged in? Also hands out the CSRF token the frontend must send with every POST. */
require_once __DIR__ . '/../includes/api.php';
api_start(['GET']);

$check = session_check();
$out = [
    'authenticated' => $check['status'] === 'ok',
    'status'        => $check['status'],   // ok | none | timeout | inactive
    'csrf'          => csrf_token(),
];
if ($check['status'] === 'ok') {
    $out['user'] = $check['user'];
}
api_out($out);
