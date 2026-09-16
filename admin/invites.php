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
                $row = invite_create((string)$course['id'], [
                    'code' => (string)($_POST['code'] ?? ''),
                    'max_uses' => (int)($_POST['max_uses'] ?? 0),
                    'expires_at' => (string)($_POST['expires_at'] ?? ''),
                    'note' => (string)($_POST['note'] ?? ''),
                ]);
                lf_flash('ok', '邀请码已生成：' . $row['code']);
            }
        } elseif ($action === 'delete') {
            invite_delete((string)($_POST['code'] ?? ''));
            lf_flash('ok', '邀请码已删除。');
        }
    }
    header('Location: ' . lf_url('/admin/invites.php'));
    exit;
}

$courses = course_all();
$courseNames = [];
foreach ($courses as $c) $courseNames[(string)$c['id']] = (string)$c['title'];
$invites = invite_all();
krsort($invites);

lf_admin_page_start(['title' => '邀请码 · LearnFlow 讲师后台', 'active' => 'invites']);
?>
<div class="lf-admin-head"><h1>邀请码</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="lf-field" style="margin:0"><label>课程</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
    <div class="lf-field" style="margin:0"><label>邀请码（留空自动）</label><input class="lf-inp" name="code" placeholder="RBECAMP4"></div>
    <div class="lf-field" style="margin:0"><label>可用次数（0=不限）</label><input class="lf-inp" type="number" min="0" name="max_uses" value="0"></div>
    <div class="lf-field" style="margin:0"><label>过期时间（可空）</label><input class="lf-inp" type="date" name="expires_at"></div>
    <div class="lf-field" style="margin:0"><label>备注</label><input class="lf-inp" name="note"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">生成</button>
  </form>
</div>

<?php if (!$invites): ?>
  <div class="lf-empty">还没有邀请码。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>邀请码</th><th>课程</th><th>使用</th><th>过期</th><th>备注</th><th>链接</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($invites as $code => $inv): ?>
        <tr>
          <td><b class="lf-cert-no"><?= lf_e((string)$code) ?></b></td>
          <td><?= lf_e((string)($courseNames[(string)($inv['course_id'] ?? '')] ?? ($inv['course_id'] ?? ''))) ?></td>
          <td><?= (int)($inv['uses'] ?? 0) ?><?= (int)($inv['max_uses'] ?? 0) > 0 ? ' / ' . (int)$inv['max_uses'] : '' ?></td>
          <td class="lf-faint"><?= lf_e((string)($inv['expires_at'] ?? '不限')) ?></td>
          <td class="lf-faint"><?= lf_e((string)($inv['note'] ?? '')) ?></td>
          <td><button class="btn subtle sm" type="button" data-lf-copy="<?= lf_e(lf_abs_url('/join?code=' . $code)) ?>">复制链接</button></td>
          <td><form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="code" value="<?= lf_e((string)$code) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
