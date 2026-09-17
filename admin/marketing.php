<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'coupon_create') {
            $row = coupon_save([
                'code' => (string)($_POST['code'] ?? ''),
                'name' => (string)($_POST['name'] ?? ''),
                'type' => (string)($_POST['type'] ?? 'fixed'),
                'value' => (float)($_POST['value'] ?? 0),
                'min_amount' => (float)($_POST['min_amount'] ?? 0),
                'max_uses' => (int)($_POST['max_uses'] ?? 0),
                'expires_at' => (string)($_POST['expires_at'] ?? ''),
                'enabled' => true,
            ]);
            lf_flash('ok', '优惠券已保存：' . $row['code']);
        } elseif ($action === 'coupon_delete') {
            coupon_delete((string)($_POST['code'] ?? ''));
            lf_flash('ok', '优惠券已删除。');
        } elseif ($action === 'reward') {
            lf_setting_set('referral_reward', (float)($_POST['referral_reward'] ?? 0));
            lf_flash('ok', '推荐奖励已保存。');
        }
    }
    header('Location: ' . lf_url('/admin/marketing.php'));
    exit;
}

$coupons = coupon_all();
$referrals = referral_all();
usort($referrals, fn($a, $b) => ((int)($b['uses'] ?? 0)) <=> ((int)($a['uses'] ?? 0)));
$attributions = array_slice(attribution_all(), 0, 15);
$reward = (float)(lf_setting_get('referral_reward', 0) ?: 0);
$students = student_all();

lf_admin_page_start(['title' => '营销 · LearnFlow 讲师后台', 'active' => 'marketing']);
?>
<div class="lf-admin-head"><h1>营销：优惠券 / 推荐</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h3 style="margin:0 0 12px;font-size:16px">新建优惠券</h3>
  <form method="post">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="coupon_create">
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>券码（留空自动）</label><input class="lf-inp" name="code" placeholder="WELCOME10"></div>
      <div class="lf-field" style="margin:0"><label>名称</label><input class="lf-inp" name="name" placeholder="新学员立减"></div>
      <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" name="type"><option value="fixed">固定金额</option><option value="percent">折扣百分比</option></select></div>
      <div class="lf-field" style="margin:0"><label>面值（元 / %）</label><input class="lf-inp" type="number" step="0.01" min="0" name="value" value="10"></div>
    </div>
    <div class="lf-row" style="margin-top:10px;align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>最低消费</label><input class="lf-inp" type="number" step="0.01" min="0" name="min_amount" value="0"></div>
      <div class="lf-field" style="margin:0"><label>可用次数（0=不限）</label><input class="lf-inp" type="number" min="0" name="max_uses" value="0"></div>
      <div class="lf-field" style="margin:0"><label>过期时间</label><input class="lf-inp" type="date" name="expires_at"></div>
      <button class="btn primary sm" type="submit" style="flex:0 0 auto">创建</button>
    </div>
  </form>
  <p class="lf-faint" style="margin-top:10px">收款仍由 PayFlow 完成；LearnFlow 负责展示、核销与归因，并把券码透传给 PayFlow 结账链接。</p>
</div>

<?php if (!$coupons): ?>
  <div class="lf-empty">还没有优惠券。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>券码</th><th>优惠</th><th>门槛</th><th>已用</th><th>过期</th><th>状态</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($coupons as $c): ?>
        <tr>
          <td><b class="lf-cert-no"><?= lf_e((string)$c['code']) ?></b><br><span class="lf-faint"><?= lf_e((string)($c['name'] ?? '')) ?></span></td>
          <td><?= $c['type'] === 'percent' ? lf_e((string)$c['value']) . '%' : '¥' . number_format((float)$c['value'], 2) ?></td>
          <td class="lf-faint"><?= (float)$c['min_amount'] > 0 ? '满 ¥' . number_format((float)$c['min_amount'], 2) : '无' ?></td>
          <td><?= (int)$c['uses'] ?><?= (int)$c['max_uses'] > 0 ? ' / ' . (int)$c['max_uses'] : '' ?></td>
          <td class="lf-faint"><?= lf_e((string)($c['expires_at'] ?: '不限')) ?></td>
          <td><?= !empty($c['enabled']) ? '<span class="lf-chip ok">启用</span>' : '<span class="lf-chip danger">停用</span>' ?></td>
          <td><form method="post" style="margin:0" onsubmit="return confirm('删除该券？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="coupon_delete"><input type="hidden" name="code" value="<?= lf_e((string)$c['code']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2 class="lf-sec-title" style="font-size:17px;margin:26px 0 10px">推荐奖励</h2>
<form method="post" class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <?= lf_csrf_field() ?><input type="hidden" name="action" value="reward">
  <div class="lf-row" style="align-items:flex-end">
    <div class="lf-field" style="margin:0"><label>每成功推荐一位，给推荐人发代金券（元，0=关闭）</label><input class="lf-inp" type="number" step="0.01" min="0" name="referral_reward" value="<?= lf_e((string)$reward) ?>"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">保存</button>
  </div>
</form>

<h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">推荐码（<?= count($referrals) ?>）</h2>
<?php if (!$referrals): ?>
  <div class="lf-empty">还没有推荐码（学员在「我的学习」访问后自动生成）。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>推荐码</th><th>推荐人</th><th>成功推荐</th><th>创建时间</th><th>分享链接</th></tr></thead>
    <tbody>
      <?php foreach ($referrals as $r): $owner = $students[(string)($r['owner_student_id'] ?? '')] ?? null; ?>
        <tr>
          <td class="lf-cert-no"><?= lf_e((string)$r['code']) ?></td>
          <td><?= lf_e((string)($owner['name'] ?? $r['owner_name'] ?? '')) ?><br><span class="lf-faint"><?= lf_e((string)($owner['email'] ?? '')) ?></span></td>
          <td><?= (int)($r['uses'] ?? 0) ?></td>
          <td class="lf-faint"><?= lf_e((string)($r['created_at'] ?? '')) ?></td>
          <td><button class="btn subtle sm" type="button" data-lf-copy="<?= lf_e(lf_abs_url('/courses?ref=' . $r['code'])) ?>">复制</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php if ($attributions): ?>
  <h2 class="lf-sec-title" style="font-size:17px;margin:26px 0 10px">最近归因</h2>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>推荐码</th><th>推荐人</th><th>下单学员</th><th>金额</th></tr></thead>
    <tbody>
      <?php foreach ($attributions as $a): ?>
        <tr>
          <td class="lf-faint"><?= lf_e((string)($a['at'] ?? '')) ?></td>
          <td class="lf-cert-no"><?= lf_e((string)($a['code'] ?? '')) ?></td>
          <td class="lf-faint"><?= lf_e((string)($students[(string)($a['referrer_id'] ?? '')]['name'] ?? ($a['referrer_id'] ?? ''))) ?></td>
          <td class="lf-faint"><?= lf_e((string)($students[(string)($a['buyer_id'] ?? '')]['name'] ?? ($a['buyer_id'] ?? ''))) ?></td>
          <td>¥<?= number_format((float)($a['amount'] ?? 0), 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
