<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $section = (string)($_POST['section'] ?? '');
        if ($section === 'site') {
            lf_setting_set('site_name', trim((string)($_POST['site_name'] ?? 'LearnFlow')));
            lf_setting_set('site_slogan', trim((string)($_POST['site_slogan'] ?? '')));
            lf_setting_set('site_description', trim((string)($_POST['site_description'] ?? '')));
            lf_setting_set('site_url', rtrim(trim((string)($_POST['site_url'] ?? '')), '/'));
            lf_setting_set('og_image', trim((string)($_POST['og_image'] ?? '')));
            lf_flash('ok', '站点设置已保存。');
        } elseif ($section === 'smtp') {
            lf_setting_set('smtp', [
                'enabled' => !empty($_POST['smtp_enabled']),
                'host' => trim((string)($_POST['smtp_host'] ?? '')),
                'port' => (int)($_POST['smtp_port'] ?? 465),
                'user' => trim((string)($_POST['smtp_user'] ?? '')),
                'pass' => (string)($_POST['smtp_pass'] ?? ''),
                'secure' => (string)($_POST['smtp_secure'] ?? 'ssl'),
                'from' => trim((string)($_POST['smtp_from'] ?? '')),
                'from_name' => trim((string)($_POST['smtp_from_name'] ?? '')),
            ]);
            lf_flash('ok', 'SMTP 设置已保存。');
        } elseif ($section === 'payflow') {
            lf_setting_set('payflow', [
                'enabled' => !empty($_POST['payflow_enabled']),
                'base_url' => rtrim(trim((string)($_POST['payflow_base_url'] ?? '')), '/'),
                'api_key' => trim((string)($_POST['payflow_api_key'] ?? '')),
                'secret' => trim((string)($_POST['payflow_secret'] ?? '')),
            ]);
            lf_flash('ok', 'PayFlow 设置已保存。');
        } elseif ($section === 'ai') {
            lf_setting_set('ai', [
                'enabled' => !empty($_POST['ai_enabled']),
                'base_url' => rtrim(trim((string)($_POST['ai_base_url'] ?? '')), '/'),
                'api_key' => trim((string)($_POST['ai_api_key'] ?? '')),
                'model' => trim((string)($_POST['ai_model'] ?? 'deepseek-chat')),
                'daily_limit' => (int)($_POST['ai_daily_limit'] ?? 200),
            ]);
            lf_flash('ok', 'AI 设置已保存。');
        } elseif ($section === 'integrations') {
            lf_setting_set('integrations', [
                'userloop' => [
                    'enabled' => !empty($_POST['userloop_enabled']),
                    'url' => trim((string)($_POST['userloop_url'] ?? '')),
                    'secret' => trim((string)($_POST['userloop_secret'] ?? '')),
                ],
                'mflow' => [
                    'enabled' => !empty($_POST['mflow_enabled']),
                    'url' => trim((string)($_POST['mflow_url'] ?? '')),
                    'secret' => trim((string)($_POST['mflow_secret'] ?? '')),
                ],
            ]);
            lf_flash('ok', '互通设置已保存。');
        } elseif ($section === 'mysql') {
            lf_setting_set('mysql', [
                'enabled' => !empty($_POST['mysql_enabled']),
                'driver' => (($_POST['mysql_driver'] ?? 'mysql') === 'sqlite') ? 'sqlite' : 'mysql',
                'host' => trim((string)($_POST['mysql_host'] ?? 'localhost')),
                'port' => (int)($_POST['mysql_port'] ?? 3306),
                'dbname' => trim((string)($_POST['mysql_dbname'] ?? 'learnflow')),
                'user' => trim((string)($_POST['mysql_user'] ?? 'learnflow')),
                'pass' => (string)($_POST['mysql_pass'] ?? ''),
                'sqlite_path' => trim((string)($_POST['mysql_sqlite_path'] ?? '')),
            ]);
            lf_flash('ok', '数据层设置已保存。');
        }
        lf_cache_flush();
    }
    header('Location: ' . lf_url('/admin/settings.php'));
    exit;
}

$payflow = payflow_config();
$smtp = array_merge(['enabled' => false, 'host' => '', 'port' => 465, 'user' => '', 'pass' => '', 'secure' => 'ssl', 'from' => '', 'from_name' => ''], (array)(lf_setting_get('smtp') ?: []));
$ai = array_merge(['enabled' => false, 'base_url' => 'https://api.deepseek.com/v1', 'api_key' => '', 'model' => 'deepseek-chat', 'daily_limit' => 200], (array)(lf_setting_get('ai') ?: []));
$integrations = integrations_config();

lf_admin_page_start(['title' => '设置 · LearnFlow 讲师后台', 'active' => 'settings']);
?>
<div class="lf-admin-head"><h1>设置</h1></div>

<form method="post" style="margin-bottom:20px">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="site">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">站点</h2>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>站点名称</label><input class="lf-inp" name="site_name" value="<?= lf_e((string)lf_setting_get('site_name', 'LearnFlow')) ?>"></div>
      <div class="lf-field" style="margin:0"><label>站点 URL（证书分享/邮件链接用）</label><input class="lf-inp" name="site_url" value="<?= lf_e((string)lf_setting_get('site_url', '')) ?>" placeholder="https://nownexts.com"></div>
    </div>
    <div class="lf-field" style="margin-top:12px"><label>口号</label><input class="lf-inp" name="site_slogan" value="<?= lf_e((string)lf_setting_get('site_slogan', '')) ?>"></div>
    <div class="lf-field" style="margin-top:12px"><label>SEO 描述</label><textarea class="lf-inp" name="site_description"><?= lf_e((string)lf_setting_get('site_description', '')) ?></textarea></div>
    <div class="lf-field" style="margin-top:12px"><label>社交分享图 URL（OG Image）</label><input class="lf-inp" name="og_image" value="<?= lf_e((string)lf_setting_get('og_image', '')) ?>"></div>
    <button class="btn primary sm" type="submit">保存站点设置</button>
  </div>
</form>

<form method="post" style="margin-bottom:20px">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="smtp">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">邮件（SMTP 直发）</h2>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="smtp_enabled" <?= !empty($smtp['enabled']) ? 'checked' : '' ?>> 启用邮件发送（未启用时邮件写入 data/mail-log.json）</label>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>SMTP 主机</label><input class="lf-inp" name="smtp_host" value="<?= lf_e((string)$smtp['host']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>端口</label><input class="lf-inp" type="number" name="smtp_port" value="<?= (int)$smtp['port'] ?>"></div>
      <div class="lf-field" style="margin:0"><label>加密</label><select class="lf-inp" name="smtp_secure"><option value="ssl" <?= $smtp['secure'] === 'ssl' ? 'selected' : '' ?>>SSL (465)</option><option value="tls" <?= $smtp['secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option><option value="none" <?= $smtp['secure'] === 'none' ? 'selected' : '' ?>>无</option></select></div>
    </div>
    <div class="lf-row" style="margin-top:12px">
      <div class="lf-field" style="margin:0"><label>账号</label><input class="lf-inp" name="smtp_user" value="<?= lf_e((string)$smtp['user']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>密码</label><input class="lf-inp" type="password" name="smtp_pass" value="<?= lf_e((string)$smtp['pass']) ?>"></div>
    </div>
    <div class="lf-row" style="margin-top:12px">
      <div class="lf-field" style="margin:0"><label>发件人邮箱</label><input class="lf-inp" name="smtp_from" value="<?= lf_e((string)$smtp['from']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>发件人名称</label><input class="lf-inp" name="smtp_from_name" value="<?= lf_e((string)$smtp['from_name']) ?>"></div>
    </div>
    <button class="btn primary sm" type="submit" style="margin-top:12px">保存邮件设置</button>
  </div>
</form>

<form method="post" style="margin-bottom:20px">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="payflow">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">PayFlow 互通（收款 → 购买即入学）</h2>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="payflow_enabled" <?= !empty($payflow['enabled']) ? 'checked' : '' ?>> 启用 PayFlow 结账</label>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>Base URL</label><input class="lf-inp" name="payflow_base_url" value="<?= lf_e((string)$payflow['base_url']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>API Key</label><input class="lf-inp" name="payflow_api_key" value="<?= lf_e((string)$payflow['api_key']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>Webhook Secret</label><input class="lf-inp" name="payflow_secret" value="<?= lf_e((string)$payflow['secret']) ?>"></div>
    </div>
    <p class="lf-faint" style="margin-top:10px">Webhook：<code><?= lf_e(lf_abs_url('/api/payflow-webhook.php')) ?></code>，签名头 <code>X-PayFlow-Signature</code>。</p>
    <button class="btn primary sm" type="submit" style="margin-top:10px">保存 PayFlow 设置</button>
  </div>
</form>

<form method="post" style="margin-bottom:20px">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="ai">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">AI（DeepSeek，OpenAI 兼容）</h2>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="ai_enabled" <?= !empty($ai['enabled']) ? 'checked' : '' ?>> 启用 AI 能力（作业点评 / 测验生成 / 学习周报）</label>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>Base URL</label><input class="lf-inp" name="ai_base_url" value="<?= lf_e((string)$ai['base_url']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>API Key</label><input class="lf-inp" type="password" name="ai_api_key" value="<?= lf_e((string)$ai['api_key']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>模型</label><input class="lf-inp" name="ai_model" value="<?= lf_e((string)$ai['model']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>每日调用上限</label><input class="lf-inp" type="number" min="0" name="ai_daily_limit" value="<?= (int)$ai['daily_limit'] ?>"></div>
    </div>
    <button class="btn primary sm" type="submit" style="margin-top:12px">保存 AI 设置</button>
  </div>
</form>

<form method="post" style="margin-bottom:20px">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="integrations">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 14px">矩阵互通（出站事件队列）</h2>
    <p class="lf-faint" style="margin:0 0 12px">学习行为/课程事件会入队，由 <code>php bin/drain.php</code>（可配 cron）投递。签名头 <code>X-LF-Signature</code> = HMAC-SHA256(body, secret)。</p>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>UserLoop Webhook URL</label><input class="lf-inp" name="userloop_url" value="<?= lf_e((string)$integrations['userloop']['url']) ?>" placeholder="https://.../userloop/api/events"></div>
      <div class="lf-field" style="margin:0"><label>UserLoop Secret</label><input class="lf-inp" name="userloop_secret" value="<?= lf_e((string)$integrations['userloop']['secret']) ?>"></div>
      <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="userloop_enabled" <?= !empty($integrations['userloop']['enabled']) ? 'checked' : '' ?>> 启用</label>
    </div>
    <div class="lf-row" style="margin-top:12px">
      <div class="lf-field" style="margin:0"><label>MFlow Webhook URL</label><input class="lf-inp" name="mflow_url" value="<?= lf_e((string)$integrations['mflow']['url']) ?>" placeholder="https://.../mflow/api/course-events"></div>
      <div class="lf-field" style="margin:0"><label>MFlow Secret</label><input class="lf-inp" name="mflow_secret" value="<?= lf_e((string)$integrations['mflow']['secret']) ?>"></div>
      <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center"><input type="checkbox" name="mflow_enabled" <?= !empty($integrations['mflow']['enabled']) ? 'checked' : '' ?>> 启用</label>
    </div>
    <button class="btn primary sm" type="submit" style="margin-top:12px">保存互通设置</button>
  </div>
</form>

<div class="lf-form-card" style="max-width:none;margin-bottom:20px">
  <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">无头 API</h2>
  <p class="lf-faint">公开只读接口：<code>GET /api/courses.php</code>（列表）、<code>GET /api/courses.php?slug=xxx</code>（含全文）。课程数据可迁移，不强依赖本前端。</p>
</div>

<?php $mysql = lf_db_config(); $dbStatus = lf_db_status(); ?>
<form method="post">
  <?= lf_csrf_field() ?><input type="hidden" name="section" value="mysql">
  <div class="lf-form-card" style="max-width:none">
    <h2 class="lf-sec-title" style="font-size:17px;margin:0 0 12px">数据层（MySQL 为主 / SQLite 为辅）</h2>
    <p class="lf-faint" style="margin:0 0 12px">启用后，所有 JSON 集合改由数据库读写（事务 + 行锁，避免并发丢写），JSON 文件仍作快照备份；未启用时保持 JSON 文件存储。当前状态：
      <b style="color:<?= !empty($dbStatus['connected']) ? 'var(--ok)' : 'var(--muted)' ?>"><?= lf_e((string)$dbStatus['message']) ?><?= !empty($dbStatus['collections']) ? '（' . (int)$dbStatus['collections'] . ' 个集合）' : '' ?></b>
    </p>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input type="checkbox" name="mysql_enabled" <?= !empty($mysql['enabled']) ? 'checked' : '' ?>> 启用数据库存储</label>
    <div class="lf-row">
      <div class="lf-field" style="margin:0"><label>驱动</label><select class="lf-inp" name="mysql_driver"><option value="mysql" <?= $mysql['driver'] === 'mysql' ? 'selected' : '' ?>>MySQL</option><option value="sqlite" <?= $mysql['driver'] === 'sqlite' ? 'selected' : '' ?>>SQLite</option></select></div>
      <div class="lf-field" style="margin:0"><label>主机</label><input class="lf-inp" name="mysql_host" value="<?= lf_e((string)$mysql['host']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>端口</label><input class="lf-inp" type="number" name="mysql_port" value="<?= (int)$mysql['port'] ?>"></div>
      <div class="lf-field" style="margin:0"><label>库名</label><input class="lf-inp" name="mysql_dbname" value="<?= lf_e((string)$mysql['dbname']) ?>"></div>
    </div>
    <div class="lf-row" style="margin-top:12px">
      <div class="lf-field" style="margin:0"><label>账号</label><input class="lf-inp" name="mysql_user" value="<?= lf_e((string)$mysql['user']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>密码</label><input class="lf-inp" type="password" name="mysql_pass" value="<?= lf_e((string)$mysql['pass']) ?>"></div>
      <div class="lf-field" style="margin:0"><label>SQLite 路径（驱动=SQLite 时）</label><input class="lf-inp" name="mysql_sqlite_path" value="<?= lf_e((string)$mysql['sqlite_path']) ?>"></div>
    </div>
    <p class="lf-faint" style="margin-top:10px">首次启用后，将现有 JSON 导入数据库：<code>php bin/migrate.php</code>（幂等，幂等导入缺失集合）。</p>
    <button class="btn primary sm" type="submit" style="margin-top:10px">保存数据层设置</button>
  </div>
</form>
<?php lf_admin_page_end(); ?>
