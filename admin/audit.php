<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$log = array_slice(array_reverse(json_read(LF_DATA_DIR . '/admin-audit.json')), 0, 100);
$apiLog = array_slice(array_reverse(json_read(LF_DATA_DIR . '/api-log.json')), 0, 40);

lf_admin_page_start(['title' => '审计 · LearnFlow 讲师后台', 'active' => 'audit']);
?>
<div class="lf-admin-head"><h1>审计日志</h1></div>

<h2 class="lf-sec-title" style="font-size:17px;margin:0 0 10px">后台操作（最近 <?= count($log) ?>）</h2>
<?php if (!$log): ?>
  <div class="lf-empty">暂无记录。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>管理员</th><th>路径</th><th>动作</th><th>IP</th></tr></thead>
    <tbody>
      <?php foreach ($log as $l): ?>
        <tr>
          <td class="lf-faint"><?= lf_e((string)($l['at'] ?? '')) ?></td>
          <td><?= lf_e((string)($l['admin'] ?? '')) ?></td>
          <td class="lf-faint"><?= lf_e((string)($l['path'] ?? '')) ?></td>
          <td><span class="lf-chip soft"><?= lf_e((string)($l['action'] ?: '—')) ?></span></td>
          <td class="lf-faint"><?= lf_e((string)($l['ip'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2 class="lf-sec-title" style="font-size:17px;margin:26px 0 10px">API 调用（最近 <?= count($apiLog) ?>）</h2>
<?php if (!$apiLog): ?>
  <div class="lf-empty">暂无记录。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>时间</th><th>密钥</th><th>工具</th><th>结果</th><th>耗时</th></tr></thead>
    <tbody>
      <?php foreach ($apiLog as $l): ?>
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
