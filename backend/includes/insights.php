<?php
/*
 * Insight messages for the Student Dashboard.
 * Chosen by risk level + the tone the student picked in Settings ("formal" or "humor").
 * Edit or add lines freely. Placeholders: {name} = first name, {subjects} = subjects needing attention.
 * Messages say "at risk" and always point to a next step - never "you will fail".
 */

function insight_templates(): array
{
    return [
        'formal' => [
            'nodata' => [
                'No grades have been recorded for you yet. Check back once your teachers post your first grades.',
            ],
            'low' => [
                "You're on track this semester, {name}. Keep up the consistent work.",
                'Your grades look healthy right now. Keep your study habits steady and you should stay on track.',
                'Good progress, {name}. Maintaining this pace keeps you in a strong position.',
            ],
            'medium' => [
                'Some of your grades need attention ({subjects}). A little extra review now can turn things around.',
                'Your results in {subjects} are drifting toward the risk zone. Try setting aside regular study time this week.',
                '{name}, {subjects} could use more attention. Consider asking your teacher for tips or a quick consultation.',
            ],
            'high' => [
                'You are at high risk in {subjects}. Please talk to your teacher or adviser soon - support is available, and acting early makes a real difference.',
                '{name}, your current grades in {subjects} put you at high risk. Please reach out to your adviser this week so you can make a plan together.',
                'Your standing in {subjects} needs immediate attention. Speak with your teacher or adviser as soon as you can; there is still time to improve.',
            ],
        ],
        'humor' => [
            'nodata' => [
                'No grades yet, so this dashboard is basically a blank page. Check back when your teachers drop the first ones.',
            ],
            'low' => [
                "You're locked in, {name}. Grades are looking good, no cap. Keep it up!",
                "Ngl, you're cooking this semester. Stay consistent and keep the streak going.",
                "It's giving 'on track' energy. Kaya 'to, keep going!",
            ],
            'medium' => [
                "Okay {name}, {subjects} is giving 'needs a little attention'. A small grind now means a big win later.",
                'Plot twist: {subjects} is wobbling a bit. A few short study sessions this week can fix that, fr.',
                "Not a red alert, but {subjects} could use some love. You've got this, {name}.",
            ],
            'high' => [
                "Real talk, {name}: {subjects} needs help ASAP. No shame in that. Hit up your teacher or adviser and let's fix this together.",
                'Big yellow-flag energy on {subjects}. Please talk to your adviser early, future you will say thanks.',
                "{name}, {subjects} is in the danger zone, but it's not game over. Message your teacher or adviser this week and make a plan. Kaya mo 'to!",
            ],
        ],
    ];
}

function insight_message(string $tone, string $level, string $firstName, array $atRiskSubjects, string $seed): string
{
    $all  = insight_templates();
    $tone = isset($all[$tone]) ? $tone : 'formal';
    $list = $all[$tone][$level] ?? $all[$tone]['nodata'];
    // Same student + same day = same message (so it doesn't change on every refresh).
    $tpl = $list[abs(crc32($seed . date('Y-m-d'))) % count($list)];

    $subjects = 'your subjects';
    if ($atRiskSubjects) {
        $shown = array_slice($atRiskSubjects, 0, 3);
        $subjects = count($shown) > 1
            ? implode(', ', array_slice($shown, 0, -1)) . ' and ' . end($shown)
            : $shown[0];
        if (count($atRiskSubjects) > 3) {
            $subjects .= ' (+' . (count($atRiskSubjects) - 3) . ' more)';
        }
    }
    return str_replace(['{name}', '{subjects}'], [$firstName, $subjects], $tpl);
}
