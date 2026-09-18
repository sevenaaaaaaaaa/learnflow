<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$student = lf_student_current();
if ($student === null) lf_json_out(['ok' => false, 'error' => '未登录'], 401);
if (!lf_csrf_check()) lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);

$sid = (string)$student['id'];
$courseId = (string)($_POST['course_id'] ?? '');
$lessonId = (string)($_POST['lesson_id'] ?? '');
if ($courseId === '' || $lessonId === '') lf_json_out(['ok' => false, 'error' => '缺少参数'], 422);

$action = (string)($_POST['action'] ?? 'list');
if ($action === 'add') {
    $text = trim((string)($_POST['text'] ?? ''));
    if ($text === '') lf_json_out(['ok' => false, 'error' => '请先选择文字'], 422);
    $row = highlight_add($sid, $courseId, $lessonId, $text, (string)($_POST['note'] ?? ''), (string)($_POST['color'] ?? 'yellow'));
    lf_json_out(['ok' => true, 'highlight' => $row]);
}
if ($action === 'delete') {
    highlight_delete($sid, $courseId, $lessonId, (string)($_POST['id'] ?? ''));
    lf_json_out(['ok' => true]);
}
lf_json_out(['ok' => true, 'highlights' => highlights_for($sid, $courseId, $lessonId)]);
