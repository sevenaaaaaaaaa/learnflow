<?php
require_once __DIR__ . '/includes/bootstrap.php';

$student = lf_student_required();
$sid = (string)$student['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && lf_csrf_check()) {
    notify_mark_read($sid, (string)($_POST['id'] ?? ''));
    header('Location: ' . lf_url('/notifications'));
    exit;
}

$items = notify_list($sid, 80);
notify_mark_read($sid);
$icons = ['course' => 'article', 'assignment' => 'file', 'certificate' => 'cert', 'reminder' => 'clock', 'system' => 'user'];

lf_page_start([
    'title' => '通知 · LearnFlow',
    'active' => 'dashboard',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:760px;margin:0 auto">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Notifications</span>
      <h2 class="lf-sec-title">通知中心</h2>
    </div>
    <a class="btn subtle sm" href="<?= lf_url('/dashboard') ?>">返回我的学习</a>
  </div>
  <?php if (!$items): ?>
    <div class="lf-empty">暂无通知。</div>
  <?php else: ?>
    <div style="display:grid;gap:10px">
      <?php foreach ($items as $n): ?>
        <a class="lf-stat" href="<?= lf_e($n['link'] !== '' ? lf_url((string)$n['link']) : '#') ?>" style="display:flex;gap:14px;align-items:flex-start;<?= empty($n['read']) ? 'border-color:var(--accent)' : '' ?>">
          <span class="lf-brand-ic" style="background:var(--accent-soft)"><?= lf_icon($icons[(string)($n['type'] ?? 'system')] ?? 'user', 17) ?></span>
          <span style="flex:1">
            <b style="font-size:15px"><?= lf_e((string)$n['title']) ?></b>
            <?php if (!empty($n['body'])): ?><span class="lf-muted" style="display:block;font-size:13.5px;margin-top:2px"><?= lf_e((string)$n['body']) ?></span><?php endif; ?>
            <span class="lf-faint" style="display:block;margin-top:4px"><?= lf_e((string)$n['created_at']) ?><?= empty($n['read']) ? ' · 未读' : '' ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
