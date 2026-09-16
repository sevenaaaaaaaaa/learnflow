<?php

if (!function_exists('lf_footer')) {
    function lf_footer(): void
    {
        $siteName = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
        $slogan = (string)(lf_setting_get('site_slogan') ?: '上课 → 进度 → 测验 → 证书 → 复购');
        ?>
<footer class="lf-foot" data-od-id="site-footer">
  <div class="lf-foot-inner">
    <div class="lf-foot-brand">
      <span class="lf-brand-ic"><svg viewBox="0 0 32 32" fill="none" aria-hidden="true"><path d="M16 5a11 11 0 1 1-11 11" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/><path d="M11 8.5v15M11 13.6h8.4M11 18.6h8.4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg></span>
      <div><strong><?= lf_e($siteName) ?></strong><span><?= lf_e($slogan) ?></span></div>
    </div>
    <nav class="lf-foot-links">
      <a href="<?= lf_url('/courses') ?>">课程</a>
      <a href="<?= lf_url('/camp') ?>">训练营</a>
      <a href="<?= lf_url('/certificate') ?>">证书验证</a>
      <a href="<?= lf_url('/admin/') ?>">讲师后台</a>
    </nav>
    <p class="lf-foot-copy">© <?= date('Y') ?> 芭乐派 · LearnFlow</p>
  </div>
</footer>
<script src="<?= lf_url('/assets/app.js') ?>?v=<?= LF_SHELL_VER ?>" defer></script>
<?php
    }
}
