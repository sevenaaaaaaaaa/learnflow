<?php

function analytics_cutoff(int $days): int
{
    return $days > 0 ? strtotime('today') - ($days - 1) * 86400 : 0;
}

function analytics_flat_enrollments(): array
{
    $out = [];
    $students = student_all();
    foreach (enrollment_all() as $courseId => $rows) {
        foreach ($rows as $studentId => $row) {
            $out[] = array_merge([
                'course_id' => (string)$courseId,
                'student_id' => (string)$studentId,
                'created_at' => (string)($students[(string)$studentId]['created_at'] ?? ''),
            ], $row);
        }
    }
    return $out;
}

function analytics_overview(int $days = 30): array
{
    $cutoff = analytics_cutoff($days);
    $inRange = fn(string $at) => $cutoff === 0 || (strtotime($at) ?: 0) >= $cutoff;

    $students = student_all();
    $newStudents = 0;
    foreach ($students as $s) if ($inRange((string)($s['created_at'] ?? ''))) $newStudents++;

    $enrollments = analytics_flat_enrollments();
    $enrolled = 0;
    $paid = 0;
    $revenue = 0.0;
    $sources = [];
    $paidStudents = [];
    $byCourse = [];
    $byDay = [];
    foreach ($enrollments as $e) {
        if (!$inRange((string)($e['enrolled_at'] ?? $e['created_at'] ?? ''))) continue;
        $enrolled++;
        $src = (string)($e['source'] ?? 'direct');
        $sources[$src] = ($sources[$src] ?? 0) + 1;
        $amount = (float)($e['amount'] ?? 0);
        if ($amount > 0) {
            $paid++;
            $revenue += $amount;
            $paidStudents[(string)$e['student_id']] = true;
            $day = substr((string)($e['enrolled_at'] ?? ''), 0, 10);
            if ($day !== '') $byDay[$day] = ($byDay[$day] ?? 0) + $amount;
            $cid = (string)$e['course_id'];
            $course = course_find($cid);
            $name = (string)($course['title'] ?? $cid);
            $byCourse[$name] = ($byCourse[$name] ?? 0) + $amount;
        }
    }

    $completed = 0;
    foreach ($enrollments as $e) {
        if (!$inRange((string)($e['enrolled_at'] ?? $e['created_at'] ?? ''))) continue;
        $course = course_find((string)$e['course_id']);
        if ($course === null) continue;
        $sum = progress_summary((string)$e['student_id'], (string)$e['course_id'], $course);
        if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
    }

    $certificates = 0;
    foreach (cert_all() as $c) if (empty($c['revoked']) && $inRange((string)($c['issued_at'] ?? ''))) $certificates++;

    ksort($byDay);
    arsort($byCourse);

    return [
        'days' => $days,
        'students' => $newStudents,
        'enrolled' => $enrolled,
        'paid' => $paid,
        'unique_payers' => count($paidStudents),
        'revenue' => round($revenue, 2),
        'avg_order' => $paid > 0 ? round($revenue / $paid, 2) : 0,
        'completed' => $completed,
        'certificates' => $certificates,
        'conv_enroll' => $newStudents > 0 ? round($enrolled / $newStudents * 100, 1) : 0,
        'conv_paid' => $enrolled > 0 ? round($paid / $enrolled * 100, 1) : 0,
        'conv_complete' => $paid > 0 ? round($completed / $paid * 100, 1) : 0,
        'sources' => $sources,
        'revenue_by_day' => $byDay,
        'revenue_by_course' => $byCourse,
        'referrals' => referral_all_stats(),
    ];
}

function referral_all_stats(): array
{
    $attributions = json_read(attributions_file());
    $codes = [];
    $amount = 0.0;
    foreach ($attributions as $a) {
        $c = (string)($a['code'] ?? '');
        $codes[$c] = ($codes[$c] ?? 0) + 1;
        $amount += (float)($a['amount'] ?? 0);
    }
    arsort($codes);
    return ['total' => count($attributions), 'amount' => round($amount, 2), 'by_code' => array_slice($codes, 0, 10, true)];
}

function analytics_userloop_overview(int $ttl = 60): ?array
{
    require_once __DIR__ . '/Events.php';
    $cfg = integrations_config()['userloop'] ?? [];
    if (empty($cfg['enabled']) || empty($cfg['url'])) return null;
    $base = preg_replace('#/api/v1/ingest/?$#', '', (string)$cfg['url']);
    $url = rtrim($base, '/') . '/api/v1/overview';
    $res = lf_get_json($url, ['X-UserLoop-Token: ' . (string)($cfg['secret'] ?? '')], 5);
    if (empty($res['ok'])) return null;
    $data = json_decode((string)$res['body'], true);
    return is_array($data) ? $data : null;
}
