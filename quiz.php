<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$course = course_find($slug);
if ($course === null) {
    http_response_code(404);
    lf_page_start(['title' => '测验 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">课程不存在。</div>';
    lf_page_end();
    exit;
}

$student = lf_student_current();
$isAdmin = lf_admin_current() !== null;
if ($student === null && !$isAdmin) {
    header('Location: /login?next=' . urlencode('/quiz/' . (string)$course['slug']));
    exit;
}
$studentId = $student ? (string)$student['id'] : '';
$hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));

$quizId = (string)($_GET['quiz'] ?? '');
$quiz = $quizId !== '' ? quiz_find($quizId) : null;
if ($quiz === null) {
    $candidates = quiz_for_course((string)$course['id']);
    foreach ($candidates as $cand) {
        if (($cand['kind'] ?? 'chapter') === 'final') { $quiz = $cand; break; }
    }
    if ($quiz === null) $quiz = $candidates[0] ?? null;
}
if ($quiz === null) {
    lf_page_start(['title' => '测验 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">该课程暂无测验。</div>';
    lf_page_end();
    exit;
}

if (!$hasAccess && !$isAdmin) {
    lf_flash('warn', '请先报名课程后再参加测验。');
    header('Location: /course/' . rawurlencode((string)$course['slug']));
    exit;
}

$result = null;
$attempt = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重新作答。');
    } else {
        $answers = (array)($_POST['answers'] ?? []);
        $res = quiz_submit($studentId, (string)$quiz['id'], $answers);
        if (!empty($res['ok'])) {
            $result = $res['result'];
            $attempt = $res['attempt'];
            if (!empty($result['passed']) && $studentId !== '') {
                if (!empty($quiz['lesson_id'])) {
                    progress_done($studentId, (string)$course['id'], (string)$quiz['lesson_id']);
                }
                $cert = cert_maybe_issue($studentId, $course, (string)($student['name'] ?? ''));
                if ($cert !== null) lf_flash('ok', '恭喜！结业证书已颁发。');
            }
        } else {
            lf_flash('danger', (string)($res['error'] ?? '提交失败'));
        }
    }
}

$best = $studentId !== '' ? quiz_best($studentId, (string)$quiz['id']) : null;
$questions = (array)($quiz['questions'] ?? []);
$total = quiz_total_score($quiz);

lf_page_start([
    'title' => (string)($quiz['title'] ?? '测验') . ' · LearnFlow',
    'active' => 'courses',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:820px;margin:0 auto">
  <a class="lf-faint" href="/learn/<?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)($quiz['lesson_id'] ?? '')) ?>">← 返回课程</a>
  <span class="lf-kicker" style="margin-top:12px;display:inline-flex"><?= ($quiz['kind'] ?? 'chapter') === 'final' ? '结业测验' : '章节测验' ?></span>
  <h1 class="lf-sec-title" style="font-size:30px"><?= lf_e((string)($quiz['title'] ?? '测验')) ?></h1>
  <p class="lf-faint">共 <?= count($questions) ?> 题 · 满分 <?= (int)$total ?> 分 · 及格 <?= (int)($quiz['pass_score'] ?? ceil($total * 0.6)) ?> 分<?= $best ? ' · 最高分 ' . (int)$best['score'] . '（' . (!empty($best['passed']) ? '已通过' : '未通过') . '）' : '' ?></p>

  <?php if ($result !== null): ?>
    <div class="lf-quiz-result <?= !empty($result['passed']) ? 'passed' : 'failed' ?>">
      <div class="lf-score"><?= (int)$result['score'] ?><span style="font-size:18px;color:var(--faint)">/<?= (int)$result['total'] ?></span></div>
      <div>
        <strong style="font-size:17px"><?= !empty($result['passed']) ? '恭喜，测验通过！' : '未达及格线，再试一次' ?></strong>
        <p class="lf-faint" style="margin:4px 0 0">及格线 <?= (int)$result['pass_score'] ?> 分</p>
      </div>
    </div>
  <?php endif; ?>

  <form method="post">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="submit">
    <?php foreach ($questions as $qi => $q):
        $qid = (string)($q['id'] ?? '');
        $type = (string)($q['type'] ?? 'single');
        $options = (array)($q['options'] ?? []);
        $detail = null;
        if ($result !== null) {
            foreach ($result['detail'] as $d) if (($d['question_id'] ?? '') === $qid) { $detail = $d; break; }
        }
    ?>
      <div class="lf-q">
        <p class="lf-q-title"><span class="lf-q-idx"><?= $qi + 1 ?>.</span> <?= lf_e((string)($q['title'] ?? '')) ?> <span class="lf-chip soft" style="margin-left:auto"><?= (int)($q['score'] ?? 1) ?> 分</span></p>
        <?php if (in_array($type, ['single', 'judge'], true)): ?>
          <?php foreach ($options as $opt):
              $oid = (string)($opt['id'] ?? '');
              $cls = '';
              if ($detail !== null && in_array($oid, (array)$detail['expected'], true)) $cls = ' right';
          ?>
            <label class="lf-opt<?= $cls ?>">
              <input type="radio" name="answers[<?= lf_e($qid) ?>]" value="<?= lf_e($oid) ?>" <?= $detail !== null ? 'disabled' : '' ?> required>
              <span><?= lf_e((string)($opt['text'] ?? '')) ?></span>
            </label>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach ($options as $opt):
              $oid = (string)($opt['id'] ?? '');
              $cls = '';
              if ($detail !== null && in_array($oid, (array)$detail['expected'], true)) $cls = ' right';
          ?>
            <label class="lf-opt<?= $cls ?>">
              <input type="checkbox" name="answers[<?= lf_e($qid) ?>][]" value="<?= lf_e($oid) ?>" <?= $detail !== null ? 'disabled' : '' ?>>
              <span><?= lf_e((string)($opt['text'] ?? '')) ?></span>
            </label>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($detail !== null && !empty($detail['explanation'])): ?>
          <p class="lf-faint" style="margin-top:8px">解析：<?= lf_e((string)$detail['explanation']) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($result === null): ?>
      <button class="btn primary" type="submit">提交答案</button>
    <?php else: ?>
      <a class="btn ghost" href="/quiz/<?= rawurlencode((string)$course['slug']) ?>?quiz=<?= rawurlencode((string)$quiz['id']) ?>">重新作答</a>
    <?php endif; ?>
  </form>
</section>
<?php lf_page_end(); ?>
