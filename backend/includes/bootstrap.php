<?php
/*
 * Shared start-up code used by every API endpoint:
 * config, session, the two database connections, small helpers.
 *
 * The backend no longer draws any HTML. Every endpoint in backend/api/ answers with JSON,
 * and the pages in frontend/ (HTML + CSS + JS) call those endpoints with fetch().
 */

define('APP_ROOT', dirname(__DIR__));

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require APP_ROOT . '/config.php';
    }
    return $cfg;
}

date_default_timezone_set(config()['timezone'] ?? 'Asia/Manila');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ini_set('display_errors', '0'); // never print PHP errors into a JSON response

set_exception_handler(function (Throwable $ex) {
    error_log('[EduWatch] ' . $ex);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $ex->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $body = [
        'ok'    => false,
        'code'  => 'server_error',
        'error' => 'EduWatch could not finish that request. Please check that MySQL is running and that '
                 . 'backend/config.php has the right database settings, then try again.',
    ];
    if (!empty(config()['debug'])) {
        $body['debug'] = (string) $ex;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
});

/* ---------------------------------------------------------------- session + headers */
if (PHP_SAPI !== 'cli') {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (session_status() === PHP_SESSION_NONE) {
        session_name('EDUWATCHSESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    }
}

/* ---------------------------------------------------------------- database */
function db(string $which): PDO
{
    static $pool = [];
    if (isset($pool[$which])) {
        return $pool[$which];
    }
    $c = config()['db_' . $which];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // Make MySQL's NOW() use the same time zone as PHP.
    $pdo->exec("SET time_zone = '" . date('P') . "'");
    return $pool[$which] = $pdo;
}

function db_auth(): PDO     { return db('auth'); }
function db_academic(): PDO { return db('academic'); }

/* ---------------------------------------------------------------- small helpers */
function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function get_str(string $key, int $maxLen = 255): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $maxLen) : '';
}

/* ---------------------------------------------------------------- CSRF */
/** The token is handed to the frontend by api/session.php and must come back in the X-CSRF-Token header on every POST. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/* ---------------------------------------------------------------- misc */
/**
 * Address of the frontend pages (used for the link inside password-reset emails).
 * config.php 'base_url' = address of the EduWatch folder, e.g. http://localhost/eduwatch
 * Left empty, it is worked out from the request (backend/api -> frontend).
 */
function frontend_url(): string
{
    $cfg = trim((string) (config()['base_url'] ?? ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/') . '/frontend';
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host  = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Only allow plain host[:port] characters (guards against a forged Host header).
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        $host = 'localhost';
    }
    $dir  = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    $root = preg_replace('#/backend/api$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . $root . '/frontend';
}

function valid_student_id(string $id): bool
{
    // School format: 124-00000 (female) or 224-00000 (male). We only validate the shape.
    return (bool) preg_match('/^(124|224)-\d{5}$/', $id);
}
