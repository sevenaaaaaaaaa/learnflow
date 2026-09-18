<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$student = lf_student_current();
if ($student === null) lf_json_out(['ok' => false, 'error' => '未登录'], 401);
if (!lf_csrf_check()) lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);

$courseId = (string)($_POST['course_id'] ?? '');
$lessonId = (string)($_POST['lesson_id'] ?? '');
if ($courseId === '' || $lessonId === '') lf_json_out(['ok' => false, 'error' => '缺少参数'], 422);

$sid = (string)$student['id'];
$course = course_find($courseId);
if ($course === null || course_lesson_find($course, $lessonId) === null) lf_json_out(['ok' => false, 'error' => '课程或课时不存在'], 404);

if (isset($_POST['content'])) {
    $row = note_save($sid, $courseId, $lessonId, (string)$_POST['content']);
    lf_json_out(['ok' => true, 'saved_at' => $row['updated_at']]);
}
lf_json_out(['ok' => true, 'note' => note_get($sid, $courseId, $lessonId)]);
