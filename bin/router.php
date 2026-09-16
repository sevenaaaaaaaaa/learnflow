<?php

$base = getenv('LF_BASE') ?: '';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($base !== '') {
    $base = '/' . trim($base, '/');
    if ($uri === $base || str_starts_with($uri, $base . '/')) {
        $uri = substr($uri, strlen($base));
    }
    if ($uri === '' || $uri === false) $uri = '/';
}
$root = dirname(__DIR__);
$path = $root . $uri;

$mimes = [
    'css' => 'text/css', 'js' => 'application/javascript', 'mjs' => 'application/javascript',
    'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png',
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ico' => 'image/x-icon',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'txt' => 'text/plain', 'md' => 'text/markdown',
];

if (is_file($path)) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        $_SERVER['SCRIPT_NAME'] = $uri;
        $_SERVER['SCRIPT_FILENAME'] = $path;
        require $path;
        return true;
    }
    header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($path));
    readfile($path);
    return true;
}

if ($uri !== '/' && is_dir($path) && is_file($path . '/index.php')) {
    $_SERVER['SCRIPT_NAME'] = rtrim($uri, '/') . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $path . '/index.php';
    require $path . '/index.php';
    return true;
}

$routes = [
    '#^/$#' => '/admin/login.php',
    '#^/courses/?$#' => '/courses.php',
    '#^/camp/?$#' => '/camp.php',
    '#^/dashboard/?$#' => '/dashboard.php',
    '#^/login/?$#' => '/login.php',
    '#^/join/?$#' => '/join.php',
    '#^/course/([^/]+)/?$#' => '/course.php?slug=$1',
    '#^/learn/([^/]+)/?$#' => '/learn.php?slug=$1',
    '#^/quiz/([^/]+)/?$#' => '/quiz.php?slug=$1',
    '#^/certificate/([^/]+)/?$#' => '/certificate.php?no=$1',
    '#^/certificate/?$#' => '/certificate.php',
    '#^/admin/?$#' => '/admin/index.php',
    '#^/admin/([a-z0-9-]+)/?$#' => '/admin/$1.php',
];

foreach ($routes as $pattern => $target) {
    if (preg_match($pattern, $uri, $m)) {
        $qs = '';
        if (str_contains($target, '?')) {
            [$target, $qs] = explode('?', $target, 2);
            $qs = preg_replace_callback('/\$(\d)/', fn($x) => rawurlencode($m[(int)$x[1]] ?? ''), $qs);
            $qs = '?' . $qs;
        }
        $_SERVER['SCRIPT_NAME'] = $target;
        $_SERVER['SCRIPT_FILENAME'] = $root . $target;
        if ($qs !== '') {
            $_SERVER['QUERY_STRING'] = ltrim($qs, '?');
            parse_str(ltrim($qs, '?'), $_GET);
        }
        require $root . $target;
        return true;
    }
}

http_response_code(404);
echo 'Not Found';
