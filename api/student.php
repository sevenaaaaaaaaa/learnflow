<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LF_ROOT . '/lib/StudentToken.php';
header('Content-Type: application/json; charset=utf-8');

$student = student_from_request();
if ($student === null) lf_json_out(['ok' => false, 'error' => '未登录或令牌失效'], 401);
$sid = (string)$student['id'];
$input = array_merge($_GET, $_POST, lf_json_input());
$action = (string)($input['action'] ?? 'profile');

if ($action === 'profile') {
    $mem = membership_for_student($sid);
    lf_json_out(['ok' => true, 'student' => ['id' => $sid, 'name' => $student['name'] ?? '', 'email' => $student['email'] ?? '', 'phone' => $student['phone'] ?? ''], 'membership' => $mem ? ['tier' => $mem['tier']['name'] ?? '', 'active' => !empty($mem['active']), 'expires_at' => (int)($mem['expires_at'] ?? 0)] : null, 'points' => points_balance($sid), 'achievements' => (array)(points_of($sid)['achievements'] ?? [])]);
}

if ($action === 'courses') {
    $out = [];
    $enrolled = enroll_by_student($sid);
    foreach (course_all(true) as $c) {
        $item = ['id' => $c['id'], 'slug' => $c['slug'] ?? $c['id'], 'title' => $c['title'] ?? '', 'subtitle' => $c['subtitle'] ?? '', 'cover' => $c['cover'] ?? '', 'price' => (float)($c['price'] ?? 0), 'members_only' => !empty($c['members_only']), 'lessons' => course_lesson_count($c), 'enrolled' => isset($enrolled[(string)$c['id']])];
        if (isset($enrolled[(string)$c['id']])) $item['percent'] = progress_summary($sid, (string)$c['id'], $c)['percent'];
        $out[] = $item;
    }
    lf_json_out(['ok' => true, 'courses' => $out]);
}

if ($action === 'my') {
    $out = [];
    foreach (enroll_by_student($sid) as $courseId => $row) {
        $c = course_find((string)$courseId);
        if ($c === null) continue;
        $sum = progress_summary($sid, (string)$courseId, $c);
        $resume = progress_resume($sid, (string)$courseId, $c);
        $out[] = ['id' => $c['id'], 'slug' => $c['slug'] ?? $c['id'], 'title' => $c['title'] ?? '', 'cover' => $c['cover'] ?? '', 'done' => $sum['done'], 'total' => $sum['total'], 'percent' => $sum['percent'], 'resume_lesson' => $resume['lesson_id'] ?? ''];
    }
    lf_json_out(['ok' => true, 'courses' => $out]);
}

if ($action === 'course') {
    $c = course_find((string)($input['slug'] ?? ''));
    if ($c === null || ($c['status'] ?? 'draft') !== 'published') lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    $has = enroll_is_active((string)$c['id'], $sid);
    if (!$has) { lf_ensure_member_access($c, $student); $has = enroll_is_active((string)$c['id'], $sid); }
    $chapters = [];
    foreach ((array)$c['chapters'] as $ch) {
        $lessons = [];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $lessons[] = ['id' => $l['id'], 'title' => $l['title'] ?? '', 'type' => $l['type'] ?? 'article', 'duration' => (int)($l['duration'] ?? 0), 'free' => !empty($l['free']), 'locked' => !$has && empty($l['free']), 'done' => !empty(progress_lesson_state($sid, (string)$c['id'], (string)$l['id'])['done'])];
        }
        $chapters[] = ['title' => $ch['title'] ?? '', 'lessons' => $lessons];
    }
    lf_json_out(['ok' => true, 'course' => ['id' => $c['id'], 'slug' => $c['slug'] ?? $c['id'], 'title' => $c['title'] ?? '', 'subtitle' => $c['subtitle'] ?? '', 'summary' => $c['summary'] ?? '', 'price' => (float)($c['price'] ?? 0), 'members_only' => !empty($c['members_only']), 'has_access' => $has], 'chapters' => $chapters]);
}

if ($action === 'lesson') {
    $c = course_find((string)($input['slug'] ?? ''));
    if ($c === null) lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    $lesson = course_lesson_find($c, (string)($input['lesson'] ?? ''));
    if ($lesson === null) lf_json_out(['ok' => false, 'error' => '课时不存在'], 404);
    $has = enroll_is_active((string)$c['id'], $sid);
    if (!$has && !empty($lesson['free'])) { /* 试看 */ }
    elseif (!$has) lf_json_out(['ok' => false, 'error' => '未报名该课程'], 403);
    $video = media_resolve($lesson, (string)$c['id'], $sid);
    lf_json_out(['ok' => true, 'lesson' => ['id' => $lesson['id'], 'course_id' => $c['id'], 'title' => $lesson['title'] ?? '', 'type' => $lesson['type'] ?? 'article', 'content' => $lesson['content'] ?? '', 'video' => $video, 'duration' => (int)($lesson['duration'] ?? 0)], 'state' => progress_lesson_state($sid, (string)$c['id'], (string)$lesson['id'])]);
}

if ($action === 'progress') {
    $c = course_find((string)($input['course_id'] ?? ''));
    if ($c === null) lf_json_out(['ok' => false, 'error' => '课程不存在'], 404);
    $lessonId = (string)($input['lesson_id'] ?? '');
    if (course_lesson_find($c, $lessonId) === null) lf_json_out(['ok' => false, 'error' => '课时不存在'], 404);
    if (!enroll_is_active((string)$c['id'], $sid)) { lf_ensure_member_access($c, $student); }
    if (!enroll_is_active((string)$c['id'], $sid)) lf_json_out(['ok' => false, 'error' => '未报名该课程'], 403);
    if (!empty($input['done'])) {
        progress_done($sid, (string)$c['id'], $lessonId);
        $cert = cert_maybe_issue($sid, $c, (string)($student['name'] ?? ''));
        lf_json_out(['ok' => true, 'summary' => progress_summary($sid, (string)$c['id'], $c), 'certificate' => $cert ? $cert['cert_no'] : null]);
    }
    progress_heartbeat($sid, (string)$c['id'], $lessonId, (int)($input['position'] ?? 0), (int)($input['duration'] ?? 0), (int)($input['delta'] ?? 0));
    lf_json_out(['ok' => true, 'summary' => progress_summary($sid, (string)$c['id'], $c)]);
}

if ($action === 'notifications') {
    lf_json_out(['ok' => true, 'notifications' => notify_list($sid, 30), 'unread' => notify_unread_count($sid)]);
}

if ($action === 'membership') {
    $tiers = [];
    foreach (membership_tiers() as $t) $tiers[] = ['id' => $t['id'], 'name' => $t['name'], 'price' => (float)$t['price'], 'duration_days' => (int)$t['duration_days'], 'discount_percent' => (float)$t['discount_percent'], 'product' => $t['payflow_product_id'] ?? ''];
    lf_json_out(['ok' => true, 'tiers' => $tiers]);
}

lf_json_out(['ok' => false, 'error' => '未知操作'], 400);
