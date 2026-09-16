<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'enroll') {
            $email = trim((string)($_POST['email'] ?? ''));
            $course = course_find((string)($_POST['course_id'] ?? ''));
            if ($course === null) {
                lf_flash('danger', '请选择课程。');
            } else {
                try {
                    $student = student_find_or_create(['email' => $email, 'name' => (string)($_POST['name'] ?? ''), 'source' => 'manual']);
                    enroll_add((string)$course['id'], (string)$student['id'], ['source' => 'manual']);
                    lf_flash('ok', '已为 ' . $email . ' 开通《' . $course['title'] . '》。');
                } catch (Throwable $e) {
                    lf_flash('danger', $e->getMessage());
                }
            }
        } elseif ($action === 'unenroll') {
            enroll_remove((string)($_POST['course_id'] ?? ''), (string)($_POST['student_id'] ?? ''));
            lf_flash('ok', '已移除报名。');
        }
    }
    header('Location: /admin/students.php');
    exit;
}

$students = student_all();
$courses = course_all();

lf_admin_page_start(['title' => '学员 · LearnFlow 讲师后台', 'active' => 'students']);
?>
<div class="lf-admin-head"><h1>学员管理</h1><span class="lf-faint">共 <?= count($students) ?> 位学员</span></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h2 class="lf-sec-title" style="font-size:16px;margin:0 0 12px">手动开通</h2>
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="enroll">
    <div class="lf-field" style="margin:0"><label>学员邮箱</label><input class="lf-inp" type="email" name="email" required></div>
    <div class="lf-field" style="margin:0"><label>称呼（可选）</label><input class="lf-inp" name="name"></div>
    <div class="lf-field" style="margin:0"><label>课程</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">开通</button>
  </form>
</div>

<?php if (!$students): ?>
  <div class="lf-empty">还没有学员。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>学员</th><th>报名课程</th><th>学习时长</th><th>最近登录</th></tr></thead>
    <tbody>
      <?php foreach ($students as $sid => $s):
          $enrolled = enroll_by_student((string)$sid);
          $minutes = 0;
      ?>
        <tr>
          <td><b><?= lf_e((string)($s['name'] ?? '')) ?></b><br><span class="lf-faint"><?= lf_e((string)($s['email'] ?? '')) ?></span></td>
          <td>
            <?php if (!$enrolled): ?><span class="lf-faint">—</span><?php else: ?>
              <?php foreach ($enrolled as $cid => $row):
                  $course = course_find((string)$cid);
                  if ($course === null) continue;
                  $sum = progress_summary((string)$sid, (string)$cid, $course);
                  $minutes += $sum['minutes'];
              ?>
                <div class="lf-row" style="flex-wrap:nowrap;justify-content:space-between;gap:8px;font-size:13px">
                  <span><?= lf_e((string)$course['title']) ?> <span class="lf-faint">(<?= (int)$sum['percent'] ?>%)</span></span>
                  <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="unenroll"><input type="hidden" name="course_id" value="<?= lf_e((string)$cid) ?>"><input type="hidden" name="student_id" value="<?= lf_e((string)$sid) ?>"><button class="btn subtle sm" type="submit" style="height:26px">移除</button></form>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td><?= (int)$minutes ?> 分钟</td>
          <td class="lf-faint"><?= lf_e((string)($s['last_login_at'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
