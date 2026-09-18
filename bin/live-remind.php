<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$hours = max(1, (int)($argv[1] ?? 24));
$storeFile = LF_DATA_DIR . '/live-reminders.json';
$sent = json_read($storeFile);
$now = time();
$count = 0;

foreach (course_all(true) as $course) {
    foreach (course_lessons($course) as $lesson) {
        if (($lesson['type'] ?? '') !== 'live') continue;
        $start = strtotime((string)($lesson['live_start'] ?? '')) ?: 0;
        if ($start === 0 || $start < $now || $start > $now + $hours * 3600) continue;
        $key = (string)$course['id'] . ':' . (string)$lesson['id'];
        if (isset($sent[$key])) continue;
        $link = '/learn/' . rawurlencode((string)($course['slug'] ?? $course['id'])) . '?lesson=' . rawurlencode((string)$lesson['id']);
        foreach (array_keys(enroll_students((string)$course['id'])) as $sid) {
            $student = student_get((string)$sid);
            if ($student === null) continue;
            lf_emit('live.reminder', [
                'student_id' => (string)$sid,
                'name' => (string)($student['name'] ?? ''),
                'email' => (string)($student['email'] ?? ''),
                'course_id' => (string)$course['id'],
                'course_title' => (string)($course['title'] ?? ''),
                'title' => (string)($course['title'] ?? '') . ' · ' . (string)($lesson['title'] ?? '直播'),
                'link' => $link,
            ]);
            $count++;
        }
        $sent[$key] = date('Y-m-d H:i:s');
    }
}

json_write($storeFile, $sent);
echo "live reminders sent: $count\n";
