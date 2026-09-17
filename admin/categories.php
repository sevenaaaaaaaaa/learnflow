<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            try {
                $row = category_save((string)($_POST['name'] ?? ''), (string)($_POST['key'] ?? ''));
                lf_flash('ok', '分类已保存：' . $row['name']);
            } catch (Throwable $e) {
                lf_flash('danger', $e->getMessage());
            }
        } elseif ($action === 'delete') {
            category_delete((string)($_POST['key'] ?? ''));
            lf_flash('ok', '分类已删除。');
        }
    }
    header('Location: ' . lf_url('/admin/categories.php'));
    exit;
}

$categories = category_all();
$counts = [];
foreach (course_all() as $c) {
    foreach ((array)($c['categories'] ?? []) as $k) $counts[(string)$k] = ($counts[(string)$k] ?? 0) + 1;
}

lf_admin_page_start(['title' => '分类 · LearnFlow 讲师后台', 'active' => 'categories']);
?>
<div class="lf-admin-head"><h1>课程分类</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="lf-field" style="margin:0"><label>分类名称</label><input class="lf-inp" name="name" required></div>
    <div class="lf-field" style="margin:0"><label>标识（留空自动）</label><input class="lf-inp" name="key" placeholder="growth"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">保存分类</button>
  </form>
</div>

<?php if (!$categories): ?>
  <div class="lf-empty">还没有分类。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>名称</th><th>标识</th><th>课程数</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td><b><?= lf_e((string)$c['name']) ?></b></td>
          <td class="lf-faint"><?= lf_e((string)$c['key']) ?></td>
          <td><?= (int)($counts[(string)$c['key']] ?? 0) ?></td>
          <td><form method="post" style="margin:0" onsubmit="return confirm('删除该分类？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="key" value="<?= lf_e((string)$c['key']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
