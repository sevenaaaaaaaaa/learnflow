<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Content-Type: application/manifest+json; charset=utf-8');
$name = (string)(lf_setting_get('site_name') ?: 'LearnFlow');
echo json_encode([
    'name' => $name,
    'short_name' => mb_substr($name, 0, 12),
    'description' => (string)(lf_setting_get('site_description') ?: '课程交付与训练营'),
    'start_url' => lf_url('/courses'),
    'scope' => lf_url('/'),
    'display' => 'standalone',
    'background_color' => '#f5f4ee',
    'theme_color' => '#2f6bff',
    'icons' => [
        ['src' => lf_url('/assets/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
        ['src' => lf_url('/assets/icon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
