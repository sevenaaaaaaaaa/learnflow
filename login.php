<?php
require_once __DIR__ . '/includes/bootstrap.php';

$next = lf_safe_next((string)($_GET['next'] ?? ''), '/dashboard');
if (lf_student_current() !== null) {
    header('Location: ' . $next);
    exit;
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        $action = (string)($_POST['action'] ?? 'login');
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($action === 'register') {
            $mode = 'register';
            try {
                if (strlen($password) < 6) throw new InvalidArgumentException('密码至少 6 位');
                $student = student_create([
                    'email' => $email,
                    'password' => $password,
                    'name' => (string)($_POST['name'] ?? ''),
                    'source' => 'register',
                ]);
                lf_student_login((string)$student['id']);
                lf_flash('ok', '注册成功，欢迎加入。');
                header('Location: ' . $next);
                exit;
            } catch (Throwable $e) {
                lf_flash('danger', $e->getMessage());
            }
        } else {
            $student = student_verify($email, $password);
            if ($student === null) {
                lf_flash('danger', '邮箱或密码不正确。');
            } else {
                lf_student_login((string)$student['id']);
                student_touch_login((string)$student['id']);
                header('Location: ' . $next);
                exit;
            }
        }
    }
}

lf_page_start([
    'title' => ($mode === 'register' ? '注册' : '登录') . ' · LearnFlow',
    'active' => '',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:56px">
  <div class="lf-form-card">
    <div class="lf-tabs">
      <a class="lf-tab<?= $mode === 'login' ? ' on' : '' ?>" href="<?= lf_url('/login?next=') ?><?= urlencode($next) ?>">登录</a>
      <a class="lf-tab<?= $mode === 'register' ? ' on' : '' ?>" href="<?= lf_url('/login?mode=register&next=') ?><?= urlencode($next) ?>">注册</a>
    </div>
    <form method="post" action="<?= lf_url('/login?mode=') ?><?= lf_e($mode) ?>&next=<?= urlencode($next) ?>">
      <?= lf_csrf_field() ?>
      <input type="hidden" name="action" value="<?= lf_e($mode) ?>">
      <?php if ($mode === 'register'): ?>
        <div class="lf-field"><label>称呼</label><input class="lf-inp" name="name" placeholder="怎么称呼你"></div>
      <?php endif; ?>
      <div class="lf-field"><label>邮箱</label><input class="lf-inp" type="email" name="email" required autofocus></div>
      <div class="lf-field"><label>密码</label><input class="lf-inp" type="password" name="password" required minlength="6"></div>
      <button class="btn primary block" type="submit"><?= $mode === 'register' ? '注册并进入' : '登录' ?></button>
    </form>
    <?php if ($mode === 'login'): ?>
      <p class="lf-faint" style="margin-top:14px;text-align:center"><a href="<?= lf_url('/forgot-password') ?>">忘记密码？</a></p>
    <?php endif; ?>
    <p class="lf-faint" style="margin-top:10px;text-align:center">购买课程后系统会自动为你开通账号，可直接用下单邮箱登录。</p>
  </div>
</section>
<?php lf_page_end(); ?>
