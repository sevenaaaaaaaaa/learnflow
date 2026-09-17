<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$course = course_find($slug);
if ($course === null || ($course['status'] ?? 'draft') !== 'published') {
    http_response_code(404);
    lf_page_start(['title' => '课程不存在 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">课程不存在或未上架。<a href="' . lf_url('/courses') . '">返回课程列表</a></div>';
    lf_page_end();
    exit;
}

$student = lf_student_current();
$studentId = $student ? (string)$student['id'] : '';
$isAdmin = lf_admin_current() !== null;
$lessons = course_lessons($course);
$price = (float)($course['price'] ?? 0);
$hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));
$summary = $studentId !== '' ? progress_summary($studentId, (string)$course['id'], $course) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } elseif ($_POST['action'] === 'invite') {
        if ($student === null) {
            lf_flash('warn', '请先登录后再使用邀请码。');
        } else {
            $res = invite_redeem((string)($_POST['invite_code'] ?? ''), $studentId);
            if (!empty($res['ok'])) {
                lf_flash('ok', '邀请码兑换成功，已加入课程。');
                header('Location: ' . lf_url('/course/' . rawurlencode((string)$course['slug'])));
                exit;
            }
            lf_flash('danger', (string)($res['error'] ?? '邀请码无效'));
        }
    } elseif ($_POST['action'] === 'free') {
        if ($student === null) {
            lf_flash('warn', '请先登录后再报名。');
        } elseif ($price > 0) {
            lf_flash('danger', '该课程需要购买。');
        } else {
            enroll_add((string)$course['id'], $studentId, ['source' => 'free']);
            lf_flash('ok', '报名成功，开始学习吧。');
            header('Location: ' . lf_url('/learn/' . rawurlencode((string)$course['slug'])));
            exit;
        }
    }
    $student = lf_student_current();
    $studentId = $student ? (string)$student['id'] : '';
    $hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));
    $summary = $studentId !== '' ? progress_summary($studentId, (string)$course['id'], $course) : null;
}

$payflowUrl = payflow_checkout_url($course, (string)($student['email'] ?? ''), lf_abs_url('/course/' . rawurlencode((string)$course['slug']) . '?enrolled=1'));

if (isset($_GET['enrolled'])) {
    lf_flash('ok', '支付完成后报名将自动同步，请稍后刷新查看。');
}

lf_page_start([
    'title' => (string)($course['title'] ?? '课程') . ' · LearnFlow',
    'description' => (string)($course['summary'] ?? $course['subtitle'] ?? ''),
    'active' => 'courses',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:32px">
  <span class="lf-kicker"><?= lf_e((string)($course['type'] ?? '单课')) ?></span>
  <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);gap:34px;align-items:start;margin-top:12px" class="lf-course-hero">
    <div>
      <h1 class="lf-sec-title" style="font-size:clamp(28px,4vw,42px);line-height:1.1"><?= lf_e((string)$course['title']) ?></h1>
      <p class="lf-muted" style="font-size:17px;margin:14px 0 0"><?= lf_e((string)($course['subtitle'] ?? '')) ?></p>
      <div class="lf-course-meta" style="margin-top:18px">
        <span class="lf-chip"><?= count((array)$course['chapters']) ?> 章</span>
        <span class="lf-chip soft"><?= count($lessons) ?> 课时</span>
        <?php if (!empty($course['level'])): ?><span class="lf-chip soft"><?= lf_e((string)$course['level']) ?></span><?php endif; ?>
        <?php if (!empty($course['instructor'])): ?><span class="lf-chip soft">讲师 · <?= lf_e((string)$course['instructor']) ?></span><?php endif; ?>
        <?php if (!empty($course['certificate'])): ?><span class="lf-chip ok">结业证书</span><?php endif; ?>
        <?php foreach (category_names((array)($course['categories'] ?? [])) as $ck => $cn): ?><a class="lf-chip soft" href="<?= lf_url('/courses?cat=' . rawurlencode((string)$ck)) ?>"><?= lf_e($cn) ?></a><?php endforeach; ?>
        <?php if (!empty($course['camp_start']) || !empty($course['camp_end'])): ?>
          <span class="lf-chip">开营 <?= lf_e((string)($course['camp_start'] ?: '待定')) ?><?= !empty($course['camp_end']) ? ' → ' . lf_e((string)$course['camp_end']) : '' ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($course['tags'])): ?>
        <div class="lf-course-meta" style="margin-top:8px">
          <?php foreach ((array)$course['tags'] as $t): ?><span class="lf-chip soft"># <?= lf_e((string)$t) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($course['summary'])): ?>
        <p style="margin-top:22px;line-height:1.9;color:var(--muted)"><?= nl2br(lf_e((string)$course['summary'])) ?></p>
      <?php endif; ?>
    </div>
    <aside class="lf-sidebar" style="position:static">
      <div class="lf-course-cover" style="aspect-ratio:16/9">
        <?php if (!empty($course['cover'])): ?><img src="<?= lf_e((string)$course['cover']) ?>" alt=""><?php else: ?><span class="lf-cover-fallback"><?= lf_e(mb_substr((string)$course['title'], 0, 1)) ?></span><?php endif; ?>
      </div>
      <div class="lf-player-body">
        <div style="font-family:var(--font-display);font-size:26px;font-weight:700"><?= lf_e(course_price_label($course)) ?></div>
        <?php if ($hasAccess): ?>
          <?php if ($summary): ?><div style="margin:14px 0"><?= lf_progress_bar((int)$summary['percent'], '已完成 ' . (int)$summary['done'] . '/' . (int)$summary['total'] . ' 课时') ?></div><?php endif; ?>
          <a class="btn primary block" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>"><?= $summary && $summary['percent'] > 0 ? '继续学习' : '开始学习' ?></a>
        <?php elseif ($student === null): ?>
          <a class="btn primary block" style="margin-top:14px" href="<?= lf_url('/login?next=') ?><?= urlencode('/course/' . (string)$course['slug']) ?>">登录后报名</a>
        <?php elseif ($price > 0 && $payflowUrl !== ''): ?>
          <a class="btn primary block" style="margin-top:14px" href="<?= lf_e($payflowUrl) ?>">立即购买</a>
          <p class="lf-faint" style="margin-top:10px">由 PayFlow 收款，购买后自动入学。</p>
        <?php elseif ($price > 0): ?>
          <p class="lf-faint" style="margin-top:14px">该课程售价 <?= lf_e(course_price_label($course)) ?>，请联系讲师开通或使用邀请码。</p>
        <?php else: ?>
          <form method="post" style="margin-top:14px">
            <?= lf_csrf_field() ?>
            <input type="hidden" name="action" value="free">
            <button class="btn primary block" type="submit">免费报名</button>
          </form>
        <?php endif; ?>
        <?php if (!$hasAccess && ($course['allow_invite'] ?? false)): ?>
          <form method="post" style="margin-top:12px;display:flex;gap:8px">
            <?= lf_csrf_field() ?>
            <input type="hidden" name="action" value="invite">
            <input class="lf-inp" name="invite_code" placeholder="邀请码" style="height:42px" required>
            <button class="btn ghost sm" type="submit">兑换</button>
          </form>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</section>

<section class="lf-sec" style="padding-top:10px">
  <div class="lf-sec-head"><h2 class="lf-sec-title" style="font-size:24px">课程大纲</h2></div>
  <?php if (!$course['chapters']): ?>
    <div class="lf-empty">课程大纲尚未发布。</div>
  <?php else: ?>
    <div style="border:1px solid var(--border);border-radius:var(--r-md);overflow:hidden;background:var(--surface)">
      <?php foreach ($course['chapters'] as $ci => $ch): ?>
        <div class="lf-chapter">
          <div class="lf-chapter-title">第 <?= $ci + 1 ?> 章 · <?= lf_e((string)($ch['title'] ?? '')) ?></div>
          <?php foreach ((array)($ch['lessons'] ?? []) as $li => $l):
              $done = $studentId !== '' ? !empty(progress_lesson_state($studentId, (string)$course['id'], (string)$l['id'])['done']) : false;
              $locked = !$hasAccess && empty($l['free']);
              $href = $hasAccess ? lf_url('/learn/' . rawurlencode((string)$course['slug']) . '?lesson=' . rawurlencode((string)$l['id'])) : '#';
          ?>
            <a class="lf-lesson-row" href="<?= lf_e($href) ?>"<?= $locked ? ' onclick="return false" style="opacity:.72"' : '' ?>>
              <span class="lf-lesson-idx"><?= $ci + 1 ?>.<?= $li + 1 ?></span>
              <?= lf_icon((string)($l['type'] ?? 'article')) ?>
              <span><?= lf_e((string)($l['title'] ?? '')) ?></span>
              <span class="lf-chip soft" style="margin-left:8px"><?= lf_lesson_type_label((string)($l['type'] ?? 'article')) ?></span>
              <?php if ($done): ?><span class="lf-lesson-state"><?= lf_icon('check', 16) ?></span><?php elseif ($locked): ?><span class="lf-lesson-state todo">🔒</span><?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
