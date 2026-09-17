<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

$id = (string)($_GET['id'] ?? '');
$quiz = $id !== '' ? quiz_find($id) : null;
if ($quiz === null) {
    lf_flash('danger', '测验不存在。');
    header('Location: ' . lf_url('/admin/quizzes.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
        header('Location: ' . lf_url('/admin/quiz-edit.php?id=' . urlencode($id)));
        exit;
    }
    $questions = [];
    foreach ((array)($_POST['questions'] ?? []) as $q) {
        $options = [];
        foreach ((array)($q['options'] ?? []) as $opt) {
            $text = trim((string)($opt['text'] ?? ''));
            if ($text === '') continue;
            $options[] = ['id' => (string)($opt['id'] ?? ('o_' . bin2hex(random_bytes(2)))), 'text' => $text];
        }
        $answer = array_values((array)($q['answer'] ?? []));
        $answer = array_map('strval', array_filter($answer, fn($v) => $v !== ''));
        $questions[] = [
            'id' => (string)($q['id'] ?? ''),
            'type' => in_array(($q['type'] ?? 'single'), ['single', 'multiple', 'judge'], true) ? $q['type'] : 'single',
            'title' => trim((string)($q['title'] ?? '')),
            'score' => max(1, (int)($q['score'] ?? 1)),
            'explanation' => (string)($q['explanation'] ?? ''),
            'options' => $options,
            'answer' => $answer,
        ];
    }
    $saved = quiz_save([
        'id' => $id,
        'title' => trim((string)($_POST['title'] ?? $quiz['title'])),
        'course_id' => (string)($_POST['course_id'] ?? $quiz['course_id']),
        'kind' => (string)($_POST['kind'] ?? $quiz['kind']),
        'pass_score' => (int)($_POST['pass_score'] ?? 0),
        'lesson_id' => (string)($_POST['lesson_id'] ?? ($quiz['lesson_id'] ?? '')),
        'questions' => $questions,
    ]);
    lf_flash('ok', '测验已保存。');
    header('Location: ' . lf_url('/admin/quiz-edit.php?id=' . urlencode((string)$saved['id'])));
    exit;
}

$courses = course_all();
$questions = (array)($quiz['questions'] ?? []);

lf_admin_page_start(['title' => '编辑测验 · LearnFlow 讲师后台', 'active' => 'courses']);
?>
<div class="lf-admin-head"><h1>编辑测验</h1><a class="btn subtle sm" href="<?= lf_url('/admin/quizzes.php') ?>">返回列表</a></div>

<form method="post">
  <?= lf_csrf_field() ?>
  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <div class="lf-row">
      <div class="lf-field" style="margin:0;flex:2"><label>标题</label><input class="lf-inp" name="title" value="<?= lf_e((string)($quiz['title'] ?? '')) ?>" required></div>
      <div class="lf-field" style="margin:0"><label>课程</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>" <?= ($quiz['course_id'] ?? '') === ($c['id'] ?? '') ? 'selected' : '' ?>><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" name="kind"><option value="chapter" <?= ($quiz['kind'] ?? '') === 'chapter' ? 'selected' : '' ?>>章节测验</option><option value="final" <?= ($quiz['kind'] ?? '') === 'final' ? 'selected' : '' ?>>结业测验</option></select></div>
      <div class="lf-field" style="margin:0"><label>及格分（0=自动 60%）</label><input class="lf-inp" type="number" min="0" name="pass_score" value="<?= (int)($quiz['pass_score'] ?? 0) ?>"></div>
    </div>
    <div class="lf-field" style="margin-top:12px"><label>关联课时 ID（可选，用于完课时自动标记）</label><input class="lf-inp" name="lesson_id" value="<?= lf_e((string)($quiz['lesson_id'] ?? '')) ?>"></div>
  </div>

  <div class="lf-admin-head">
    <h2 class="lf-sec-title" style="font-size:18px">题目</h2>
    <div class="lf-row" style="flex:0 0 auto;gap:6px">
      <input class="lf-inp" id="ai-topic" placeholder="AI 出题主题" style="height:38px;width:180px">
      <input class="lf-inp" id="ai-count" type="number" min="1" max="10" value="3" style="height:38px;width:70px">
      <button class="btn ghost sm" type="button" id="ai-gen">AI 生成</button>
      <button class="btn ghost sm" type="button" id="add-q">+ 添加题目</button>
    </div>
  </div>
  <div id="questions"></div>

  <div style="margin-top:20px"><button class="btn primary" type="submit">保存测验</button></div>
</form>

<template id="tpl-q">
  <div class="lf-form-card q-box" style="max-width:none;margin-bottom:14px" data-q>
    <input type="hidden" data-name="id">
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0;flex:3"><label>题干</label><input class="lf-inp" data-name="title"></div>
      <div class="lf-field" style="margin:0"><label>题型</label><select class="lf-inp" data-name="type" data-type><option value="single">单选</option><option value="multiple">多选</option><option value="judge">判断</option></select></div>
      <div class="lf-field" style="margin:0"><label>分值</label><input class="lf-inp" type="number" min="1" data-name="score" value="1"></div>
      <button class="btn subtle sm" type="button" style="flex:0 0 auto" data-remove-q>移除</button>
    </div>
    <div class="options" style="margin-top:12px"></div>
    <button class="btn subtle sm" type="button" data-add-opt>+ 选项</button>
    <div class="lf-field" style="margin-top:10px"><label>解析</label><input class="lf-inp" data-name="explanation"></div>
  </div>
</template>

<template id="tpl-opt">
  <div class="lf-row opt-row" style="align-items:center;margin-bottom:8px" data-opt>
    <input type="radio" data-name="answer" value="" style="flex:0 0 auto">
    <input class="lf-inp" data-name="text" placeholder="选项内容" style="flex:1">
    <input type="hidden" data-name="id">
    <button class="btn subtle sm" type="button" style="flex:0 0 auto" data-remove-opt>×</button>
  </div>
</template>

<script>
(function () {
  var wrap = document.getElementById('questions');
  var tplQ = document.getElementById('tpl-q');
  var tplOpt = document.getElementById('tpl-opt');
  var initial = <?= json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function setVal(box, name, val) { var el = box.querySelector('[data-name="' + name + '"]'); if (el) { if (el.type === 'checkbox') el.checked = !!val; else el.value = val == null ? '' : val; } }
  function reindex() {
    Array.prototype.forEach.call(wrap.querySelectorAll('[data-q]'), function (q, qi) {
      Array.prototype.forEach.call(q.querySelectorAll('[data-name]'), function (el) {
        if (el.closest('[data-opt]')) return;
        el.name = 'questions[' + qi + '][' + el.getAttribute('data-name') + ']';
      });
      Array.prototype.forEach.call(q.querySelectorAll('[data-opt]'), function (opt, oi) {
        Array.prototype.forEach.call(opt.querySelectorAll('[data-name]'), function (el) {
          var n = el.getAttribute('data-name');
          el.name = 'questions[' + qi + '][options][' + oi + '][' + n + ']';
          if (n === 'answer') el.name = 'questions[' + qi + '][answer][]';
        });
      });
    });
  }
  function syncType(q) {
    var type = q.querySelector('[data-type]').value;
    Array.prototype.forEach.call(q.querySelectorAll('[data-opt] input[type=radio], [data-opt] input[type=checkbox]'), function (inp) {
      inp.type = type === 'multiple' ? 'checkbox' : 'radio';
    });
  }
  function addOpt(q, data) {
    var opt = tplOpt.content.firstElementChild.cloneNode(true);
    data = data || {};
    setVal(opt, 'id', data.id || 'o_' + Math.random().toString(36).slice(2, 8));
    setVal(opt, 'text', data.text);
    var ans = opt.querySelector('[data-name="answer"]');
    ans.value = data.id || opt.querySelector('[data-name="id"]').value;
    ans.checked = !!(data.checked);
    q.querySelector('.options').appendChild(opt);
    opt.querySelector('[data-remove-opt]').addEventListener('click', function () { opt.remove(); reindex(); });
    opt.querySelectorAll('input').forEach(function (el) { el.addEventListener('input', reindex); el.addEventListener('change', reindex); });
    reindex();
  }
  function addQ(data) {
    var q = tplQ.content.firstElementChild.cloneNode(true);
    wrap.appendChild(q);
    data = data || {};
    setVal(q, 'id', data.id || 'q_' + Math.random().toString(36).slice(2, 8));
    setVal(q, 'title', data.title); setVal(q, 'type', data.type || 'single');
    setVal(q, 'score', data.score || 1); setVal(q, 'explanation', data.explanation);
    var answers = (data.answer || []).map(String);
    var opts = data.options || [];
    if (!opts.length) opts = [{ text: '' }, { text: '' }];
    opts.forEach(function (o) { addOpt(q, { id: o.id, text: o.text, checked: answers.indexOf(String(o.id)) >= 0 }); });
    q.querySelector('[data-remove-q]').addEventListener('click', function () { q.remove(); reindex(); });
    q.querySelector('[data-add-opt]').addEventListener('click', function () { addOpt(q, {}); });
    q.querySelector('[data-type]').addEventListener('change', function () { syncType(q); reindex(); });
    syncType(q);
    reindex();
    return q;
  }
  document.getElementById('add-q').addEventListener('click', function () { addQ({}); });
  if (initial.length) initial.forEach(function (q) { addQ(q); }); else addQ({});

  var aiBtn = document.getElementById('ai-gen');
  if (aiBtn) aiBtn.addEventListener('click', function () {
    var topic = document.getElementById('ai-topic').value.trim();
    if (!topic) { alert('请填写主题'); return; }
    var count = document.getElementById('ai-count').value || 3;
    var old = aiBtn.textContent; aiBtn.textContent = '生成中…'; aiBtn.disabled = true;
    var fd = new FormData();
    fd.append('_token', '<?= lf_csrf_token() ?>');
    fd.append('action', 'generate_quiz');
    fd.append('topic', topic);
    fd.append('count', count);
    fetch('<?= lf_url('/api/ai.php') ?>', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
      aiBtn.textContent = old; aiBtn.disabled = false;
      if (!d.ok) { alert(d.error || 'AI 失败'); return; }
      (d.questions || []).forEach(function (q) {
        var answers = (q.answer || []).map(String);
        addQ({
          title: q.title || '', type: q.type || 'single', score: q.score || 1,
          explanation: q.explanation || '',
          options: (q.options || []).map(function (o) { return { id: o.id || undefined, text: o.text || '' }; }),
          answer: answers
        });
      });
    }).catch(function () { aiBtn.textContent = old; aiBtn.disabled = false; alert('网络错误'); });
  });
})();
</script>
<?php lf_admin_page_end(); ?>
