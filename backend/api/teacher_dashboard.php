<?php
/*
 * Teacher Dashboard data: student list (with optional search), class-wide at-risk list and risk summary.
 * Grade create/edit/delete is api/grades.php. Risk levels come from the Risk Prediction Module (includes/risk.php).
 */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
api_start(['GET']);

$user = api_require_role('teacher');
$db   = db_academic();
$sem  = current_semester($db);

if (!$sem) {
    api_out(['semester' => null]);
}
$semId = (int) $sem['id'];
risk_recalculate_missing($db, $semId); // first run / newly added students

// A one-time box showing the temporary password of a student account that was just created.
$newAccount = $_SESSION['new_account'] ?? null;
unset($_SESSION['new_account']);

/* ---- list with optional search ---- */
$q = get_str('q', 60);
$sql = 'SELECT s.student_id, s.last_name, s.first_name, s.with_makeup_grades,
               r.risk_level, r.risk_points, r.general_avg
          FROM students s
          LEFT JOIN risk_results r ON r.student_id = s.student_id AND r.semester_id = ?';
$params = [$semId];
if ($q !== '') {
    $sql .= ' WHERE s.student_id LIKE ? OR s.last_name LIKE ? OR s.first_name LIKE ?';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY s.last_name, s.first_name';
$st = $db->prepare($sql);
$st->execute($params);
$students = [];
foreach ($st->fetchAll() as $r) {
    $level = $r['risk_level'] ?? 'nodata';
    $students[] = [
        'student_id'         => $r['student_id'],
        'last_name'          => $r['last_name'],
        'first_name'         => $r['first_name'],
        'with_makeup_grades' => (bool) (int) $r['with_makeup_grades'],
        'general_avg'        => $r['general_avg'] === null ? null : (float) $r['general_avg'],
        'risk_level'         => $level,
        'risk_label'         => risk_level_label($level),
    ];
}

/* ---- summary over the WHOLE class (not just the search result) ---- */
$counts = ['high' => 0, 'medium' => 0, 'low' => 0, 'nodata' => 0];
$all = $db->prepare('SELECT risk_level, COUNT(*) AS n FROM risk_results WHERE semester_id = ? GROUP BY risk_level');
$all->execute([$semId]);
foreach ($all->fetchAll() as $r) {
    $counts[$r['risk_level']] = (int) $r['n'];
}

/* ---- class-wide at-risk list: high first, then medium ---- */
$atRiskStmt = $db->prepare(
    "SELECT s.student_id, s.last_name, s.first_name, r.risk_level, r.risk_points, r.details
       FROM risk_results r JOIN students s ON s.student_id = r.student_id
      WHERE r.semester_id = ? AND r.risk_level IN ('high','medium')
      ORDER BY (r.risk_level = 'high') DESC, r.risk_points DESC, s.last_name"
);
$atRiskStmt->execute([$semId]);
$atRisk = [];
foreach ($atRiskStmt->fetchAll() as $a) {
    $d = json_decode((string) $a['details'], true) ?: [];
    $atRisk[] = [
        'student_id' => $a['student_id'],
        'last_name'  => $a['last_name'],
        'first_name' => $a['first_name'],
        'risk_level' => $a['risk_level'],
        'risk_label' => risk_level_label($a['risk_level']),
        'reasons'    => array_slice($d['reasons'] ?? [], 0, 2),
    ];
}

api_out([
    'semester'      => ['id' => $semId, 'label' => $sem['label']],
    'passing_grade' => (float) config()['passing_grade'],
    'query'         => $q,
    'counts'        => $counts,
    'total'         => array_sum($counts),
    'at_risk'       => $atRisk,
    'students'      => $students,
    'new_account'   => $newAccount,
]);
