<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$course = course_find($slug);
if ($course === null) {
    http_response_code(404);
    lf_page_start(['title' => '课程不存在 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">课程不存在。<a href="' . lf_url('/courses') . '">返回课程列表</a></div>';
    lf_page_end();
    exit;
}

$student = lf_student_current();
$isAdmin = lf_admin_current() !== null;
$studentId = $student ? (string)$student['id'] : '';

if ($student === null && !$isAdmin) {
    header('Location: ' . lf_url('/login?next=' . urlencode('/learn/' . (string)$course['slug'])));
    exit;
}

$hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));
$lessons = course_lessons($course);
$lessonId = (string)($_GET['lesson'] ?? '');
$lesson = $lessonId !== '' ? course_lesson_find($course, $lessonId) : null;

if ($lesson === null) {
    $resume = $studentId !== '' ? progress_resume($studentId, (string)$course['id'], $course) : null;
    $lesson = $resume ? course_lesson_find($course, (string)$resume['lesson_id']) : null;
    if ($lesson === null) $lesson = $lessons[0] ?? null;
}

if ($lesson === null) {
    lf_page_start(['title' => '课程为空 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">该课程还没有课时内容。</div>';
    lf_page_end();
    exit;
}

$isFreePreview = !empty($lesson['free']);
if (!$hasAccess && !$isFreePreview && !$isAdmin) {
    lf_flash('warn', '请先报名该课程后继续学习。');
    header('Location: ' . lf_url('/course/' . rawurlencode((string)$course['slug'])));
    exit;
}

$state = ($studentId !== '' && $hasAccess) ? progress_get($studentId, (string)$course['id'], (string)$lesson['id']) : [];
$resumePosition = (int)($state['position'] ?? 0);
$neighbors = course_lesson_neighbors($course, (string)$lesson['id']);
$summary = ($studentId !== '' && $hasAccess) ? progress_summary($studentId, (string)$course['id'], $course) : null;
$lessonType = (string)($lesson['type'] ?? 'article');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'done' && $studentId !== '' && $hasAccess) {
    if (lf_csrf_check()) {
        progress_done($studentId, (string)$course['id'], (string)$lesson['id']);
        cert_maybe_issue($studentId, $course, (string)($student['name'] ?? ''));
        lf_flash('ok', '已标记完成。');
    }
    header('Location: ' . lf_url('/learn/' . rawurlencode((string)$course['slug']) . '?lesson=' . rawurlencode((string)$lesson['id'])));
    exit;
}

lf_page_start([
    'title' => (string)($lesson['title'] ?? '学习') . ' · ' . (string)$course['title'],
    'description' => (string)($course['summary'] ?? ''),
    'active' => 'courses',
    'container' => true,
]);
?>
<div class="lf-layout">
  <div>
    <div class="lf-player-wrap" data-lf-player data-endpoint="<?= lf_url('/api/progress.php') ?>" data-course="<?= lf_e((string)$course['id']) ?>" data-lesson="<?= lf_e((string)$lesson['id']) ?>" data-resume="<?= (int)$resumePosition ?>">
      <?php if ($lessonType === 'video' && !empty($lesson['video'])): ?>
        <video class="lf-player-video" controls playsinline preload="metadata" src="<?= lf_e((string)$lesson['video']) ?><?= $resumePosition > 2 ? '#t=' . (int)$resumePosition : '' ?>"></video>
      <?php elseif ($lessonType === 'quiz'): ?>
        <div class="lf-player-art">
          <span class="lf-kicker">测验</span>
          <h1 style="font-family:var(--font-display);margin:10px 0"><?= lf_e((string)$lesson['title']) ?></h1>
          <p class="lf-muted">本课时为测验，请在独立页面完成。通过后回到此处继续。</p>
          <a class="btn primary" style="margin-top:12px" href="<?= lf_url('/quiz/') ?><?= rawurlencode((string)$course['slug']) ?>?quiz=<?= rawurlencode((string)$lesson['quiz_id']) ?>">开始测验</a>
        </div>
      <?php else: ?>
        <div class="lf-player-art">
          <span class="lf-kicker"><?= lf_e(lf_lesson_type_label($lessonType)) ?></span>
          <h1 style="font-family:var(--font-display);margin:10px 0"><?= lf_e((string)$lesson['title']) ?></h1>
          <div class="lf-prose" style="margin-top:18px"><?= $lesson['content'] ?? '' ?></div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($lessonType !== 'quiz'): ?>
    <div class="lf-player-body" style="border:1px solid var(--border);border-radius:var(--r-md);margin-top:16px;background:var(--surface)">
      <div class="lf-course-meta">
        <span class="lf-chip soft"><?= lf_e((string)$lesson['chapter_title']) ?></span>
        <?php if ((int)($lesson['duration'] ?? 0) > 0): ?><span class="lf-chip soft"><?= (int)$lesson['duration'] ?> 分钟</span><?php endif; ?>
        <?php if (!empty($state['done'])): ?><span class="lf-chip ok">已完成</span><?php endif; ?>
      </div>
      <h1 style="font-family:var(--font-display);font-size:23px;margin:10px 0 0"><?= lf_e((string)$lesson['title']) ?></h1>
      <?php foreach ((array)($lesson['attachments'] ?? []) as $att): ?>
        <a class="lf-faint" style="display:inline-flex;align-items:center;gap:6px;margin-top:10px" href="<?= lf_e((string)($att['url'] ?? '#')) ?>" download><?= lf_icon('file', 16) ?> <?= lf_e((string)($att['name'] ?? '附件')) ?></a>
      <?php endforeach; ?>
      <div class="lf-player-actions">
        <?php if ($hasAccess): ?>
          <form method="post">
            <?= lf_csrf_field() ?>
            <input type="hidden" name="action" value="done">
            <button class="btn ghost sm" type="submit"><?= !empty($state['done']) ? '标记为未完成' : '标记完成' ?></button>
          </form>
        <?php endif; ?>
        <?php if ($neighbors['prev']): ?><a class="btn subtle sm" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$neighbors['prev']['id']) ?>"><?= lf_icon('arrow-left', 16) ?> 上一节</a><?php endif; ?>
        <?php if ($neighbors['next']): ?><a class="btn primary sm" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$neighbors['next']['id']) ?>">下一节 <?= lf_icon('arrow-right', 16) ?></a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <aside class="lf-sidebar">
    <div class="lf-sidebar-head">
      <a class="lf-faint" href="<?= lf_url('/course/') ?><?= rawurlencode((string)$course['slug']) ?>">← <?= lf_e((string)$course['title']) ?></a>
      <?php if ($summary): ?>
        <?= lf_progress_bar((int)$summary['percent'], '进度 ' . (int)$summary['done'] . '/' . (int)$summary['total'] . ' 课时') ?>
      <?php endif; ?>
    </div>
    <?php foreach ($course['chapters'] as $ci => $ch): ?>
      <div class="lf-chapter">
        <div class="lf-chapter-title">第 <?= $ci + 1 ?> 章 · <?= lf_e((string)($ch['title'] ?? '')) ?></div>
        <?php foreach ((array)($ch['lessons'] ?? []) as $l):
            $isOn = (string)$l['id'] === (string)$lesson['id'];
            $lDone = $studentId !== '' ? !empty(progress_lesson_state($studentId, (string)$course['id'], (string)$l['id'])['done']) : false;
        ?>
          <a class="lf-lesson-row<?= $isOn ? ' on' : '' ?>" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$l['id']) ?>">
            <?= lf_icon((string)($l['type'] ?? 'article')) ?>
            <span><?= lf_e((string)($l['title'] ?? '')) ?></span>
            <?php if ($lDone): ?><span class="lf-lesson-state"><?= lf_icon('check', 15) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($summary && $summary['total'] > 0 && $summary['done'] >= $summary['total']): ?>
      <div class="lf-sidebar-head">
        <span class="lf-chip ok">已学完全部课时</span>
        <?php if (!empty($course['certificate'])): ?>
          <a class="btn primary sm block" href="<?= lf_url('/certificate?course=') ?><?= rawurlencode((string)$course['id']) ?>">查看结业证书</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>
<?php lf_page_end(); ?>
