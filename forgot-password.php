<?php
require_once __DIR__ . '/includes/bootstrap.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        $student = student_by_email($email);
        if ($student !== null) {
            $token = bin2hex(random_bytes(24));
            student_set_reset((string)$student['id'], $token, time() + 3600);
            lf_mail_template_send($email, 'reset', [
                'name' => (string)($student['name'] ?? ''),
                'url' => lf_abs_url('/reset-password?token=' . $token),
            ], (string)($student['name'] ?? ''));
        }
        $sent = true;
    }
}

lf_page_start([
    'title' => '找回密码 · LearnFlow',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:64px">
  <div class="lf-form-card">
    <span class="lf-kicker">Reset</span>
    <h2 class="lf-sec-title" style="font-size:26px;margin:8px 0 16px">找回密码</h2>
    <?php if ($sent): ?>
      <div class="lf-flash ok">如果该邮箱已注册，我们已发送重置链接（1 小时内有效）。请查收邮件。</div>
      <p style="margin-top:16px"><a class="btn ghost block" href="<?= lf_url('/login') ?>">返回登录</a></p>
    <?php else: ?>
      <form method="post">
        <?= lf_csrf_field() ?>
        <div class="lf-field"><label>注册邮箱</label><input class="lf-inp" type="email" name="email" required autofocus></div>
        <button class="btn primary block" type="submit">发送重置链接</button>
      </form>
      <p class="lf-faint" style="margin-top:14px;text-align:center"><a href="<?= lf_url('/login') ?>">返回登录</a></p>
    <?php endif; ?>
  </div>
</section>
<?php lf_page_end(); ?>
