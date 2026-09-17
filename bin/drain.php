<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$res = lf_dispatch_webhooks((int)($argv[1] ?? 50));
echo json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
