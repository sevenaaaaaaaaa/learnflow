<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lf_json_out(['ok' => false, 'error' => '仅支持 POST'], 405);
}

header('Content-Type: application/json; charset=utf-8');
$student = lf_student_current();
if ($student === null) {
    lf_json_out(['ok' => false, 'error' => '未登录'], 401);
}

$input = lf_json_input();
$courseId = (string)($input['course_id'] ?? '');
$lessonId = (string)($input['lesson_id'] ?? '');
$course = course_find($courseId);
if ($course === null) lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
if (course_lesson_find($course, $lessonId) === null) lf_json_out(['ok' => false, 'error' => '课时不存在'], 404);

$studentId = (string)$student['id'];
if (!enroll_is_active((string)$course['id'], $studentId)) {
    lf_json_out(['ok' => false, 'error' => '未报名该课程'], 403);
}

$position = (int)($input['position'] ?? 0);
$duration = (int)($input['duration'] ?? 0);
$delta = (int)($input['delta'] ?? 0);

if (!empty($input['done'])) {
    progress_done($studentId, (string)$course['id'], $lessonId);
    $cert = cert_maybe_issue($studentId, $course, (string)($student['name'] ?? ''));
    lf_json_out(['ok' => true, 'state' => progress_lesson_state($studentId, (string)$course['id'], $lessonId), 'certificate' => $cert ? $cert['cert_no'] : null]);
}

$state = progress_heartbeat($studentId, (string)$course['id'], $lessonId, $position, $duration, $delta);
$summary = progress_summary($studentId, (string)$course['id'], $course);
$cert = cert_maybe_issue($studentId, $course, (string)($student['name'] ?? ''));
lf_json_out([
    'ok' => true,
    'state' => progress_lesson_state($studentId, (string)$course['id'], $lessonId),
    'summary' => $summary,
    'certificate' => $cert ? $cert['cert_no'] : null,
]);
