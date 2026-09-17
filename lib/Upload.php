<?php

function lf_upload_allowed(): array
{
    return ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'md', 'csv', 'zip',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'mp4', 'mov', 'webm', 'm4a', 'mp3', 'm3u8', 'ts'];
}

function lf_upload_save(array $file, string $subdir = 'misc', int $maxBytes = 52428800): array
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('上传失败，请重试');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new InvalidArgumentException('文件超过大小限制');
    }
    $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, lf_upload_allowed(), true)) {
        throw new InvalidArgumentException('不支持的文件类型');
    }
    $subdir = trim(preg_replace('/[^a-z0-9_\-\/]/i', '', $subdir), '/');
    $dir = LF_UPLOAD_DIR . '/' . $subdir;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $dir . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('保存文件失败');
    }
    @chmod($target, 0644);
    return [
        'rel' => $subdir . '/' . $name,
        'name' => mb_substr((string)$file['name'], 0, 120),
        'size' => (int)filesize($target),
        'ext' => $ext,
    ];
}

function lf_file_sign(string $rel, string $scope = '', int $expires = 0): string
{
    return hash_hmac('sha256', $rel . '|' . $scope . '|' . $expires, lf_secret());
}

function lf_file_url(string $rel, string $scope = '', int $ttl = 86400): string
{
    $expires = $ttl > 0 ? time() + $ttl : 0;
    $sig = substr(lf_file_sign($rel, $scope, $expires), 0, 24);
    return lf_url('/file?p=' . rawurlencode($rel) . '&e=' . $expires . '&s=' . $sig . ($scope !== '' ? '&u=' . rawurlencode($scope) : ''));
}

function lf_file_verify(string $rel, string $scope, int $expires, string $sig): bool
{
    if ($expires > 0 && $expires < time()) return false;
    return hash_equals(substr(lf_file_sign($rel, $scope, $expires), 0, 24), $sig);
}

function lf_file_path(string $rel): ?string
{
    $rel = str_replace('\\', '/', $rel);
    if ($rel === '' || str_contains($rel, '..') || str_starts_with($rel, '/')) return null;
    $path = LF_UPLOAD_DIR . '/' . $rel;
    $real = realpath($path);
    $root = realpath(LF_UPLOAD_DIR);
    if ($real === false || $root === false || !str_starts_with($real, $root . '/')) return null;
    return is_file($real) ? $real : null;
}
