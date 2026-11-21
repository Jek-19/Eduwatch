<?php
/*
 * Settings (both roles): dark mode and, for students, the tone of the insight message. Saved in the AUTH database.
 *   GET                              -> current settings
 *   POST {theme, tone}               -> save them
 * Changing the password is api/change_password.php.
 */
require_once __DIR__ . '/../includes/api.php';
$body = api_start(['GET', 'POST']);
$user = api_require_login(true);

$forced = !empty($_SESSION['must_change']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($forced) {
        api_fail('Please choose a new password before you continue.', 403, 'password_change_required');
    }
    $theme = body_str($body, 'theme', 10) === 'dark' ? 'dark' : 'light';
    $tone  = body_str($body, 'tone', 10) === 'humor' ? 'humor' : 'formal';
    if ($user['role'] !== 'student') {
        $tone = $_SESSION['tone'] ?? 'formal'; // tone only applies to student insight messages
    }
    db_auth()->prepare(
        'INSERT INTO user_settings (user_id, theme, tone) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE theme = VALUES(theme), tone = VALUES(tone)'
    )->execute([$user['id'], $theme, $tone]);
    $_SESSION['theme'] = $theme;
    $_SESSION['tone']  = $tone;
    api_out(['message' => 'Your settings were saved.', 'settings' => ['theme' => $theme, 'tone' => $tone]]);
}

api_out([
    'role'     => $user['role'],
    'forced'   => $forced,
    'settings' => load_settings($user['id']),
]);
