<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
lf_admin_required();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效。');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $quiz = quiz_save([
                'title' => trim((string)($_POST['title'] ?? '未命名测验')),
                'course_id' => (string)($_POST['course_id'] ?? ''),
                'kind' => (string)($_POST['kind'] ?? 'chapter'),
                'pass_score' => (int)($_POST['pass_score'] ?? 0),
                'questions' => [],
            ]);
            lf_flash('ok', '测验已创建，继续添加题目。');
            header('Location: /admin/quiz-edit.php?id=' . urlencode((string)$quiz['id']));
            exit;
        }
        if ($action === 'delete') {
            quiz_delete((string)($_POST['id'] ?? ''));
            lf_flash('ok', '测验已删除。');
        }
    }
    header('Location: /admin/quizzes.php');
    exit;
}

$quizzes = quiz_all();
$courses = course_all();
$courseNames = [];
foreach ($courses as $c) $courseNames[(string)$c['id']] = (string)$c['title'];

lf_admin_page_start(['title' => '测验 · LearnFlow 讲师后台', 'active' => 'courses']);
?>
<div class="lf-admin-head"><h1>测验管理</h1></div>

<div class="lf-form-card" style="max-width:none;margin-bottom:22px">
  <form method="post" class="lf-row" style="align-items:flex-end">
    <?= lf_csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="lf-field" style="margin:0;flex:2"><label>测验标题</label><input class="lf-inp" name="title" placeholder="如：第 1 章测验" required></div>
    <div class="lf-field" style="margin:0"><label>课程</label><select class="lf-inp" name="course_id"><?php foreach ($courses as $c): ?><option value="<?= lf_e((string)$c['id']) ?>"><?= lf_e((string)$c['title']) ?></option><?php endforeach; ?></select></div>
    <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" name="kind"><option value="chapter">章节测验</option><option value="final">结业测验</option></select></div>
    <button class="btn primary sm" type="submit" style="flex:0 0 auto">创建</button>
  </form>
</div>

<?php if (!$quizzes): ?>
  <div class="lf-empty">还没有测验。</div>
<?php else: ?>
  <table class="lf-table">
    <thead><tr><th>测验</th><th>课程</th><th>类型</th><th>题目</th><th>满分</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($quizzes as $qid => $q): ?>
        <tr>
          <td><b><?= lf_e((string)($q['title'] ?? '')) ?></b></td>
          <td><?= lf_e((string)($courseNames[(string)($q['course_id'] ?? '')] ?? '')) ?></td>
          <td><span class="lf-chip soft"><?= ($q['kind'] ?? 'chapter') === 'final' ? '结业' : '章节' ?></span></td>
          <td><?= count((array)($q['questions'] ?? [])) ?></td>
          <td><?= quiz_total_score($q) ?></td>
          <td class="lf-row" style="flex-wrap:nowrap">
            <a class="btn subtle sm" href="/admin/quiz-edit.php?id=<?= urlencode((string)$qid) ?>">编辑</a>
            <form method="post" style="margin:0" onsubmit="return confirm('确认删除？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= lf_e((string)$qid) ?>"><button class="btn subtle sm" type="submit" style="color:var(--danger)">删除</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php lf_admin_page_end(); ?>
