<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (string)($_GET['id'] ?? '');
$m = $id !== '' ? media_find($id) : null;
if ($m === null) {
    http_response_code(404);
    exit('not found');
}
$path = lf_file_path((string)$m['rel']);
if ($path === null) {
    http_response_code(404);
    exit('not found');
}

$mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'avif' => 'image/avif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'pdf' => 'application/pdf'];
$ext = strtolower((string)($m['ext'] ?? ''));
$mime = $mimes[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream');

$size = filesize($path);
$start = 0;
$end = $size - 1;
$partial = false;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $mm)) {
    $start = $mm[1] === '' ? 0 : (int)$mm[1];
    $end = $mm[2] === '' ? $size - 1 : (int)$mm[2];
    if ($start > $end || $end >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $partial = true;
}

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Disposition: inline; filename="' . rawurlencode((string)$m['name']) . '"');
if ($partial) {
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
}
header('Content-Length: ' . ($end - $start + 1));

$fp = fopen($path, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fseek($fp, $start);
$remaining = $end - $start + 1;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, (int)min(65536, $remaining));
    if ($chunk === false) break;
    echo $chunk;
    $remaining -= strlen($chunk);
    if (connection_aborted()) break;
}
fclose($fp);
