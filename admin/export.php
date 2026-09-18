<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$type = (string)($_GET['type'] ?? 'students');
$courseId = (string)($_GET['course'] ?? '');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="learnflow-' . preg_replace('/[^a-z_]/', '', $type) . '-' . date('Ymd') . '.csv"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

if (!function_exists('lf_csv')) {
    function lf_csv($out, array $row): void
    {
        fputcsv($out, $row, ',', '"', '');
    }
}

if ($type === 'students') {
    lf_csv($out, ['学员ID', '称呼', '邮箱', '手机', '注册来源', '注册时间', '最近登录']);
    foreach (student_all() as $id => $s) {
        lf_csv($out, [(string)$id, $s['name'] ?? '', $s['email'] ?? '', $s['phone'] ?? '', $s['source'] ?? '', $s['created_at'] ?? '', $s['last_login_at'] ?? '']);
    }
} elseif ($type === 'progress' && $courseId !== '') {
    $course = course_find($courseId);
    lf_csv($out, ['学员ID', '称呼', '邮箱', '分组', '完成课时', '总课时', '完成度%', '学习分钟', '最近活动']);
    foreach (enroll_students($courseId) as $sid => $row) {
        $student = student_get((string)$sid);
        $sum = $course !== null ? progress_summary((string)$sid, $courseId, $course) : ['done' => 0, 'total' => 0, 'percent' => 0, 'minutes' => 0];
        $last = progress_last_activity((string)$sid, $courseId);
        lf_csv($out, [(string)$sid, $student['name'] ?? '', $student['email'] ?? '', $row['group'] ?? '', $sum['done'], $sum['total'], $sum['percent'], $sum['minutes'], $last > 0 ? date('Y-m-d H:i', $last) : '从未']);
    }
} elseif ($type === 'at_risk' && $courseId !== '') {
    lf_csv($out, ['学员ID', '称呼', '邮箱', '完成度%', '最近活动']);
    foreach (progress_at_risk($courseId, (int)($_GET['days'] ?? 7), (int)($_GET['max'] ?? 50)) as $r) {
        lf_csv($out, [$r['student_id'], $r['name'], $r['email'], $r['percent'], $r['last_at']]);
    }
} elseif ($type === 'revenue') {
    $ov = analytics_overview((int)($_GET['days'] ?? 30));
    lf_csv($out, ['日期', '营收']);
    foreach ($ov['revenue_by_day'] as $day => $amount) lf_csv($out, [(string)$day, number_format((float)$amount, 2, '.', '')]);
    lf_csv($out, ['合计', number_format((float)$ov['revenue'], 2, '.', '')]);
} elseif ($type === 'funnel') {
    $ov = analytics_overview((int)($_GET['days'] ?? 30));
    lf_csv($out, ['阶段', '数量']);
    lf_csv($out, ['新注册', $ov['students']]);
    lf_csv($out, ['报名', $ov['enrolled']]);
    lf_csv($out, ['付费', $ov['paid']]);
    lf_csv($out, ['完课', $ov['completed']]);
    lf_csv($out, ['发证', $ov['certificates']]);
    lf_csv($out, ['营收', number_format((float)$ov['revenue'], 2, '.', '')]);
    lf_csv($out, ['注册→报名%', $ov['conv_enroll']]);
    lf_csv($out, ['报名→付费%', $ov['conv_paid']]);
    lf_csv($out, ['付费→完课%', $ov['conv_complete']]);
} elseif ($type === 'certificates') {
    lf_csv($out, ['证书编号', '学员', '课程', '颁发时间', '状态']);
    foreach (cert_all() as $no => $c) {
        lf_csv($out, [(string)$no, $c['name'] ?? '', $c['course_title'] ?? '', $c['issued_at'] ?? '', empty($c['revoked']) ? '有效' : '已撤销']);
    }
} else {
    lf_csv($out, ['无数据']);
}

fclose($out);
