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

if ($action === 'lesson_content') {
    $course = course_find((string)($_POST['course_id'] ?? ''));
    if ($course === null) lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    $lesson = course_lesson_find($course, (string)($_POST['lesson_id'] ?? ''));
    if ($lesson === null) lf_json_out(['ok' => false, 'error' => '课时不存在'], 404);
    $html = ai_lesson_content($course, $lesson, (string)($_POST['hint'] ?? ''));
    if ($html === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败'], 502);
    if (!empty($_POST['write'])) {
        foreach ((array)($course['chapters'] ?? []) as $ci => $ch) {
            foreach ((array)($ch['lessons'] ?? []) as $li => $l) {
                if ((string)($l['id'] ?? '') === (string)$lesson['id']) {
                    $course['chapters'][$ci]['lessons'][$li]['content'] = $html;
                }
            }
        }
        course_save(course_normalize($course));
    }
    lf_json_out(['ok' => true, 'html' => $html, 'written' => !empty($_POST['write'])]);
}

if ($action === 'marketing') {
    $type = (string)($_POST['type'] ?? 'page');
    $topic = trim((string)($_POST['topic'] ?? ''));
    if ($topic === '') lf_json_out(['ok' => false, 'error' => '请填写主题/课程'], 400);
    $text = ai_marketing($type, $topic, (string)($_POST['extra'] ?? ''));
    if ($text === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败'], 502);
    $draft = ai_draft_save($type, $topic, $text);
    lf_json_out(['ok' => true, 'content' => $text, 'draft_id' => $draft['id']]);
}

if ($action === 'from_material') {
    if (empty($_FILES['file'])) lf_json_out(['ok' => false, 'error' => '缺少资料文件'], 400);
    try {
        $saved = lf_upload_save($_FILES['file'], 'materials');
    } catch (Throwable $e) {
        lf_json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    $path = lf_file_path((string)$saved['rel']);
    $text = $path !== null ? ai_extract_text($path, (string)$saved['ext']) : '';
    if (strlen(trim($text)) < 30) {
        lf_json_out(['ok' => false, 'error' => '未能从资料中提取到足够文本（支持 txt/md/csv/html/docx/pptx）'], 422);
    }
    $outline = ai_outline_from_text($text, (string)($_POST['title'] ?? ''));
    if ($outline === null) lf_json_out(['ok' => false, 'error' => 'AI 生成大纲失败'], 502);
    $media = media_add($saved, ['scope' => 'materials']);
    lf_json_out(['ok' => true, 'title' => (string)($outline['title'] ?? ''), 'chapters' => $outline['chapters'], 'material_id' => $media['id'], 'chars' => mb_strlen($text)]);
}

if ($action === 'assistant') {
    $prompt = trim((string)($_POST['prompt'] ?? ''));
    if ($prompt === '') lf_json_out(['ok' => false, 'error' => '请输入内容'], 400);
    $text = ai_chat([
        ['role' => 'system', 'content' => '你是 LearnFlow 工作台助手，帮助知识付费创作者运营课程：选题、内容、招生、训练营运营、数据答疑。回答用中文、直接可执行、简洁。'],
        ['role' => 'user', 'content' => $prompt],
    ], 0.7, 1200);
    if ($text === null) lf_json_out(['ok' => false, 'error' => 'AI 生成失败'], 502);
    lf_json_out(['ok' => true, 'content' => $text]);
}

lf_json_out(['ok' => false, 'error' => '未知操作'], 400);
