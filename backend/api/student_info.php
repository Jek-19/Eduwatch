<?php
/* GET ?student=ID -> the name behind a student ID (teacher only). Used by the printable QR page. */
require_once __DIR__ . '/../includes/api.php';
api_start(['GET']);
api_require_role('teacher');

$st = db_academic()->prepare('SELECT student_id, last_name, first_name FROM students WHERE student_id = ?');
$st->execute([get_str('student', 10)]);
$student = $st->fetch();
if (!$student) {
    api_fail('That student was not found.', 404, 'not_found');
}
api_out(['student' => $student]);
