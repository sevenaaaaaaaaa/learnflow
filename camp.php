<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');

if ($slug === '') {
    $camps = array_values(array_filter(course_all(true), function ($c) {
        return ($c['type'] ?? '') === '训练营' || !empty($c['allow_invite']) || !empty($c['camp_start']);
    }));
    $student = lf_student_current();
    lf_page_start([
        'title' => lf_t('训练营', 'Bootcamp') . ' · LearnFlow',
        'description' => '开营节奏、每日任务、作业提交、打卡与圈子。',
        'active' => 'camp',
        'container' => true,
    ]);
    ?>
    <section class="lf-sec" style="padding-top:34px">
      <div class="lf-sec-head">
        <div>
          <span class="lf-kicker">Bootcamp</span>
          <h2 class="lf-sec-title">训练营</h2>
        </div>
      </div>
      <?php if (!$camps): ?>
        <div class="lf-empty">暂无训练营。将课程类型设为「训练营」或设置开营日期即可在此展示。</div>
      <?php else: ?>
        <div class="lf-grid">
          <?php foreach ($camps as $course) {
              $opts = [];
              if ($student && enroll_is_active((string)$course['id'], (string)$student['id'])) {
                  $opts['progress'] = progress_summary((string)$student['id'], (string)$course['id'], $course)['percent'];
              }
              echo lf_course_card($course, $opts);
          } ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
    lf_page_end();
    exit;
}

$course = course_find($slug);
if ($course === null) {
    http_response_code(404);
    lf_page_start(['title' => '训练营不存在 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">训练营不存在。<a href="' . lf_url('/camp') . '">返回训练营</a></div>';
    lf_page_end();
    exit;
}

$student = lf_student_current();
$isAdmin = lf_admin_current() !== null;
$sid = $student ? (string)$student['id'] : '';
$course = lf_localize_course($course);
$hasAccess = $isAdmin || ($sid !== '' && enroll_is_active((string)$course['id'], $sid));
if (!$hasAccess && $student !== null) {
    lf_ensure_member_access($course, $student);
    $hasAccess = $isAdmin || ($sid !== '' && enroll_is_active((string)$course['id'], $sid));
}

if (!$hasAccess) {
    lf_flash('warn', '请先报名该训练营。');
    header('Location: ' . lf_url('/course/' . rawurlencode((string)$course['slug'])));
    exit;
}

$tab = (string)($_GET['tab'] ?? 'overview');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if (!lf_csrf_check()) {
        lf_flash('danger', '请求已失效，请重试。');
    } else {
        if ($action === 'checkin' && $sid !== '') {
            $res = checkin_do((string)$course['id'], $sid, (string)($_POST['note'] ?? ''));
            lf_flash('ok', '打卡成功，连续 ' . $res['streak'] . ' 天！');
        } elseif ($action === 'task_done' && $sid !== '') {
            task_complete((string)$course['id'], $sid, (string)($_POST['task_id'] ?? ''));
            lf_flash('ok', '任务已完成。');
        } elseif ($action === 'submit_assignment' && $sid !== '') {
            $assignment = assignment_find((string)($_POST['assignment_id'] ?? ''));
            if ($assignment !== null && ($assignment['course_id'] ?? '') === ($course['id'] ?? '')) {
                $files = [];
                if (!empty($assignment['allow_file']) && !empty($_FILES['file']['name'])) {
                    try {
                        $saved = lf_upload_save($_FILES['file'], 'assignments/' . (string)$course['id']);
                        $files[] = $saved;
                    } catch (Throwable $e) {
                        lf_flash('danger', $e->getMessage());
                    }
                }
                assignment_submit((string)$assignment['id'], $sid, (string)($_POST['content'] ?? ''), $files);
                lf_flash('ok', '作业已提交。');
            }
        } elseif ($action === 'post_create' && $student !== null) {
            if (trim((string)($_POST['body'] ?? '')) !== '' || trim((string)($_POST['title'] ?? '')) !== '') {
                post_create((string)$course['id'], $student, [
                    'type' => (string)($_POST['type'] ?? 'note'),
                    'title' => (string)($_POST['title'] ?? ''),
                    'body' => (string)($_POST['body'] ?? ''),
                ]);
                lf_flash('ok', '已发布。');
            }
        } elseif ($action === 'post_like' && $sid !== '') {
            post_like((string)$course['id'], (string)($_POST['post_id'] ?? ''), $sid);
        } elseif ($action === 'comment_add' && $student !== null) {
            if (trim((string)($_POST['body'] ?? '')) !== '') {
                comment_add((string)$course['id'], (string)($_POST['post_id'] ?? ''), $student, (string)($_POST['body'] ?? ''));
            }
        } elseif ($action === 'post_delete' && ($isAdmin || $sid !== '')) {
            post_delete((string)$course['id'], (string)($_POST['post_id'] ?? ''));
            lf_flash('ok', '已删除。');
        }
    }
    header('Location: ' . lf_url('/camp/' . rawurlencode((string)$course['slug']) . '?tab=' . urlencode($tab)));
    exit;
}

$lessons = course_lessons($course);
$summary = $sid !== '' ? progress_summary($sid, (string)$course['id'], $course) : null;
$tasks = task_all((string)$course['id']);
$taskDone = $sid !== '' ? task_done_map((string)$course['id'], $sid) : [];
$taskProgress = $sid !== '' ? task_progress((string)$course['id'], $sid) : ['total' => count($tasks), 'done' => 0, 'percent' => 0];
$streak = $sid !== '' ? checkin_streak((string)$course['id'], $sid) : 0;
$checkedToday = $sid !== '' && checkin_today((string)$course['id'], $sid);
$checkinStats = checkin_course_stats((string)$course['id']);
$assignments = assignment_all((string)$course['id']);
$posts = post_all((string)$course['id']);

$lessonTitles = [];
foreach ($lessons as $l) $lessonTitles[(string)$l['id']] = (string)$l['title'];

lf_page_start([
    'title' => (string)$course['title'] . ' · 训练营',
    'active' => 'camp',
    'container' => true,
]);
?>
<section class="lf-sec" style="padding-top:30px">
  <a class="lf-faint" href="<?= lf_url('/camp') ?>">← 训练营</a>
  <div class="lf-sec-head" style="margin-top:12px">
    <div>
      <span class="lf-kicker">Bootcamp</span>
      <h1 class="lf-sec-title" style="font-size:clamp(26px,4vw,38px)"><?= lf_e((string)$course['title']) ?></h1>
      <div class="lf-course-meta" style="margin-top:10px">
        <?php $status = camp_status($course); ?>
        <?php if ($status !== ''): ?><span class="lf-chip"><?= lf_e($status) ?></span><?php endif; ?>
        <?php if (!empty($course['camp_start'])): ?><span class="lf-chip soft">开营 <?= lf_e((string)$course['camp_start']) ?></span><?php endif; ?>
        <?php if (!empty($course['camp_end'])): ?><span class="lf-chip soft">结营 <?= lf_e((string)$course['camp_end']) ?></span><?php endif; ?>
        <?php if ($status === '进行中'): ?><span class="lf-chip ok">Day <?= camp_current_day($course) ?></span><?php endif; ?>
      </div>
    </div>
    <a class="btn ghost sm" href="<?= lf_url('/learn/' . rawurlencode((string)$course['slug'])) ?>">进入课程学习</a>
  </div>

  <div class="lf-tabs">
    <a class="lf-tab<?= $tab === 'overview' ? ' on' : '' ?>" href="<?= lf_url('/camp/' . rawurlencode((string)$course['slug']) . '?tab=overview') ?>">节奏与任务</a>
    <a class="lf-tab<?= $tab === 'assignments' ? ' on' : '' ?>" href="<?= lf_url('/camp/' . rawurlencode((string)$course['slug']) . '?tab=assignments') ?>">作业 (<?= count($assignments) ?>)</a>
    <a class="lf-tab<?= $tab === 'community' ? ' on' : '' ?>" href="<?= lf_url('/camp/' . rawurlencode((string)$course['slug']) . '?tab=community') ?>">圈子 (<?= count($posts) ?>)</a>
  </div>

  <?php if ($tab === 'overview'): ?>
    <div class="lf-grid" style="grid-template-columns:minmax(0,1fr) 320px;align-items:start">
      <div>
        <h2 class="lf-sec-title" style="font-size:20px;margin-bottom:14px">每日任务</h2>
        <?php if (!$tasks): ?>
          <div class="lf-empty">讲师还没有排每日任务。</div>
        <?php else: ?>
          <div style="display:grid;gap:10px">
            <?php foreach ($tasks as $t): $doneT = isset($taskDone[(string)$t['id']]); ?>
              <div class="lf-stat" style="display:flex;gap:14px;align-items:flex-start">
                <span class="lf-brand-ic" style="background:<?= $doneT ? 'var(--ok-soft)' : 'var(--accent-soft)' ?>;color:<?= $doneT ? 'var(--ok)' : 'var(--accent)' ?>"><?= lf_icon($doneT ? 'check' : 'clock', 17) ?></span>
                <div style="flex:1">
                  <b style="font-size:15px">Day <?= (int)$t['day_index'] ?> · <?= lf_e((string)$t['title']) ?></b>
                  <?php if (!empty($t['description'])): ?><span class="lf-muted" style="display:block;font-size:13.5px;margin-top:3px"><?= nl2br(lf_e((string)$t['description'])) ?></span><?php endif; ?>
                  <?php if (!empty($t['lesson_id']) && isset($lessonTitles[(string)$t['lesson_id']])): ?>
                    <a class="lf-faint" style="display:inline-block;margin-top:4px" href="<?= lf_url('/learn/' . rawurlencode((string)$course['slug']) . '?lesson=' . rawurlencode((string)$t['lesson_id'])) ?>">对应课时：<?= lf_e($lessonTitles[(string)$t['lesson_id']]) ?></a>
                  <?php endif; ?>
                </div>
                <?php if ($doneT): ?>
                  <span class="lf-chip ok">已完成</span>
                <?php else: ?>
                  <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="task_done"><input type="hidden" name="task_id" value="<?= lf_e((string)$t['id']) ?>"><button class="btn primary sm" type="submit">完成</button></form>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php $lives = array_values(array_filter(course_lessons($course), fn($l) => ($l['type'] ?? '') === 'live')); ?>
        <?php if ($lives): ?>
          <h2 class="lf-sec-title" style="font-size:20px;margin:24px 0 14px">直播日程</h2>
          <div style="display:grid;gap:10px">
            <?php foreach ($lives as $l):
              $ls = strtotime((string)($l['live_start'] ?? '')) ?: 0;
              $le = strtotime((string)($l['live_end'] ?? '')) ?: 0;
              $st = !$ls ? '未排期' : (time() < $ls ? '未开始' : (($le && time() > $le) ? '已结束' : '直播中'));
            ?>
              <div class="lf-stat" style="display:flex;gap:14px;align-items:center">
                <span class="lf-brand-ic" style="background:var(--accent-soft);color:var(--accent)"><?= lf_icon('live', 17) ?></span>
                <div style="flex:1">
                  <b><?= lf_e((string)($l['title'] ?? '')) ?></b>
                  <span class="lf-faint" style="display:block"><?= !empty($l['live_start']) ? lf_e((string)$l['live_start']) : '待定' ?><?= !empty($l['live_end']) ? ' — ' . lf_e((string)$l['live_end']) : '' ?></span>
                </div>
                <span class="lf-chip <?= $st === '直播中' ? 'ok' : 'soft' ?>"><?= lf_e($st) ?></span>
                <a class="btn ghost sm" href="<?= lf_url('/learn/' . rawurlencode((string)$course['slug']) . '?lesson=' . rawurlencode((string)$l['id'])) ?>">进入</a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <aside>
        <div class="lf-form-card" style="max-width:none">
          <h3 style="margin:0 0 6px;font-size:16px">今日打卡</h3>
          <p class="lf-faint" style="margin:0 0 12px">连续 <?= (int)$streak ?> 天 · 累计 <?= $sid !== '' ? checkin_total((string)$course['id'], $sid) : 0 ?> 次</p>
          <?php if ($checkedToday): ?>
            <div class="lf-flash ok" style="margin:0 0 12px">今日已打卡</div>
          <?php else: ?>
            <form method="post">
              <?= lf_csrf_field() ?><input type="hidden" name="action" value="checkin">
              <input class="lf-inp" name="note" placeholder="今天学了什么（可选）" style="margin-bottom:8px">
              <button class="btn primary block" type="submit">打卡</button>
            </form>
          <?php endif; ?>
          <?php if ($sid !== ''): $recent = checkin_recent((string)$course['id'], $sid, 28); ?>
            <div style="display:grid;grid-template-columns:repeat(14,1fr);gap:4px;margin-top:14px">
              <?php foreach ($recent as $day => $has): ?>
                <span title="<?= lf_e($day) ?>" style="aspect-ratio:1;border-radius:4px;background:<?= $has ? 'var(--ok)' : 'var(--hover-strong)' ?>"></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <p class="lf-faint" style="margin-top:12px">全营今日打卡 <?= (int)$checkinStats['today'] ?> 人</p>
        </div>

        <div class="lf-form-card" style="max-width:none;margin-top:16px">
          <h3 style="margin:0 0 12px;font-size:16px">我的进度</h3>
          <?php if ($summary): ?>
            <?= lf_progress_bar((int)$summary['percent'], '课时 ' . (int)$summary['done'] . '/' . (int)$summary['total']) ?>
          <?php endif; ?>
          <div style="margin-top:14px"><?= lf_progress_bar((int)$taskProgress['percent'], '任务 ' . (int)$taskProgress['done'] . '/' . (int)$taskProgress['total']) ?></div>
        </div>
      </aside>
    </div>

  <?php elseif ($tab === 'assignments'): ?>
    <?php if (!$assignments): ?>
      <div class="lf-empty">讲师还没有布置作业。</div>
    <?php else: ?>
      <div style="display:grid;gap:16px;max-width:820px">
        <?php foreach ($assignments as $a): $sub = $sid !== '' ? assignment_submission((string)$a['id'], $sid) : null; ?>
          <div class="lf-q">
            <div class="lf-row" style="justify-content:space-between;align-items:flex-start">
              <div>
                <b style="font-size:16px"><?= lf_e((string)$a['title']) ?></b>
                <?php if (!empty($a['due_at'])): ?><span class="lf-chip soft" style="margin-left:8px">截止 <?= lf_e((string)$a['due_at']) ?></span><?php endif; ?>
                <?php if ($sub !== null): ?><span class="lf-chip <?= !empty($sub['graded_at']) ? 'ok' : 'soft' ?>" style="margin-left:8px"><?= !empty($sub['graded_at']) ? '已点评' : '已提交' ?></span><?php endif; ?>
              </div>
            </div>
            <?php if (!empty($a['description'])): ?><p class="lf-muted" style="margin:8px 0 0;font-size:14px"><?= nl2br(lf_e((string)$a['description'])) ?></p><?php endif; ?>

            <?php if ($sub !== null): ?>
              <div class="lf-flash info" style="margin-top:12px;font-size:13.5px">
                <b>我的提交</b>（<?= lf_e((string)$sub['submitted_at']) ?>）<br>
                <?= nl2br(lf_e((string)($sub['content'] ?? ''))) ?>
                <?php foreach ((array)($sub['files'] ?? []) as $f): ?>
                  <br><a href="<?= lf_e(lf_file_url((string)$f['rel'], $sid)) ?>"><?= lf_icon('file', 15) ?> <?= lf_e((string)$f['name']) ?></a>
                <?php endforeach; ?>
              </div>
              <?php if (!empty($sub['graded_at'])): ?>
                <div class="lf-flash ok" style="margin-top:10px;font-size:13.5px">
                  <b>讲师点评<?= $sub['grade'] !== null && $sub['grade'] !== '' ? '（' . lf_e((string)$sub['grade']) . ' 分）' : '' ?></b><br>
                  <?= nl2br(lf_e((string)($sub['feedback'] ?? ''))) ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($sid !== '' && ($sub === null || empty($sub['graded_at']))): ?>
              <form method="post" enctype="multipart/form-data" style="margin-top:12px">
                <?= lf_csrf_field() ?>
                <input type="hidden" name="action" value="submit_assignment">
                <input type="hidden" name="assignment_id" value="<?= lf_e((string)$a['id']) ?>">
                <div class="lf-field"><label><?= $sub === null ? '提交作业' : '重新提交' ?></label><textarea class="lf-inp" name="content" placeholder="用文字作答…" required></textarea></div>
                <?php if (!empty($a['allow_file'])): ?>
                  <div class="lf-field"><label>附件（可选）</label><input class="lf-inp" type="file" name="file" style="height:auto;padding:10px"></div>
                <?php endif; ?>
                <button class="btn primary sm" type="submit">提交</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <div style="max-width:820px">
      <?php if ($student !== null): ?>
        <div class="lf-form-card" style="max-width:none;margin-bottom:18px">
          <form method="post">
            <?= lf_csrf_field() ?><input type="hidden" name="action" value="post_create">
            <div class="lf-row" style="align-items:flex-end">
              <div class="lf-field" style="margin:0"><label>类型</label><select class="lf-inp" name="type"><option value="note">心得</option><option value="question">提问</option><option value="progress">晒进度</option></select></div>
              <div class="lf-field" style="margin:0;flex:2"><label>标题（可选）</label><input class="lf-inp" name="title"></div>
            </div>
            <div class="lf-field" style="margin-top:10px"><label>内容</label><textarea class="lf-inp" name="body" placeholder="分享你的进度或提问…" required></textarea></div>
            <button class="btn primary sm" type="submit">发布</button>
          </form>
        </div>
      <?php endif; ?>

      <?php if (!$posts): ?>
        <div class="lf-empty">圈子还很安静，来发第一条吧。</div>
      <?php else: ?>
        <div style="display:grid;gap:14px">
          <?php foreach ($posts as $p): $liked = $sid !== '' && in_array($sid, array_map('strval', (array)($p['likes'] ?? [])), true); ?>
            <div class="lf-q">
              <div class="lf-row" style="justify-content:space-between">
                <span><b><?= lf_e((string)$p['name']) ?></b> <span class="lf-chip soft" style="margin-left:6px"><?= ['question' => '提问', 'progress' => '晒进度', 'note' => '心得'][(string)$p['type']] ?? '心得' ?></span></span>
                <span class="lf-faint"><?= lf_e((string)$p['created_at']) ?></span>
              </div>
              <?php if (!empty($p['title'])): ?><b style="display:block;margin-top:8px"><?= lf_e((string)$p['title']) ?></b><?php endif; ?>
              <p style="margin:6px 0 0;font-size:14.5px"><?= nl2br(lf_e((string)$p['body'])) ?></p>
              <div class="lf-row" style="margin-top:10px;gap:14px">
                <form method="post" style="margin:0"><?= lf_csrf_field() ?><input type="hidden" name="action" value="post_like"><input type="hidden" name="post_id" value="<?= lf_e((string)$p['id']) ?>"><button class="btn subtle sm" type="submit" style="height:30px"><?= $liked ? '已赞' : '赞' ?> (<?= count((array)($p['likes'] ?? [])) ?>)</button></form>
                <?php if ($isAdmin || ($sid !== '' && $sid === (string)$p['student_id'])): ?>
                  <form method="post" style="margin:0" onsubmit="return confirm('删除该帖？')"><?= lf_csrf_field() ?><input type="hidden" name="action" value="post_delete"><input type="hidden" name="post_id" value="<?= lf_e((string)$p['id']) ?>"><button class="btn subtle sm" type="submit" style="height:30px;color:var(--danger)">删除</button></form>
                <?php endif; ?>
              </div>
              <?php foreach ((array)($p['comments'] ?? []) as $c): ?>
                <div style="margin-top:10px;padding:10px 12px;border-radius:10px;background:var(--hover);font-size:13.5px">
                  <b><?= lf_e((string)$c['name']) ?></b> <span class="lf-faint"><?= lf_e((string)$c['created_at']) ?></span><br>
                  <?= nl2br(lf_e((string)$c['body'])) ?>
                </div>
              <?php endforeach; ?>
              <?php if ($student !== null): ?>
                <form method="post" style="margin-top:10px;display:flex;gap:8px">
                  <?= lf_csrf_field() ?><input type="hidden" name="action" value="comment_add"><input type="hidden" name="post_id" value="<?= lf_e((string)$p['id']) ?>">
                  <input class="lf-inp" name="body" placeholder="回复…" style="height:38px" required>
                  <button class="btn ghost sm" type="submit" style="flex:0 0 auto">回复</button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php lf_page_end(); ?>
