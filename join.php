<?php
require_once __DIR__ . '/includes/bootstrap.php';

$code = trim((string)($_GET['code'] ?? $_POST['code'] ?? ''));
$student = lf_student_current();

if ($student === null) {
    $target = '/join' . ($code !== '' ? '?code=' . urlencode($code) : '');
    header('Location: /login?next=' . urlencode($target));
    exit;
}

$invite = $code !== '' ? invite_find($code) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'redeem') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        $res = invite_redeem((string)$_POST['code'], (string)$student['id']);
        if (!empty($res['ok'])) {
            $course = course_find((string)$res['course_id']);
            lf_flash('ok', !empty($res['already']) ? '你已在该课程中。' : '兑换成功，已加入课程。');
            header('Location: ' . ($course ? '/learn/' . rawurlencode((string)$course['slug']) : '/dashboard'));
            exit;
        }
        lf_flash('danger', (string)($res['error'] ?? '邀请码无效'));
    }
}

lf_page_start([
    'title' => '邀请码入学 · LearnFlow',
    'active' => 'courses',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:56px">
  <div class="lf-form-card">
    <span class="lf-kicker">Invite</span>
    <h2 class="lf-sec-title" style="font-size:26px;margin:8px 0 6px">邀请码入学</h2>
    <?php if ($invite !== null): ?>
      <?php $inviteCourse = course_find((string)$invite['course_id']); ?>
      <p class="lf-muted">邀请码对应课程：<b><?= lf_e((string)($inviteCourse['title'] ?? $invite['course_id'])) ?></b></p>
    <?php endif; ?>
    <form method="post" style="margin-top:18px">
      <?= lf_csrf_field() ?>
      <input type="hidden" name="action" value="redeem">
      <div class="lf-field"><label>邀请码</label><input class="lf-inp" name="code" value="<?= lf_e($code) ?>" placeholder="输入邀请码" required></div>
      <button class="btn primary block" type="submit">兑换入学</button>
    </form>
  </div>
</section>
<?php lf_page_end(); ?>
