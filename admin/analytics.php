<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;
$ov = analytics_overview($days);
$ul = analytics_userloop_overview();
$maxDay = 0.0;
foreach ($ov['revenue_by_day'] as $v) $maxDay = max($maxDay, (float)$v);

lf_admin_page_start(['title' => '营收看板 · LearnFlow 讲师后台', 'active' => 'analytics']);
?>
<div class="lf-admin-head">
  <h1>营收与漏斗</h1>
  <div class="lf-tabs" style="margin:0">
    <?php foreach ([7, 30, 90] as $d): ?>
      <a class="lf-tab<?= $days === $d ? ' on' : '' ?>" href="<?= lf_url('/admin/analytics.php?days=' . $d) ?>">近 <?= $d ?> 天</a>
    <?php endforeach; ?>
  </div>
</div>

<div class="lf-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:20px">
  <div class="lf-stat"><b>¥<?= number_format((float)$ov['revenue'], 2) ?></b><span>营收</span></div>
  <div class="lf-stat"><b><?= (int)$ov['paid'] ?></b><span>付费订单</span></div>
  <div class="lf-stat"><b>¥<?= number_format((float)$ov['avg_order'], 2) ?></b><span>客单价</span></div>
  <div class="lf-stat"><b><?= (int)$ov['unique_payers'] ?></b><span>付费人数</span></div>
</div>

<div class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">转化漏斗（近 <?= $days ?> 天）</h2>
  <div style="display:grid;gap:10px">
    <?php
    $funnel = [
        ['新注册', (int)$ov['students'], ''],
        ['报名', (int)$ov['enrolled'], '注册→报名 ' . $ov['conv_enroll'] . '%'],
        ['付费', (int)$ov['paid'], '报名→付费 ' . $ov['conv_paid'] . '%'],
        ['完课', (int)$ov['completed'], '付费→完课 ' . $ov['conv_complete'] . '%'],
        ['发证', (int)$ov['certificates'], ''],
    ];
    $maxN = max(1, max(array_column($funnel, 1)));
    foreach ($funnel as $f): $pct = (int)round($f[1] / $maxN * 100); ?>
      <div>
        <div class="lf-row" style="justify-content:space-between"><span><?= lf_e($f[0]) ?> <b><?= $f[1] ?></b></span><span class="lf-faint"><?= lf_e($f[2]) ?></span></div>
        <div class="lf-progress" style="margin-top:4px"><div class="lf-progress-fill" style="width:<?= $pct ?>%"></div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="lf-grid" style="grid-template-columns:1fr 1fr;align-items:start">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:16px;margin:0 0 12px">每日营收</h2>
    <?php if (!$ov['revenue_by_day']): ?>
      <div class="lf-empty">该区间暂无营收。</div>
    <?php else: ?>
      <div style="display:flex;gap:4px;align-items:flex-end;height:120px;overflow-x:auto">
        <?php foreach ($ov['revenue_by_day'] as $day => $amount): ?>
          <div title="<?= lf_e($day) ?> ¥<?= number_format((float)$amount, 2) ?>" style="flex:0 0 auto;width:14px;display:flex;flex-direction:column;justify-content:flex-end;height:100%">
            <span style="height:<?= (int)round((float)$amount / max(0.01, $maxDay) * 110) ?>px;background:var(--accent);border-radius:3px"></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:16px;margin:0 0 12px">课程营收</h2>
    <?php if (!$ov['revenue_by_course']): ?>
      <div class="lf-empty">暂无数据。</div>
    <?php else: ?>
      <table class="lf-table">
        <thead><tr><th>课程</th><th>营收</th></tr></thead>
        <tbody>
          <?php foreach ($ov['revenue_by_course'] as $name => $amount): ?>
            <tr><td><?= lf_e((string)$name) ?></td><td>¥<?= number_format((float)$amount, 2) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="lf-grid" style="grid-template-columns:1fr 1fr;align-items:start;margin-top:20px">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:16px;margin:0 0 12px">报名来源</h2>
    <?php if (!$ov['sources']): ?><div class="lf-empty">暂无数据。</div><?php else: ?>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($ov['sources'] as $src => $n): ?><span class="lf-chip soft"><?= lf_e((string)$src) ?> · <?= (int)$n ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:16px;margin:0 0 12px">推荐与流量</h2>
    <p class="lf-muted" style="margin:0;font-size:13.5px">推荐带来订单 <b><?= (int)$ov['referrals']['total'] ?></b> 笔，金额 <b>¥<?= number_format((float)$ov['referrals']['amount'], 2) ?></b></p>
    <?php if ($ul !== null): ?>
      <p class="lf-faint" style="margin-top:8px">UserLoop 概览（API）：
        <?php foreach (['users', 'visitors', 'events'] as $k): if (isset($ul[$k])): ?>
          <span class="lf-chip soft"><?= lf_e($k) ?> <?= is_numeric($ul[$k]) ? (int)$ul[$k] : lf_e((string)$ul[$k]) ?></span>
        <?php endif; endforeach; ?>
      </p>
    <?php else: ?>
      <p class="lf-faint" style="margin-top:8px">访问/访客口径由 UserLoop（track.js）/WebsFlow 提供；接通后在此显示。</p>
    <?php endif; ?>
    <div class="lf-row" style="margin-top:12px;gap:8px">
      <a class="btn subtle sm" href="<?= lf_url('/admin/export.php?type=revenue&days=' . $days) ?>">导出营收 CSV</a>
      <a class="btn subtle sm" href="<?= lf_url('/admin/export.php?type=funnel&days=' . $days) ?>">导出漏斗 CSV</a>
    </div>
  </div>
</div>
<?php lf_admin_page_end(); ?>
