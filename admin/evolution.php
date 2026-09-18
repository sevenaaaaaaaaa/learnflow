<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'run') {
            $list = evolution_generate();
            lf_flash('ok', '体检完成，发现 ' . count($list) . ' 条建议。');
        } elseif ($action === 'status') {
            evolution_set_status((string)($_POST['id'] ?? ''), (string)($_POST['status'] ?? 'open'));
            lf_flash('ok', '状态已更新。');
        } elseif ($action === 'execute') {
            $res = evolution_execute((string)($_POST['id'] ?? ''));
            lf_flash(!empty($res['ok']) ? 'ok' : 'danger', (string)($res['result'] ?? '执行失败'));
        } elseif ($action === 'ai') {
            if (!ai_enabled()) {
                lf_flash('danger', 'AI 未启用（后台 → 设置 → AI）。');
            } else {
                $text = ai_chat([
                    ['role' => 'system', 'content' => '你是 LearnFlow 的运营与工程改进助理，输出可执行的中文改进计划。'],
                    ['role' => 'user', 'content' => evolution_ai_prompt()],
                ], 0.5, 900);
                if ($text !== null) { evolution_ai_plan_set($text); lf_flash('ok', 'AI 改进计划已生成。'); }
                else lf_flash('danger', 'AI 生成失败。');
            }
        }
    }
    header('Location: ' . lf_url('/admin/evolution.php'));
    exit;
}

$state = evolution_state();
$proposals = evolution_proposals();
$sevLabel = ['high' => '高', 'medium' => '中', 'low' => '低'];

lf_admin_page_start(['title' => '自进化 · LearnFlow 讲师后台', 'active' => 'evolution']);
?>
<div class="lf-admin-head">
  <h1>自进化 · 体检与改进</h1>
  <div class="lf-row" style="flex:0 0 auto;gap:8px">
    <span class="lf-faint" style="align-self:center">上次体检：<?= lf_e((string)($state['at'] ?: '未运行')) ?></span>
    <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="run"><button class="btn primary sm" type="submit">运行体检</button></form>
    <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="ai"><button class="btn ghost sm" type="submit">AI 改进计划</button></form>
  </div>
</div>
<p class="lf-faint">体检聚合工程/内容/运营/业务信号，产出可执行建议；采纳→执行→验证，逐步走向护栏内自治（详见 docs/EVOLUTION.md）。</p>

<?php if ($state['ai_plan'] !== ''): ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h3 style="margin:0 0 8px;font-size:15px">AI 改进计划 <span class="lf-faint" style="font-size:12px"><?= lf_e((string)$state['ai_at']) ?></span></h3>
    <div style="white-space:pre-wrap;font-size:14px;line-height:1.8"><?= lf_e((string)$state['ai_plan']) ?></div>
  </div>
<?php endif; ?>

<?php if (!$proposals): ?>
  <div class="lf-empty">还没有体检记录，点「运行体检」。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>级别</th><th>类别</th><th>建议</th><th>状态</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($proposals as $p): ?>
        <tr>
          <td><span class="lf-chip <?= ($p['severity'] ?? '') === 'high' ? 'danger' : (($p['severity'] ?? '') === 'medium' ? '' : 'soft') ?>"><?= lf_e($sevLabel[$p['severity'] ?? 'low'] ?? '—') ?></span></td>
          <td class="lf-faint"><?= lf_e((string)$p['category']) ?></td>
          <td><b><?= lf_e((string)$p['title']) ?></b><br><span class="lf-faint"><?= lf_e((string)$p['detail']) ?></span><?php if (!empty($p['hint'])): ?> <a class="lf-faint" href="<?= lf_e(lf_url((string)$p['hint'])) ?>">去处理</a><?php endif; ?><?php if (!empty($p['result'])): ?><br><span class="lf-chip soft" style="margin-top:4px"><?= lf_e((string)$p['result']) ?></span><?php endif; ?></td>
          <td>
            <?php $st = (string)($p['status'] ?? 'open'); ?>
            <span class="lf-chip <?= in_array($st, ['resolved', 'verified'], true) ? 'ok' : ($st === 'failed' ? 'danger' : ($st === 'executed' ? '' : 'soft')) ?>"><?= ['open' => '待处理', 'accepted' => '已采纳', 'executed' => '已执行', 'verified' => '已验证', 'resolved' => '已解决', 'ignored' => '已忽略', 'failed' => '执行失败'][$st] ?? $st ?></span>
          </td>
          <td class="lf-row" style="flex-wrap:nowrap;gap:6px">
            <?php if (!empty($p['action']) && in_array($st, ['open', 'accepted', 'failed'], true)): ?>
              <form method="post" style="margin:0" onsubmit="return confirm('执行该动作？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="execute"><input type="hidden" name="id" value="<?= lf_e((string)$p['id']) ?>"><button class="btn primary sm" type="submit">执行<?= $st === 'failed' ? '（重试）' : '' ?></button></form>
            <?php endif; ?>
            <?php foreach ([['accepted', '采纳'], ['resolved', '已解决'], ['ignored', '忽略']] as $b): ?>
              <?php if ($st !== $b[0]): ?>
                <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= lf_e((string)$p['id']) ?>"><input type="hidden" name="status" value="<?= lf_e($b[0]) ?>"><button class="btn subtle sm" type="submit"><?= lf_e($b[1]) ?></button></form>
              <?php endif; ?>
            <?php endforeach; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
