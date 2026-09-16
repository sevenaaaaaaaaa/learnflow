<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $title = trim((string)($_POST['title'] ?? ''));
            if ($title === '') {
                lf_flash('danger', '请填写课程标题。');
            } else {
                $course = course_save(course_normalize(['title' => $title, 'status' => 'draft']));
                lf_flash('ok', '课程已创建，继续完善大纲。');
                header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$course['id'])));
                exit;
            }
        } elseif ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            course_delete($id);
            lf_flash('ok', '课程已删除。');
        } elseif ($action === 'toggle') {
            $course = course_find((string)($_POST['id'] ?? ''));
            if ($course !== null) {
                $course['status'] = ($course['status'] ?? 'draft') === 'published' ? 'draft' : 'published';
                course_save($course);
                lf_flash('ok', '状态已更新。');
            }
        }
    }
    header('Location: ' . lf_url('/admin/courses.php'));
    exit;
}

$courses = course_all();
lf_admin_page_start(['title' => '课程 · LearnFlow 讲师后台', 'active' => 'courses']);
?>
<div class="lf-admin-head"><h1>课程管理</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:24px">
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="lf-field" style="flex:1;margin:0"><label>新建课程标题</label><input class="lf-inp" name="title" placeholder="如：R.B.E 训练营 · 第 4 期" required></div>
    <button class="btn primary" type="submit" style="flex:0 0 auto">创建课程</button>
  </form>
</div>

<?php if (!$courses): ?>
  <div class="lf-empty">还没有课程。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>课程</th><th>结构</th><th>报名</th><th>价格</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($courses as $course): ?>
        <tr>
          <td><b><?= lf_e((string)$course['title']) ?></b><br><span class="lf-faint">/course/<?= lf_e((string)($course['slug'] ?? '')) ?></span></td>
          <td><?= count((array)$course['chapters']) ?> 章 · <?= course_lesson_count($course) ?> 课时</td>
          <td><?= enroll_count((string)$course['id']) ?></td>
          <td><?= lf_e(course_price_label($course)) ?></td>
          <td><span class="lf-chip <?= ($course['status'] ?? '') === 'published' ? 'ok' : 'soft' ?>"><?= ($course['status'] ?? 'draft') === 'published' ? '已上架' : '草稿' ?></span></td>
          <td class="lf-row" style="flex-wrap:nowrap">
            <a class="btn subtle sm" href="<?= lf_url('/admin/course-edit.php?id=') ?><?= urlencode((string)$course['id']) ?>">编辑</a>
            <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= lf_e((string)$course['id']) ?>"><button class="btn ghost sm" type="submit"><?= ($course['status'] ?? '') === 'published' ? '下架' : '上架' ?></button></form>
            <form method="post" style="margin:0" onsubmit="return confirm('确认删除该课程？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= lf_e((string)$course['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
