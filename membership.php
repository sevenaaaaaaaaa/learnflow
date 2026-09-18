<?php
require_once __DIR__ . '/includes/bootstrap.php';

$tiers = membership_tiers();
$student = lf_student_current();
$current = $student ? membership_for_student((string)$student['id']) : null;

if (isset($_GET['joined'])) lf_flash('ok', '支付完成后会员将自动开通，请稍后刷新查看。');

lf_page_start([
    'title' => lf_t('会员', 'Membership') . ' · LearnFlow',
    'description' => '开通会员，畅学会员专享课程与专属折扣。',
    'active' => 'membership',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:40px">
  <span class="lf-kicker">Membership</span>
  <h1 class="lf-sec-title" style="font-size:clamp(28px,4vw,40px);margin-top:10px"><?= lf_t('会员', 'Membership') ?></h1>
  <p class="lf-muted" style="max-width:620px"><?= lf_t('会员可学习「会员专享」课程，并享受专属折扣。订阅收款由 PayFlow 完成。', 'Members get access to members-only courses and exclusive discounts. Payment via PayFlow.') ?></p>

  <?php if ($current !== null): ?>
    <div class="lf-flash <?= !empty($current['active']) ? 'ok' : 'warn' ?>" style="margin-top:18px;max-width:620px">
      当前会员：<b><?= lf_e((string)($current['tier']['name'] ?? '会员')) ?></b>
      <?= !empty($current['active']) ? '· 有效期至 ' . lf_e(date('Y-m-d', (int)$current['expires_at'])) : '· 已过期' ?>
    </div>
  <?php endif; ?>

  <?php if (!$tiers): ?>
    <div class="lf-empty" style="margin-top:24px">讲师还没有配置会员等级。</div>
  <?php else: ?>
    <div class="lf-grid" style="margin-top:24px">
      <?php foreach ($tiers as $t):
          $isCurrent = $current !== null && ($current['tier_id'] ?? '') === $t['id'] && !empty($current['active']);
          $checkout = (!empty($t['payflow_product_id']) && $student !== null) ? payflow_product_checkout((string)$t['payflow_product_id'], (string)($student['email'] ?? '')) : '';
      ?>
        <article class="lf-course-card">
          <div class="lf-course-body">
            <div class="lf-course-meta"><span class="lf-chip">会员</span><?php if (!empty($t['members_only'])): ?><span class="lf-chip ok">含专享课程</span><?php endif; ?></div>
            <h3 class="lf-course-title"><?= lf_e((string)$t['name']) ?></h3>
            <div style="font-family:var(--font-display);font-size:26px;font-weight:700">¥<?= number_format((float)$t['price'], 2) ?><span class="lf-faint" style="font-size:13px;font-weight:400"> / <?= (int)$t['duration_days'] ?> 天</span></div>
            <?php if (!empty($t['description'])): ?><p class="lf-course-sub"><?= nl2br(lf_e((string)$t['description'])) ?></p><?php endif; ?>
            <ul class="lf-prose" style="font-size:13.5px;margin:6px 0">
              <?php if ((float)$t['discount_percent'] > 0): ?><li>课程折扣 <?= lf_e((string)$t['discount_percent']) ?>%</li><?php endif; ?>
              <?php if (!empty($t['members_only'])): ?><li>会员专享课程</li><?php endif; ?>
              <li>学习提醒与圈子权限</li>
            </ul>
            <?php if ($isCurrent): ?>
              <span class="lf-chip ok">当前会员</span>
            <?php elseif ($student === null): ?>
              <a class="btn primary block" href="<?= lf_url('/login?next=') ?><?= urlencode('/membership') ?>">登录后开通</a>
            <?php elseif ($checkout !== ''): ?>
              <a class="btn primary block" href="<?= lf_e($checkout) ?>">开通 / 续费</a>
            <?php else: ?>
              <p class="lf-faint">请联系讲师开通。</p>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
