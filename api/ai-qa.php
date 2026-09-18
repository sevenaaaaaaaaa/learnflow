<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$student = lf_student_current();
if ($student === null) {
    lf_json_out(['ok' => false, 'error' => '未登录'], 401);
}
if (!lf_csrf_check()) {
    lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);
}
if (!ai_enabled()) {
    lf_json_out(['ok' => false, 'error' => 'AI 未启用'], 400);
}
if (!ai_budget_ok()) {
    lf_json_out(['ok' => false, 'error' => '今日 AI 用量已达上限'], 429);
}
$sid = (string)$student['id'];
$rl = lf_throttle('aiqa:' . $sid, 20, 3600);
if (empty($rl['allowed'])) {
    lf_json_out(['ok' => false, 'error' => '提问过于频繁，请稍后再试'], 429);
}

$course = course_find((string)($_POST['course_id'] ?? ''));
if ($course === null) {
    lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
}
if (!enroll_is_active((string)$course['id'], $sid)) {
    lf_ensure_member_access($course, $student);
}
if (!enroll_is_active((string)$course['id'], $sid)) {
    lf_json_out(['ok' => false, 'error' => '请先报名该课程'], 403);
}

$question = trim((string)($_POST['question'] ?? ''));
try {
    $res = ai_course_ask($course, $question);
    ai_qa_record((string)$course['id'], $sid, $question, (string)$res['answer'], (array)$res['sources']);
    lf_json_out(['ok' => true, 'answer' => $res['answer'], 'sources' => $res['sources']]);
} catch (Throwable $e) {
    lf_json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
