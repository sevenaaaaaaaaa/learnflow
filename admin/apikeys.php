<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LF_ROOT . '/lib/ApiKey.php';
require_once LF_ROOT . '/lib/ApiActions.php';
lf_admin_required();

$newToken = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $created = api_key_create((string)($_POST['name'] ?? ''), (array)($_POST['scopes'] ?? []), (int)($_POST['rate_limit'] ?? 120));
            $newToken = $created['token'];
            lf_flash('ok', '密钥已生成，请立即复制（只显示一次）。');
        } elseif ($action === 'revoke') {
            api_key_revoke((string)($_POST['id'] ?? ''));
            lf_flash('ok', '密钥已停用。');
        } elseif ($action === 'delete') {
            api_key_delete((string)($_POST['id'] ?? ''));
            lf_flash('ok', '密钥已删除。');
        }
    }
    if ($newToken === '') {
        header('Location: ' . lf_url('/admin/apikeys.php'));
        exit;
    }
}

$keys = api_key_all();
$log = array_slice(array_reverse(json_read(LF_DATA_DIR . '/api-log.json')), 0, 20);
$tools = lf_api_tool_list();

lf_admin_page_start(['title' => 'API 密钥 · LearnFlow 讲师后台', 'active' => 'apikeys']);
?>
<div class="lf-admin-head"><h1>API 密钥 / MCP</h1></div>

<?php if ($newToken !== ''): ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:20px;border-color:var(--ok)">
    <h3 style="margin:0 0 8px;font-size:15px">新密钥（只显示这一次）</h3>
    <div class="lf-row">
      <input class="lf-inp" id="newkey" value="<?= lf_e($newToken) ?>" readonly style="font-family:var(--font-mono);flex:1">
      <button class="btn primary sm" type="button" style="flex:0 0 auto" data-lf-copy="<?= lf_e($newToken) ?>">复制</button>
    </div>
  </div>
<?php endif; ?>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <h3 style="margin:0 0 12px;font-size:16px">创建密钥</h3>
  <form method="post">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>名称</label><input class="lf-inp" name="name" placeholder="如 我的 Agent / Zapier" required></div>
      <div class="lf-field" style="margin:0"><label>速率（次/分钟）</label><input class="lf-inp" type="number" min="10" max="6000" name="rate_limit" value="120"></div>
      <div class="lf-field" style="margin:0;flex:0 0 auto">
        <label style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
          <span style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="scopes[]" value="read" checked> 读</span>
          <span style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="scopes[]" value="write" checked> 写</span>
          <span style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="scopes[]" value="ai"> AI</span>
        </label>
      </div>
      <button class="btn primary sm" type="submit" style="flex:0 0 auto">生成密钥</button>
    </div>
  </form>
  <p class="lf-faint" style="margin-top:10px">
    REST：<code>POST <?= lf_e(lf_abs_url('/api/v1')) ?></code>，头 <code>Authorization: Bearer &lt;key&gt;</code>，体 <code>{"tool":"course.list","params":{}}</code><br>
    MCP（HTTP / JSON-RPC 2.0）：<code><?= lf_e(lf_abs_url('/mcp')) ?></code>（initialize / tools/list / tools/call）
  </p>
</div>

<?php if (!$keys): ?>
  <div class="lf-empty">还没有 API 密钥。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>名称</th><th>前缀</th><th>作用域</th><th>调用</th><th>最近使用</th><th>状态</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($keys as $id => $k): ?>
        <tr>
          <td><b><?= lf_e((string)$k['name']) ?></b><br><span class="lf-faint"><?= lf_e((string)$k['created_at']) ?></span></td>
          <td class="lf-cert-no"><?= lf_e((string)$k['prefix']) ?>…</td>
          <td><?php foreach ((array)$k['scopes'] as $s): ?><span class="lf-chip soft"><?= lf_e($s) ?></span><?php endforeach; ?></td>
          <td><?= (int)($k['calls'] ?? 0) ?></td>
          <td class="lf-faint"><?= lf_e((string)($k['last_used_at'] ?: '—')) ?></td>
          <td><?= !empty($k['enabled']) ? '<span class="lf-chip ok">启用</span>' : '<span class="lf-chip danger">停用</span>' ?></td>
          <td class="lf-row" style="flex-wrap:nowrap">
            <?php if (!empty($k['enabled'])): ?><form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= lf_e((string)$id) ?>"><button class="btn subtle sm" type="submit">停用</button></form><?php endif; ?>
            <form method="post" style="margin:0" onsubmit="return confirm('删除该密钥？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= lf_e((string)$id) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2 class="lf-sec-title" style="font-size:17px;margin:24px 0 10px">可调用工具（<?= count($tools) ?>）</h2>
<div style="display:flex;gap:6px;flex-wrap:wrap">
  <?php foreach ($tools as $t): ?><span class="lf-chip soft" title="<?= lf_e($t['description']) ?>"><?= lf_e($t['name']) ?></span><?php endforeach; ?>
</div>

<?php if ($log): ?>
  <h2 class="lf-sec-title" style="font-size:17px;margin:24px 0 10px">最近调用</h2>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>密钥</th><th>工具</th><th>结果</th><th>耗时</th></tr></thead>
    <tbody>
      <?php foreach ($log as $l): ?>
        <tr>
          <td class="lf-faint"><?= lf_e((string)($l['at'] ?? '')) ?></td>
          <td class="lf-faint"><?= lf_e((string)($l['key'] ?? '')) ?></td>
          <td><?= lf_e((string)($l['tool'] ?? '')) ?></td>
          <td><?= !empty($l['ok']) ? '<span class="lf-chip ok">ok</span>' : '<span class="lf-chip danger">err</span>' ?></td>
          <td class="lf-faint"><?= (int)($l['ms'] ?? 0) ?>ms</td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
