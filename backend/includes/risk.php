<?php
/*
 * Risk Prediction Module
 * ----------------------
 * Estimates, from a student's CURRENT grades, how likely they are to fail next semester.
 * Grading scale: 1.00 is the HIGHEST grade and 5.00 the LOWEST (3.00 = passing), so a LARGER number is WORSE.
 * It is a simple, explainable rule-based score (easy to defend in a capstone panel).
 * Swap risk_compute() for a trained model later without touching the rest of the system.
 *
 * Scoring (per subject, using the average of the terms recorded so far):
 *   average worse than the passing grade (above 3.00) ........ +30 points
 *   average within 0.50 of passing (2.51 to 3.00) ............ +15 points  (borderline)
 *   latest term got worse by 0.50 or more vs the one before .. +10 points
 * Plus: has makeup grades on record ........... +10 points
 *
 * Level:  40+ points = HIGH,  15-39 = MEDIUM,  below 15 = LOW,  no grades = NO DATA
 *
 * Gender, ID number and other personal traits are NOT used as inputs.
 */
require_once __DIR__ . '/bootstrap.php';

const EW_TERMS = [
    'prelim'   => 'Prelim',
    'midterm'  => 'Midterm',
    'prefinal' => 'Pre-final',
    'final'    => 'Final',
];

function risk_level_label(string $level): string
{
    return [
        'high'   => 'High risk',
        'medium' => 'Medium risk',
        'low'    => 'Low risk',
        'nodata' => 'No data yet',
    ][$level] ?? 'No data yet';
}

function fmt_num($n, int $dec = 1): string
{
    return number_format((float) $n, $dec, '.', '');
}

/**
 * Pure function: grade rows in, risk result out.
 * $rows = list of ['subject_id', 'subject_name', 'term', 'score']
 */
function risk_compute(array $rows, bool $hasMakeup, float $pass, float $margin = 0.5): array
{
    $bySubject = [];
    foreach ($rows as $r) {
        $sid = (int) $r['subject_id'];
        $bySubject[$sid]['name'] = $r['subject_name'];
        $bySubject[$sid]['terms'][$r['term']] = (float) $r['score'];
    }
    ksort($bySubject);

    if (!$bySubject) {
        $reasons = ['No grades have been recorded yet.'];
        if ($hasMakeup) {
            $reasons[] = 'Has makeup grades on record.';
        }
        return [
            'level' => 'nodata', 'points' => 0, 'general_avg' => null,
            'reasons' => $reasons, 'subjects' => [], 'at_risk' => [],
        ];
    }

    $points = 0;
    $reasons = [];
    $subjects = [];
    $atRisk = [];
    $sumAvg = 0.0;
    $termOrder = array_keys(EW_TERMS);

    foreach ($bySubject as $sid => $s) {
        $scores = [];
        $termKeys = [];
        foreach ($termOrder as $t) {
            if (isset($s['terms'][$t])) {
                $scores[] = $s['terms'][$t];
                $termKeys[] = $t;
            }
        }
        $avg = round(array_sum($scores) / count($scores), 2);   // same 2-decimal value that is shown on screen
        $sumAvg += $avg;
        $n = count($scores);
        // On this scale a bigger number is worse, so a positive trend means the grade got worse.
        $trend = $n >= 2 ? round($scores[$n - 1] - $scores[$n - 2], 2) : null;

        $status = 'ok';
        if ($avg > $pass) {
            $points += 30;
            $status = 'failing';
            $reasons[] = sprintf('%s: average %s is failing (passing is %s or better).', $s['name'], fmt_num($avg, 2), fmt_num($pass, 2));
        } elseif ($avg > $pass - $margin) {
            $points += 15;
            $status = 'borderline';
            $reasons[] = sprintf('%s: average %s is borderline (passing is %s or better).', $s['name'], fmt_num($avg, 2), fmt_num($pass, 2));
        }
        if ($trend !== null && $trend >= 0.5) {
            $points += 10;
            if ($status === 'ok') {
                $status = 'declining';
            }
            $reasons[] = sprintf(
                '%s: grade got worse by %s from %s to %s.',
                $s['name'], fmt_num($trend, 2), EW_TERMS[$termKeys[$n - 2]], EW_TERMS[$termKeys[$n - 1]]
            );
        }
        if ($status !== 'ok') {
            $atRisk[] = $s['name'];
        }
        $subjects[$sid] = [
            'name' => $s['name'], 'terms' => $s['terms'],
            'avg' => $avg, 'trend' => $trend, 'status' => $status,
        ];
    }

    if ($hasMakeup) {
        $points += 10;
        $reasons[] = 'Has makeup grades on record.';
    }

    $points = min(100, $points);
    $level = $points >= 40 ? 'high' : ($points >= 15 ? 'medium' : 'low');
    if (!$reasons) {
        $reasons[] = 'All subjects are comfortably better than the passing grade.';
    }

    return [
        'level' => $level, 'points' => $points, 'general_avg' => round($sumAvg / count($bySubject), 2),
        'reasons' => $reasons, 'subjects' => $subjects, 'at_risk' => $atRisk,
    ];
}

/* ---------------------------------------------------------------- database helpers */
function current_semester(PDO $db): ?array
{
    $row = $db->query('SELECT id, label FROM semesters WHERE is_current = 1 ORDER BY id DESC LIMIT 1')->fetch();
    return $row ?: null;
}

function grade_rows(PDO $db, string $studentId, int $semesterId): array
{
    $st = $db->prepare(
        'SELECT g.id, g.subject_id, s.name AS subject_name, g.term, g.score
           FROM grades g JOIN subjects s ON s.id = g.subject_id
          WHERE g.student_id = ? AND g.semester_id = ?
          ORDER BY s.id, g.term'
    );
    $st->execute([$studentId, $semesterId]);
    return $st->fetchAll();
}

/** Recompute one student's risk from their grades and save it. Returns the result array. */
function risk_recalculate(PDO $db, string $studentId, int $semesterId): array
{
    $st = $db->prepare('SELECT with_makeup_grades FROM students WHERE student_id = ?');
    $st->execute([$studentId]);
    $stu = $st->fetch();
    if (!$stu) {
        throw new RuntimeException('Unknown student ' . $studentId);
    }
    $res = risk_compute(grade_rows($db, $studentId, $semesterId), (bool) $stu['with_makeup_grades'],
                        (float) config()['passing_grade'], (float) (config()['borderline_margin'] ?? 0.5));

    $db->prepare(
        'INSERT INTO risk_results (student_id, semester_id, risk_level, risk_points, general_avg, details, computed_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE risk_level = VALUES(risk_level), risk_points = VALUES(risk_points),
                                 general_avg = VALUES(general_avg), details = VALUES(details), computed_at = NOW()'
    )->execute([
        $studentId, $semesterId, $res['level'], $res['points'], $res['general_avg'],
        json_encode(['reasons' => $res['reasons'], 'at_risk' => $res['at_risk']], JSON_UNESCAPED_UNICODE),
    ]);
    return $res;
}

function risk_recalculate_all(PDO $db, int $semesterId): int
{
    $ids = $db->query('SELECT student_id FROM students')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        risk_recalculate($db, (string) $id, $semesterId);
    }
    return count($ids);
}

/** Compute risk only for students that have no saved result yet (first run / newly added students). */
function risk_recalculate_missing(PDO $db, int $semesterId): void
{
    $st = $db->prepare(
        'SELECT s.student_id FROM students s
          LEFT JOIN risk_results r ON r.student_id = s.student_id AND r.semester_id = ?
          WHERE r.student_id IS NULL'
    );
    $st->execute([$semesterId]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        risk_recalculate($db, (string) $id, $semesterId);
    }
}

/** Saved risk for one student (computed on the spot if missing). */
function risk_get(PDO $db, string $studentId, int $semesterId): array
{
    $st = $db->prepare('SELECT * FROM risk_results WHERE student_id = ? AND semester_id = ?');
    $st->execute([$studentId, $semesterId]);
    $row = $st->fetch();
    if (!$row) {
        risk_recalculate($db, $studentId, $semesterId);
        $st->execute([$studentId, $semesterId]);
        $row = $st->fetch();
    }
    $d = json_decode((string) $row['details'], true) ?: [];
    return [
        'level'       => $row['risk_level'],
        'points'      => (int) $row['risk_points'],
        'general_avg' => $row['general_avg'] === null ? null : (float) $row['general_avg'],
        'reasons'     => $d['reasons'] ?? [],
        'at_risk'     => $d['at_risk'] ?? [],
        'computed_at' => $row['computed_at'],
    ];
}

/** Grades grouped per subject for tables/charts: [subject_id => ['name'=>, 'terms'=>[term=>score]]] */
function grades_by_subject(PDO $db, string $studentId, int $semesterId): array
{
    $out = [];
    foreach (grade_rows($db, $studentId, $semesterId) as $r) {
        $out[(int) $r['subject_id']]['name'] = $r['subject_name'];
        $out[(int) $r['subject_id']]['terms'][$r['term']] = (float) $r['score'];
    }
    ksort($out);
    return $out;
}
