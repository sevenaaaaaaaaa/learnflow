<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$next = lf_safe_next((string)($_REQUEST['next'] ?? ''), '/admin/');
if (str_contains($next, '/admin/login.php') || str_contains($next, '/logout')) $next = lf_url('/admin/');

if (lf_admin_current() !== null) {
    header('Location: ' . $next);
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
                header('Location: ' . $next);
                exit;
            } catch (Throwable $e) {
                lf_flash('danger', $e->getMessage());
            }
        } else {
            $rlKey = 'adminlogin:' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . ':' . strtolower($username);
            $rl = lf_throttle($rlKey, 10, 600);
            if (empty($rl['allowed'])) {
                lf_flash('danger', '尝试次数过多，请 ' . (int)ceil($rl['retry'] / 60) . ' 分钟后再试。');
            } elseif (lf_admin_authenticate($username, $password)) {
                lf_throttle_reset($rlKey);
                lf_admin_login($username);
                header('Location: ' . $next);
                exit;
            } else {
                lf_flash('danger', '账号或密码错误。');
            }
        }
    }
}

$siteName = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
lf_page_start([
    'title' => '讲师登录 · ' . $siteName,
    'description' => $siteName . ' 讲师后台登录',
    'container' => true,
    'bare' => true,
]);
?>
<section class="lf-sec" style="padding-top:64px">
  <div class="lf-form-card">
    <div class="lf-sec-head" style="margin-bottom:6px">
      <span class="lf-kicker"><?= $needsSetup ? 'Setup' : 'Admin' ?></span>
      <a class="lf-faint" href="<?= lf_url('/courses') ?>">学员入口 →</a>
    </div>
    <h2 class="lf-sec-title" style="font-size:26px;margin:8px 0 18px"><?= $needsSetup ? '创建管理员' : '讲师后台登录' ?></h2>
    <form method="post">
      <?= lf_csrf_field() ?>
      <input type="hidden" name="next" value="<?= lf_e($next) ?>">
      <?php if ($needsSetup): ?>
        <div class="lf-field"><label>称呼</label><input class="lf-inp" name="name" placeholder="可选"></div>
      <?php endif; ?>
      <div class="lf-field"><label>账号</label><input class="lf-inp" name="username" required autofocus></div>
      <div class="lf-field"><label>密码</label><input class="lf-inp" type="password" name="password" required minlength="6"></div>
      <button class="btn primary block" type="submit"><?= $needsSetup ? '创建并进入' : '登录' ?></button>
    </form>
    <?php if (!$needsSetup): ?>
      <p class="lf-faint" style="margin-top:14px;text-align:center"><a href="<?= lf_url('/') ?>">← 返回首页入口</a></p>
    <?php endif; ?>
  </div>
</section>
<?php lf_page_end(); ?>
