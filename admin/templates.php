<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $key = (string)($_POST['key'] ?? '');
        if ($key !== '' && isset(template_defaults()[$key])) {
            template_save($key, (string)($_POST['subject'] ?? ''), (string)($_POST['body'] ?? ''));
            lf_flash('ok', '模板已保存。');
        }
    }
    header('Location: ' . lf_url('/admin/templates.php'));
    exit;
}

$templates = templates_all();

lf_admin_page_start(['title' => '文案模板 · LearnFlow 讲师后台', 'active' => 'templates']);
?>
<div class="lf-admin-head"><h1>通知 / 邮件文案模板</h1></div>
<p class="lf-faint">可用变量：<code>{site}</code> <code>{name}</code> <code>{email}</code> <code>{course}</code> <code>{title}</code> <code>{feedback}</code> <code>{url}</code>。邮件正文支持 HTML。</p>

<?php foreach ($templates as $key => $t): ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:16px">
    <div class="lf-admin-head" style="margin-bottom:10px">
      <h3 style="margin:0;font-size:15px"><?= lf_e((string)$t['label']) ?> <span class="lf-faint" style="font-size:12px">（<?= lf_e($key) ?>）</span></h3>
    </div>
    <form method="post">
      <?= lf_csrf_field() ?><input type="hidden" name="key" value="<?= lf_e($key) ?>">
      <div class="lf-field"><label>标题 / 主题</label><input class="lf-inp" name="subject" value="<?= lf_e((string)$t['subject']) ?>"></div>
      <div class="lf-field"><label>正文</label><textarea class="lf-inp lf-rich" name="body" style="min-height:90px"><?= lf_e((string)$t['body']) ?></textarea></div>
      <button class="btn primary sm" type="submit">保存</button>
    </form>
  </div>
<?php endforeach; ?>
<?php lf_admin_page_end(); ?>
