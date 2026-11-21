<?php
/* Audit log (teacher only): who changed which grade, when, and from what to what. GET ?student=ID filters by student. */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
api_start(['GET']);
api_require_role('teacher');
$db = db_academic();

$q = get_str('student', 10);
$sql = 'SELECT l.*, s.name AS subject_name, st.last_name, st.first_name
          FROM grade_audit_log l
          LEFT JOIN subjects s ON s.id = l.subject_id
          LEFT JOIN students st ON st.student_id = l.student_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE l.student_id = ?';
    $params[] = $q;
}
$sql .= ' ORDER BY l.id DESC LIMIT 200';
$st = $db->prepare($sql);
$st->execute($params);

$labels = ['create' => 'Added', 'update' => 'Changed', 'delete' => 'Deleted', 'makeup_flag' => 'Makeup flag'];
$rows = [];
foreach ($st->fetchAll() as $r) {
    $rows[] = [
        'when'         => $r['created_at'],
        'by'           => $r['username'],
        'action'       => $r['action'],
        'action_label' => $labels[$r['action']] ?? $r['action'],
        'student_id'   => $r['student_id'],
        'last_name'    => $r['last_name'],
        'detail'       => $r['detail'],
        'subject'      => $r['subject_name'],
        'term_label'   => $r['term'] ? (EW_TERMS[$r['term']] ?? $r['term']) : null,
        'old_score'    => $r['old_score'] === null ? null : (float) $r['old_score'],
        'new_score'    => $r['new_score'] === null ? null : (float) $r['new_score'],
    ];
}
api_out(['query' => $q, 'rows' => $rows]);
