<?php
require_once __DIR__ . '/includes/bootstrap.php';

$student = lf_student_current();
if ($student === null) {
    header('Location: ' . lf_url('/login?next=' . urlencode('/dashboard')));
    exit;
}
$studentId = (string)$student['id'];
$enrollments = enroll_by_student($studentId);
$courses = [];
foreach ($enrollments as $courseId => $row) {
    $course = course_find((string)$courseId);
    if ($course === null) continue;
    $courses[] = ['course' => $course, 'row' => $row];
}
$certs = cert_for_student($studentId);
$totalMinutes = 0;

lf_page_start([
    'title' => '我的学习 · LearnFlow',
    'active' => 'dashboard',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">My Learning</span>
      <h2 class="lf-sec-title">你好，<?= lf_e((string)$student['name']) ?></h2>
    </div>
    <div class="lf-row" style="flex:0 0 auto">
      <?php if ($certs): ?><a class="btn ghost sm" href="<?= lf_url('/certificate') ?>">我的证书 (<?= count($certs) ?>)</a><?php endif; ?>
      <form method="post" action="<?= lf_url('/logout.php') ?>"><?= lf_csrf_field() ?><button class="btn subtle sm" type="submit">退出</button></form>
    </div>
  </div>

  <?php if (!$courses): ?>
    <div class="lf-empty">你还没有加入任何课程。<a href="<?= lf_url('/courses') ?>">去看看课程</a> 或使用邀请码 <a href="<?= lf_url('/join') ?>">兑换入学</a>。</div>
  <?php else: ?>
    <div class="lf-grid">
      <?php foreach ($courses as $item):
          $course = lf_localize_course($item['course']);
          $sum = progress_summary($studentId, (string)$course['id'], $course);
          $totalMinutes += $sum['minutes'];
          $resume = progress_resume($studentId, (string)$course['id'], $course);
          $link = lf_url('/learn/' . rawurlencode((string)$course['slug']) . ($resume ? '?lesson=' . rawurlencode((string)$resume['lesson_id']) : ''));
      ?>
        <article class="lf-course-card">
          <div class="lf-course-body">
            <div class="lf-course-meta">
              <span class="lf-chip"><?= lf_e((string)($course['type'] ?? '单课')) ?></span>
              <?php if (($item['row']['source'] ?? '') === 'payflow'): ?><span class="lf-chip ok">已购</span><?php elseif (($item['row']['source'] ?? '') === 'invite'): ?><span class="lf-chip soft">邀请码</span><?php endif; ?>
            </div>
            <h3 class="lf-course-title"><a href="<?= lf_e($link) ?>"><?= lf_e((string)$course['title']) ?></a></h3>
            <?= lf_progress_bar((int)$sum['percent'], '已完成 ' . (int)$sum['done'] . '/' . (int)$sum['total'] . ' 课时 · 学习 ' . (int)$sum['minutes'] . ' 分钟') ?>
            <div class="lf-player-actions" style="margin-top:6px">
              <a class="btn primary sm" href="<?= lf_e($link) ?>"><?= $sum['percent'] > 0 ? '继续学习' : '开始学习' ?></a>
              <?php if ($sum['total'] > 0 && $sum['done'] >= $sum['total'] && !empty($course['certificate'])): ?>
                <a class="btn ghost sm" href="<?= lf_url('/certificate?course=') ?><?= rawurlencode((string)$course['id']) ?>">领取证书</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
