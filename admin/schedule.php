<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $courseId = (string)($_POST['course_id'] ?? '');
        if ($action === 'save') {
            task_save($courseId, [
                'id' => (string)($_POST['id'] ?? ''),
                'day_index' => (int)($_POST['day_index'] ?? 1),
                'title' => (string)($_POST['title'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'lesson_id' => (string)($_POST['lesson_id'] ?? ''),
                'requires_checkin' => !empty($_POST['requires_checkin']),
            ]);
            lf_flash('ok', '任务已保存。');
        } elseif ($action === 'delete') {
            task_delete($courseId, (string)($_POST['task_id'] ?? ''));
            lf_flash('ok', '任务已删除。');
        }
    }
    header('Location: ' . lf_url('/admin/schedule.php?course=' . urlencode($courseId)));
    exit;
}

$courses = course_all();
$courseId = (string)($_GET['course'] ?? ($courses[0]['id'] ?? ''));
$course = $courseId !== '' ? course_find($courseId) : null;
$tasks = $course !== null ? task_all((string)$course['id']) : [];
$lessons = $course !== null ? course_lessons($course) : [];
$editId = (string)($_GET['edit'] ?? '');
$editTask = $editId !== '' ? task_find((string)$course['id'], $editId) : null;

lf_admin_page_start(['title' => '排期 · LearnFlow 讲师后台', 'active' => 'schedule']);
?>
<div class="lf-admin-head"><h1>开营节奏与每日任务</h1></div>

<form method="get" class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <div class="lf-row" style="align-items:flex-end">
    <div class="lf-field" style="margin:0"><label>选择训练营/课程</label><select class="lf-inp" name="course" onchange="this.form.submit()"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>" <?= $courseId === (string)$c['id'] ? 'selected' : '' ?>><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
    <?php if ($course !== null): ?>
      <div class="lf-field" style="margin:0"><label>开营日期</label><input class="lf-inp" value="<?= lf_e((string)($course['camp_start'] ?: '未设置（在课程编辑里设置）')) ?>" disabled></div>
      <div class="lf-field" style="margin:0"><label>结营日期</label><input class="lf-inp" value="<?= lf_e((string)($course['camp_end'] ?: '未设置')) ?>" disabled></div>
    <?php endif; ?>
  </div>
</form>

<?php if ($course === null): ?>
  <div class="lf-empty">还没有课程。</div>
<?php else: ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:22px">
    <h3 style="margin:0 0 12px;font-size:16px"><?= $editTask ? '编辑任务' : '新增任务' ?></h3>
    <form method="post">
      <?= lf_csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="course_id" value="<?= lf_e((string)$course['id']) ?>"><input type="hidden" name="id" value="<?= lf_e((string)($editTask['id'] ?? '')) ?>">
      <div class="lf-row" style="align-items:flex-end">
        <div class="lf-field" style="margin:0"><label>Day</label><input class="lf-inp" type="number" min="1" name="day_index" value="<?= (int)($editTask['day_index'] ?? 1) ?>"></div>
        <div class="lf-field" style="margin:0;flex:2"><label>任务标题</label><input class="lf-inp" name="title" value="<?= lf_e((string)($editTask['title'] ?? '')) ?>" required></div>
        <div class="lf-field" style="margin:0"><label>关联课时</label><select class="lf-inp" name="lesson_id"><option value="">— 无 —</option><?php foreach ($lessons as $l): ?><option value="<?= lf_e((string)$l['id']) ?>" <?= ($editTask['lesson_id'] ?? '') === (string)$l['id'] ? 'selected' : '' ?>><?= lf_e((string)$l['title']) ?></option><?php endforeach; ?></select></div>
        <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="requires_checkin" <?= !empty($editTask['requires_checkin']) ? 'checked' : '' ?>> 需打卡</label>
      </div>
      <div class="lf-field" style="margin-top:10px"><label>说明</label><textarea class="lf-inp" name="description"><?= lf_e((string)($editTask['description'] ?? '')) ?></textarea></div>
      <button class="btn primary sm" type="submit" style="margin-top:10px"><?= $editTask ? '保存修改' : '添加任务' ?></button>
      <?php if ($editTask): ?><a class="btn subtle sm" href="<?= lf_url('/admin/schedule.php?course=' . urlencode((string)$course['id'])) ?>">取消编辑</a><?php endif; ?>
    </form>
  </div>

  <?php if (!$tasks): ?>
    <div class="lf-empty">还没有排任务。</div>
  <?php else: ?>
    <table class="lf-table">
      <thead><tr><th>Day</th><th>任务</th><th>关联课时</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
          <tr>
            <td><span class="lf-chip">Day <?= (int)$t['day_index'] ?></span></td>
            <td><b><?= lf_e((string)$t['title']) ?></b><?php if (!empty($t['description'])): ?><br><span class="lf-faint"><?= lf_e(mb_substr((string)$t['description'], 0, 60)) ?></span><?php endif; ?></td>
            <td class="lf-faint"><?= lf_e((string)($t['lesson_id'] ?? '')) ?></td>
            <td class="lf-row" style="flex-wrap:nowrap">
              <a class="btn subtle sm" href="<?= lf_url('/admin/schedule.php?course=' . urlencode((string)$course['id']) . '&edit=' . urlencode((string)$t['id'])) ?>">编辑</a>
              <form method="post" style="margin:0" onsubmit="return confirm('删除该任务？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= lf_e((string)$course['id']) ?>"><input type="hidden" name="task_id" value="<?= lf_e((string)$t['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
