<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$courses = course_all();
$students = student_all();
$enrollCount = 0;
$certCount = count(cert_all());
foreach (enrollment_all() as $rows) $enrollCount += count($rows);

lf_admin_page_start(['title' => '看板 · LearnFlow 讲师后台', 'active' => 'index']);
?>
<div class="lf-admin-head">
  <h1>交付看板</h1>
  <a class="btn primary sm" href="<?= lf_url('/admin/course-edit.php') ?>">+ 新建课程</a>
</div>

<div class="lf-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:26px">
  <div class="lf-stat"><b><?= count($courses) ?></b><span>课程</span></div>
  <div class="lf-stat"><b><?= count($students) ?></b><span>学员</span></div>
  <div class="lf-stat"><b><?= $enrollCount ?></b><span>报名人次</span></div>
  <div class="lf-stat"><b><?= $certCount ?></b><span>已发证书</span></div>
</div>

<h2 class="lf-sec-title" style="font-size:20px;margin-bottom:14px">完课率看板</h2>
<?php if (!$courses): ?>
  <div class="lf-empty">还没有课程。<a href="<?= lf_url('/admin/course-edit.php') ?>">创建第一门课程</a></div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>课程</th><th>学员</th><th>完课</th><th>完课率</th><th>状态</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($courses as $course):
        $enrolled = enroll_students((string)$course['id']);
        $learners = 0; $completed = 0;
        foreach ($enrolled as $sid => $row) {
            $learners++;
            $sum = progress_summary((string)$sid, (string)$course['id'], $course);
            if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
        }
        $rate = $learners > 0 ? round($completed / $learners * 100) : 0;
    ?>
      <tr>
        <td><a href="<?= lf_url('/admin/course-edit.php?id=') ?><?= urlencode((string)$course['id']) ?>"><b><?= lf_e((string)$course['title']) ?></b></a><br><span class="lf-faint"><?= count((array)$course['chapters']) ?> 章 · <?= course_lesson_count($course) ?> 课时</span></td>
        <td><?= $learners ?></td>
        <td><?= $completed ?></td>
        <td style="min-width:150px"><?= lf_progress_bar($rate, $rate . '%') ?></td>
        <td><span class="lf-chip <?= ($course['status'] ?? '') === 'published' ? 'ok' : 'soft' ?>"><?= ($course['status'] ?? 'draft') === 'published' ? '已上架' : '草稿' ?></span></td>
        <td><a class="btn subtle sm" href="<?= lf_url('/course/') ?><?= rawurlencode((string)($course['slug'] ?? $course['id'])) ?>" target="_blank">预览</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
