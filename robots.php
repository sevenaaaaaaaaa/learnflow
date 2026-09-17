<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
$siteUrl = rtrim(lf_abs_url('/'), '/');
echo "User-agent: *\n";
foreach (['/admin/', '/api/', '/account', '/notifications', '/forgot-password', '/reset-password', '/data/', '/join', '/logout.php'] as $p) {
    echo "Disallow: " . lf_url($p) . "\n";
}
echo "\nSitemap: " . $siteUrl . "/sitemap.xml\n";
