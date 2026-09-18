<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'inflo') {
    if (!lf_csrf_check()) { lf_flash('danger', '请求已失效。'); }
    else {
        $res = matrix_call('inflow', '/api/v1/insights', [], 'GET');
        if (empty($res['ok'])) {
            lf_flash('danger', 'inFlow 未就绪/未配置：' . (string)($res['error'] ?? ('HTTP ' . (int)($res['code'] ?? 0))));
        } else {
            $body = (string)$res['body'];
            $draft = ai_draft_save('topic', 'inFlow 选题灵感', mb_substr($body, 0, 4000));
            lf_flash('ok', '已取回 inFlow 洞察并存入草稿库（' . $draft['id'] . '）。');
        }
    }
    header('Location: ' . lf_url('/admin/ai.php'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'course_from_outline') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
        header('Location: ' . lf_url('/admin/ai.php'));
        exit;
    }
    $data = json_decode((string)($_POST['payload'] ?? ''), true);
    if (!is_array($data) || empty($data['chapters'])) {
        lf_flash('danger', '大纲数据无效。');
        header('Location: ' . lf_url('/admin/ai.php'));
        exit;
    }
    $chapters = [];
    foreach ((array)$data['chapters'] as $ch) {
        $lessons = [];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $lessons[] = ['title' => (string)($l['title'] ?? ''), 'type' => in_array(($l['type'] ?? 'article'), ['article', 'video', 'quiz', 'file', 'live'], true) ? $l['type'] : 'article', 'duration' => (int)($l['duration'] ?? 0)];
        }
        $chapters[] = ['title' => (string)($ch['title'] ?? ''), 'summary' => (string)($ch['summary'] ?? ''), 'lessons' => $lessons];
    }
    $course = course_save(course_normalize(['title' => (string)($data['title'] ?? '未命名课程'), 'status' => 'draft', 'chapters' => $chapters]));
    lf_flash('ok', '已根据资料创建课程草稿。');
    header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$course['id'])));
    exit;
}

$courses = course_all();
$drafts = array_slice(ai_drafts(), 0, 20);
$types = ai_marketing_types();

lf_admin_page_start(['title' => 'AI 工作台 · LearnFlow 讲师后台', 'active' => 'ai']);
?>
<div class="lf-admin-head"><h1>AI 内容工作台</h1></div>

<div class="lf-grid" style="grid-template-columns:1fr 1fr;align-items:start">
  <div class="lf-form-card" style="max-width:none">
    <h3 style="margin:0 0 12px;font-size:16px">营销文案生成</h3>
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" id="mkt-type"><?php foreach ($types as $k => $v): ?><option value="<?= lf_e($k) ?>"><?= lf_e($v) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field" style="margin:0;flex:2"><label>主题 / 课程</label><input class="lf-inp" id="mkt-topic" placeholder="如：R.B.E 训练营第 4 期"></div>
    </div>
    <div class="lf-field" style="margin-top:10px"><label>补充信息（可选）</label><input class="lf-inp" id="mkt-extra" placeholder="价格、卖点、时间等"></div>
    <button class="btn primary sm" type="button" id="mkt-go" style="margin-top:10px">生成</button>
    <div id="mkt-out" class="lf-editor" style="display:none;margin-top:12px"><div class="lf-editor-area" id="mkt-out-body"></div></div>
    <div id="mkt-actions" style="display:none;margin-top:8px"><button class="btn ghost sm" type="button" data-lf-copy="" id="mkt-copy">复制</button></div>
  </div>

  <div class="lf-form-card" style="max-width:none">
    <h3 style="margin:0 0 12px;font-size:16px">课时讲义生成</h3>
    <div class="lf-field"><label>课程</label><select class="lf-inp" id="ls-course" onchange="lfLoadLessons()"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
    <div class="lf-field"><label>课时</label><select class="lf-inp" id="ls-lesson"></select></div>
    <div class="lf-field"><label>要求（可选）</label><input class="lf-inp" id="ls-hint" placeholder="篇幅/风格/重点"></div>
    <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px"><input type="checkbox" id="ls-write" checked> 生成后直接写入课时正文</label>
    <button class="btn primary sm" type="button" id="ls-go">生成讲义</button>
    <div id="ls-out" class="lf-editor" style="display:none;margin-top:12px"><div class="lf-editor-area" id="ls-out-body"></div></div>
  </div>
</div>

<div class="lf-form-card" style="max-width:none;margin-top:20px">
  <h3 style="margin:0 0 12px;font-size:16px">从资料生成课程（支持 txt / md / html / docx / pptx）</h3>
  <div class="lf-row" style="align-items:flex-end">
    <div class="lf-field" style="margin:0"><label>课程标题（可选）</label><input class="lf-inp" id="mat-title"></div>
    <div class="lf-field" style="margin:0;flex:1"><label>资料文件</label><input class="lf-inp" type="file" id="mat-file" accept=".txt,.md,.csv,.html,.docx,.pptx"></div>
    <button class="btn primary sm" type="button" id="mat-go" style="flex:0 0 auto">生成大纲</button>
  </div>
  <div id="mat-out" style="display:none;margin-top:14px"></div>
</div>

<?php $ms = matrix_status(); ?>
<div class="lf-form-card" style="max-width:none;margin-top:20px">
  <div class="lf-row" style="justify-content:space-between">
    <h3 style="margin:0;font-size:16px">矩阵联动</h3>
    <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="inflo"><button class="btn ghost sm" type="submit">从 inFlow 取选题</button></form>
  </div>
  <div class="lf-row" style="gap:8px;flex-wrap:wrap;margin-top:10px">
    <?php foreach ($ms as $pk => $st): ?>
      <span class="lf-chip <?= empty($st['enabled']) ? 'soft' : (!empty($st['ok']) ? 'ok' : 'danger') ?>"><?= lf_e($pk) ?>：<?= empty($st['enabled']) ? '未启用' : lf_e((string)$st['detail']) ?></span>
    <?php endforeach; ?>
  </div>
  <p class="lf-faint" style="margin-top:8px">在「设置 → 矩阵互通」配置各产品 Base URL 与 Token。</p>
</div>

<?php if ($drafts): ?>
  <h2 class="lf-sec-title" style="font-size:17px;margin:24px 0 10px">文案草稿</h2>
  <table class="lf-table">
    <thead><tr><th>类型</th><th>主题</th><th>时间</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($drafts as $d): ?>
        <tr>
          <td><span class="lf-chip soft"><?= lf_e((string)($types[(string)$d['kind']] ?? $d['kind'])) ?></span></td>
          <td><?= lf_e((string)$d['title']) ?></td>
          <td class="lf-faint"><?= lf_e((string)$d['created_at']) ?></td>
          <td><button class="btn subtle sm" type="button" data-lf-copy="<?= lf_e((string)$d['content']) ?>">复制</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<script>
(function () {
  var TOKEN = '<?= lf_csrf_token() ?>', API = '<?= lf_url('/api/ai.php') ?>';
  var COURSES = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'title' => $c['title'] ?? '', 'lessons' => array_map(fn($l) => ['id' => $l['id'], 'title' => $l['title'] ?? ''], course_lessons($c))], $courses), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  window.lfLoadLessons = function () {
    var cid = document.getElementById('ls-course').value;
    var sel = document.getElementById('ls-lesson'); sel.innerHTML = '';
    var c = COURSES.find(function (x) { return x.id === cid; });
    (c ? c.lessons : []).forEach(function (l) { var o = document.createElement('option'); o.value = l.id; o.textContent = l.title; sel.appendChild(o); });
  };
  lfLoadLessons();

  function post(fd, done) {
    fd.append('_token', TOKEN);
    fetch(API, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(done).catch(function () { alert('网络错误'); });
  }
  function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); }

  document.getElementById('mkt-go').addEventListener('click', function () {
    var b = this; var old = b.textContent; b.disabled = true; b.textContent = '生成中…';
    var fd = new FormData(); fd.append('action', 'marketing');
    fd.append('type', document.getElementById('mkt-type').value);
    fd.append('topic', document.getElementById('mkt-topic').value);
    fd.append('extra', document.getElementById('mkt-extra').value);
    post(fd, function (d) {
      b.disabled = false; b.textContent = old;
      if (!d.ok) { alert(d.error || 'AI 失败'); return; }
      document.getElementById('mkt-out').style.display = 'block';
      document.getElementById('mkt-out-body').innerHTML = '<pre style="white-space:pre-wrap">' + esc(d.content) + '</pre>';
      document.getElementById('mkt-actions').style.display = 'block';
      document.getElementById('mkt-copy').setAttribute('data-lf-copy', d.content);
    });
  });

  document.getElementById('ls-go').addEventListener('click', function () {
    var b = this; var old = b.textContent; b.disabled = true; b.textContent = '生成中…';
    var fd = new FormData(); fd.append('action', 'lesson_content');
    fd.append('course_id', document.getElementById('ls-course').value);
    fd.append('lesson_id', document.getElementById('ls-lesson').value);
    fd.append('hint', document.getElementById('ls-hint').value);
    if (document.getElementById('ls-write').checked) fd.append('write', '1');
    post(fd, function (d) {
      b.disabled = false; b.textContent = old;
      if (!d.ok) { alert(d.error || 'AI 失败'); return; }
      document.getElementById('ls-out').style.display = 'block';
      document.getElementById('ls-out-body').innerHTML = d.html;
    });
  });

  document.getElementById('mat-go').addEventListener('click', function () {
    var file = document.getElementById('mat-file').files[0];
    if (!file) { alert('请选择资料文件'); return; }
    var b = this; var old = b.textContent; b.disabled = true; b.textContent = '生成中…';
    var fd = new FormData(); fd.append('action', 'from_material');
    fd.append('title', document.getElementById('mat-title').value);
    fd.append('file', file);
    post(fd, function (d) {
      b.disabled = false; b.textContent = old;
      if (!d.ok) { alert(d.error || '失败'); return; }
      var box = document.getElementById('mat-out'); box.style.display = 'block';
      var html = '<div class="lf-flash ok">已读取 ' + d.chars + ' 字，生成大纲：</div>';
      html += '<div style="margin-top:10px">';
      (d.chapters || []).forEach(function (ch) {
        html += '<div class="lf-chip soft" style="margin:2px 0">' + esc(ch.title) + '</div><ul style="margin:4px 0 10px 18px">';
        (ch.lessons || []).forEach(function (l) { html += '<li class="lf-faint">' + esc(l.title) + '</li>'; });
        html += '</ul>';
      });
      html += '</div>';
      html += '<form method="post"><input type="hidden" name="_token" value="' + TOKEN + '"><input type="hidden" name="action" value="course_from_outline"><input type="hidden" name="payload" id="mat-payload"><button class="btn primary sm" type="submit">创建课程并写入大纲</button></form>';
      box.innerHTML = html;
      document.getElementById('mat-payload').value = JSON.stringify({ title: d.title || document.getElementById('mat-title').value, chapters: d.chapters });
    });
  });
})();
</script>
<?php lf_admin_page_end(); ?>
