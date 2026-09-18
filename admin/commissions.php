<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'config') {
            lf_setting_set('commission', [
                'enabled' => !empty($_POST['enabled']),
                'level1' => (float)($_POST['level1'] ?? 10),
                'level2' => (float)($_POST['level2'] ?? 5),
            ]);
            lf_flash('ok', '佣金比例已保存。');
        } elseif ($action === 'settle') {
            commission_mark_settled((string)($_POST['id'] ?? ''));
            lf_flash('ok', '已标记为已结算。');
        }
    }
    header('Location: ' . lf_url('/admin/commissions.php'));
    exit;
}

$cfg = commission_config();
$rows = array_reverse(commission_all());
$students = student_all();
$sum = commission_summary();

lf_admin_page_start(['title' => '分销佣金 · LearnFlow 讲师后台', 'active' => 'commissions']);
?>
<div class="lf-admin-head"><h1>分销佣金</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <p class="lf-faint" style="margin:0 0 10px">LearnFlow 只做**佣金记录与归因**，不涉及资金结算/提现（结算与打款由 PayFlow/财务完成）。多级按推荐关系自动计提。</p>
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="config">
    <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="enabled" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>> 启用分销佣金</label>
    <div class="lf-field" style="margin:0"><label>一级佣金 %</label><input class="lf-inp" type="number" step="0.1" min="0" name="level1" value="<?= lf_e((string)$cfg['level1']) ?>"></div>
    <div class="lf-field" style="margin:0"><label>二级佣金 %</label><input class="lf-inp" type="number" step="0.1" min="0" name="level2" value="<?= lf_e((string)$cfg['level2']) ?>"></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">保存</button>
  </form>
</div>

<div class="lf-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:20px">
  <div class="lf-stat"><b>¥<?= number_format((float)$sum['pending'], 2) ?></b><span>待结算</span></div>
  <div class="lf-stat"><b>¥<?= number_format((float)$sum['settled'], 2) ?></b><span>已结算</span></div>
  <div class="lf-stat"><b><?= (int)$sum['count'] ?></b><span>佣金记录</span></div>
</div>

<div class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <h3 style="margin:0 0 10px;font-size:16px">按推荐人汇总（待结算）</h3>
  <?php if (!$sum['by_referrer']): ?><div class="lf-empty">暂无佣金。</div><?php else: ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php foreach ($sum['by_referrer'] as $rid => $amt): $s = $students[(string)$rid] ?? null; ?>
        <span class="lf-chip soft"><?= lf_e((string)($s['name'] ?? $rid)) ?> · ¥<?= number_format((float)$amt, 2) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($rows): ?>
  <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">佣金明细</h2>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>订单</th><th>金额</th><th>推荐人</th><th>级别</th><th>佣金</th><th>状态</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): $s = $students[(string)($r['referrer_id'] ?? '')] ?? null; ?>
        <tr>
          <td class="lf-faint"><?= lf_e((string)($r['at'] ?? '')) ?></td>
          <td class="lf-faint"><?= lf_e((string)($r['order_id'] ?? '')) ?></td>
          <td>¥<?= number_format((float)($r['amount'] ?? 0), 2) ?></td>
          <td><?= lf_e((string)($s['name'] ?? ($r['referrer_id'] ?? ''))) ?></td>
          <td><span class="lf-chip soft">L<?= (int)($r['level'] ?? 1) ?> · <?= lf_e((string)($r['rate'] ?? 0)) ?>%</span></td>
          <td>¥<?= number_format((float)($r['commission'] ?? 0), 2) ?></td>
          <td><?= ($r['status'] ?? 'pending') === 'settled' ? '<span class="lf-chip ok">已结算</span>' : '<span class="lf-chip">待结算</span>' ?></td>
          <td><?php if (($r['status'] ?? 'pending') !== 'settled'): ?><form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="settle"><input type="hidden" name="id" value="<?= lf_e((string)($r['id'] ?? '')) ?>"><button class="btn subtle sm" type="submit">标记结算</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
