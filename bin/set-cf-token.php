<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }

// 把 Cloudflare API Token 存配置，供部署 Worker 使用
// 用法：php bin/set-cf-token.php   （重启终端粘贴；读取 docs/secrets-local.md）
if (PHP_SAPI === 'cli') {
    if (empty($argv[1])) {
        // 从 docs/secrets-local.md 读取
        $txt = @file_get_contents(dirname(__DIR__) . '/docs/secrets-local.md');
        if (preg_match('/(cfat_[A-Za-z0-9]+)/', $txt, $m)) {
            $argv[1] = $m[1];
        }
    }
}

$token = trim((string)($argv[1] ?? ''));
if ($token === '') { fwrite(STDERR, "用法: php bin/set-cf-token.php <CF_TOKEN>\n"); exit(1); }

lf_setting_set('cf_token', $token);
echo "cf_token saved, prefix " . mb_substr($token, 0, 8) . "…\n";
