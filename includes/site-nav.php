<?php

if (!function_exists('lf_nav')) {
    function lf_nav(array $opts = []): void
    {
        $active = (string)($opts['active'] ?? '');
        $student = function_exists('lf_student_current') ? lf_student_current() : null;
        $items = [
            'courses' => [lf_url('/courses'), '课程'],
            'camp' => [lf_url('/camp'), '训练营'],
            'dashboard' => [lf_url('/dashboard'), '我的学习'],
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
      <?php if ($student): ?>
        <a class="btn ghost lf-nav-cta" href="<?= lf_url('/dashboard') ?>"><?= lf_e((string)($student['name'] ?? '我')) ?></a>
      <?php else: ?>
        <a class="lf-nav-link lf-nav-login" href="<?= lf_url('/login') ?>">登录</a>
        <a class="btn primary lf-nav-cta" href="<?= lf_url('/courses') ?>">开始学习</a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php
    }
}
