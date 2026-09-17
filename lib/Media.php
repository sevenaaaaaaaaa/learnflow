<?php

function media_resolve(array $lesson, string $courseId = '', string $studentId = ''): string
{
    $video = trim((string)($lesson['video'] ?? ''));
    if ($video === '') return '';
    if (str_starts_with($video, 'upload:')) {
        $rel = substr($video, 7);
        require_once __DIR__ . '/Upload.php';
        return lf_file_url($rel, $studentId, 7200);
    }
    return $video;
}

function media_kind(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    if (str_ends_with(strtolower($path), '.m3u8')) return 'hls';
    return 'video';
}

function media_is_protected(array $lesson): bool
{
    return str_starts_with(trim((string)($lesson['video'] ?? '')), 'upload:');
}

function media_upload_rel(array $lesson): string
{
    $video = trim((string)($lesson['video'] ?? ''));
    return str_starts_with($video, 'upload:') ? substr($video, 7) : '';
}
