<?php
/*
 * Student Dashboard data (read-only): the student's own grades, saved risk result and insight message.
 * Tone (Formal / Humor) comes from Settings. Only the data is returned here; the frontend draws the page and charts.
 */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/insights.php';
api_start(['GET']);

$user = api_require_role('student');
$db   = db_academic();

$st = $db->prepare('SELECT student_id, last_name, first_name, with_makeup_grades FROM students WHERE student_id = ?');
$st->execute([$user['username']]);
$student = $st->fetch();
$sem = current_semester($db);

if (!$student || !$sem) {
    api_out(['record' => false]);
}

$semId  = (int) $sem['id'];
$pass   = (float) config()['passing_grade'];
$risk   = risk_get($db, $student['student_id'], $semId);
$grades = grades_by_subject($db, $student['student_id'], $semId);
$message = insight_message($user['tone'], $risk['level'], $student['first_name'], $risk['at_risk'], $student['student_id']);

$terms = [];
foreach (EW_TERMS as $key => $label) {
    $terms[] = ['key' => $key, 'label' => $label];
}

$rows = [];
foreach ($grades as $g) {
    $scores = array_values($g['terms']);
    $avg = array_sum($scores) / count($scores);
    $rows[] = [
        'subject' => $g['name'],
        'scores'  => (object) $g['terms'],   // {"prelim": 85, ...} only for terms that exist
        'average' => $avg,
        'failing' => round($avg, 2) > $pass,   // 1 = highest, 5 = lowest, so a bigger number is worse
    ];
}

api_out([
    'record'        => true,
    'student'       => [
        'student_id' => $student['student_id'],
        'last_name'  => $student['last_name'],
        'first_name' => $student['first_name'],
        'with_makeup_grades' => (bool) (int) $student['with_makeup_grades'],
    ],
    'semester'      => ['id' => $semId, 'label' => $sem['label']],
    'passing_grade' => $pass,
    'borderline_margin' => (float) (config()['borderline_margin'] ?? 0.5),
    'terms'         => $terms,
    'risk'          => $risk + ['label' => risk_level_label($risk['level'])],
    'message'       => $message,
    'grades'        => $rows,
]);
