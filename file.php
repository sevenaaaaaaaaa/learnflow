<?php
require_once __DIR__ . '/includes/bootstrap.php';

$rel = (string)($_GET['p'] ?? '');
$expires = (int)($_GET['e'] ?? 0);
$sig = (string)($_GET['s'] ?? '');
$scope = (string)($_GET['u'] ?? '');

if (!lf_file_verify($rel, $scope, $expires, $sig)) {
    http_response_code(403);
    exit('forbidden');
}

$isAdmin = lf_admin_current() !== null;
$student = lf_student_current();

if ($scope !== '') {
    if (!$isAdmin && ($student === null || (string)$student['id'] !== $scope)) {
        http_response_code(403);
        exit('forbidden');
    }
} elseif ($student === null && !$isAdmin) {
    http_response_code(403);
    exit('forbidden');
}

$path = lf_file_path($rel);
if ($path === null) {
    http_response_code(404);
    exit('not found');
}

$size = filesize($path);
$mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
$start = 0;
$end = $size - 1;
$partial = false;
if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $m)) {
    $start = $m[1] === '' ? 0 : (int)$m[1];
    $end = $m[2] === '' ? $size - 1 : (int)$m[2];
    if ($start > $end || $end >= $size) {
        http_response_code(416);
        header('Content-Range: bytes */' . $size);
        exit;
    }
    $partial = true;
}

header('Content-Type: ' . $mime);
header('Accept-Ranges: bytes');
header('Content-Disposition: inline; filename="' . rawurlencode(basename($rel)) . '"');
header('Cache-Control: private, max-age=3600');
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
    $chunk = fread($fp, (int)min(8192, $remaining));
    if ($chunk === false) break;
    echo $chunk;
    $remaining -= strlen($chunk);
    if (connection_aborted()) break;
}
fclose($fp);
