<?php
/* Risk Prediction Module entry point: POST -> a teacher recalculates everyone's risk. The scoring rules live in includes/risk.php */
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/risk.php';
api_start(['POST']);

api_require_role('teacher');
$db  = db_academic();
$sem = current_semester($db);
if (!$sem) {
    api_fail('No current semester is set up yet.', 409, 'no_semester');
}
$n = risk_recalculate_all($db, (int) $sem['id']);
api_out(['count' => $n, 'message' => "Risk levels were recalculated for {$n} student(s)."]);
