<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'send') {
            $title = trim((string)($_POST['title'] ?? ''));
            $body = trim((string)($_POST['body'] ?? ''));
            $courseId = (string)($_POST['course_id'] ?? '');
            $withMail = !empty($_POST['with_mail']);
            if ($title === '') {
                lf_flash('danger', '请填写通知标题。');
            } else {
                $targets = $courseId !== '' ? array_keys(enroll_students($courseId)) : array_keys(student_all());
                $n = 0;
                foreach ($targets as $sid) {
                    $student = student_get((string)$sid);
                    if ($student === null) continue;
                    notify_add((string)$sid, 'system', $title, $body, '');
                    if ($withMail && !empty($student['email'])) {
                        lf_mail_send((string)$student['email'], $title, '<div style="font-size:14px;line-height:1.9">' . nl2br(lf_e($body)) . '</div>', (string)($student['name'] ?? ''));
                    }
                    $n++;
                }
                lf_flash('ok', '已发送给 ' . $n . ' 位学员' . ($withMail ? '（含邮件）' : '') . '。');
            }
        }
    }
    header('Location: ' . lf_url('/admin/notify.php'));
    exit;
}

$courses = course_all();
$recent = array_slice(array_reverse(json_read(LF_DATA_DIR . '/events.json')), 0, 15);

lf_admin_page_start(['title' => '通知 · LearnFlow 讲师后台', 'active' => 'notify']);
?>
<div class="lf-admin-head"><h1>群发通知</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <form method="post">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="send">
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>发送范围</label><select class="lf-inp" name="course_id"><option value="">全部学员</option><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?> 的学员</option><?php endforeach; ?></select></div>
      <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="with_mail"> 同时发邮件</label>
    </div>
    <div class="lf-field" style="margin-top:12px"><label>标题</label><input class="lf-inp" name="title" required></div>
    <div class="lf-field" style="margin-top:12px"><label>正文</label><textarea class="lf-inp lf-rich" name="body"></textarea></div>
    <button class="btn primary sm" type="submit" style="margin-top:12px">发送</button>
  </form>
</div>

<h2 class="lf-sec-title" style="font-size:17px;margin-bottom:12px">最近事件</h2>
<?php if (!$recent): ?>
  <div class="lf-empty">暂无事件。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>事件</th><th>对象</th></tr></thead>
    <tbody>
      <?php foreach ($recent as $e): $d = (array)($e['data'] ?? []); ?>
        <tr>
          <td class="lf-faint"><?= lf_e((string)($e['at'] ?? '')) ?></td>
          <td><span class="lf-chip soft"><?= lf_e((string)($e['event'] ?? '')) ?></span></td>
          <td class="lf-faint"><?= lf_e((string)($d['course_title'] ?? $d['title'] ?? $d['email'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
