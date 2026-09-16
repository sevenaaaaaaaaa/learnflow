<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        lf_setting_set('site_name', trim((string)($_POST['site_name'] ?? 'LearnFlow')));
        lf_setting_set('site_slogan', trim((string)($_POST['site_slogan'] ?? '')));
        lf_setting_set('site_url', rtrim(trim((string)($_POST['site_url'] ?? '')), '/'));
        lf_setting_set('payflow', [
            'enabled' => !empty($_POST['payflow_enabled']),
            'base_url' => rtrim(trim((string)($_POST['payflow_base_url'] ?? '')), '/'),
            'api_key' => trim((string)($_POST['payflow_api_key'] ?? '')),
            'secret' => trim((string)($_POST['payflow_secret'] ?? '')),
        ]);
        lf_cache_flush();
        lf_flash('ok', '设置已保存。');
    }
    header('Location: ' . lf_url('/admin/settings.php'));
    exit;
}

$payflow = payflow_config();
lf_admin_page_start(['title' => '设置 · LearnFlow 讲师后台', 'active' => 'settings']);
?>
<div class="lf-admin-head"><h1>设置</h1></div>

<form method="post">
  <?= lf_csrf_field() ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">站点</h2>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>站点名称</label><input class="lf-inp" name="site_name" value="<?= lf_e((string)lf_setting_get('site_name', 'LearnFlow')) ?>"></div>
      <div class="lf-field" style="margin:0"><label>站点 URL（用于证书分享链接）</label><input class="lf-inp" name="site_url" value="<?= lf_e((string)lf_setting_get('site_url', '')) ?>" placeholder="https://learnflow.nownexts.com"></div>
    </div>
    <div class="lf-field" style="margin-top:12px"><label>口号</label><input class="lf-inp" name="site_slogan" value="<?= lf_e((string)lf_setting_get('site_slogan', '')) ?>"></div>
  </div>

  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">PayFlow 互通（收款 → 购买即入学）</h2>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="payflow_enabled" <?= !empty($payflow['enabled']) ? 'checked' : '' ?>> 启用 PayFlow 结账</label>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>Base URL</label><input class="lf-inp" name="payflow_base_url" value="<?= lf_e((string)$payflow['base_url']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>API Key</label><input class="lf-inp" name="payflow_api_key" value="<?= lf_e((string)$payflow['api_key']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>Webhook Secret</label><input class="lf-inp" name="payflow_secret" value="<?= lf_e((string)$payflow['secret']) ?>"></div>
    </div>
    <p class="lf-faint" style="margin-top:10px">Webhook 地址：<code><?= lf_e(lf_abs_url('/api/payflow-webhook.php')) ?></code>，签名头 <code>X-PayFlow-Signature</code> = HMAC-SHA256(body, secret)。</p>
  </div>

  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">无头 API</h2>
    <p class="lf-faint">公开只读接口：<code>GET /api/courses.php</code>（列表）、<code>GET /api/courses.php?slug=xxx</code>（含全文）。课程数据可迁移，不强依赖本前端。</p>
  </div>

  <button class="btn primary" type="submit">保存设置</button>
</form>
<?php lf_admin_page_end(); ?>
