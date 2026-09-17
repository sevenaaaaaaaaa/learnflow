<?php

function checkins_file(): string
{
    return LF_DATA_DIR . '/checkins.json';
}

function checkin_map(string $courseId, string $studentId): array
{
    $all = json_read(checkins_file());
    return $all[$courseId][$studentId] ?? [];
}

function checkin_do(string $courseId, string $studentId, string $note = ''): array
{
    $day = date('Y-m-d');
    $row = ['at' => date('Y-m-d H:i:s'), 'note' => mb_substr($note, 0, 200)];
    json_update(checkins_file(), function (array $all) use ($courseId, $studentId, $day, $row) {
        $all[$courseId][$studentId][$day] = $row;
        return $all;
    });
    return ['day' => $day, 'streak' => checkin_streak($courseId, $studentId)];
}

function checkin_today(string $courseId, string $studentId): bool
{
    return isset(checkin_map($courseId, $studentId)[date('Y-m-d')]);
}

function checkin_streak(string $courseId, string $studentId): int
{
    $map = checkin_map($courseId, $studentId);
    if (!$map) return 0;
    $cursor = strtotime('today');
    if (!isset($map[date('Y-m-d', $cursor)])) {
        $cursor -= 86400;
        if (!isset($map[date('Y-m-d', $cursor)])) return 0;
    }
    $streak = 0;
    while (isset($map[date('Y-m-d', $cursor)])) {
        $streak++;
        $cursor -= 86400;
    }
    return $streak;
}

function checkin_total(string $courseId, string $studentId): int
{
    return count(checkin_map($courseId, $studentId));
}

function checkin_recent(string $courseId, string $studentId, int $days = 28): array
{
    $map = checkin_map($courseId, $studentId);
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $out[$d] = isset($map[$d]);
    }
    return $out;
}

function checkin_course_stats(string $courseId): array
{
    $all = json_read(checkins_file());
    $rows = $all[$courseId] ?? [];
    $today = date('Y-m-d');
    $todayCount = 0;
    $total = 0;
    foreach ($rows as $map) {
        $total += count($map);
        if (isset($map[$today])) $todayCount++;
    }
    return ['students' => count($rows), 'today' => $todayCount, 'total' => $total];
}
