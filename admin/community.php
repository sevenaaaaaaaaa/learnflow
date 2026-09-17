<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } elseif (($_POST['action'] ?? '') === 'delete') {
        post_delete((string)($_POST['course_id'] ?? ''), (string)($_POST['post_id'] ?? ''));
        lf_flash('ok', '帖子已删除。');
    }
    header('Location: ' . lf_url('/admin/community.php?course=' . urlencode((string)($_POST['course_id'] ?? ''))));
    exit;
}

$courses = course_all();
$courseId = (string)($_GET['course'] ?? ($courses[0]['id'] ?? ''));
$posts = $courseId !== '' ? post_all($courseId) : [];

lf_admin_page_start(['title' => '圈子 · LearnFlow 讲师后台', 'active' => 'community']);
?>
<div class="lf-admin-head"><h1>训练营圈子</h1></div>

<form method="get" class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <div class="lf-field" style="margin:0"><label>选择课程</label><select class="lf-inp" name="course" onchange="this.form.submit()"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>" <?= $courseId === (string)$c['id'] ? 'selected' : '' ?>><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
</form>

<?php if (!$posts): ?>
  <div class="lf-empty">该课程圈子暂无内容。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>作者</th><th>类型</th><th>内容</th><th>互动</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
        <tr>
          <td><?= lf_e((string)$p['name']) ?><br><span class="lf-faint"><?= lf_e((string)$p['created_at']) ?></span></td>
          <td><span class="lf-chip soft"><?= ['question' => '提问', 'progress' => '晒进度', 'note' => '心得'][(string)$p['type']] ?? '心得' ?></span></td>
          <td style="max-width:360px"><?php if (!empty($p['title'])): ?><b><?= lf_e((string)$p['title']) ?></b><br><?php endif; ?><?= nl2br(lf_e(mb_substr((string)$p['body'], 0, 160))) ?></td>
          <td class="lf-faint">赞 <?= count((array)($p['likes'] ?? [])) ?> · 评 <?= count((array)($p['comments'] ?? [])) ?></td>
          <td><form method="post" style="margin:0" onsubmit="return confirm('删除该帖？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" value="<?= lf_e($courseId) ?>"><input type="hidden" name="post_id" value="<?= lf_e((string)$p['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
