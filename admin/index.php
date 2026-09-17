<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$courses = course_all();
$students = student_all();
$enrollCount = 0;
foreach (enrollment_all() as $rows) $enrollCount += count($rows);
$certCount = count(cert_all());

lf_admin_page_start(['title' => '看板 · LearnFlow 讲师后台', 'active' => 'index']);
?>
<div class="lf-admin-head">
  <h1>交付看板</h1>
  <div class="lf-row" style="flex:0 0 auto">
    <a class="btn ghost sm" href="<?= lf_url('/admin/export.php?type=students') ?>">导出学员 CSV</a>
    <a class="btn primary sm" href="<?= lf_url('/admin/course-edit.php') ?>">+ 新建课程</a>
  </div>
</div>

<div class="lf-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:26px">
  <div class="lf-stat"><b><?= count($courses) ?></b><span>课程</span></div>
  <div class="lf-stat"><b><?= count($students) ?></b><span>学员</span></div>
  <div class="lf-stat"><b><?= $enrollCount ?></b><span>报名人次</span></div>
  <div class="lf-stat"><b><?= $certCount ?></b><span>已发证书</span></div>
</div>

<h2 class="lf-sec-title" style="font-size:20px;margin-bottom:14px">完课率与学习曲线</h2>
<?php if (!$courses): ?>
  <div class="lf-empty">还没有课程。<a href="<?= lf_url('/admin/course-edit.php') ?>">创建第一门课程</a></div>
<?php else: ?>
  <?php foreach ($courses as $course):
      $enrolled = enroll_students((string)$course['id']);
      $learners = 0; $completed = 0;
      foreach ($enrolled as $sid => $row) {
          $learners++;
          $sum = progress_summary((string)$sid, (string)$course['id'], $course);
          if ($sum['total'] > 0 && $sum['done'] >= $sum['total']) $completed++;
      }
      $rate = $learners > 0 ? round($completed / $learners * 100) : 0;
      $curve = progress_curve((string)$course['id']);
      $atRisk = progress_at_risk((string)$course['id']);
      $checkin = checkin_course_stats((string)$course['id']);
  ?>
    <div class="lf-form-card" style="max-width:none;margin-bottom:18px">
      <div class="lf-admin-head" style="margin-bottom:12px">
        <div>
          <b style="font-size:16px"><a href="<?= lf_url('/admin/course-edit.php?id=' . urlencode((string)$course['id'])) ?>"><?= lf_e((string)$course['title']) ?></a></b>
          <div class="lf-faint"><?= count((array)$course['chapters']) ?> 章 · <?= course_lesson_count($course) ?> 课时 · 学员 <?= $learners ?> · 完课 <?= $completed ?> · 打卡今日 <?= (int)$checkin['today'] ?></div>
        </div>
        <span class="lf-chip <?= ($course['status'] ?? '') === 'published' ? 'ok' : 'soft' ?>"><?= ($course['status'] ?? 'draft') === 'published' ? '已上架' : '草稿' ?></span>
      </div>
      <?= lf_progress_bar((int)$rate, '完课率 ' . $rate . '%') ?>

      <?php if (!empty($curve['lessons'])): ?>
        <div style="margin-top:16px">
          <div class="lf-faint" style="margin-bottom:6px">课时学习曲线（开始 → 完成，<?= (int)$curve['learners'] ?> 名学员）</div>
          <div style="display:flex;gap:6px;align-items:flex-end;height:70px;overflow-x:auto;padding-bottom:4px">
            <?php $maxStarted = max(1, max(array_map(fn($l) => max($l['started'], $l['done']), $curve['lessons']))); ?>
            <?php foreach ($curve['lessons'] as $l): ?>
              <div title="<?= lf_e((string)$l['title']) ?>：开始 <?= (int)$l['started'] ?> / 完成 <?= (int)$l['done'] ?>" style="flex:0 0 auto;width:22px;display:flex;flex-direction:column;justify-content:flex-end;gap:2px;height:100%">
                <span style="height:<?= (int)round($l['started'] / $maxStarted * 60) ?>px;background:var(--accent-soft);border-radius:3px"></span>
                <span style="height:<?= (int)round($l['done'] / $maxStarted * 60) ?>px;background:var(--accent);border-radius:3px"></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($atRisk): ?>
        <details style="margin-top:14px">
          <summary style="cursor:pointer;color:var(--warn);font-size:13.5px">风险学员 <?= count($atRisk) ?> 人（进度低且近 7 天不活跃）</summary>
          <div class="lf-row" style="margin-top:8px;gap:8px;flex-wrap:wrap">
            <?php foreach ($atRisk as $r): ?>
              <span class="lf-chip" title="<?= lf_e($r['email']) ?> · 最近 <?= lf_e($r['last_at']) ?>" style="background:var(--warn-soft);color:var(--warn)"><?= lf_e($r['name']) ?> <?= (int)$r['percent'] ?>%</span>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endif; ?>

      <div class="lf-row" style="margin-top:10px;gap:8px">
        <a class="btn subtle sm" href="<?= lf_url('/admin/export.php?type=progress&course=' . urlencode((string)$course['id'])) ?>">导出进度 CSV</a>
        <a class="btn subtle sm" href="<?= lf_url('/admin/export.php?type=at_risk&course=' . urlencode((string)$course['id'])) ?>">导出风险学员</a>
        <button class="btn subtle sm" type="button" data-ai-report data-course="<?= lf_e((string)$course['id']) ?>">AI 周报</button>
        <a class="btn subtle sm" href="<?= lf_url('/camp/' . rawurlencode((string)($course['slug'] ?? $course['id']))) ?>" target="_blank">训练营页</a>
      </div>
      <div class="lf-flash info" id="rpt-<?= lf_e((string)$course['id']) ?>" style="display:none;margin-top:10px;font-size:13.5px;white-space:pre-wrap"></div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<script>
(function () {
  var token = '<?= lf_csrf_token() ?>';
  var api = '<?= lf_url('/api/ai.php') ?>';
  Array.prototype.forEach.call(document.querySelectorAll('[data-ai-report]'), function (btn) {
    btn.addEventListener('click', function () {
      var box = document.getElementById('rpt-' + btn.getAttribute('data-course'));
      var old = btn.textContent; btn.textContent = '生成中…'; btn.disabled = true;
      var fd = new FormData();
      fd.append('_token', token); fd.append('action', 'weekly_report');
      fd.append('course_id', btn.getAttribute('data-course'));
      fetch(api, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
        btn.textContent = old; btn.disabled = false;
        if (!box) return;
        box.style.display = 'block';
        box.textContent = d.ok ? d.report : (d.error || 'AI 失败');
      }).catch(function () { btn.textContent = old; btn.disabled = false; });
    });
  });
})();
</script>
<?php lf_admin_page_end(); ?>
