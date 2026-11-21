<?php
/*
 * Grades for one student (teacher only): Create / Read / Update / Delete.
 *   GET  ?student=ID                                   -> everything the grades page needs
 *   POST {student, action: add,    subject_id, term, score}
 *   POST {student, action: update, grade_id, score}
 *   POST {student, action: delete, grade_id}
 *   POST {student, action: makeup, makeup: "1" | "0"}
 * Every change is written to the audit log (who, what, old -> new) and then the Risk Prediction Module
 * recalculates that student's risk level.
 */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
$body = api_start(['GET', 'POST']);

$user = api_require_role('teacher');
$db   = db_academic();
$sem  = current_semester($db);
$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

$studentId = $isPost ? body_str($body, 'student', 10) : get_str('student', 10);
$st = $db->prepare('SELECT * FROM students WHERE student_id = ?');
$st->execute([$studentId]);
$student = $st->fetch();

if (!$student) {
    api_fail('That student was not found.', 404, 'not_found');
}
if (!$sem) {
    api_fail('No current semester is set up yet.', 409, 'no_semester');
}
$semId = (int) $sem['id'];

function audit_log(PDO $db, array $user, string $action, string $studentId, ?int $subjectId, ?int $semId,
                   ?string $term, $old, $new, ?string $detail = null): void
{
    $db->prepare(
        'INSERT INTO grade_audit_log (user_id, username, action, student_id, subject_id, semester_id, term, old_score, new_score, detail)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$user['id'], $user['username'], $action, $studentId, $subjectId, $semId, $term, $old, $new, $detail]);
}

/** Accepts "1", "1.5", "2.25", "5.00" (1 = highest, 5 = lowest). Returns a float or null if invalid. */
function parse_score(string $raw): ?float
{
    $raw = trim($raw);
    if (!preg_match('/^\d(\.\d{1,2})?$/', $raw)) {
        return null;
    }
    $v = (float) $raw;
    return ($v >= 1 && $v <= 5) ? $v : null;
}

/** A score may arrive as a JSON number or a string - treat both the same way. */
function body_score(array $body): ?float
{
    $v = $body['score'] ?? '';
    return parse_score(is_scalar($v) ? mb_substr((string) $v, 0, 7) : '');
}

if ($isPost) {
    $action = body_str($body, 'action', 20);
    $terms  = array_keys(EW_TERMS);
    $badScore = 'The grade must be a number from 1 to 5 (up to 2 decimals), where 1 is the highest and 5 is the lowest.';

    if ($action === 'add') {
        $subjectId = (int) body_str($body, 'subject_id', 10);
        $term  = body_str($body, 'term', 10);
        $score = body_score($body);
        $chk = $db->prepare('SELECT id FROM subjects WHERE id = ?');
        $chk->execute([$subjectId]);

        if (!$chk->fetch() || !in_array($term, $terms, true)) {
            api_fail('Please choose a subject and a term.', 422, 'validation');
        }
        if ($score === null) {
            api_fail($badScore, 422, 'validation');
        }
        $dup = $db->prepare('SELECT id FROM grades WHERE student_id = ? AND subject_id = ? AND semester_id = ? AND term = ?');
        $dup->execute([$student['student_id'], $subjectId, $semId, $term]);
        if ($dup->fetch()) {
            api_fail('A grade for that subject and term already exists. Edit it instead.', 409, 'duplicate');
        }
        $db->prepare('INSERT INTO grades (student_id, subject_id, semester_id, term, score, updated_by) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$student['student_id'], $subjectId, $semId, $term, $score, $user['username']]);
        audit_log($db, $user, 'create', $student['student_id'], $subjectId, $semId, $term, null, $score);
        $r = risk_recalculate($db, $student['student_id'], $semId);
        api_out(['type' => 'success', 'message' => 'Grade added. Risk level is now: ' . risk_level_label($r['level']) . '.']);
    }

    if ($action === 'update' || $action === 'delete') {
        $gradeId = (int) body_str($body, 'grade_id', 10);
        $g = $db->prepare('SELECT * FROM grades WHERE id = ? AND student_id = ? AND semester_id = ?');
        $g->execute([$gradeId, $student['student_id'], $semId]);
        $grade = $g->fetch();
        if (!$grade) {
            api_fail('That grade entry was not found.', 404, 'not_found');
        }
        if ($action === 'update') {
            $score = body_score($body);
            if ($score === null) {
                api_fail($badScore, 422, 'validation');
            }
            if (abs($score - (float) $grade['score']) < 0.001) {
                api_out(['type' => 'info', 'message' => 'No change - that is already the saved grade.']);
            }
            $db->prepare('UPDATE grades SET score = ?, updated_by = ? WHERE id = ?')->execute([$score, $user['username'], $gradeId]);
            audit_log($db, $user, 'update', $student['student_id'], (int) $grade['subject_id'], $semId, $grade['term'], $grade['score'], $score);
            $r = risk_recalculate($db, $student['student_id'], $semId);
            api_out(['type' => 'success', 'message' => 'Grade updated. Risk level is now: ' . risk_level_label($r['level']) . '.']);
        }
        $db->prepare('DELETE FROM grades WHERE id = ?')->execute([$gradeId]);
        audit_log($db, $user, 'delete', $student['student_id'], (int) $grade['subject_id'], $semId, $grade['term'], $grade['score'], null);
        $r = risk_recalculate($db, $student['student_id'], $semId);
        api_out(['type' => 'success', 'message' => 'Grade deleted. Risk level is now: ' . risk_level_label($r['level']) . '.']);
    }

    if ($action === 'makeup') {
        $mk = $body['makeup'] ?? '';
        $flag = ($mk === true || $mk === 1 || $mk === '1') ? 1 : 0;
        if ($flag !== (int) $student['with_makeup_grades']) {
            $db->prepare('UPDATE students SET with_makeup_grades = ? WHERE student_id = ?')->execute([$flag, $student['student_id']]);
            audit_log($db, $user, 'makeup_flag', $student['student_id'], null, $semId, null, null, null,
                      'With makeup grades: ' . ($flag ? 'No -> Yes' : 'Yes -> No'));
            $r = risk_recalculate($db, $student['student_id'], $semId);
            api_out(['type' => 'success', 'message' => 'Saved. Risk level is now: ' . risk_level_label($r['level']) . '.']);
        }
        api_out(['type' => 'info', 'message' => 'No change.']);
    }

    api_fail('Unknown action.', 400, 'bad_action');
}

/* ---------------------------------------------------------------- GET: data for the page */
$subjects = $db->query('SELECT id, code, name FROM subjects ORDER BY id')->fetchAll();
$grades   = grade_rows($db, $student['student_id'], $semId);
$risk     = risk_get($db, $student['student_id'], $semId);

$bySubject = []; // subject_id => ['subject_id', 'subject', 'cells' => [term => ['id', 'score']]]
foreach ($grades as $g) {
    $sid = (int) $g['subject_id'];
    $bySubject[$sid]['subject_id'] = $sid;
    $bySubject[$sid]['subject']    = $g['subject_name'];
    $bySubject[$sid]['cells'][$g['term']] = ['id' => (int) $g['id'], 'score' => (float) $g['score']];
}
ksort($bySubject);
foreach ($bySubject as &$row) {
    $row['cells'] = (object) $row['cells'];
}
unset($row);

$terms = [];
foreach (EW_TERMS as $key => $label) {
    $terms[] = ['key' => $key, 'label' => $label];
}

api_out([
    'student'  => [
        'student_id' => $student['student_id'],
        'last_name'  => $student['last_name'],
        'first_name' => $student['first_name'],
        'with_makeup_grades' => (bool) (int) $student['with_makeup_grades'],
    ],
    'semester' => ['id' => $semId, 'label' => $sem['label']],
    'risk'     => $risk + ['label' => risk_level_label($risk['level'])],
    'terms'    => $terms,
    'subjects' => array_map(static fn ($s) => ['id' => (int) $s['id'], 'code' => $s['code'], 'name' => $s['name']], $subjects),
    'grades'   => array_values($bySubject),
]);
