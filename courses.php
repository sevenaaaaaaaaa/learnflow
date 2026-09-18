<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categories = category_all();
$activeCat = trim((string)($_GET['cat'] ?? ''));
$courses = course_all(true);
if ($activeCat !== '') {
    $courses = array_values(array_filter($courses, fn($c) => in_array($activeCat, array_map('strval', (array)($c['categories'] ?? [])), true)));
}
$student = lf_student_current();
$enrolled = $student ? enroll_by_student((string)$student['id']) : [];

lf_page_start([
    'title' => '课程 · LearnFlow',
    'description' => '在架课程列表：课程结构、章节课时、报名入口。',
    'active' => 'courses',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Courses</span>
      <h2 class="lf-sec-title"><?= lf_t('全部课程', 'All courses') ?></h2>
    </div>
    <span class="lf-faint"><?= lf_t('共', '') ?> <?= count($courses) ?> <?= lf_t('门', 'courses') ?></span>
  </div>

  <?php if ($categories): ?>
    <div class="lf-tabs" style="margin-bottom:22px">
      <a class="lf-tab<?= $activeCat === '' ? ' on' : '' ?>" href="<?= lf_url('/courses') ?>"><?= lf_t('全部', 'All') ?></a>
      <?php foreach ($categories as $cat): ?>
        <a class="lf-tab<?= $activeCat === (string)$cat['key'] ? ' on' : '' ?>" href="<?= lf_url('/courses?cat=' . rawurlencode((string)$cat['key'])) ?>"><?= lf_e((string)$cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$courses): ?>
    <div class="lf-empty">该分类下暂无课程。</div>
  <?php else: ?>
    <div class="lf-grid">
      <?php
      foreach ($courses as $course) {
          $opts = [];
          if (isset($enrolled[$course['id']])) {
              $opts['progress'] = progress_summary((string)$student['id'], (string)$course['id'], $course)['percent'];
          }
          echo lf_course_card($course, $opts);
      }
      ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
