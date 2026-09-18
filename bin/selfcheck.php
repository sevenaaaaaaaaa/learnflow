<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$list = evolution_generate();
$high = 0;
foreach ($list as $p) if (($p['severity'] ?? '') === 'high' && ($p['status'] ?? '') === 'open') $high++;
echo "self-check done: proposals=" . count($list) . " open_high={$high}\n";
foreach ($list as $p) if (($p['status'] ?? '') === 'open') echo '  [' . $p['severity'] . '] ' . $p['title'] . "\n";
