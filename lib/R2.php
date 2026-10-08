<?php

require_once __DIR__ . '/ApiKey.php';

function r2_token(): string
{
    return (string)(lf_setting_get('r2_token') ?: '');
}

function r2_base(): string
{
    return (string)(lf_setting_get('r2_base') ?: 'https://nownexts.com/lf-files');
}

function r2_key_for(string $rel): string
{
    return 'media/learnflow/' . ltrim($rel, '/');
}

function r2_url(string $rel): string
{
    return r2_base() . '/' . r2_key_for($rel);
}

function r2_put(string $rel, string $localPath, ?string &$err = null): bool
{
    $err = null;
    if (!is_file($localPath)) { $err = 'local file missing'; return false; }
    $url = r2_base() . '/' . r2_key_for($rel);
    $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    $mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'avif' => 'image/avif', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'pdf' => 'application/pdf'];
    $mime = $mimes[$ext] ?? (function_exists('mime_content_type') && is_file($localPath) ? (mime_content_type($localPath) ?: 'application/octet-stream') : 'application/octet-stream');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'X-LF-Token: ' . r2_token(),
            'Content-Type: ' . $mime,
            'Expect:',
        ],
        CURLOPT_POSTFIELDS => file_get_contents($localPath),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) { $err = 'curl: ' . curl_error($ch); return false; }
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 200) { $err = 'HTTP ' . $code . ' ' . mb_substr((string)$resp, 0, 200); return false; }
    return str_contains((string)$resp, '"ok":true');
}

function r2_get(string $rel): string
{
    $url = r2_base() . '/' . r2_key_for($rel);
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
    return @file_get_contents($url, false, $ctx);
}

function r2_delete(string $rel): bool
{
    $url = r2_base() . '/' . r2_key_for($rel);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => ['X-LF-Token: ' . r2_token()],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $code === 200;
}
