<?php
/*
 * Small toolkit shared by every endpoint in backend/api/.
 *
 * Rules of the API (the "contract" the frontend relies on):
 *   - Every answer is JSON:  success -> {"ok": true, ...data}      failure -> {"ok": false, "error": "message", "code": "..."}
 *   - Every POST sends a JSON body and an X-CSRF-Token header (the token comes from api/session.php).
 *   - Not logged in -> HTTP 401 (code "not_logged_in" or "timeout"); wrong role -> 403 (code "wrong_role");
 *     must choose a new password first -> 403 (code "password_change_required").
 */
require_once __DIR__ . '/auth.php';

function api_out(array $data = [], int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function api_fail(string $message, int $status = 400, string $code = 'error', array $extra = []): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => $message, 'code' => $code] + $extra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/**
 * Call first in every endpoint. Checks the HTTP method, reads the JSON body (POST) and checks the CSRF header (POST).
 * Returns the decoded body ([] for GET).
 */
function api_start(array $methods = ['GET'], bool $csrf = true): array
{
    header('Content-Type: application/json; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        api_fail('That request method is not allowed here.', 405, 'method_not_allowed');
    }
    if ($method !== 'POST') {
        return [];
    }

    if ($csrf) {
        $sent  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $known = (string) ($_SESSION['csrf'] ?? '');
        // Both must be non-empty: an empty token must never "match" a session that has no token yet.
        if ($known === '' || !is_string($sent) || $sent === '' || !hash_equals($known, $sent)) {
            api_fail('Your session expired or was invalid. Please refresh the page and try again.', 400, 'csrf');
        }
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_fail('The request body must be valid JSON.', 400, 'bad_json');
    }
    return $data;
}

/** A trimmed, length-limited string from the JSON body ('' if missing). JSON numbers (e.g. an id) count as text too. */
function body_str(array $body, string $key, int $maxLen = 255): string
{
    $v = $body[$key] ?? '';
    if (is_int($v) || is_float($v)) {
        $v = (string) $v;
    }
    return is_string($v) ? mb_substr(trim($v), 0, $maxLen) : '';
}

/** A string from the JSON body exactly as sent (used for passwords: no trimming). */
function body_raw(array $body, string $key): string
{
    $v = $body[$key] ?? '';
    return is_string($v) ? $v : '';
}

/* ---------------------------------------------------------------- guards */
/**
 * Make sure someone is logged in. Returns the session user, or stops with a JSON error.
 * $allowForced = true lets a user who must change their password still use this endpoint.
 */
function api_require_login(bool $allowForced = false): array
{
    $check = session_check();
    if ($check['status'] === 'timeout') {
        api_fail('You were logged out because of inactivity. Please log in again.', 401, 'timeout');
    }
    if ($check['status'] !== 'ok') {
        api_fail('Please log in first.', 401, 'not_logged_in');
    }
    $user = $check['user'];
    if ($user['must_change'] && !$allowForced) {
        api_fail('Please choose a new password before you continue.', 403, 'password_change_required');
    }
    return $user;
}

function api_require_role(string $role, bool $allowForced = false): array
{
    $user = api_require_login($allowForced);
    if ($user['role'] !== $role) {
        api_fail('This is only available to ' . ($role === 'teacher' ? 'staff' : 'student') . ' accounts.', 403, 'wrong_role', ['role' => $user['role']]);
    }
    return $user;
}
