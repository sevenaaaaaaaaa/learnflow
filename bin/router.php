<?php
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$root = __DIR__ . '/..';
$path = $root . $uri;

if ($uri !== '/' && is_file($path)) {
    return false;
}

$routes = [
    '#^/$#' => '/index.php',
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
