<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

function lf_parse_attachments(string $text): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if (str_contains($line, '|')) {
            [$name, $url] = array_map('trim', explode('|', $line, 2));
        } else {
            $name = basename($line);
            $url = $line;
        }
        if ($url !== '') $out[] = ['name' => $name !== '' ? $name : $url, 'url' => $url];
    }
    return $out;
}

function lf_attachments_text(array $attachments): string
{
    $lines = [];
    foreach ($attachments as $a) {
        $lines[] = (string)($a['name'] ?? '') . '|' . (string)($a['url'] ?? '');
    }
    return implode("\n", $lines);
}

$id = (string)($_GET['id'] ?? $_POST['id'] ?? '');
$course = $id !== '' ? course_find($id) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
        header('Location: ' . lf_url('/admin/courses.php'));
        exit;
    }
    $raw = [
        'id' => $course['id'] ?? '',
        'title' => (string)($_POST['title'] ?? ''),
        'slug' => (string)($_POST['slug'] ?? ''),
        'subtitle' => (string)($_POST['subtitle'] ?? ''),
        'summary' => (string)($_POST['summary'] ?? ''),
        'cover' => (string)($_POST['cover'] ?? ''),
        'instructor' => (string)($_POST['instructor'] ?? ''),
        'type' => (string)($_POST['type'] ?? '单课'),
        'level' => (string)($_POST['level'] ?? ''),
        'price' => (float)($_POST['price'] ?? 0),
        'status' => (string)($_POST['status'] ?? 'draft'),
        'certificate' => !empty($_POST['certificate']),
        'allow_invite' => !empty($_POST['allow_invite']),
        'categories' => array_values((array)($_POST['categories'] ?? [])),
        'tags' => array_filter(array_map('trim', explode(',', (string)($_POST['tags'] ?? '')))),
        'camp_start' => (string)($_POST['camp_start'] ?? ''),
        'camp_end' => (string)($_POST['camp_end'] ?? ''),
        'payflow_product_id' => (string)($_POST['payflow_product_id'] ?? ''),
        'chapters' => [],
    ];
    foreach ((array)($_POST['chapters'] ?? []) as $ch) {
        $lessons = [];
        foreach ((array)($ch['lessons'] ?? []) as $l) {
            $lessons[] = [
                'id' => (string)($l['id'] ?? ''),
                'type' => (string)($l['type'] ?? 'article'),
                'title' => (string)($l['title'] ?? ''),
                'duration' => (int)($l['duration'] ?? 0),
                'video' => (string)($l['video'] ?? ''),
                'content' => (string)($l['content'] ?? ''),
                'quiz_id' => (string)($l['quiz_id'] ?? ''),
                'free' => !empty($l['free']),
                'attachments' => lf_parse_attachments((string)($l['attachments'] ?? '')),
            ];
        }
        $raw['chapters'][] = [
            'id' => (string)($ch['id'] ?? ''),
            'title' => (string)($ch['title'] ?? ''),
            'summary' => (string)($ch['summary'] ?? ''),
            'lessons' => $lessons,
        ];
    }
    $saved = course_save(course_normalize($raw));
    lf_flash('ok', '课程已保存。');
    header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$saved['id'])));
    exit;
}

$isNew = $course === null;
if ($isNew) {
    $course = course_normalize(['title' => '未命名课程']);
    $course['id'] = '';
}
$quizzes = quiz_all();
$published = $course['status'] ?? 'draft';
$types = ['单课', '专栏', '认证课', '系列课', '训练营'];

lf_admin_page_start(['title' => '编辑课程 · LearnFlow 讲师后台', 'active' => 'courses']);
?>
<div class="lf-admin-head">
  <h1><?= $isNew ? '新建课程' : '编辑课程' ?></h1>
  <div class="lf-row" style="flex:0 0 auto">
    <a class="btn subtle sm" href="<?= lf_url('/admin/courses.php') ?>">返回列表</a>
    <?php if (!$isNew): ?><a class="btn ghost sm" href="<?= lf_url('/course/') ?><?= rawurlencode((string)($course['slug'] ?? $course['id'])) ?>" target="_blank">预览</a><?php endif; ?>
  </div>
</div>

<form method="post">
  <?= lf_csrf_field() ?>
  <input type="hidden" name="id" value="<?= lf_e((string)($course['id'] ?? '')) ?>">

  <div class="lf-form-card" style="max-width:none;margin-bottom:20px">
    <h2 class="lf-sec-title" style="font-size:18px;margin:0 0 14px">基本信息</h2>
    <div class="lf-row">
      <div class="lf-field"><label>标题</label><input class="lf-inp" name="title" value="<?= lf_e((string)($course['title'] ?? '')) ?>" required></div>
      <div class="lf-field"><label>Slug（URL，可留空自动）</label><input class="lf-inp" name="slug" value="<?= lf_e((string)($course['slug'] ?? '')) ?>"></div>
    </div>
    <div class="lf-field"><label>副标题</label><input class="lf-inp" name="subtitle" value="<?= lf_e((string)($course['subtitle'] ?? '')) ?>"></div>
    <div class="lf-field"><label>课程简介</label><textarea class="lf-inp" name="summary"><?= lf_e((string)($course['summary'] ?? '')) ?></textarea></div>
    <div class="lf-row">
      <div class="lf-field"><label>封面图 URL</label><input class="lf-inp" name="cover" value="<?= lf_e((string)($course['cover'] ?? '')) ?>"></div>
      <div class="lf-field"><label>讲师</label><input class="lf-inp" name="instructor" value="<?= lf_e((string)($course['instructor'] ?? '')) ?>"></div>
    </div>
    <div class="lf-row">
      <div class="lf-field"><label>类型</label><select class="lf-inp" name="type"><?php foreach ($types as $t): ?><option <?= ($course['type'] ?? '') === $t ? 'selected' : '' ?>><?= lf_e($t) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field"><label>难度</label><input class="lf-inp" name="level" value="<?= lf_e((string)($course['level'] ?? '')) ?>" placeholder="入门 / 进阶"></div>
      <div class="lf-field"><label>价格（元，0 = 免费）</label><input class="lf-inp" name="price" type="number" step="0.01" min="0" value="<?= lf_e((string)($course['price'] ?? 0)) ?>"></div>
      <div class="lf-field"><label>状态</label><select class="lf-inp" name="status">
        <option value="draft" <?= $published === 'draft' ? 'selected' : '' ?>>草稿</option>
        <option value="published" <?= $published === 'published' ? 'selected' : '' ?>>已上架</option>
        <option value="archived" <?= $published === 'archived' ? 'selected' : '' ?>>归档</option>
      </select></div>
    </div>
    <div class="lf-row">
      <div class="lf-field"><label>PayFlow 商品 ID</label><input class="lf-inp" name="payflow_product_id" value="<?= lf_e((string)($course['payflow_product_id'] ?? '')) ?>" placeholder="用于购买即入学"></div>
      <div class="lf-field"><label>开营日期</label><input class="lf-inp" type="date" name="camp_start" value="<?= lf_e((string)($course['camp_start'] ?? '')) ?>"></div>
      <div class="lf-field"><label>结营日期</label><input class="lf-inp" type="date" name="camp_end" value="<?= lf_e((string)($course['camp_end'] ?? '')) ?>"></div>
      <div class="lf-field" style="justify-content:flex-end;flex-direction:row;gap:20px;align-items:center">
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="certificate" <?= !empty($course['certificate']) ? 'checked' : '' ?>> 颁发结业证书</label>
        <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="allow_invite" <?= !empty($course['allow_invite']) ? 'checked' : '' ?>> 允许邀请码</label>
      </div>
    </div>
    <div class="lf-field"><label>标签（逗号分隔）</label><input class="lf-inp" name="tags" value="<?= lf_e(implode(', ', (array)($course['tags'] ?? []))) ?>" placeholder="训练营, 增长"></div>
    <?php $allCats = category_all(); $courseCats = array_map('strval', (array)($course['categories'] ?? [])); ?>
    <?php if ($allCats): ?>
      <div class="lf-field"><label>分类</label>
        <div class="lf-row" style="flex-wrap:wrap;gap:14px">
          <?php foreach ($allCats as $cat): ?>
            <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center;min-width:0"><input type="checkbox" name="categories[]" value="<?= lf_e((string)$cat['key']) ?>" <?= in_array((string)$cat['key'], $courseCats, true) ? 'checked' : '' ?>> <?= lf_e((string)$cat['name']) ?></label>
          <?php endforeach; ?>
        </div>
        <span class="lf-faint">在「分类」里新增可选分类。</span>
      </div>
    <?php else: ?>
      <p class="lf-faint">还没有分类，可到 <a href="<?= lf_url('/admin/categories.php') ?>">分类管理</a> 添加。</p>
    <?php endif; ?>
  </div>

  <div class="lf-admin-head"><h2 class="lf-sec-title" style="font-size:18px">章节与课时</h2><button class="btn ghost sm" type="button" id="add-chapter">+ 添加章节</button></div>
  <div id="chapters"></div>

  <div style="margin-top:20px;position:sticky;bottom:0;background:var(--bg);padding:14px 0;border-top:1px solid var(--border)">
    <button class="btn primary" type="submit">保存课程</button>
  </div>
</form>

<template id="tpl-chapter">
  <div class="lf-form-card chapter-box" style="max-width:none;margin-bottom:16px" data-chapter>
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>章节标题</label><input class="lf-inp" data-name="title" placeholder="第 N 章"></div>
      <div class="lf-field" style="margin:0"><label>章节说明</label><input class="lf-inp" data-name="summary"></div>
      <button class="btn ghost sm" type="button" style="flex:0 0 auto" data-remove-chapter>删除章节</button>
    </div>
    <input type="hidden" data-name="id">
    <div class="lessons" style="margin-top:14px"></div>
    <button class="btn subtle sm" type="button" data-add-lesson>+ 添加课时</button>
  </div>
</template>

<template id="tpl-lesson">
  <div class="lf-lesson-editor" data-lesson>
    <input type="hidden" data-name="id">
    <div class="lf-row">
      <div class="lf-field" style="margin:0;flex:2"><label>课时标题</label><input class="lf-inp" data-name="title"></div>
      <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" data-name="type" data-type-select>
        <option value="article">图文</option><option value="video">视频</option><option value="quiz">测验</option><option value="file">资料</option><option value="live">直播</option>
      </select></div>
      <div class="lf-field" style="margin:0"><label>时长(分)</label><input class="lf-inp" type="number" min="0" data-name="duration"></div>
      <button class="btn subtle sm" type="button" style="flex:0 0 auto" data-remove-lesson>移除</button>
    </div>
    <div class="lf-row" style="margin-top:10px">
      <div class="lf-field" style="margin:0" data-field="video"><label>视频 URL (mp4/HLS)</label><input class="lf-inp" data-name="video"></div>
      <div class="lf-field" style="margin:0" data-field="quiz"><label>关联测验</label><select class="lf-inp" data-name="quiz_id"><option value="">— 选择测验 —</option><?php foreach ($quizzes as $qid => $q): ?><option value="<?= lf_e((string)$qid) ?>"><?= lf_e((string)($q['title'] ?? $qid)) ?></option><?php endforeach; ?></select></div>
      <label style="flex:0 0 auto;display:flex;gap:8px;align-items:center;margin-top:20px"><input type="checkbox" data-name="free"> 试看</label>
    </div>
    <div class="lf-field" style="margin-top:10px" data-field="content"><label>图文内容（支持 HTML）</label><textarea class="lf-inp" data-name="content" style="min-height:110px"></textarea></div>
    <div class="lf-field" style="margin-top:10px"><label>附件（每行一个：名称|URL）</label><textarea class="lf-inp" data-name="attachments" style="min-height:56px"></textarea></div>
  </div>
</template>

<script>
(function () {
  var chaptersEl = document.getElementById('chapters');
  var tplChapter = document.getElementById('tpl-chapter');
  var tplLesson = document.getElementById('tpl-lesson');
  var initial = <?= json_encode(array_map(function ($ch) {
      $lessons = array_map(function ($l) {
          return [
              'id' => (string)($l['id'] ?? ''),
              'title' => (string)($l['title'] ?? ''),
              'type' => (string)($l['type'] ?? 'article'),
              'duration' => (int)($l['duration'] ?? 0),
              'video' => (string)($l['video'] ?? ''),
              'content' => (string)($l['content'] ?? ''),
              'quiz_id' => (string)($l['quiz_id'] ?? ''),
              'free' => !empty($l['free']),
              'attachments' => lf_attachments_text((array)($l['attachments'] ?? [])),
          ];
      }, (array)($ch['lessons'] ?? []));
      return ['id' => (string)($ch['id'] ?? ''), 'title' => (string)($ch['title'] ?? ''), 'summary' => (string)($ch['summary'] ?? ''), 'lessons' => $lessons];
  }, (array)($course['chapters'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function setVal(box, name, val) {
    var el = box.querySelector('[data-name="' + name + '"]');
    if (!el) return;
    if (el.type === 'checkbox') el.checked = !!val; else el.value = val == null ? '' : val;
  }
  function reindex() {
    Array.prototype.forEach.call(chaptersEl.querySelectorAll('[data-chapter]'), function (ch, ci) {
      Array.prototype.forEach.call(ch.querySelectorAll('[data-name]'), function (el) {
        el.name = 'chapters[' + ci + '][' + el.getAttribute('data-name') + ']';
      });
      Array.prototype.forEach.call(ch.querySelectorAll('[data-lesson]'), function (ls, li) {
        Array.prototype.forEach.call(ls.querySelectorAll('[data-name]'), function (el) {
          el.name = 'chapters[' + ci + '][lessons][' + li + '][' + el.getAttribute('data-name') + ']';
        });
      });
    });
  }
  function syncLesson(ls) {
    var type = ls.querySelector('[data-type-select]').value;
    ls.querySelector('[data-field="video"]').style.display = type === 'video' ? '' : 'none';
    ls.querySelector('[data-field="quiz"]').style.display = type === 'quiz' ? '' : 'none';
    ls.querySelector('[data-field="content"]').style.display = type === 'article' ? '' : 'none';
  }
  function bindLesson(ls) {
    ls.querySelector('[data-remove-lesson]').addEventListener('click', function () { ls.remove(); reindex(); });
    ls.querySelector('[data-type-select]').addEventListener('change', function () { syncLesson(ls); });
    ls.querySelectorAll('[data-name]').forEach(function (el) {
      el.addEventListener('input', reindex);
      el.addEventListener('change', reindex);
    });
  }
  function addLesson(ch, data) {
    var ls = tplLesson.content.firstElementChild.cloneNode(true);
    data = data || {};
    setVal(ls, 'id', data.id); setVal(ls, 'title', data.title); setVal(ls, 'type', data.type || 'article');
    setVal(ls, 'duration', data.duration); setVal(ls, 'video', data.video); setVal(ls, 'content', data.content);
    setVal(ls, 'quiz_id', data.quiz_id); setVal(ls, 'free', data.free); setVal(ls, 'attachments', data.attachments);
    ch.querySelector('.lessons').appendChild(ls);
    bindLesson(ls); syncLesson(ls);
    return ls;
  }
  function addChapter(data) {
    var ch = tplChapter.content.firstElementChild.cloneNode(true);
    chaptersEl.appendChild(ch);
    data = data || {};
    setVal(ch, 'id', data.id); setVal(ch, 'title', data.title); setVal(ch, 'summary', data.summary);
    ch.querySelector('[data-remove-chapter]').addEventListener('click', function () { ch.remove(); reindex(); });
    ch.querySelector('[data-add-lesson]').addEventListener('click', function () { addLesson(ch, {}); reindex(); });
    (data.lessons || []).forEach(function (l) { addLesson(ch, l); });
    if (!data.lessons || !data.lessons.length) addLesson(ch, {});
    reindex();
    return ch;
  }
  document.getElementById('add-chapter').addEventListener('click', function () { addChapter({}); });
  initial.forEach(function (ch) { addChapter(ch); });
  if (!initial.length) addChapter({});
  reindex();
})();
</script>
<?php lf_admin_page_end(); ?>
