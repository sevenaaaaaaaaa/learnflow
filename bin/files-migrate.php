<?php
/**
 * 迁移 uploads/ 中的历史文件到 R2（幂等：已有副本跳过）
 * 用法: php bin/files-migrate.php
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('cli only'); }

$root = LF_UPLOAD_DIR;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$seen = 0; $pushed = 0; $failed = 0; $bytes = 0;
foreach ($rii as $file) {
    if (!$file->isFile()) continue;
    $seen++;
    $rel = substr($file->getPathname(), strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);
    if (function_exists('lf_file_path') && lf_file_path($rel) === null) continue; // 越权路径
    if (r2_get($rel) !== '') continue; // 幂等跳过
    $err = '';
    if (r2_put($rel, (string)$file->getPathname(), $err)) $pushed++;
    else { $failed++; fwrite(STDERR, 'fail: ' . $rel . ' — ' . $err . "\n"); }
}
echo "scan={$seen} pushed={$pushed} failed={$failed}\n";
