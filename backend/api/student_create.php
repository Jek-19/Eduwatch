<?php
/*
 * Add a student (teacher only). Creates:
 *   - the academic record (ACADEMIC database: Student ID, Last name, First name, With makeup grades)
 *   - the login account (AUTH database) with a one-time temporary password the student must change at first login.
 * The two databases are linked only by the student ID.
 * POST {student_id, last_name, first_name, email?, makeup?}
 * The temporary password is kept in the SESSION and shown once by api/teacher_dashboard.php (it never sits in the browser's storage).
 */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
$body = api_start(['POST']);
api_require_role('teacher');

function random_temp_password(int $len = 10): string
{
    $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    $digits  = '23456789';
    $all     = $letters . $digits;
    $pw = $letters[random_int(0, strlen($letters) - 1)] . $digits[random_int(0, strlen($digits) - 1)];
    while (strlen($pw) < $len) {
        $pw .= $all[random_int(0, strlen($all) - 1)];
    }
    return str_shuffle($pw);
}

$mk  = $body['makeup'] ?? '';
$old = [
    'student_id' => body_str($body, 'student_id', 10),
    'last_name'  => body_str($body, 'last_name', 60),
    'first_name' => body_str($body, 'first_name', 60),
    'email'      => mb_strtolower(body_str($body, 'email', 120)),
    'makeup'     => ($mk === true || $mk === 1 || $mk === '1'),
];
$nameRe = '/^[\p{L}][\p{L}\s.\'\-]*$/u';
$errors = [];

if (!valid_student_id($old['student_id'])) {
    $errors[] = 'Student ID must look like 124-00000 or 224-00000.';
}
if ($old['last_name'] === '' || !preg_match($nameRe, $old['last_name'])) {
    $errors[] = 'Please enter a valid last name (letters, spaces, . \' - only).';
}
if ($old['first_name'] === '' || !preg_match($nameRe, $old['first_name'])) {
    $errors[] = 'Please enter a valid first name (letters, spaces, . \' - only).';
}
if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'That email address does not look right.';
}

$acad = db_academic();
$auth = db_auth();
if (!$errors) {
    $c1 = $acad->prepare('SELECT 1 FROM students WHERE student_id = ?');
    $c1->execute([$old['student_id']]);
    $c2 = $auth->prepare('SELECT 1 FROM users WHERE username = ?');
    $c2->execute([$old['student_id']]);
    if ($c1->fetch() || $c2->fetch()) {
        $errors[] = 'A student with that ID already exists.';
    }
    if ($old['email'] !== '') {
        $c3 = $auth->prepare('SELECT 1 FROM users WHERE email = ?');
        $c3->execute([$old['email']]);
        if ($c3->fetch()) {
            $errors[] = 'That email address is already used by another account.';
        }
    }
}
if ($errors) {
    api_fail($errors[0], 422, 'validation', ['errors' => $errors]);
}

$temp = random_temp_password();
$fullName = $old['first_name'] . ' ' . $old['last_name'];
$acad->prepare('INSERT INTO students (student_id, last_name, first_name, with_makeup_grades) VALUES (?, ?, ?, ?)')
     ->execute([$old['student_id'], $old['last_name'], $old['first_name'], $old['makeup'] ? 1 : 0]);
try {
    $auth->prepare(
        "INSERT INTO users (username, email, password_hash, role, full_name, must_change_password)
         VALUES (?, ?, ?, 'student', ?, 1)"
    )->execute([$old['student_id'], $old['email'] !== '' ? $old['email'] : null,
                password_hash($temp, PASSWORD_DEFAULT), $fullName]);
    $uid = (int) $auth->lastInsertId();
    $auth->prepare('INSERT INTO user_settings (user_id) VALUES (?)')->execute([$uid]);
} catch (Throwable $ex) {
    // Keep the two databases in step: undo the academic record if the account could not be created.
    $acad->prepare('DELETE FROM students WHERE student_id = ?')->execute([$old['student_id']]);
    throw $ex;
}
if ($sem = current_semester($acad)) {
    risk_recalculate($acad, $old['student_id'], (int) $sem['id']);
}
$_SESSION['new_account'] = ['id' => $old['student_id'], 'name' => $fullName, 'password' => $temp];
api_out(['message' => 'Student added.'], 201);
