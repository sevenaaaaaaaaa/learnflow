<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $course = course_find((string)($_POST['course_id'] ?? ''));
            if ($course === null) {
                lf_flash('danger', '请选择课程。');
            } else {
                assignment_save([
                    'course_id' => (string)$course['id'],
                    'title' => (string)($_POST['title'] ?? ''),
                    'description' => (string)($_POST['description'] ?? ''),
                    'due_at' => (string)($_POST['due_at'] ?? ''),
                    'lesson_id' => (string)($_POST['lesson_id'] ?? ''),
                    'allow_file' => !empty($_POST['allow_file']),
                ]);
                lf_flash('ok', '作业已创建。');
            }
        } elseif ($action === 'delete') {
            assignment_delete((string)($_POST['id'] ?? ''));
            lf_flash('ok', '作业已删除。');
        } elseif ($action === 'grade') {
            $grade = ($_POST['grade'] ?? '') === '' ? null : (float)$_POST['grade'];
            assignment_grade((string)($_POST['assignment_id'] ?? ''), (string)($_POST['student_id'] ?? ''), $grade, (string)($_POST['feedback'] ?? ''));
            lf_flash('ok', '点评已保存并通知学员。');
        }
    }
    $back = (string)($_POST['back'] ?? '/admin/assignments.php');
    header('Location: ' . lf_url($back));
    exit;
}

$courses = course_all();
$viewId = (string)($_GET['id'] ?? '');
$courseFilter = (string)($_GET['course'] ?? '');

lf_admin_page_start(['title' => '作业 · LearnFlow 讲师后台', 'active' => 'assignments']);
?>
<div class="lf-admin-head"><h1>作业</h1></div>

<?php if ($viewId !== '' && ($assignment = assignment_find($viewId)) !== null): ?>
  <?php
  $course = course_find((string)$assignment['course_id']);
  $subs = assignment_submissions($viewId);
  $students = student_all();
  $enrolled = enroll_students((string)$assignment['course_id']);
  ?>
  <div class="lf-admin-head">
    <h2 class="lf-sec-title" style="font-size:18px"><?= lf_e((string)$assignment['title']) ?> · 提交与点评</h2>
    <a class="btn subtle sm" href="<?= lf_url('/admin/assignments.php' . ($courseFilter !== '' ? '?course=' . urlencode($courseFilter) : '')) ?>">返回列表</a>
  </div>
  <p class="lf-faint">课程：<?= lf_e((string)($course['title'] ?? '')) ?> · 应交 <?= count($enrolled) ?> 人 · 已交 <?= count($subs) ?> 人</p>
  <?php if (!$subs): ?>
    <div class="lf-empty">还没有提交。</div>
  <?php else: ?>
    <table class="lf-table">
      <thead><tr><th>学员</th><th>提交内容</th><th>附件</th><th>评分/点评</th></tr></thead>
      <tbody>
        <?php foreach ($subs as $studentId => $s): $stu = $students[$studentId] ?? null; ?>
          <tr>
            <td><b><?= lf_e((string)($stu['name'] ?? $studentId)) ?></b><br><span class="lf-faint"><?= lf_e((string)($s['submitted_at'] ?? '')) ?></span></td>
            <td style="max-width:280px"><?= nl2br(lf_e((string)($s['content'] ?? ''))) ?></td>
            <td><?php foreach ((array)($s['files'] ?? []) as $f): ?><a href="<?= lf_e(lf_file_url((string)$f['rel'], (string)$studentId)) ?>"><?= lf_e((string)$f['name']) ?></a><br><?php endforeach; ?></td>
            <td>
              <form method="post">
                <?= lf_csrf_field() ?>
                <input type="hidden" name="action" value="grade">
                <input type="hidden" name="assignment_id" value="<?= lf_e($viewId) ?>">
                <input type="hidden" name="student_id" value="<?= lf_e((string)$studentId) ?>">
                <input type="hidden" name="back" value="/admin/assignments.php?id=<?= urlencode($viewId) ?>">
                <div class="lf-row" style="gap:6px">
                  <input class="lf-inp" name="grade" type="number" step="0.5" min="0" value="<?= lf_e((string)($s['grade'] ?? '')) ?>" placeholder="分数" style="height:34px;width:80px">
                  <input class="lf-inp" name="feedback" value="<?= lf_e((string)($s['feedback'] ?? '')) ?>" placeholder="点评" style="height:34px">
                  <button class="btn primary sm" type="submit" style="height:34px">保存</button>
                </div>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

<?php else: ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:22px">
    <form method="post">
      <?= lf_csrf_field() ?><input type="hidden" name="action" value="create">
      <div class="lf-row" style="align-items:flex-end">
        <div class="lf-field" style="margin:0"><label>课程</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
        <div class="lf-field" style="margin:0;flex:2"><label>作业标题</label><input class="lf-inp" name="title" required></div>
        <div class="lf-field" style="margin:0"><label>截止时间</label><input class="lf-inp" type="date" name="due_at"></div>
        <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="allow_file" checked> 允许附件</label>
      </div>
      <div class="lf-field" style="margin-top:10px"><label>说明</label><textarea class="lf-inp" name="description"></textarea></div>
      <button class="btn primary sm" type="submit" style="margin-top:10px">创建作业</button>
    </form>
  </div>

  <?php
  $all = assignment_all($courseFilter);
  $coursesById = [];
  foreach ($courses as $c) $coursesById[(string)$c['id']] = (string)$c['title'];
  ?>
  <?php if (!$all): ?>
    <div class="lf-empty">还没有作业。</div>
  <?php else: ?>
    <table class="lf-table">
      <thead><tr><th>作业</th><th>课程</th><th>截止</th><th>提交</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($all as $a): $subs = assignment_submissions((string)$a['id']); $graded = 0; foreach ($subs as $s) if (!empty($s['graded_at'])) $graded++; ?>
          <tr>
            <td><b><?= lf_e((string)$a['title']) ?></b></td>
            <td class="lf-faint"><?= lf_e($coursesById[(string)$a['course_id']] ?? '') ?></td>
            <td class="lf-faint"><?= lf_e((string)($a['due_at'] ?: '—')) ?></td>
            <td><?= count($subs) ?> / 待点评 <?= count($subs) - $graded ?></td>
            <td class="lf-row" style="flex-wrap:nowrap">
              <a class="btn subtle sm" href="<?= lf_url('/admin/assignments.php?id=' . urlencode((string)$a['id'])) ?>">批改</a>
              <form method="post" style="margin:0" onsubmit="return confirm('删除该作业？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= lf_e((string)$a['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
