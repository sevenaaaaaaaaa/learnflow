<?php

function gdpr_student_export(string $studentId): array
{
    $student = student_get($studentId);
    $enrollments = [];
    foreach (enroll_by_student($studentId) as $courseId => $row) {
        $course = course_find((string)$courseId);
        $enrollments[(string)$courseId] = array_merge($row, ['course_title' => (string)($course['title'] ?? '')]);
    }
    $progress = [];
    $allProgress = progress_all();
    if (isset($allProgress[$studentId])) $progress = $allProgress[$studentId];

    $certs = array_map(fn($c) => ['cert_no' => $c['cert_no'] ?? '', 'course_title' => $c['course_title'] ?? '', 'issued_at' => $c['issued_at'] ?? ''], cert_for_student($studentId));

    $notifications = array_map(fn($n) => ['title' => $n['title'] ?? '', 'body' => $n['body'] ?? '', 'at' => $n['created_at'] ?? ''], notify_list($studentId, 100));

    $allAttempts = json_read(LF_DATA_DIR . '/quiz-attempts.json');
    $quizAttempts = [];
    foreach ((array)($allAttempts[$studentId] ?? []) as $quizId => $attempts) {
        foreach ((array)$attempts as $a) $quizAttempts[] = ['quiz_id' => (string)$quizId, 'score' => $a['score'] ?? 0, 'passed' => !empty($a['passed']), 'at' => $a['submitted_at'] ?? ''];
    }

    $allSubs = json_read(LF_DATA_DIR . '/assignment-submissions.json');
    $submissions = [];
    foreach ($allSubs as $assignmentId => $subs) {
        if (isset($subs[$studentId])) $submissions[] = ['assignment_id' => (string)$assignmentId, 'content' => $subs[$studentId]['content'] ?? '', 'grade' => $subs[$studentId]['grade'] ?? null, 'at' => $subs[$studentId]['submitted_at'] ?? ''];
    }

    $allCheckins = json_read(LF_DATA_DIR . '/checkins.json');
    $checkins = [];
    foreach ($allCheckins as $courseId => $students) {
        if (isset($students[$studentId])) $checkins[(string)$courseId] = array_keys((array)$students[$studentId]);
    }

    return [
        'exported_at' => date('c'),
        'student' => $student,
        'enrollments' => $enrollments,
        'progress' => $progress,
        'certificates' => $certs,
        'notifications' => $notifications,
        'quiz_attempts' => $quizAttempts,
        'assignment_submissions' => $submissions,
        'checkins' => $checkins,
    ];
}

function gdpr_student_erase(string $studentId): void
{
    json_update(students_file(), function (array $all) use ($studentId) { unset($all[$studentId]); return $all; });
    json_update(enrollments_file(), function (array $all) use ($studentId) {
        foreach ($all as $cid => $rows) { unset($all[$cid][$studentId]); if (empty($all[$cid])) unset($all[$cid]); }
        return $all;
    });
    json_update(progress_file(), function (array $all) use ($studentId) { unset($all[$studentId]); return $all; });
    json_update(cert_file(), function (array $all) use ($studentId) {
        foreach ($all as $no => $c) if (($c['student_id'] ?? '') === $studentId) unset($all[$no]);
        return $all;
    });
    json_update(notifications_file(), function (array $all) use ($studentId) { unset($all[$studentId]); return $all; });
    json_update(LF_DATA_DIR . '/quiz-attempts.json', function (array $all) use ($studentId) { unset($all[$studentId]); return $all; });
    json_update(LF_DATA_DIR . '/checkins.json', function (array $all) use ($studentId) {
        foreach ($all as $cid => $students) { unset($all[$cid][$studentId]); if (empty($all[$cid])) unset($all[$cid]); }
        return $all;
    });
    json_update(LF_DATA_DIR . '/task-completions.json', function (array $all) use ($studentId) {
        foreach ($all as $cid => $students) { unset($all[$cid][$studentId]); if (empty($all[$cid])) unset($all[$cid]); }
        return $all;
    });
    json_update(LF_DATA_DIR . '/assignment-submissions.json', function (array $all) use ($studentId) {
        foreach ($all as $aid => $subs) { unset($all[$aid][$studentId]); if (empty($all[$aid])) unset($all[$aid]); }
        return $all;
    });
    json_update(community_file(), function (array $all) use ($studentId) {
        foreach ($all as $cid => $posts) {
            $kept = [];
            foreach ((array)$posts as $p) {
                if (($p['student_id'] ?? '') === $studentId) continue;
                $p['comments'] = array_values(array_filter((array)($p['comments'] ?? []), fn($c) => ($c['student_id'] ?? '') !== $studentId));
                $p['likes'] = array_values(array_filter(array_map('strval', (array)($p['likes'] ?? [])), fn($s) => $s !== $studentId));
                $kept[] = $p;
            }
            $all[$cid] = $kept;
        }
        return $all;
    });
    json_update(LF_DATA_DIR . '/reminders.json', function (array $all) use ($studentId) { unset($all[$studentId]); return $all; });
    json_update(attributions_file(), function (array $all) use ($studentId) {
        return array_values(array_filter($all, fn($a) => ($a['referrer_id'] ?? '') !== $studentId && ($a['buyer_id'] ?? '') !== $studentId));
    });
}
