<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $key = (string)($_POST['key'] ?? '');
        if ($action === 'status') {
            strategy_set_status($key, (string)($_POST['status'] ?? 'active'));
            lf_flash('ok', '策略状态已更新。');
        } elseif ($action === 'ab') {
            $r = strategy_ab_generate($key);
            lf_flash(!empty($r['ok']) ? 'ok' : 'danger', !empty($r['ok']) ? '已生成 A/B 两版文案。' : (string)($r['error'] ?? '生成失败'));
        } elseif ($action === 'choose') {
            $s = strategy_find($key);
            if ($s !== null) strategy_set_variants($key, (array)($s['variants'] ?? []), (string)($_POST['label'] ?? 'A'));
            lf_flash('ok', '已选定主用版本。');
        }
    }
    header('Location: ' . lf_url('/admin/strategies.php'));
    exit;
}

$strategies = strategy_all();
$stats = strategy_stats();
$verdictLabels = ['effective' => '有效', 'ineffective' => '无效'];

lf_admin_page_start(['title' => '策略库 · LearnFlow 讲师后台', 'active' => 'strategies']);
?>
<div class="lf-admin-head"><h1>策略库（复利）</h1></div>
<p class="lf-faint">每次「执行」沉淀为策略；体检信号消失记为「有效」，执行失败记为「无效」，据此淘汰或保留，形成越用越准的策略库。</p>

<div class="lf-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:20px">
  <div class="lf-stat"><b><?= (int)$stats['count'] ?></b><span>策略</span></div>
  <div class="lf-stat"><b><?= (int)$stats['runs'] ?></b><span>执行次数</span></div>
  <div class="lf-stat"><b><?= (int)$stats['successes'] ?>/<?= (int)$stats['failures'] ?></b><span>有效/无效</span></div>
  <div class="lf-stat"><b><?= $stats['success_rate'] ?>%</b><span>有效率</span></div>
</div>

<?php if (!$strategies): ?>
  <div class="lf-empty">还没有策略——在「自进化」页执行提案后会在此沉淀。</div>
<?php else: ?>
  <?php foreach ($strategies as $s): $rate = ((int)$s['successes'] + (int)$s['failures']) > 0 ? (int)round((int)$s['successes'] / ((int)$s['successes'] + (int)$s['failures']) * 100) : 0; ?>
    <div class="lf-form-card" style="max-width:none;margin-bottom:14px">
      <div class="lf-row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <b><?= lf_e((string)$s['title']) ?></b>
          <span class="lf-chip soft"><?= lf_e((string)$s['category']) ?></span>
          <span class="lf-chip soft"><?= lf_e((string)$s['action_type']) ?></span>
          <span class="lf-chip <?= ($s['status'] ?? 'active') === 'active' ? 'ok' : 'danger' ?>"><?= ($s['status'] ?? 'active') === 'active' ? '启用' : '已淘汰' ?></span>
          <div class="lf-faint" style="margin-top:6px">执行 <?= (int)$s['runs'] ?> 次 · 有效 <?= (int)$s['successes'] ?> / 无效 <?= (int)$s['failures'] ?> · 有效率 <?= $rate ?>% · 最近判定 <?= lf_e($verdictLabels[(string)($s['last_verdict'] ?? '')] ?? '—') ?></div>
        </div>
        <div class="lf-row" style="flex:0 0 auto;gap:6px">
          <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="key" value="<?= lf_e((string)$s['key']) ?>"><input type="hidden" name="status" value="<?= ($s['status'] ?? 'active') === 'active' ? 'retired' : 'active' ?>"><button class="btn subtle sm" type="submit"><?= ($s['status'] ?? 'active') === 'active' ? '淘汰' : '恢复' ?></button></form>
          <?php if (($s['action_type'] ?? '') === 'marketing'): ?>
            <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="ab"><input type="hidden" name="key" value="<?= lf_e((string)$s['key']) ?>"><button class="btn ghost sm" type="submit">生成 A/B</button></form>
          <?php endif; ?>
        </div>
      </div>
      <?php if (!empty($s['variants'])): ?>
        <div style="margin-top:12px;display:grid;gap:8px">
          <?php foreach ((array)$s['variants'] as $v): $isChosen = (string)($s['chosen'] ?? '') === (string)$v['label']; ?>
            <div style="border:1px solid <?= $isChosen ? 'var(--accent)' : 'var(--border-soft)' ?>;border-radius:10px;padding:10px;font-size:13px;white-space:pre-wrap">
              <div class="lf-row" style="justify-content:space-between"><b>版本 <?= lf_e((string)$v['label']) ?><?= $isChosen ? ' · 主用' : '' ?></b>
                <?php if (!$isChosen): ?><form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="choose"><input type="hidden" name="key" value="<?= lf_e((string)$s['key']) ?>"><input type="hidden" name="label" value="<?= lf_e((string)$v['label']) ?>"><button class="btn subtle sm" type="submit">设为主用</button></form><?php endif; ?>
              </div>
              <div style="margin-top:6px"><?= lf_e((string)$v['content']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
