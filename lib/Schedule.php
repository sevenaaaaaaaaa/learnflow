<?php

function schedule_file(): string
{
    return LF_DATA_DIR . '/schedule.json';
}

function task_completions_file(): string
{
    return LF_DATA_DIR . '/task-completions.json';
}

function task_all(string $courseId): array
{
    $all = json_read(schedule_file());
    $rows = $all[$courseId] ?? [];
    usort($rows, fn($a, $b) => ((int)($a['day_index'] ?? 0)) <=> ((int)($b['day_index'] ?? 0)));
    return $rows;
}

function task_find(string $courseId, string $taskId): ?array
{
    foreach (task_all($courseId) as $t) if (($t['id'] ?? '') === $taskId) return $t;
    return null;
}

function task_save(string $courseId, array $data): array
{
    $row = [
        'id' => (string)($data['id'] ?? ('tsk_' . bin2hex(random_bytes(4)))),
        'day_index' => max(1, (int)($data['day_index'] ?? 1)),
        'title' => trim((string)($data['title'] ?? '')),
        'description' => (string)($data['description'] ?? ''),
        'lesson_id' => (string)($data['lesson_id'] ?? ''),
        'requires_checkin' => !empty($data['requires_checkin']),
    ];
    json_update(schedule_file(), function (array $all) use ($courseId, $row) {
        $rows = $all[$courseId] ?? [];
        $found = false;
        foreach ($rows as $i => $t) if (($t['id'] ?? '') === $row['id']) { $rows[$i] = $row; $found = true; break; }
        if (!$found) $rows[] = $row;
        $all[$courseId] = $rows;
        return $all;
    });
    return $row;
}

function task_delete(string $courseId, string $taskId): void
{
    json_update(schedule_file(), function (array $all) use ($courseId, $taskId) {
        $all[$courseId] = array_values(array_filter($all[$courseId] ?? [], fn($t) => ($t['id'] ?? '') !== $taskId));
        return $all;
    });
}

function task_done_map(string $courseId, string $studentId): array
{
    $all = json_read(task_completions_file());
    return $all[$courseId][$studentId] ?? [];
}

function task_complete(string $courseId, string $studentId, string $taskId): void
{
    json_update(task_completions_file(), function (array $all) use ($courseId, $studentId, $taskId) {
        $all[$courseId][$studentId][$taskId] = date('Y-m-d H:i:s');
        return $all;
    });
}

function task_progress(string $courseId, string $studentId): array
{
    $tasks = task_all($courseId);
    $done = task_done_map($courseId, $studentId);
    $doneCount = 0;
    foreach ($tasks as $t) if (isset($done[(string)$t['id']])) $doneCount++;
    return ['total' => count($tasks), 'done' => $doneCount, 'percent' => count($tasks) ? (int)round($doneCount / count($tasks) * 100) : 0];
}

function camp_current_day(array $course): int
{
    $start = strtotime((string)($course['camp_start'] ?? ''));
    if (!$start) return 0;
    $days = (int)floor((strtotime('today') - strtotime('midnight', $start)) / 86400) + 1;
    return max(1, $days);
}

function camp_status(array $course): string
{
    $start = strtotime((string)($course['camp_start'] ?? ''));
    $end = strtotime((string)($course['camp_end'] ?? ''));
    if (!$start) return '';
    $today = strtotime('today');
    if ($today < $start) return '尚未开营';
    if ($end && $today > $end) return '已结营';
    return '进行中';
}
