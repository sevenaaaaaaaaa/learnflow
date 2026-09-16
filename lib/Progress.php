<?php

function progress_file(): string
{
    return LF_DATA_DIR . '/progress.json';
}

function progress_all(): array
{
    return json_read(progress_file());
}

function progress_course(string $studentId, string $courseId): array
{
    $all = progress_all();
    return $all[$studentId][$courseId] ?? [];
}

function progress_get(string $studentId, string $courseId, string $lessonId): array
{
    return progress_course($studentId, $courseId)[$lessonId] ?? [];
}

function progress_set(string $studentId, string $courseId, string $lessonId, array $state): array
{
    if ($studentId === '' || $courseId === '' || $lessonId === '') return [];
    $merged = [];
    json_update(progress_file(), function (array $all) use ($studentId, $courseId, $lessonId, $state, &$merged) {
        $prev = $all[$studentId][$courseId][$lessonId] ?? [];
        $merged = array_merge([
            'done' => false,
            'position' => 0,
            'duration' => 0,
            'seconds' => 0,
            'first_at' => date('Y-m-d H:i:s'),
        ], $prev, $state, ['updated_at' => date('Y-m-d H:i:s')]);
        if (empty($prev['first_at'])) $merged['first_at'] = date('Y-m-d H:i:s');
        $all[$studentId][$courseId][$lessonId] = $merged;
        return $all;
    });
    return $merged;
}

function progress_done(string $studentId, string $courseId, string $lessonId): array
{
    return progress_set($studentId, $courseId, $lessonId, ['done' => true]);
}

function progress_undone(string $studentId, string $courseId, string $lessonId): array
{
    return progress_set($studentId, $courseId, $lessonId, ['done' => false]);
}

function progress_heartbeat(string $studentId, string $courseId, string $lessonId, int $position, int $duration, int $deltaSeconds): array
{
    $prev = progress_get($studentId, $courseId, $lessonId);
    $seconds = (int)($prev['seconds'] ?? 0) + max(0, min($deltaSeconds, 600));
    $ratioDone = $duration > 0 && $position / $duration >= 0.95;
    return progress_set($studentId, $courseId, $lessonId, [
        'position' => max(0, $position),
        'duration' => max(0, $duration),
        'seconds' => $seconds,
        'done' => !empty($prev['done']) || $ratioDone,
    ]);
}

function progress_resume(string $studentId, string $courseId, array $course): ?array
{
    $rows = progress_course($studentId, $courseId);
    $candidates = [];
    foreach ($rows as $lessonId => $state) {
        if (!empty($state['position']) && empty($state['done'])) $candidates[$lessonId] = $state;
    }
    if (!$candidates) {
        foreach ($rows as $lessonId => $state) {
            if (empty($state['done'])) $candidates[$lessonId] = $state;
        }
    }
    if ($candidates) {
        uasort($candidates, fn($a, $b) => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
        $lessonId = array_key_first($candidates);
        return ['lesson_id' => $lessonId, 'position' => (int)($candidates[$lessonId]['position'] ?? 0), 'state' => $candidates[$lessonId]];
    }
    $first = course_first_lesson($course);
    if ($first !== null) return ['lesson_id' => (string)$first['id'], 'position' => 0, 'state' => []];
    return null;
}

function progress_summary(string $studentId, string $courseId, array $course): array
{
    $rows = progress_course($studentId, $courseId);
    $total = 0;
    $done = 0;
    $inProgress = 0;
    $seconds = 0;
    foreach (course_lessons($course) as $l) {
        $total++;
        $st = $rows[$l['id'] ?? ''] ?? null;
        if ($st && !empty($st['done'])) {
            $done++;
        } elseif ($st && (!empty($st['position']) || !empty($st['seconds']))) {
            $inProgress++;
        }
        $seconds += (int)($st['seconds'] ?? 0);
    }
    return [
        'total' => $total,
        'done' => $done,
        'in_progress' => $inProgress,
        'percent' => $total > 0 ? (int)round($done / $total * 100) : 0,
        'seconds' => $seconds,
        'minutes' => (int)round($seconds / 60),
    ];
}

function progress_lesson_state(string $studentId, string $courseId, string $lessonId): array
{
    $st = progress_get($studentId, $courseId, $lessonId);
    return [
        'done' => !empty($st['done']),
        'position' => (int)($st['position'] ?? 0),
        'seconds' => (int)($st['seconds'] ?? 0),
        'updated_at' => (string)($st['updated_at'] ?? ''),
    ];
}

function progress_curve(string $courseId): array
{
    $course = course_find($courseId);
    if ($course === null) return ['lessons' => [], 'learners' => 0, 'active_7d' => 0, 'daily' => []];
    $all = progress_all();
    $learners = 0;
    $started = [];
    $done = [];
    $daily = [];
    $seenStudents = [];
    $cutoff = time() - 7 * 86400;
    $active7 = 0;

    foreach ($all as $studentId => $courses) {
        if (!isset($courses[$courseId])) continue;
        $learners++;
        $lastTouch = 0;
        foreach (course_lessons($course) as $l) {
            $st = $courses[$courseId][$l['id'] ?? ''] ?? null;
            if (!$st) continue;
            if (!empty($st['seconds']) || !empty($st['position'])) {
                $started[$l['id']] = ($started[$l['id']] ?? 0) + 1;
            }
            if (!empty($st['done'])) $done[$l['id']] = ($done[$l['id']] ?? 0) + 1;
            $t = strtotime((string)($st['updated_at'] ?? '')) ?: 0;
            $lastTouch = max($lastTouch, $t);
            $day = substr((string)($st['updated_at'] ?? ''), 0, 10);
            if ($day !== '') $daily[$day] = ($daily[$day] ?? 0) + 1;
        }
        if ($lastTouch >= $cutoff) $active7++;
    }

    $labels = [];
    foreach (course_lessons($course) as $l) {
        $labels[] = [
            'id' => $l['id'] ?? '',
            'title' => $l['title'] ?? '',
            'started' => (int)($started[$l['id']] ?? 0),
            'done' => (int)($done[$l['id']] ?? 0),
        ];
    }
    ksort($daily);
    return ['lessons' => $labels, 'learners' => $learners, 'active_7d' => $active7, 'daily' => $daily];
}
