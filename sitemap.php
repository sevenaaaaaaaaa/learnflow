<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
$base = rtrim(lf_abs_url('/'), '/');
$urls = [
    ['loc' => $base . lf_url('/'), 'priority' => '1.0'],
    ['loc' => $base . lf_url('/courses'), 'priority' => '0.9'],
    ['loc' => $base . lf_url('/camp'), 'priority' => '0.7'],
    ['loc' => $base . lf_url('/certificate'), 'priority' => '0.4'],
];
foreach (course_all(true) as $c) {
    $urls[] = ['loc' => $base . lf_url('/course/' . rawurlencode((string)($c['slug'] ?? $c['id']))), 'priority' => '0.8', 'lastmod' => substr((string)($c['updated_at'] ?? ''), 0, 10)];
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url><loc>" . htmlspecialchars($u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>";
    if (!empty($u['lastmod'])) echo "<lastmod>" . htmlspecialchars($u['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</lastmod>";
    echo "<priority>" . $u['priority'] . "</priority></url>\n";
}
echo "</urlset>\n";
