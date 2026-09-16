<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (lf_admin_current() !== null) {
    header('Location: ' . lf_url('/admin/'));
    exit;
}

$needsSetup = lf_admin_count() === 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        if ($needsSetup) {
            try {
                if ($username === '') throw new InvalidArgumentException('请输入账号');
                if (strlen($password) < 6) throw new InvalidArgumentException('密码至少 6 位');
                lf_admin_create($username, $password, (string)($_POST['name'] ?? ''));
                lf_admin_login($username);
                lf_flash('ok', '管理员创建成功。');
                header('Location: ' . lf_url('/admin/'));
                exit;
            } catch (Throwable $e) {
                lf_flash('danger', $e->getMessage());
            }
        } elseif (lf_admin_authenticate($username, $password)) {
            lf_admin_login($username);
            header('Location: ' . lf_url('/admin/'));
            exit;
        } else {
            lf_flash('danger', '账号或密码错误。');
        }
    }
}

lf_page_start([
    'title' => '讲师登录 · LearnFlow',
    'container' => true,
    'bare' => true,
]);
?>
<section class="lf-sec" style="padding-top:64px">
  <div class="lf-form-card">
    <span class="lf-kicker"><?= $needsSetup ? 'Setup' : 'Admin' ?></span>
    <h2 class="lf-sec-title" style="font-size:26px;margin:8px 0 18px"><?= $needsSetup ? '创建管理员' : '讲师后台登录' ?></h2>
    <form method="post">
      <?= lf_csrf_field() ?>
      <?php if ($needsSetup): ?>
        <div class="lf-field"><label>称呼</label><input class="lf-inp" name="name" placeholder="可选"></div>
      <?php endif; ?>
      <div class="lf-field"><label>账号</label><input class="lf-inp" name="username" required autofocus></div>
      <div class="lf-field"><label>密码</label><input class="lf-inp" type="password" name="password" required minlength="6"></div>
      <button class="btn primary block" type="submit"><?= $needsSetup ? '创建并进入' : '登录' ?></button>
    </form>
  </div>
</section>
<?php lf_page_end(); ?>
