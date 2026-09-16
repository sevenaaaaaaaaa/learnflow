<?php
require_once __DIR__ . '/includes/bootstrap.php';

$camps = array_values(array_filter(course_all(true), function ($c) {
    return ($c['type'] ?? '') === '训练营' || !empty($c['allow_invite']);
}));
$student = lf_student_current();

lf_page_start([
    'title' => '训练营 · LearnFlow',
    'description' => '开营节奏、作业提交、打卡与圈子——训练营运营。',
    'active' => 'camp',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Bootcamp</span>
      <h2 class="lf-sec-title">训练营</h2>
      <p class="lf-muted" style="margin:10px 0 0">开营节奏、作业与打卡将在 H3 上线；现在可先用邀请码组织学员入学。</p>
    </div>
  </div>
  <?php if (!$camps): ?>
    <div class="lf-empty">暂无训练营课程。将有邀请码的课程或类型设为「训练营」即可在此展示。</div>
  <?php else: ?>
    <div class="lf-grid">
      <?php
      foreach ($camps as $course) {
          $opts = [];
          if ($student && enroll_is_active((string)$course['id'], (string)$student['id'])) {
              $opts['progress'] = progress_summary((string)$student['id'], (string)$course['id'], $course)['percent'];
          }
          echo lf_course_card($course, $opts);
      }
      ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
