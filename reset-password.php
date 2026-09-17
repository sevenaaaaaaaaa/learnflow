<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$student = $token !== '' ? student_by_reset($token) : null;
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student !== null) {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            lf_flash('danger', '密码至少 6 位。');
        } else {
            student_set_password((string)$student['id'], $password);
            student_clear_reset((string)$student['id']);
            $done = true;
        }
    }
}

lf_page_start([
    'title' => '重置密码 · LearnFlow',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:64px">
  <div class="lf-form-card">
    <span class="lf-kicker">Reset</span>
    <h2 class="lf-sec-title" style="font-size:26px;margin:8px 0 16px">设置新密码</h2>
    <?php if ($done): ?>
      <div class="lf-flash ok">密码已重置，请使用新密码登录。</div>
      <p style="margin-top:16px"><a class="btn primary block" href="<?= lf_url('/login') ?>">去登录</a></p>
    <?php elseif ($student === null): ?>
      <div class="lf-flash danger">链接无效或已过期，请重新申请。</div>
      <p style="margin-top:16px"><a class="btn ghost block" href="<?= lf_url('/forgot-password') ?>">重新申请</a></p>
    <?php else: ?>
      <form method="post">
        <?= lf_csrf_field() ?>
        <input type="hidden" name="token" value="<?= lf_e($token) ?>">
        <div class="lf-field"><label>新密码</label><input class="lf-inp" type="password" name="password" required minlength="6" autofocus></div>
        <button class="btn primary block" type="submit">确认重置</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php lf_page_end(); ?>
