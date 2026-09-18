<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$course = course_find($slug);
if ($course === null) {
    http_response_code(404);
    lf_page_start(['title' => '课程不存在 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">课程不存在。<a href="' . lf_url('/courses') . '">返回课程列表</a></div>';
    lf_page_end();
    exit;
}

$student = lf_student_current();
$isAdmin = lf_admin_current() !== null;
$studentId = $student ? (string)$student['id'] : '';

$hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));
if (!$hasAccess && $student !== null) {
    lf_ensure_member_access($course, $student);
    $hasAccess = $isAdmin || ($studentId !== '' && enroll_is_active((string)$course['id'], $studentId));
}
$lessons = course_lessons($course);
$lessonId = (string)($_GET['lesson'] ?? '');
$lesson = $lessonId !== '' ? course_lesson_find($course, $lessonId) : null;

if ($lesson === null) {
    $resume = $studentId !== '' ? progress_resume($studentId, (string)$course['id'], $course) : null;
    $lesson = $resume ? course_lesson_find($course, (string)$resume['lesson_id']) : null;
    if ($lesson === null) $lesson = $lessons[0] ?? null;
}

if ($lesson === null) {
    lf_page_start(['title' => '课程为空 · LearnFlow', 'container' => true]);
    echo '<div class="lf-empty" style="margin:60px auto">该课程还没有课时内容。</div>';
    lf_page_end();
    exit;
}

$isFreePreview = !empty($lesson['free']);
if (!$hasAccess && !$isFreePreview && !$isAdmin) {
    if ($student === null) {
        header('Location: ' . lf_url('/login?next=' . urlencode('/learn/' . (string)$course['slug'])));
        exit;
    }
    lf_flash('warn', '请先报名该课程后继续学习。');
    header('Location: ' . lf_url('/course/' . rawurlencode((string)$course['slug'])));
    exit;
}

$state = ($studentId !== '' && $hasAccess) ? progress_get($studentId, (string)$course['id'], (string)$lesson['id']) : [];
$resumePosition = (int)($state['position'] ?? 0);
$neighbors = course_lesson_neighbors($course, (string)$lesson['id']);
$summary = ($studentId !== '' && $hasAccess) ? progress_summary($studentId, (string)$course['id'], $course) : null;
$lessonType = (string)($lesson['type'] ?? 'article');
$course = lf_localize_course($course);
$lesson = lf_localize_lesson($lesson, $course);

if (!$hasAccess && $isFreePreview && $student !== null && function_exists('lf_emit')) {
    $pvKey = 'lf_pv_' . (string)$course['id'] . '_' . (string)$lesson['id'];
    if (empty($_SESSION[$pvKey])) {
        $_SESSION[$pvKey] = 1;
        lf_emit('preview.viewed', lf_emit_context($studentId, [
            'course_id' => (string)$course['id'],
            'course_title' => (string)$course['title'],
            'lesson_id' => (string)$lesson['id'],
        ]));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'done' && $studentId !== '' && $hasAccess) {
    if (lf_csrf_check()) {
        progress_done($studentId, (string)$course['id'], (string)$lesson['id']);
        cert_maybe_issue($studentId, $course, (string)($student['name'] ?? ''));
        lf_flash('ok', '已标记完成。');
    }
    header('Location: ' . lf_url('/learn/' . rawurlencode((string)$course['slug']) . '?lesson=' . rawurlencode((string)$lesson['id'])));
    exit;
}

lf_page_start([
    'title' => (string)($lesson['title'] ?? '学习') . ' · ' . (string)$course['title'],
    'description' => (string)($course['summary'] ?? ''),
    'active' => 'courses',
    'container' => true,
]);
?>
<div class="lf-layout">
  <div>
    <div class="lf-player-wrap" data-lf-player<?= ($studentId !== '' && $hasAccess) ? ' data-endpoint="' . lf_url('/api/progress.php') . '"' : '' ?> data-course="<?= lf_e((string)$course['id']) ?>" data-lesson="<?= lf_e((string)$lesson['id']) ?>" data-resume="<?= (int)$resumePosition ?>">
      <?php if ($lessonType === 'video' && !empty($lesson['video'])):
          $videoUrl = media_resolve($lesson, (string)$course['id'], $hasAccess ? $studentId : '');
          $videoKind = media_kind($videoUrl);
      ?>
        <?php if ($videoKind === 'hls'): ?>
          <script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script>
        <?php endif; ?>
        <video class="lf-player-video" controls playsinline preload="metadata"<?= $videoKind === 'hls' ? ' data-hls="1"' : '' ?> src="<?= lf_e($videoUrl) ?><?= ($resumePosition > 2 && $videoKind !== 'hls') ? '#t=' . (int)$resumePosition : '' ?>"></video>
        <?php if ($videoKind === 'hls'): ?>
          <script>document.addEventListener('DOMContentLoaded',function(){var v=document.querySelector('[data-hls]');if(v&&window.Hls&&window.Hls.isSupported()){var h=new window.Hls();h.loadSource(v.getAttribute('src'));h.attachMedia(v);}else if(v){v.play&&0;}});</script>
        <?php endif; ?>
      <?php elseif ($lessonType === 'live'): ?>
        <?php
        $liveUrl = (string)($lesson['live_url'] ?? '');
        $ls = strtotime((string)($lesson['live_start'] ?? '')) ?: 0;
        $le = strtotime((string)($lesson['live_end'] ?? '')) ?: 0;
        $lstate = !$ls ? '未排期' : (time() < $ls ? '未开始' : (($le && time() > $le) ? '已结束' : '直播中'));
        $livePath = parse_url($liveUrl, PHP_URL_PATH) ?: $liveUrl;
        $isHls = $liveUrl !== '' && str_ends_with(strtolower($livePath), '.m3u8');
        $isEmbed = $liveUrl !== '' && !$isHls;
        $liveRoomId = (string)($lesson['live_room_id'] ?? '');
        $ofRemote = $liveRoomId !== '' ? live_openflow_status($liveRoomId) : null;
        if (is_array($ofRemote) && ($ofRemote['status'] ?? '') === 'live') $lstate = '直播中';
        $roomLink = $liveRoomId !== '' ? live_openflow_room_url($liveRoomId) : '';
        ?>
        <div class="lf-player-art" style="min-height:300px">
          <span class="lf-kicker">直播 · <?= lf_e($lstate) ?></span>
          <h1 style="font-family:var(--font-display);margin:10px 0"><?= lf_e((string)$lesson['title']) ?></h1>
          <?php if (!empty($lesson['live_start'])): ?><p class="lf-muted">开播 <?= lf_e((string)$lesson['live_start']) ?><?= !empty($lesson['live_end']) ? ' — ' . lf_e((string)$lesson['live_end']) : '' ?></p><?php endif; ?>
          <?php if ($roomLink !== ''): ?><p class="lf-muted">OpenFlow 直播间：<span class="lf-cert-no"><?= lf_e($liveRoomId) ?></span><?= is_array($ofRemote) ? ' · 状态 ' . lf_e((string)($ofRemote['status'] ?? '')) : '' ?></p><?php endif; ?>
          <?php if ($liveUrl === ''): ?>
            <?php if ($roomLink !== ''): ?>
              <a class="btn primary" style="margin-top:12px" href="<?= lf_e($roomLink) ?>" target="_blank">进入 OpenFlow 直播间</a>
            <?php else: ?>
              <p class="lf-faint">讲师尚未设置直播地址。</p>
            <?php endif; ?>
          <?php elseif ($isEmbed): ?>
            <div style="margin-top:14px;border-radius:var(--r-md);overflow:hidden"><iframe src="<?= lf_e($liveUrl) ?>" allowfullscreen style="width:100%;aspect-ratio:16/9;border:0;background:#000"></iframe></div>
          <?php else: ?>
            <?php if ($lstate !== '直播中'): ?><p class="lf-faint">当前<?= lf_e($lstate) ?>，开播后可直接观看。</p><?php endif; ?>
            <video class="lf-player-video" controls playsinline src="<?= lf_e($liveUrl) ?>" data-live-hls="<?= $isHls ? '1' : '0' ?>"></video>
            <?php if ($isHls): ?><script src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"></script><script>document.addEventListener('DOMContentLoaded',function(){var v=document.querySelector('[data-live-hls="1"]');if(v&&window.Hls&&window.Hls.isSupported()){var h=new window.Hls();h.loadSource(v.getAttribute('src'));h.attachMedia(v);}});</script><?php endif; ?>
          <?php endif; ?>
        </div>
      <?php elseif ($lessonType === 'quiz'): ?>
        <div class="lf-player-art">
          <span class="lf-kicker">测验</span>
          <h1 style="font-family:var(--font-display);margin:10px 0"><?= lf_e((string)$lesson['title']) ?></h1>
          <p class="lf-muted">本课时为测验，请在独立页面完成。通过后回到此处继续。</p>
          <a class="btn primary" style="margin-top:12px" href="<?= lf_url('/quiz/') ?><?= rawurlencode((string)$course['slug']) ?>?quiz=<?= rawurlencode((string)$lesson['quiz_id']) ?>">开始测验</a>
        </div>
      <?php else: ?>
        <div class="lf-player-art">
          <span class="lf-kicker"><?= lf_e(lf_lesson_type_label($lessonType)) ?></span>
          <h1 style="font-family:var(--font-display);margin:10px 0"><?= lf_e((string)$lesson['title']) ?></h1>
          <div class="lf-prose" style="margin-top:18px"><?= $lesson['content'] ?? '' ?></div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($lessonType !== 'quiz'): ?>
    <div class="lf-player-body" style="border:1px solid var(--border);border-radius:var(--r-md);margin-top:16px;background:var(--surface)">
      <div class="lf-course-meta">
        <span class="lf-chip soft"><?= lf_e((string)$lesson['chapter_title']) ?></span>
        <?php if ((int)($lesson['duration'] ?? 0) > 0): ?><span class="lf-chip soft"><?= (int)$lesson['duration'] ?> 分钟</span><?php endif; ?>
        <?php if (!empty($state['done'])): ?><span class="lf-chip ok">已完成</span><?php endif; ?>
      </div>
      <h1 style="font-family:var(--font-display);font-size:23px;margin:10px 0 0"><?= lf_e((string)$lesson['title']) ?></h1>
      <?php foreach ((array)($lesson['attachments'] ?? []) as $att): ?>
        <a class="lf-faint" style="display:inline-flex;align-items:center;gap:6px;margin-top:10px" href="<?= lf_e((string)($att['url'] ?? '#')) ?>" download><?= lf_icon('file', 16) ?> <?= lf_e((string)($att['name'] ?? '附件')) ?></a>
      <?php endforeach; ?>
      <div class="lf-player-actions">
        <?php if ($hasAccess): ?>
          <form method="post">
            <?= lf_csrf_field() ?>
            <input type="hidden" name="action" value="done">
            <button class="btn ghost sm" type="submit"><?= !empty($state['done']) ? lf_t('标记为未完成', 'Mark incomplete') : lf_t('标记完成', 'Mark complete') ?></button>
          </form>
        <?php endif; ?>
        <?php if ($neighbors['prev']): ?><a class="btn subtle sm" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$neighbors['prev']['id']) ?>"><?= lf_icon('arrow-left', 16) ?> <?= lf_t('上一节', 'Prev') ?></a><?php endif; ?>
        <?php if ($neighbors['next']): ?><a class="btn primary sm" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$neighbors['next']['id']) ?>"><?= lf_t('下一节', 'Next') ?> <?= lf_icon('arrow-right', 16) ?></a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  <?php if ($hasAccess && ai_enabled()): ?>
    <div class="lf-form-card" style="max-width:none;margin-top:16px">
      <span class="lf-kicker"><?= lf_t('AI 答疑', 'Ask AI') ?></span>
      <div style="display:flex;gap:8px;margin-top:8px">
        <input class="lf-inp" id="ai-q" placeholder="就本课程提问，例如：这一章的核心是什么？" style="flex:1">
        <button class="btn primary sm" type="button" id="ai-ask" style="flex:0 0 auto">提问</button>
      </div>
      <div id="ai-a" class="lf-flash info" style="display:none;margin-top:10px;white-space:pre-wrap"></div>
    </div>
    <script>
    (function () {
      var t = '<?= lf_csrf_token() ?>', c = '<?= lf_e((string)$course['id']) ?>', api = '<?= lf_url('/api/ai-qa.php') ?>';
      var btn = document.getElementById('ai-ask'), q = document.getElementById('ai-q'), a = document.getElementById('ai-a');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var v = (q.value || '').trim(); if (!v) return;
        btn.disabled = true; btn.textContent = '思考中…'; a.style.display = 'block'; a.textContent = '正在检索课程资料…';
        var fd = new FormData(); fd.append('_token', t); fd.append('course_id', c); fd.append('question', v);
        fetch(api, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
          btn.disabled = false; btn.textContent = '提问';
          if (!d.ok) { a.textContent = d.error || 'AI 失败'; return; }
          a.textContent = d.answer + (d.sources && d.sources.length ? '\n\n—— 参考课时：' + d.sources.join('、') : '');
        }).catch(function () { btn.disabled = false; btn.textContent = '提问'; a.textContent = '网络错误'; });
      });
    })();
    </script>
  <?php endif; ?>

  <?php if ($hasAccess && $studentId !== ''): ?>
    <?php $myNote = note_get($studentId, (string)$course['id'], (string)$lesson['id']); ?>
    <div class="lf-form-card" style="max-width:none;margin-top:16px">
      <span class="lf-kicker"><?= lf_t('我的笔记', 'My notes') ?></span>
      <textarea class="lf-inp" id="note-text" style="margin-top:8px;min-height:90px" placeholder="记录你的笔记（仅自己可见）"><?= lf_e((string)($myNote['content'] ?? '')) ?></textarea>
      <div style="margin-top:8px"><button class="btn primary sm" type="button" id="note-save">保存笔记</button> <span class="lf-faint" id="note-state" style="margin-left:8px"><?= !empty($myNote['updated_at']) ? '上次保存 ' . lf_e((string)$myNote['updated_at']) : '' ?></span></div>
    </div>
    <script>
    (function () {
      var b = document.getElementById('note-save'); if (!b) return;
      b.addEventListener('click', function () {
        var fd = new FormData();
        fd.append('_token', '<?= lf_csrf_token() ?>');
        fd.append('course_id', '<?= lf_e((string)$course['id']) ?>');
        fd.append('lesson_id', '<?= lf_e((string)$lesson['id']) ?>');
        fd.append('content', document.getElementById('note-text').value);
        b.disabled = true;
        fetch('<?= lf_url('/api/note.php') ?>', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (d) {
          b.disabled = false;
          document.getElementById('note-state').textContent = d.ok ? ('已保存 ' + d.saved_at) : (d.error || '失败');
        }).catch(function () { b.disabled = false; });
      });
    })();
    </script>
  <?php endif; ?>
  </div>

  <aside class="lf-sidebar">
    <div class="lf-sidebar-head">
      <a class="lf-faint" href="<?= lf_url('/course/') ?><?= rawurlencode((string)$course['slug']) ?>">← <?= lf_e((string)$course['title']) ?></a>
      <?php if ($summary): ?>
        <?= lf_progress_bar((int)$summary['percent'], '进度 ' . (int)$summary['done'] . '/' . (int)$summary['total'] . ' 课时') ?>
      <?php endif; ?>
      <input class="lf-inp" id="lf-lesson-search" placeholder="<?= lf_t('搜索课时…', 'Search lessons…') ?>" style="height:36px;margin-top:8px">
    </div>
    <?php foreach ($course['chapters'] as $ci => $ch): ?>
      <div class="lf-chapter">
        <div class="lf-chapter-title">第 <?= $ci + 1 ?> 章 · <?= lf_e((string)($ch['title'] ?? '')) ?></div>
        <?php foreach ((array)($ch['lessons'] ?? []) as $l):
            $isOn = (string)$l['id'] === (string)$lesson['id'];
            $lDone = $studentId !== '' ? !empty(progress_lesson_state($studentId, (string)$course['id'], (string)$l['id'])['done']) : false;
        ?>
          <a class="lf-lesson-row<?= $isOn ? ' on' : '' ?>" href="<?= lf_url('/learn/') ?><?= rawurlencode((string)$course['slug']) ?>?lesson=<?= rawurlencode((string)$l['id']) ?>">
            <?= lf_icon((string)($l['type'] ?? 'article')) ?>
            <span><?= lf_e((string)($l['title'] ?? '')) ?></span>
            <?php if ($lDone): ?><span class="lf-lesson-state"><?= lf_icon('check', 15) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($summary && $summary['total'] > 0 && $summary['done'] >= $summary['total']): ?>
      <div class="lf-sidebar-head">
        <span class="lf-chip ok">已学完全部课时</span>
        <?php if (!empty($course['certificate'])): ?>
          <a class="btn primary sm block" href="<?= lf_url('/certificate?course=') ?><?= rawurlencode((string)$course['id']) ?>">查看结业证书</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php $files = course_attachments($course); if ($files): ?>
      <div class="lf-sidebar-head">
        <span class="lf-kicker"><?= lf_t('课程资料', 'Materials') ?></span>
        <?php foreach ($files as $f): ?>
          <a class="lf-faint" style="display:block;margin-top:4px" href="<?= lf_e((string)$f['url']) ?>" target="_blank" rel="noopener"<?= preg_match('#^https?://#', (string)$f['url']) ? '' : ' download' ?>><?= lf_icon('file', 15) ?> <?= lf_e((string)$f['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </aside>
</div>
<script>
(function () {
  var s = document.getElementById('lf-lesson-search'); if (!s) return;
  s.addEventListener('input', function () {
    var q = s.value.trim().toLowerCase();
    document.querySelectorAll('.lf-lesson-row').forEach(function (a) {
      a.style.display = (!q || (a.textContent || '').toLowerCase().indexOf(q) >= 0) ? '' : 'none';
    });
    document.querySelectorAll('.lf-chapter').forEach(function (ch) {
      var any = false;
      ch.querySelectorAll('.lf-lesson-row').forEach(function (a) { if (a.style.display !== 'none') any = true; });
      ch.style.display = any ? '' : 'none';
    });
  });
})();
</script>
<?php lf_page_end(); ?>
