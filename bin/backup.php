<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$withUploads = in_array('--with-uploads', $argv, true);
$dir = (string)(lf_setting_get('backup_dir') ?: (LF_DATA_DIR . '/backups'));
$keep = max(1, (int)(lf_setting_get('backup_keep', 14) ?: 14));
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$stamp = date('Ymd-His');
$dest = rtrim($dir, '/') . '/' . $stamp;
if (!is_dir($dest)) @mkdir($dest, 0755, true);

$manifest = ['at' => date('Y-m-d H:i:s'), 'driver' => lf_db_config()['driver'], 'enabled' => lf_db_enabled(), 'collections' => 0, 'files' => 0];

if (lf_db_enabled()) {
    $pdo = lf_db();
    if ($pdo !== null) {
        $rows = $pdo->query('SELECT k, v, updated_at FROM lf_kv')->fetchAll();
        $dump = [];
        foreach ($rows as $r) $dump[$r['k']] = ['updated_at' => $r['updated_at'], 'v' => $r['v']];
        file_put_contents($dest . '/lf_kv.json', json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $manifest['collections'] = count($dump);
    }
}

$jsonDir = $dest . '/json';
@mkdir($jsonDir, 0755, true);
foreach (glob(LF_DATA_DIR . '/*.json') ?: [] as $f) {
    @copy($f, $jsonDir . '/' . basename($f));
    $manifest['files']++;
}

if ($withUploads && is_dir(LF_UPLOAD_DIR)) {
    $zipFile = $dest . '/uploads.zip';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(LF_UPLOAD_DIR, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) $zip->addFile($file->getPathname(), 'uploads/' . substr($file->getPathname(), strlen(LF_UPLOAD_DIR) + 1));
            }
            $zip->close();
        }
    }
    $manifest['uploads'] = true;
}

file_put_contents($dest . '/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$dirs = array_values(array_filter(glob(rtrim($dir, '/') . '/*', GLOB_ONLYDIR) ?: [], fn($d) => basename($d) !== 'backups'));
rsort($dirs);
$removed = 0;
foreach (array_slice($dirs, $keep) as $old) {
    $fit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($fit as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
    if (@rmdir($old)) $removed++;
}

echo "backup: {$dest}\n";
echo "collections={$manifest['collections']} files={$manifest['files']} uploads=" . (!empty($manifest['uploads']) ? 'yes' : 'no') . "\n";
echo "pruned={$removed} keep={$keep}\n";
