<?php

function points_file(): string
{
    return LF_DATA_DIR . '/points.json';
}

function points_of(string $studentId): array
{
    $all = json_read(points_file());
    return array_merge(['points' => 0, 'log' => [], 'achievements' => []], (array)($all[$studentId] ?? []));
}

function points_balance(string $studentId): int
{
    return (int)(points_of($studentId)['points'] ?? 0);
}

function points_log(string $studentId, int $limit = 30): array
{
    $log = (array)(points_of($studentId)['log'] ?? []);
    return array_slice(array_reverse($log), 0, $limit);
}

function points_earn(string $studentId, string $reason, int $delta, string $dedupeKey = ''): void
{
    if ($studentId === '' || $delta === 0) return;
    json_update(points_file(), function (array $all) use ($studentId, $reason, $delta, $dedupeKey) {
        $row = array_merge(['points' => 0, 'log' => [], 'achievements' => []], (array)($all[$studentId] ?? []));
        if ($dedupeKey !== '') {
            foreach ((array)$row['log'] as $l) if (($l['key'] ?? '') === $dedupeKey) return $all;
        }
        $row['points'] = (int)$row['points'] + $delta;
        $row['log'][] = ['at' => date('Y-m-d H:i:s'), 'reason' => $reason, 'delta' => $delta, 'key' => $dedupeKey];
        if (count($row['log']) > 500) $row['log'] = array_slice($row['log'], -500);
        $all[$studentId] = $row;
        return $all;
    });
}

function points_achievements(): array
{
    return [
        'first_checkin' => '首次打卡',
        'streak_7' => '连续 7 天打卡',
        'first_lesson' => '完成第一课时',
        'first_assignment' => '提交第一份作业',
        'course_master' => '完成一门课',
        'certified' => '获得结业证书',
        'referrer' => '成功推荐一位学员',
    ];
}

function points_has_achievement(string $studentId, string $id): bool
{
    return in_array($id, (array)(points_of($studentId)['achievements'] ?? []), true);
}

function points_award(string $studentId, string $achId): void
{
    if ($studentId === '' || !isset(points_achievements()[$achId])) return;
    $awarded = false;
    json_update(points_file(), function (array $all) use ($studentId, $achId, &$awarded) {
        $row = array_merge(['points' => 0, 'log' => [], 'achievements' => []], (array)($all[$studentId] ?? []));
        $ach = array_map('strval', (array)$row['achievements']);
        if (in_array($achId, $ach, true)) return $all;
        $ach[] = $achId;
        $row['achievements'] = array_values($ach);
        $all[$studentId] = $row;
        $awarded = true;
        return $all;
    });
    if ($awarded) {
        require_once __DIR__ . '/Notify.php';
        notify_add($studentId, 'system', '解锁成就：' . points_achievements()[$achId], '继续加油！', lf_url('/dashboard'));
    }
}

function points_leaderboard(int $limit = 10): array
{
    $all = json_read(points_file());
    $rows = [];
    foreach ($all as $sid => $r) {
        $s = function_exists('student_get') ? student_get((string)$sid) : null;
        $rows[] = ['student_id' => (string)$sid, 'name' => (string)($s['name'] ?? $sid), 'points' => (int)($r['points'] ?? 0)];
    }
    usort($rows, fn($a, $b) => $b['points'] <=> $a['points']);
    return array_slice($rows, 0, $limit);
}

function points_for_event(string $event, array $d): void
{
    $sid = (string)($d['student_id'] ?? '');
    if ($sid === '') return;
    switch ($event) {
        case 'checkin.done':
            points_earn($sid, '每日打卡', 1, 'checkin:' . date('Y-m-d'));
            points_award($sid, 'first_checkin');
            if ((int)($d['streak'] ?? 0) >= 7) {
                points_earn($sid, '连续 7 天打卡', 5, 'streak7:' . date('Y-m-d'));
                points_award($sid, 'streak_7');
            }
            break;
        case 'lesson.completed':
            points_earn($sid, '完成课时', 2, 'lesson:' . (string)($d['lesson_id'] ?? ''));
            points_award($sid, 'first_lesson');
            break;
        case 'assignment.submitted':
            points_earn($sid, '提交作业', 5, 'asg:' . (string)($d['assignment_id'] ?? '') . ':' . $sid);
            points_award($sid, 'first_assignment');
            break;
        case 'course.completed':
            points_earn($sid, '完成课程', 20, 'course:' . (string)($d['course_id'] ?? ''));
            points_award($sid, 'course_master');
            break;
        case 'certificate.issued':
            points_earn($sid, '获得证书', 10, 'cert:' . (string)($d['cert_no'] ?? ''));
            points_award($sid, 'certified');
            break;
    }
}
