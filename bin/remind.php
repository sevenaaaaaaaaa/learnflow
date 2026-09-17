<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$inactiveDays = (int)($argv[1] ?? 3);
$cutoff = time() - $inactiveDays * 86400;
$remindersFile = LF_DATA_DIR . '/reminders.json';
$reminders = json_read($remindersFile);
$sent = 0;

foreach (course_all(true) as $course) {
    $courseId = (string)$course['id'];
    foreach (enroll_students($courseId) as $sid => $row) {
        $sid = (string)$sid;
        $sum = progress_summary($sid, $courseId, $course);
        if ($sum['total'] === 0 || $sum['done'] >= $sum['total']) continue;
        $last = progress_last_activity($sid, $courseId);
        if ($last !== 0 && $last >= $cutoff) continue;
        $prev = strtotime((string)($reminders[$sid][$courseId] ?? '')) ?: 0;
        if ($prev >= $cutoff) continue;
        $student = student_get($sid);
        if ($student === null) continue;
        lf_emit('study.reminder', [
            'student_id' => $sid,
            'name' => (string)($student['name'] ?? ''),
            'email' => (string)($student['email'] ?? ''),
            'course_id' => $courseId,
            'course_title' => (string)($course['title'] ?? ''),
            'link' => '/learn/' . rawurlencode((string)($course['slug'] ?? $courseId)),
        ]);
        $reminders[$sid][$courseId] = date('Y-m-d H:i:s');
        $sent++;
    }
}

json_write($remindersFile, $reminders);

if (function_exists('lf_dispatch_webhooks')) lf_dispatch_webhooks(50);
echo "reminders sent: $sent\n";
