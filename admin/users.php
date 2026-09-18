<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$me = lf_admin_current();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $u = trim((string)($_POST['username'] ?? ''));
            $p = (string)($_POST['password'] ?? '');
            if ($u === '' || strlen($p) < 6) {
                lf_flash('danger', '账号必填，密码至少 6 位。');
            } elseif (isset(lf_admins()[$u])) {
                lf_flash('danger', '账号已存在。');
            } else {
                lf_admin_create($u, $p, (string)($_POST['name'] ?? ''), (string)($_POST['role'] ?? 'editor'));
                lf_flash('ok', '已创建账号：' . $u);
            }
        } elseif ($action === 'role') {
            lf_admin_set_role((string)($_POST['username'] ?? ''), (string)($_POST['role'] ?? 'editor'));
            lf_flash('ok', '角色已更新。');
        } elseif ($action === 'password') {
            lf_admin_set_password((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
            lf_flash('ok', '密码已重置。');
        } elseif ($action === 'delete') {
            $u = (string)($_POST['username'] ?? '');
            if ($u === $me) {
                lf_flash('danger', '不能删除当前登录账号。');
            } else {
                lf_admin_delete($u);
                lf_flash('ok', '账号已删除。');
            }
        }
    }
    header('Location: ' . lf_url('/admin/users.php'));
    exit;
}

$admins = lf_admins();
$roles = ['admin' => '管理员（全部权限）', 'editor' => '编辑（内容/学员）', 'viewer' => '只读'];

lf_admin_page_start(['title' => '账号 · LearnFlow 讲师后台', 'active' => 'users']);
?>
<div class="lf-admin-head"><h1>讲师账号与权限</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h3 style="margin:0 0 12px;font-size:16px">新建账号</h3>
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="lf-field" style="margin:0"><label>账号</label><input class="lf-inp" name="username" required></div>
    <div class="lf-field" style="margin:0"><label>称呼</label><input class="lf-inp" name="name"></div>
    <div class="lf-field" style="margin:0"><label>密码</label><input class="lf-inp" type="password" name="password" required minlength="6"></div>
    <div class="lf-field" style="margin:0"><label>角色</label><select class="lf-inp" name="role"><?php foreach ($roles as $k => $v): ?><option value="<?= lf_e($k) ?>" <?= $k === 'editor' ? 'selected' : '' ?>><?= lf_e($v) ?></option><?php endforeach; ?></select></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">创建</button>
  </form>
</div>

<table class="lf-table">
  <thead><tr><th>账号</th><th>称呼</th><th>角色</th><th>创建时间</th><th>操作</th></tr></thead>
  <tbody>
    <?php foreach ($admins as $username => $a): ?>
      <tr>
        <td><b><?= lf_e((string)$username) ?></b><?= $username === $me ? ' <span class="lf-chip soft">我</span>' : '' ?></td>
        <td><?= lf_e((string)($a['name'] ?? '')) ?></td>
        <td>
          <form method="post" class="lf-row" style="margin:0;gap:6px;flex-wrap:nowrap">
            <?= lf_csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="username" value="<?= lf_e((string)$username) ?>">
            <select class="lf-inp" name="role" style="height:32px;width:150px"><?php foreach ($roles as $k => $v): ?><option value="<?= lf_e($k) ?>" <?= (($a['role'] ?? 'admin') === $k) ? 'selected' : '' ?>><?= lf_e($k) ?></option><?php endforeach; ?></select>
            <button class="btn subtle sm" type="submit" style="height:32px">保存</button>
          </form>
        </td>
        <td class="lf-faint"><?= lf_e((string)($a['created_at'] ?? '')) ?></td>
        <td>
          <form method="post" class="lf-row" style="margin:0;gap:6px;flex-wrap:nowrap">
            <?= lf_csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="username" value="<?= lf_e((string)$username) ?>">
            <input class="lf-inp" type="password" name="password" placeholder="新密码" minlength="6" style="height:32px;width:120px">
            <button class="btn subtle sm" type="submit" style="height:32px">重置</button>
          </form>
          <?php if ($username !== $me): ?>
            <form method="post" style="margin:6px 0 0" onsubmit="return confirm('删除该账号？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="username" value="<?= lf_e((string)$username) ?>"><button class="btn subtle sm" type="submit" style="height:28px;color:var(--danger)">删除</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php lf_admin_page_end(); ?>
