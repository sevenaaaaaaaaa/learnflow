<?php

if (!function_exists('lf_nav')) {
    function lf_nav(array $opts = []): void
    {
        $active = (string)($opts['active'] ?? '');
        $student = function_exists('lf_student_current') ? lf_student_current() : null;
        $items = [
            'courses' => [lf_url('/courses'), lf_t('课程', 'Courses')],
            'camp' => [lf_url('/camp'), lf_t('训练营', 'Bootcamp')],
            'membership' => [lf_url('/membership'), lf_t('会员', 'Membership')],
            'dashboard' => [lf_url('/dashboard'), lf_t('我的学习', 'My Learning')],
        ];
        $siteName = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
        ?>
<header class="lf-nav" data-od-id="site-nav">
  <div class="lf-nav-inner">
    <a class="lf-brand" href="<?= lf_url('/courses') ?>">
      <span class="lf-brand-ic"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 5a11 11 0 1 1-11 11" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M11 8.5v15M11 13.6h8.4M11 18.6h8.4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></span>
      <span class="lf-brand-tx"><?= lf_e($siteName) ?><small>课程交付引擎</small></span>
    </a>
    <nav class="lf-nav-links">
      <?php foreach ($items as $key => [$href, $label]): ?>
        <a class="lf-nav-link<?= $active === $key ? ' on' : '' ?>" href="<?= lf_e($href) ?>"><?= lf_e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="lf-nav-actions">
      <?= lf_theme_toggle() ?>
      <?php
      $reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
      $b = lf_base_path();
      $relPath = ($b !== '' && str_starts_with($reqPath, $b)) ? substr($reqPath, strlen($b)) : $reqPath;
      $relPath = preg_replace('#^/en(?=/|$)#', '', $relPath);
      if ($relPath === '') $relPath = '/';
      $switchHref = lf_lang() === 'en' ? lf_url($relPath) : lf_url('/en' . ($relPath === '/' ? '' : $relPath));
      ?>
      <a class="lf-nav-link" href="<?= lf_e($switchHref) ?>" style="height:38px"><?= lf_lang() === 'en' ? '中文' : 'EN' ?></a>
      <?php if ($student): ?>
        <?php $unread = function_exists('notify_unread_count') ? notify_unread_count((string)$student['id']) : 0; ?>
        <a class="icon-btn lf-bell" href="<?= lf_url('/notifications') ?>" aria-label="通知">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M10.3 20a2 2 0 0 0 3.4 0"/></svg>
          <?php if ($unread > 0): ?><span class="lf-badge"><?= $unread > 99 ? '99+' : (int)$unread ?></span><?php endif; ?>
        </a>
        <a class="btn ghost lf-nav-cta" href="<?= lf_url('/account') ?>"><?= lf_e((string)($student['name'] ?? '我')) ?></a>
      <?php else: ?>
        <a class="lf-nav-link lf-nav-login" href="<?= lf_url('/login') ?>"><?= lf_t('登录', 'Log in') ?></a>
        <a class="btn primary lf-nav-cta" href="<?= lf_url('/courses') ?>"><?= lf_t('开始学习', 'Start learning') ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php
    }
}
