<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

if (!lf_db_enabled()) {
    fwrite(STDERR, "数据库未启用：后台 → 设置 → 数据层 启用后再迁移\n");
    exit(1);
}
$pdo = lf_db();
if ($pdo === null) {
    fwrite(STDERR, "数据库连接失败，请检查配置\n");
    exit(1);
}

$imported = 0;
$skipped = 0;
foreach (glob(LF_DATA_DIR . '/*.json') ?: [] as $file) {
    $name = basename($file);
    if (in_array($name, ['php-error.log'], true)) { $skipped++; continue; }
    $data = json_read_file($file);
    if (!$data) { $skipped++; continue; }
    $key = lf_db_key($file);
    lf_kv_write($key, $data);
    $imported++;
    echo "  imported {$name} -> {$key} (" . count($data) . ")\n";
}

$count = (int)$pdo->query('SELECT COUNT(*) AS c FROM lf_kv')->fetch()['c'];
echo "完成：导入 {$imported} 个集合，跳过 {$skipped} 个，库中集合数 {$count}\n";
