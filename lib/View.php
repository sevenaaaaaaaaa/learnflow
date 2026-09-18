<?php

function lf_flash(string $type, string $message): void
{
    $_SESSION['lf_flash'][] = ['type' => $type, 'message' => $message];
}

function lf_flash_render(): string
{
    $items = $_SESSION['lf_flash'] ?? [];
    unset($_SESSION['lf_flash']);
    if (!$items) return '';
    $out = '<div class="lf-flash-stack">';
    foreach ($items as $it) {
        $type = in_array($it['type'], ['ok', 'warn', 'danger', 'info'], true) ? $it['type'] : 'info';
        $out .= '<div class="lf-flash ' . $type . '">' . lf_e((string)$it['message']) . '</div>';
    }
    return $out . '</div>';
}

function lf_page_start(array $opts = []): void
{
    require_once LF_ROOT . '/includes/site-head.php';
    require_once LF_ROOT . '/includes/site-nav.php';
    $bare = !empty($opts['bare']);
    $bodyClass = trim((string)($opts['body_class'] ?? ''));
    echo '<!doctype html>' . "\n";
    echo '<html lang="zh-CN" data-theme="light">' . "\n";
    echo '<head>' . "\n";
    lf_head($opts);
    echo '</head>' . "\n";
    echo '<body class="' . lf_e($bodyClass) . '">' . "\n";
    if (!$bare) {
        lf_nav(['active' => (string)($opts['active'] ?? '')]);
    }
    echo '<main class="lf-main">' . "\n";
    if (!empty($opts['container']) && !$bare) echo '<div class="lf-container">' . "\n";
    echo lf_flash_render();
}

function lf_page_end(array $opts = []): void
{
    require_once LF_ROOT . '/includes/site-footer.php';
    if (!empty($opts['container']) && empty($opts['bare'])) echo '</div>' . "\n";
    echo '</main>' . "\n";
    lf_footer();
    echo '</body></html>';
}

function lf_icon(string $name, int $size = 18): string
{
    $paths = [
        'play' => '<path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"/>',
        'article' => '<path d="M6 4h9l4 4v12H6z" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9 11h7M9 15h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'quiz' => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9.5 9.6a2.5 2.5 0 1 1 3.2 2.4c-.8.3-1.2.9-1.2 1.7M12 16.6h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'file' => '<path d="M7 3.5h7l4 4v13H7z" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M14 3.5v4h4" fill="none" stroke="currentColor" stroke-width="1.7"/>',
        'live' => '<circle cx="12" cy="12" r="3" fill="currentColor"/><path d="M6 6a8.5 8.5 0 0 0 0 12M18 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
        'cert' => '<circle cx="12" cy="10" r="6" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9 15.5 8 22l4-2.2L16 22l-1-6.5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>',
        'arrow-left' => '<path d="M14.5 6 8.5 12l6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'arrow-right' => '<path d="M9.5 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'user' => '<circle cx="12" cy="8" r="3.6" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M5 20c1.2-4 4-5.6 7-5.6S17.8 16 19 20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'chart' => '<path d="M5 19V5M5 19h14M9 16v-5M13 16V8M17 16v-3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M12 7.5V12l3 2" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'share' => '<circle cx="7" cy="12" r="2.6" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="17" cy="6.5" r="2.6" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="17" cy="17.5" r="2.6" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M9.3 10.7 14.7 7.8M9.3 13.3l5.4 2.9" stroke="currentColor" stroke-width="1.7"/>',
    ];
    $inner = $paths[$name] ?? $paths['article'];
    return '<svg class="lf-ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

function lf_lesson_type_label(string $type): string
{
    return ['video' => '视频', 'article' => '图文', 'quiz' => '测验', 'file' => '资料', 'live' => '直播'][$type] ?? '课时';
}

function lf_progress_bar(int $percent, string $label = ''): string
{
    $percent = max(0, min(100, $percent));
    $out = '<div class="lf-progress"><div class="lf-progress-fill" style="width:' . $percent . '%"></div></div>';
    if ($label !== '') $out .= '<span class="lf-progress-label">' . lf_e($label) . '</span>';
    return $out;
}

function lf_course_card(array $course, array $opts = []): string
{
    if (function_exists('lf_localize_course')) $course = lf_localize_course($course);
    $href = lf_url('/course/' . rawurlencode((string)($course['slug'] ?? $course['id'] ?? '')));
    $lessons = course_lesson_count($course);
    $chapters = count((array)($course['chapters'] ?? []));
    $progress = $opts['progress'] ?? null;
    ob_start();
    ?>
<article class="lf-course-card">
  <a class="lf-course-cover" href="<?= lf_e($href) ?>" aria-hidden="true">
    <?php if (!empty($course['cover'])): ?>
      <img src="<?= lf_e((string)$course['cover']) ?>" alt="">
    <?php else: ?>
      <span class="lf-cover-fallback"><?= lf_e(mb_substr((string)($course['title'] ?? '课'), 0, 1)) ?></span>
    <?php endif; ?>
  </a>
  <div class="lf-course-body">
    <div class="lf-course-meta">
      <span class="lf-chip"><?= lf_e((string)($course['type'] ?? '单课')) ?></span>
      <span class="lf-chip soft"><?= (int)$chapters ?> 章 · <?= (int)$lessons ?> 课时</span>
    </div>
    <h3 class="lf-course-title"><a href="<?= lf_e($href) ?>"><?= lf_e((string)($course['title'] ?? '')) ?></a></h3>
    <p class="lf-course-sub"><?= lf_e((string)($course['subtitle'] ?? $course['summary'] ?? '')) ?></p>
    <?php if ($progress !== null): ?>
      <div class="lf-course-progress"><?= lf_progress_bar((int)$progress, '已完成 ' . (int)$progress . '%') ?></div>
    <?php endif; ?>
    <div class="lf-course-foot">
      <span class="lf-price"><?= lf_e(course_price_label($course)) ?></span>
      <?php if (!empty($course['instructor'])): ?><span class="lf-instructor"><?= lf_e((string)$course['instructor']) ?></span><?php endif; ?>
    </div>
  </div>
</article>
    <?php
    return (string)ob_get_clean();
}

function lf_admin_page_start(array $opts = []): void
{
    require_once LF_ROOT . '/includes/site-head.php';
    $active = (string)($opts['active'] ?? '');
    $nav = [
        'index' => [lf_url('/admin/'), '看板', 'chart'],
        'analytics' => [lf_url('/admin/analytics.php'), '营收', 'chart'],
        'courses' => [lf_url('/admin/courses.php'), '课程', 'article'],
        'quizzes' => [lf_url('/admin/quizzes.php'), '测验', 'quiz'],
        'categories' => [lf_url('/admin/categories.php'), '分类', 'file'],
        'media' => [lf_url('/admin/media.php'), '素材', 'file'],
        'library' => [lf_url('/admin/library.php'), '内容库', 'file'],
        'ai' => [lf_url('/admin/ai.php'), 'AI 工作台', 'chart'],
        'schedule' => [lf_url('/admin/schedule.php'), '排期', 'clock'],
        'assignments' => [lf_url('/admin/assignments.php'), '作业', 'file'],
        'students' => [lf_url('/admin/students.php'), '学员', 'user'],
        'invites' => [lf_url('/admin/invites.php'), '邀请码', 'share'],
        'certificates' => [lf_url('/admin/certificates.php'), '证书', 'cert'],
        'community' => [lf_url('/admin/community.php'), '圈子', 'share'],
        'notify' => [lf_url('/admin/notify.php'), '通知', 'share'],
        'templates' => [lf_url('/admin/templates.php'), '模板', 'file'],
        'marketing' => [lf_url('/admin/marketing.php'), '营销', 'share'],
        'membership' => [lf_url('/admin/membership.php'), '会员', 'user'],
        'commissions' => [lf_url('/admin/commissions.php'), '分销', 'share'],
        'apikeys' => [lf_url('/admin/apikeys.php'), 'API/MCP', 'share'],
        'evolution' => [lf_url('/admin/evolution.php'), '自进化', 'chart'],
        'audit' => [lf_url('/admin/audit.php'), '审计', 'file'],
        'users' => [lf_url('/admin/users.php'), '账号', 'user'],
        'settings' => [lf_url('/admin/settings.php'), '设置', 'chart'],
    ];
    $adminOnlyKeys = ['marketing', 'membership', 'commissions', 'apikeys', 'audit', 'evolution', 'users', 'settings'];
    $role = function_exists('lf_admin_role') ? lf_admin_role() : 'admin';
    if ($role !== 'admin') {
        foreach ($adminOnlyKeys as $k) unset($nav[$k]);
    }
    echo '<!doctype html><html lang="zh-CN" data-theme="light"><head>';
    lf_head(array_merge([
        'title' => (string)($opts['title'] ?? '讲师后台 · LearnFlow'),
        'description' => 'LearnFlow 讲师后台',
    ], $opts));
    echo '</head><body><div class="lf-admin"><aside class="lf-admin-side">';
    echo '<a class="lf-brand" href="' . lf_url('/admin/') . '"><span class="lf-brand-ic"><svg viewBox="0 0 32 32" fill="none"><path d="M16 5a11 11 0 1 1-11 11" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M11 8.5v15M11 13.6h8.4M11 18.6h8.4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></span><span class="lf-brand-tx">LearnFlow<small>讲师后台</small></span></a>';
    echo '<nav class="lf-admin-nav">';
    foreach ($nav as $key => [$href, $label, $icon]) {
        echo '<a class="' . ($active === $key ? 'on' : '') . '" href="' . $href . '">' . lf_icon($icon, 17) . lf_e($label) . '</a>';
    }
    echo '</nav>';
    $roleLabels = ['admin' => '管理员', 'editor' => '编辑', 'viewer' => '只读'];
    echo '<div style="margin:14px 8px 0;font-size:12px;color:var(--faint)">' . lf_e((string)(lf_admin_current() ?? '')) . ' · ' . lf_e($roleLabels[$role] ?? $role) . '</div>';
    echo '<div style="margin-top:14px;padding:0 8px"><form method="post" action="' . lf_url('/admin/logout.php') . '">' . lf_csrf_field() . '<button class="btn subtle sm block" type="submit">退出后台</button></form></div>';
    echo '</aside><main class="lf-admin-body">';
    echo lf_flash_render();
}

function lf_admin_page_end(): void
{
    echo '</main></div>';
    echo '<script src="' . lf_url('/assets/app.js') . '?v=' . LF_SHELL_VER . '" defer></script>';
    echo '<script src="' . lf_url('/assets/editor.js') . '?v=' . LF_SHELL_VER . '" defer></script>';
    echo '<script src="' . lf_url('/assets/admin-ai.js') . '?v=' . LF_SHELL_VER . '" defer></script>';
    echo '</body></html>';
}

