<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'tier_save') {
            $t = tier_save([
                'id' => (string)($_POST['id'] ?? ''),
                'name' => (string)($_POST['name'] ?? ''),
                'price' => (float)($_POST['price'] ?? 0),
                'duration_days' => (int)($_POST['duration_days'] ?? 365),
                'discount_percent' => (float)($_POST['discount_percent'] ?? 0),
                'members_only' => !empty($_POST['members_only']),
                'payflow_product_id' => (string)($_POST['payflow_product_id'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
            ]);
            lf_flash('ok', '会员等级已保存：' . $t['name']);
        } elseif ($action === 'tier_delete') {
            tier_delete((string)($_POST['id'] ?? ''));
            lf_flash('ok', '会员等级已删除。');
        } elseif ($action === 'grant') {
            try {
                $s = student_find_or_create(['email' => (string)($_POST['email'] ?? ''), 'name' => (string)($_POST['name'] ?? ''), 'source' => 'manual']);
                $days = (int)($_POST['days'] ?? 0);
                membership_grant((string)$s['id'], (string)($_POST['tier_id'] ?? ''), $days > 0 ? $days : null);
                lf_flash('ok', '已为该学员开通/续期会员。');
            } catch (Throwable $e) {
                lf_flash('danger', $e->getMessage());
            }
        } elseif ($action === 'revoke') {
            membership_revoke((string)($_POST['student_id'] ?? ''));
            lf_flash('ok', '已取消该学员会员。');
        }
    }
    header('Location: ' . lf_url('/admin/membership.php'));
    exit;
}

$tiers = membership_tiers();
$editId = (string)($_GET['edit'] ?? '');
$edit = $editId !== '' ? tier_find($editId) : null;
$members = [];
foreach (student_all() as $id => $s) {
    $m = membership_for_student((string)$id);
    if ($m !== null && !empty($m['active'])) $members[] = ['student_id' => (string)$id, 'name' => $s['name'] ?? '', 'email' => $s['email'] ?? '', 'tier' => $m['tier']['name'] ?? '', 'expires' => (int)$m['expires_at']];
}
usort($members, fn($a, $b) => $b['expires'] <=> $a['expires']);

lf_admin_page_start(['title' => '会员 · LearnFlow 讲师后台', 'active' => 'membership']);
?>
<div class="lf-admin-head"><h1>会员与积分</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h3 style="margin:0 0 12px;font-size:16px"><?= $edit ? '编辑会员等级' : '新建会员等级' ?></h3>
  <form method="post">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="tier_save"><input type="hidden" name="id" value="<?= lf_e((string)($edit['id'] ?? '')) ?>">
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>名称</label><input class="lf-inp" name="name" value="<?= lf_e((string)($edit['name'] ?? '')) ?>" required></div>
      <div class="lf-field" style="margin:0"><label>价格（元）</label><input class="lf-inp" type="number" step="0.01" min="0" name="price" value="<?= lf_e((string)($edit['price'] ?? 0)) ?>"></div>
      <div class="lf-field" style="margin:0"><label>时长（天）</label><input class="lf-inp" type="number" min="1" name="duration_days" value="<?= (int)($edit['duration_days'] ?? 365) ?>"></div>
      <div class="lf-field" style="margin:0"><label>课程折扣 %</label><input class="lf-inp" type="number" min="0" max="100" name="discount_percent" value="<?= lf_e((string)($edit['discount_percent'] ?? 0)) ?>"></div>
    </div>
    <div class="lf-row" style="margin-top:10px;align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>PayFlow 商品 ID</label><input class="lf-inp" name="payflow_product_id" value="<?= lf_e((string)($edit['payflow_product_id'] ?? '')) ?>"></div>
      <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="members_only" <?= !isset($edit['members_only']) || !empty($edit['members_only']) ? 'checked' : '' ?>> 含专享课程</label>
      <button class="btn primary sm" type="submit" style="flex:0 0 auto"><?= $edit ? '保存' : '创建' ?></button>
      <?php if ($edit): ?><a class="btn subtle sm" href="<?= lf_url('/admin/membership.php') ?>">取消</a><?php endif; ?>
    </div>
    <div class="lf-field" style="margin-top:10px"><label>说明</label><input class="lf-inp" name="description" value="<?= lf_e((string)($edit['description'] ?? '')) ?>"></div>
  </form>
</div>

<?php if ($tiers): ?>
  <table class="lf-table" style="margin-bottom:22px">
    <thead><tr><th>等级</th><th>价格</th><th>时长</th><th>折扣</th><th>PayFlow</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tiers as $t): ?>
        <tr>
          <td><b><?= lf_e((string)$t['name']) ?></b><?php if (!empty($t['members_only'])): ?> <span class="lf-chip ok">专享</span><?php endif; ?></td>
          <td>¥<?= number_format((float)$t['price'], 2) ?></td>
          <td><?= (int)$t['duration_days'] ?> 天</td>
          <td><?= lf_e((string)$t['discount_percent']) ?>%</td>
          <td class="lf-faint"><?= lf_e((string)($t['payflow_product_id'] ?: '—')) ?></td>
          <td class="lf-row" style="flex-wrap:nowrap">
            <a class="btn subtle sm" href="<?= lf_url('/admin/membership.php?edit=' . urlencode((string)$t['id'])) ?>">编辑</a>
            <form method="post" style="margin:0" onsubmit="return confirm('删除该等级？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="tier_delete"><input type="hidden" name="id" value="<?= lf_e((string)$t['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h3 style="margin:0 0 12px;font-size:16px">手动开通会员</h3>
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="grant">
    <div class="lf-field" style="margin:0"><label>学员邮箱</label><input class="lf-inp" type="email" name="email" required></div>
    <div class="lf-field" style="margin:0"><label>称呼（可选）</label><input class="lf-inp" name="name"></div>
    <div class="lf-field" style="margin:0"><label>等级</label><select class="lf-inp" name="tier_id"><?php foreach ($tiers as $t): ?><option value="<?= lf_e((string)$t['id']) ?>"><?= lf_e((string)$t['name']) ?></option><?php endforeach; ?></select></div>
    <div class="lf-field" style="margin:0"><label>天数（0=按等级）</label><input class="lf-inp" type="number" min="0" name="days" value="0"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">开通</button>
  </form>
</div>

<h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">当前会员（<?= count($members) ?>）</h2>
<?php if (!$members): ?>
  <div class="lf-empty">暂无有效会员。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>学员</th><th>等级</th><th>到期</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($members as $m): ?>
        <tr>
          <td><?= lf_e($m['name']) ?><br><span class="lf-faint"><?= lf_e($m['email']) ?></span></td>
          <td><?= lf_e($m['tier']) ?></td>
          <td class="lf-faint"><?= lf_e(date('Y-m-d', $m['expires'])) ?></td>
          <td><form method="post" style="margin:0" onsubmit="return confirm('取消该会员？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="student_id" value="<?= lf_e($m['student_id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">取消</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php $lb = points_leaderboard(10); if ($lb): ?>
  <h2 class="lf-sec-title" style="font-size:17px;margin:26px 0 10px">积分榜</h2>
  <table class="lf-table">
    <thead><tr><th>#</th><th>学员</th><th>积分</th></tr></thead>
    <tbody>
      <?php foreach ($lb as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= lf_e((string)$r['name']) ?></td><td><?= (int)$r['points'] ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
