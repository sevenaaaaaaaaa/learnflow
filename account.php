<?php
require_once __DIR__ . '/includes/bootstrap.php';

$student = lf_student_required();
$sid = (string)$student['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'profile') {
            student_update($sid, [
                'name' => trim((string)($_POST['name'] ?? '')),
                'phone' => trim((string)($_POST['phone'] ?? '')),
            ]);
            lf_flash('ok', '资料已更新。');
        } elseif ($action === 'password') {
            $current = (string)($_POST['current'] ?? '');
            $next = (string)($_POST['password'] ?? '');
            if (empty($student['password_hash'])) {
                if (strlen($next) < 6) {
                    lf_flash('danger', '密码至少 6 位。');
                } else {
                    student_set_password($sid, $next);
                    lf_flash('ok', '密码已设置。');
                }
            } elseif (!password_verify($current, (string)$student['password_hash'])) {
                lf_flash('danger', '当前密码不正确。');
            } elseif (strlen($next) < 6) {
                lf_flash('danger', '新密码至少 6 位。');
            } else {
                student_set_password($sid, $next);
                lf_flash('ok', '密码已更新。');
            }
        }
    }
    header('Location: ' . lf_url('/account'));
    exit;
}

$student = student_get($sid);
lf_page_start([
    'title' => '账户设置 · LearnFlow',
    'active' => 'dashboard',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:34px;max-width:680px;margin:0 auto">
  <div class="lf-sec-head">
    <div>
      <span class="lf-kicker">Account</span>
      <h2 class="lf-sec-title">账户设置</h2>
    </div>
    <a class="btn subtle sm" href="<?= lf_url('/dashboard') ?>">返回我的学习</a>
  </div>

  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h3 style="margin:0 0 14px;font-size:16px">基本资料</h3>
    <form method="post">
      <?= lf_csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <div class="lf-field"><label>称呼</label><input class="lf-inp" name="name" value="<?= lf_e((string)($student['name'] ?? '')) ?>"></div>
      <div class="lf-field"><label>邮箱（登录名，不可修改）</label><input class="lf-inp" value="<?= lf_e((string)($student['email'] ?? '')) ?>" disabled></div>
      <div class="lf-field"><label>手机号</label><input class="lf-inp" name="phone" value="<?= lf_e((string)($student['phone'] ?? '')) ?>"></div>
      <button class="btn primary sm" type="submit">保存资料</button>
    </form>
  </div>

  <div class="lf-form-card" style="max-width:none">
    <h3 style="margin:0 0 14px;font-size:16px"><?= empty($student['password_hash']) ? '设置密码' : '修改密码' ?></h3>
    <form method="post">
      <?= lf_csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <?php if (!empty($student['password_hash'])): ?>
        <div class="lf-field"><label>当前密码</label><input class="lf-inp" type="password" name="current" required></div>
      <?php endif; ?>
      <div class="lf-field"><label>新密码</label><input class="lf-inp" type="password" name="password" required minlength="6"></div>
      <button class="btn primary sm" type="submit"><?= empty($student['password_hash']) ? '设置密码' : '更新密码' ?></button>
    </form>
  </div>
</section>
<?php lf_page_end(); ?>
