<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$type = (string)($_GET['type'] ?? 'students');
$courseId = (string)($_GET['course'] ?? '');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="learnflow-' . preg_replace('/[^a-z_]/', '', $type) . '-' . date('Ymd') . '.csv"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

if ($type === 'students') {
    fputcsv($out, ['学员ID', '称呼', '邮箱', '手机', '注册来源', '注册时间', '最近登录']);
    foreach (student_all() as $id => $s) {
        fputcsv($out, [(string)$id, $s['name'] ?? '', $s['email'] ?? '', $s['phone'] ?? '', $s['source'] ?? '', $s['created_at'] ?? '', $s['last_login_at'] ?? '']);
    }
} elseif ($type === 'progress' && $courseId !== '') {
    $course = course_find($courseId);
    fputcsv($out, ['学员ID', '称呼', '邮箱', '分组', '完成课时', '总课时', '完成度%', '学习分钟', '最近活动']);
    foreach (enroll_students($courseId) as $sid => $row) {
        $student = student_get((string)$sid);
        $sum = $course !== null ? progress_summary((string)$sid, $courseId, $course) : ['done' => 0, 'total' => 0, 'percent' => 0, 'minutes' => 0];
        $last = progress_last_activity((string)$sid, $courseId);
        fputcsv($out, [(string)$sid, $student['name'] ?? '', $student['email'] ?? '', $row['group'] ?? '', $sum['done'], $sum['total'], $sum['percent'], $sum['minutes'], $last > 0 ? date('Y-m-d H:i', $last) : '从未']);
    }
} elseif ($type === 'at_risk' && $courseId !== '') {
    fputcsv($out, ['学员ID', '称呼', '邮箱', '完成度%', '最近活动']);
    foreach (progress_at_risk($courseId, (int)($_GET['days'] ?? 7), (int)($_GET['max'] ?? 50)) as $r) {
        fputcsv($out, [$r['student_id'], $r['name'], $r['email'], $r['percent'], $r['last_at']]);
    }
} elseif ($type === 'certificates') {
    fputcsv($out, ['证书编号', '学员', '课程', '颁发时间', '状态']);
    foreach (cert_all() as $no => $c) {
        fputcsv($out, [(string)$no, $c['name'] ?? '', $c['course_title'] ?? '', $c['issued_at'] ?? '', empty($c['revoked']) ? '有效' : '已撤销']);
    }
} else {
    fputcsv($out, ['无数据']);
}

fclose($out);
