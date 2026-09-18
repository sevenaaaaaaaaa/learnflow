<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$res = autonomy_run((int)($argv[1] ?? 10));
echo "autonomy run: executed={$res['executed']} level=" . autonomy_settings()['level'] . "\n";
foreach ($res['log'] as $l) echo '  ' . $l['id'] . ' — ' . $l['reason'] . "\n";
