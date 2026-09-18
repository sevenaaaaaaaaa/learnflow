<?php

if (!defined('LF_SHELL_VER')) define('LF_SHELL_VER', '20260916a');

if (!function_exists('lf_head')) {
    function lf_head(array $opts = []): void
    {
        $title = (string)($opts['title'] ?? 'LearnFlow · 课程与训练营交付引擎');
        $desc = (string)($opts['description'] ?? '课程交付、学员进度、测验证书、训练营运营——讲师与训练营主理人的交付工具。');
        $canonical = (string)($opts['canonical'] ?? lf_abs_url((string)($_SERVER['REQUEST_URI'] ?? '/')));
        $appcss = (string)($opts['page_css'] ?? '');
        $v = LF_SHELL_VER;
        echo '<meta charset="utf-8">' . "\n";
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        echo '<title>' . lf_e($title) . '</title>' . "\n";
        echo '<meta name="description" content="' . lf_e($desc) . '">' . "\n";
        echo '<link rel="canonical" href="' . lf_e($canonical) . '">' . "\n";
        $reqPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $base = lf_base_path();
        $rel = ($base !== '' && str_starts_with($reqPath, $base)) ? substr($reqPath, strlen($base)) : $reqPath;
        $rel = preg_replace('#^/en(?=/|$)#', '', $rel);
        if ($rel === '') $rel = '/';
        $zhUrl = lf_abs_url($rel);
        $enUrl = lf_abs_url('/en' . ($rel === '/' ? '' : $rel));
        echo '<link rel="alternate" hreflang="zh-CN" href="' . lf_e($zhUrl) . '">' . "\n";
        echo '<link rel="alternate" hreflang="en" href="' . lf_e($enUrl) . '">' . "\n";
        echo '<link rel="alternate" hreflang="x-default" href="' . lf_e($zhUrl) . '">' . "\n";
        echo '<meta property="og:title" content="' . lf_e($title) . '">' . "\n";
        echo '<meta property="og:description" content="' . lf_e($desc) . '">' . "\n";
        echo '<meta property="og:type" content="website">' . "\n";
        $ogImage = (string)(lf_setting_get('og_image') ?: '');
        if ($ogImage !== '') echo '<meta property="og:image" content="' . lf_e($ogImage) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta property="og:site_name" content="' . lf_e((string)(lf_setting_get('site_name') ?: 'LearnFlow')) . '">' . "\n";
        echo '<link rel="icon" href="' . lf_e(lf_favicon_data_uri()) . '">' . "\n";
        echo '<link rel="manifest" href="' . lf_url('/manifest.webmanifest') . '">' . "\n";
        echo '<meta name="theme-color" content="#2f6bff">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . lf_url('/assets/icon.svg') . '">' . "\n";
        echo '<script>window.LF_BASE=' . json_encode(lf_base_path()) . ';</script>' . "\n";
        echo '<script>try{var t=JSON.parse(localStorage.getItem("learnflow-theme")||"{}");if(t.theme)document.documentElement.dataset.theme=t.theme;else if(matchMedia("(prefers-color-scheme:dark)").matches)document.documentElement.dataset.theme="dark";}catch(e){}</script>' . "\n";
        echo '<link rel="stylesheet" href="' . lf_url('/assets/fonts/fonts.css') . '?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" href="' . lf_url('/assets/tokens.css') . '?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" href="' . lf_url('/assets/modules.css') . '?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" href="' . lf_url('/assets/app.css') . '?v=' . $v . '">' . "\n";
        echo '<link rel="stylesheet" href="' . lf_url('/assets/editor.css') . '?v=' . $v . '">' . "\n";
        if ($appcss !== '') echo $appcss . "\n";
    }
}

if (!function_exists('lf_favicon_data_uri')) {
    function lf_favicon_data_uri(): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><defs><linearGradient id='g' x1='2' y1='16' x2='30' y2='16' gradientUnits='userSpaceOnUse'><stop stop-color='oklch(52%25 .17 258)'/><stop offset='1' stop-color='oklch(58%25 .16 285)'/></linearGradient></defs><circle cx='16' cy='16' r='16' fill='oklch(16%25 0 0)'/><path d='M16 6.5a9.5 9.5 0 1 1-9.5 9.5' stroke='url(%23g)' stroke-width='2.4' stroke-linecap='round' fill='none'/><path d='M12 9.5v13M12 13.6h7.6M12 18h7.6' stroke='oklch(96%25 0 0)' stroke-width='2.2' stroke-linecap='round' fill='none'/></svg>";
        return 'data:image/svg+xml,' . str_replace(['#', '"'], ['%23', "'"], $svg);
    }
}

if (!function_exists('lf_theme_toggle')) {
    function lf_theme_toggle(): string
    {
        return '<button type="button" class="icon-btn" data-lf-theme aria-label="切换主题">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/></svg>'
            . '</button>';
    }
}
