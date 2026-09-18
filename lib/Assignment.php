<?php

require_once __DIR__ . '/Events.php';

function assignments_file(): string
{
    return LF_DATA_DIR . '/assignments.json';
}

function submissions_file(): string
{
    return LF_DATA_DIR . '/assignment-submissions.json';
}

function assignment_all(string $courseId = ''): array
{
    $all = json_read(assignments_file());
    if ($courseId !== '') $all = array_values(array_filter($all, fn($a) => ($a['course_id'] ?? '') === $courseId));
    usort($all, fn($a, $b) => strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? '')));
    return $all;
}

function assignment_find(string $id): ?array
{
    foreach (assignment_all() as $a) if (($a['id'] ?? '') === $id) return $a;
    return null;
}

function assignment_save(array $data): array
{
    if (empty($data['id'])) {
        $data['id'] = 'asg_' . bin2hex(random_bytes(5));
        $data['created_at'] = date('Y-m-d H:i:s');
    }
    $row = [
        'id' => (string)$data['id'],
        'course_id' => (string)($data['course_id'] ?? ''),
        'title' => trim((string)($data['title'] ?? '')),
        'description' => (string)($data['description'] ?? ''),
        'due_at' => (string)($data['due_at'] ?? ''),
        'lesson_id' => (string)($data['lesson_id'] ?? ''),
        'allow_file' => !empty($data['allow_file']),
        'created_at' => (string)($data['created_at'] ?? date('Y-m-d H:i:s')),
    ];
    json_update(assignments_file(), function (array $all) use ($row) {
        foreach ($all as $i => $a) if (($a['id'] ?? '') === $row['id']) { $all[$i] = $row; return $all; }
        $all[] = $row;
        return $all;
    });
    return $row;
}

function assignment_delete(string $id): void
{
    json_update(assignments_file(), function (array $all) use ($id) {
        return array_values(array_filter($all, fn($a) => ($a['id'] ?? '') !== $id));
    });
}

function assignment_submission(string $assignmentId, string $studentId): ?array
{
    $all = json_read(submissions_file());
    return $all[$assignmentId][$studentId] ?? null;
}

function assignment_submissions(string $assignmentId): array
{
    $all = json_read(submissions_file());
    return $all[$assignmentId] ?? [];
}

function assignment_submit(string $assignmentId, string $studentId, string $content, array $files = []): array
{
    $row = [
        'content' => mb_substr($content, 0, 5000),
        'files' => $files,
        'submitted_at' => date('Y-m-d H:i:s'),
        'status' => 'submitted',
        'grade' => null,
        'feedback' => '',
        'graded_at' => '',
    ];
    json_update(submissions_file(), function (array $all) use ($assignmentId, $studentId, $row) {
        $prev = $all[$assignmentId][$studentId] ?? null;
        if ($prev !== null) $row = array_merge($row, ['grade' => $prev['grade'] ?? null, 'feedback' => $prev['feedback'] ?? '', 'status' => !empty($prev['grade']) ? 'graded' : 'submitted']);
        $all[$assignmentId][$studentId] = $row;
        return $all;
    });
    $assignment = assignment_find($assignmentId);
    if ($assignment !== null && function_exists('lf_emit')) {
        lf_emit('assignment.submitted', lf_emit_context($studentId, [
            'assignment_id' => $assignmentId,
            'title' => (string)($assignment['title'] ?? ''),
            'course_id' => (string)($assignment['course_id'] ?? ''),
        ]));
    }
    return $row;
}

function assignment_grade(string $assignmentId, string $studentId, ?float $grade, string $feedback): void
{
    $assignment = assignment_find($assignmentId);
    json_update(submissions_file(), function (array $all) use ($assignmentId, $studentId, $grade, $feedback) {
        if (isset($all[$assignmentId][$studentId])) {
            $all[$assignmentId][$studentId]['grade'] = $grade;
            $all[$assignmentId][$studentId]['feedback'] = mb_substr($feedback, 0, 3000);
            $all[$assignmentId][$studentId]['status'] = 'graded';
            $all[$assignmentId][$studentId]['graded_at'] = date('Y-m-d H:i:s');
        }
        return $all;
    });
    if ($assignment !== null && function_exists('student_get')) {
        $student = student_get($studentId);
        if ($student !== null) {
            lf_emit('assignment.graded', [
                'student_id' => $studentId,
                'name' => (string)($student['name'] ?? ''),
                'email' => (string)($student['email'] ?? ''),
                'title' => (string)($assignment['title'] ?? ''),
                'feedback' => $feedback,
                'course_id' => (string)($assignment['course_id'] ?? ''),
                'link' => '/camp/' . rawurlencode((string)($assignment['course_id'] ?? '')) . '?tab=assignments',
            ]);
        }
    }
}

function assignment_stats(string $courseId): array
{
    $out = [];
    foreach (assignment_all($courseId) as $a) {
        $subs = assignment_submissions((string)$a['id']);
        $graded = 0;
        foreach ($subs as $s) if (!empty($s['graded_at'])) $graded++;
        $out[(string)$a['id']] = ['title' => (string)$a['title'], 'submitted' => count($subs), 'graded' => $graded];
    }
    return $out;
}
