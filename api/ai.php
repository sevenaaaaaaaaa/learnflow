<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (lf_admin_current() === null) {
    lf_json_out(['ok' => false, 'error' => '未登录'], 401);
}
if (!lf_csrf_check()) {
    lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);
}
if (!ai_enabled()) {
    lf_json_out(['ok' => false, 'error' => 'AI 未启用（后台 → 设置 → AI 填写 Key 并启用）'], 400);
}
if (!ai_budget_ok()) {
    lf_json_out(['ok' => false, 'error' => '今日 AI 调用已达上限（预算保险丝）'], 429);
}

$action = (string)($_POST['action'] ?? '');

if ($action === 'grade') {
    $assignment = assignment_find((string)($_POST['assignment_id'] ?? ''));
    $studentId = (string)($_POST['student_id'] ?? '');
    if ($assignment === null) lf_json_out(['ok' => false, 'error' => '作业不存在'], 404);
    $sub = assignment_submission((string)$assignment['id'], $studentId);
    if ($sub === null) lf_json_out(['ok' => false, 'error' => '该学员尚未提交'], 404);
    $res = ai_grade_assignment($assignment, (string)($sub['content'] ?? ''));
    if ($res === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败，请稍后重试'], 502);
    lf_json_out(['ok' => true, 'grade' => $res['grade'], 'feedback' => $res['feedback']]);
}

if ($action === 'generate_quiz') {
    $topic = trim((string)($_POST['topic'] ?? ''));
    if ($topic === '') lf_json_out(['ok' => false, 'error' => '请填写主题'], 400);
    $count = max(1, min(10, (int)($_POST['count'] ?? 3)));
    $data = ai_generate_quiz($topic, $count, (string)($_POST['difficulty'] ?? '中等'));
    if ($data === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败，请稍后重试'], 502);
    lf_json_out(['ok' => true, 'questions' => $data['questions']]);
}

if ($action === 'weekly_report') {
    $course = course_find((string)($_POST['course_id'] ?? ''));
    if ($course === null) lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    $learners = 0; $completed = 0;
    foreach (enroll_students((string)$course['id']) as $sid => $row) {
        $learners++;
        $sum = progress_summary((string)$sid, (string)$course['id'], $course);
        if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
    }
    $curve = progress_curve((string)$course['id']);
    $stats = [
        'learners' => $learners,
        'rate' => $learners > 0 ? round($completed / $learners * 100) : 0,
        'active_7d' => $curve['active_7d'],
        'at_risk' => count(progress_at_risk((string)$course['id'])),
        'checkin_today' => checkin_course_stats((string)$course['id'])['today'],
    ];
    $text = ai_weekly_report($course, $stats);
    if ($text === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败，请稍后重试'], 502);
    lf_json_out(['ok' => true, 'report' => $text, 'stats' => $stats]);
}

if ($action === 'outline') {
    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') lf_json_out(['ok' => false, 'error' => '请填写课程标题'], 400);
    $data = ai_course_outline($title, (string)($_POST['summary'] ?? ''), (string)($_POST['requirements'] ?? ''));
    if ($data === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败，请稍后重试'], 502);
    lf_json_out(['ok' => true, 'chapters' => $data['chapters']]);
}

lf_json_out(['ok' => false, 'error' => '未知操作'], 400);
