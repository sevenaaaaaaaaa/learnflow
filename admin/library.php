<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'tpl_from_course') {
            $c = course_find((string)($_POST['course_id'] ?? ''));
            if ($c === null) lf_flash('danger', '课程不存在。');
            else { course_template_from_course($c, (string)($_POST['name'] ?? '')); lf_flash('ok', '已存为模板。'); }
        } elseif ($action === 'tpl_new_course') {
            $c = course_from_template((string)($_POST['template_id'] ?? ''));
            if ($c === null) lf_flash('danger', '模板不存在。');
            else { lf_flash('ok', '已从模板创建课程。'); header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$c['id']))); exit; }
        } elseif ($action === 'tpl_delete') {
            course_template_delete((string)($_POST['template_id'] ?? ''));
            lf_flash('ok', '模板已删除。');
        } elseif ($action === 'copy_chapter') {
            $r = course_copy_chapter((string)($_POST['from_course'] ?? ''), (int)($_POST['chapter_index'] ?? -1), (string)($_POST['to_course'] ?? ''));
            if ($r === null) lf_flash('danger', '复制失败。');
            else { lf_flash('ok', '章节已复制。'); header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$r['id']))); exit; }
        } elseif ($action === 'import_md') {
            $chapters = course_import_markdown((string)($_POST['md'] ?? ''));
            if (!$chapters) {
                lf_flash('danger', '未解析到内容。');
            } elseif (($_POST['target'] ?? 'new') === 'new') {
                $c = course_save(course_normalize(['title' => (string)($_POST['title'] ?? '导入课程'), 'status' => 'draft', 'chapters' => $chapters]));
                lf_flash('ok', '已导入为新课程。');
                header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$c['id'])));
                exit;
            } else {
                $c = course_find((string)$_POST['target']);
                if ($c !== null) { foreach ($chapters as $ch) $c['chapters'][] = $ch; course_save(course_normalize($c)); lf_flash('ok', '已追加到课程。'); header('Location: ' . lf_url('/admin/course-edit.php?id=' . urlencode((string)$c['id']))); exit; }
                lf_flash('danger', '目标课程不存在。');
            }
        }
    }
    header('Location: ' . lf_url('/admin/library.php'));
    exit;
}

$courses = course_all();
$templates = course_templates();

lf_admin_page_start(['title' => '内容库 · LearnFlow 讲师后台', 'active' => 'library']);
?>
<div class="lf-admin-head"><h1>内容库：模板 / 复制 / 导入</h1></div>

<div class="lf-grid" style="grid-template-columns:1fr 1fr;align-items:start">
  <div class="lf-form-card" style="max-width:none">
    <h3 style="margin:0 0 12px;font-size:16px">课程模板</h3>
    <form method="post" class="lf-row" style="align-items:flex-end;margin-bottom:16px">
      <?= lf_csrf_field() ?><input type="hidden" name="action" value="tpl_from_course">
      <div class="lf-field" style="margin:0"><label>把课程存为模板</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field" style="margin:0"><label>模板名（可选）</label><input class="lf-inp" name="name"></div>
      <button class="btn primary sm" type="submit" style="flex:0 0 auto">存为模板</button>
    </form>
    <?php if (!$templates): ?><div class="lf-empty">还没有模板。</div><?php else: ?>
      <table class="lf-table">
        <thead><tr><th>模板</th><th>结构</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($templates as $t): $lc = 0; foreach ((array)$t['chapters'] as $ch) $lc += count((array)($ch['lessons'] ?? [])); ?>
            <tr>
              <td><b><?= lf_e((string)$t['name']) ?></b></td>
              <td class="lf-faint"><?= count((array)$t['chapters']) ?> 章 · <?= $lc ?> 课时</td>
              <td class="lf-row" style="flex-wrap:nowrap">
                <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="tpl_new_course"><input type="hidden" name="template_id" value="<?= lf_e((string)$t['id']) ?>"><button class="btn subtle sm" type="submit">新建课程</button></form>
                <form method="post" style="margin:0" onsubmit="return confirm('删除模板？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="tpl_delete"><input type="hidden" name="template_id" value="<?= lf_e((string)$t['id']) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="lf-form-card" style="max-width:none">
    <h3 style="margin:0 0 12px;font-size:16px">跨课程复制章节</h3>
    <form method="post">
      <?= lf_csrf_field() ?><input type="hidden" name="action" value="copy_chapter">
      <div class="lf-field"><label>来源课程</label><select class="lf-inp" name="from_course" onchange="lfChapterOptions(this.value)"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field"><label>章节</label><select class="lf-inp" name="chapter_index" id="chapter-index"></select></div>
      <div class="lf-field"><label>目标课程</label><select class="lf-inp" name="to_course"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
      <button class="btn primary sm" type="submit">复制章节</button>
    </form>
  </div>
</div>

<div class="lf-form-card" style="max-width:none;margin-top:20px">
  <h3 style="margin:0 0 12px;font-size:16px">Markdown 导入（按 # 拆章、## 拆课时）</h3>
  <form method="post">
    <?= lf_csrf_field() ?><input type="hidden" name="action" value="import_md">
    <div class="lf-row" style="align-items:flex-end">
      <div class="lf-field" style="margin:0"><label>导入到</label><select class="lf-inp" name="target"><option value="new">新建课程</option><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>">追加到：<?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
      <div class="lf-field" style="margin:0;flex:1"><label>新课程标题（新建时用）</label><input class="lf-inp" name="title" placeholder="导入课程"></div>
    </div>
    <div class="lf-field" style="margin-top:10px"><label>Markdown 内容</label><textarea class="lf-inp" name="md" style="min-height:160px" placeholder="# 第一章&#10;## 第一节&#10;正文…"></textarea></div>
    <button class="btn primary sm" type="submit" style="margin-top:10px">导入</button>
  </form>
</div>

<script>
var COURSE_CHAPTERS = <?= json_encode(array_map(fn($c) => ['id' => $c['id'], 'chapters' => array_map(fn($ch) => (string)($ch['title'] ?? ''), (array)($c['chapters'] ?? []))], $courses), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
function lfChapterOptions(cid) {
  var sel = document.getElementById('chapter-index'); sel.innerHTML = '';
  var c = COURSE_CHAPTERS.find(function (x) { return x.id === cid; });
  (c ? c.chapters : []).forEach(function (t, i) { var o = document.createElement('option'); o.value = i; o.textContent = (i + 1) + '. ' + t; sel.appendChild(o); });
}
lfChapterOptions(document.querySelector('select[name="from_course"]').value);
</script>
<?php lf_admin_page_end(); ?>
