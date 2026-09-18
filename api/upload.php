<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$admin = lf_admin_current();
if ($admin === null) {
    lf_json_out(['ok' => false, 'error' => '未登录'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lf_json_out(['ok' => false, 'error' => '仅支持 POST'], 405);
}
if (!lf_csrf_check((string)($_POST['_token'] ?? ''))) {
    lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);
}
if (empty($_FILES['file'])) {
    lf_json_out(['ok' => false, 'error' => '缺少文件'], 400);
}

$scope = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_POST['scope'] ?? 'media'));
try {
    $saved = lf_upload_save($_FILES['file'], 'courses/' . ($scope !== '' ? $scope : 'media'));
    $media = media_add($saved, ['scope' => $scope]);
    lf_json_out([
        'ok' => true,
        'id' => $media['id'],
        'rel' => $saved['rel'],
        'name' => $saved['name'],
        'size' => $saved['size'],
        'kind' => $media['kind'],
        'url' => media_public_url((string)$media['id']),
        'play' => lf_file_url($saved['rel'], '', 3600),
    ]);
} catch (Throwable $e) {
    lf_json_out(['ok' => false, 'error' => $e->getMessage()], 400);
}
