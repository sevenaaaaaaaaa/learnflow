<?php

if (!defined('LF_BOOTSTRAPPED')) {
    define('LF_BOOTSTRAPPED', true);
}

define('LF_ROOT', dirname(__DIR__));

$lfHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$lfIsHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $lfIsHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 7200);
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Shanghai');

$lfEnv = getenv('LF_ENV') ?: (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $lfHost) ? 'dev' : 'prod');
define('LF_ENV', $lfEnv);

if (LF_ENV === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

define('LF_DATA_DIR', getenv('LF_DATA_DIR') ?: LF_ROOT . '/data');
define('LF_UPLOAD_DIR', getenv('LF_UPLOAD_DIR') ?: LF_ROOT . '/uploads');
define('LF_CACHE_DIR', LF_DATA_DIR . '/cache');
define('LF_LOG_FILE', LF_DATA_DIR . '/php-error.log');

@ini_set('log_errors', '1');
@ini_set('error_log', LF_LOG_FILE);

foreach ([LF_DATA_DIR, LF_UPLOAD_DIR, LF_CACHE_DIR] as $lfDir) {
    if (!is_dir($lfDir)) @mkdir($lfDir, 0755, true);
}

$lfEnvFile = LF_ROOT . '/.env';
if (is_file($lfEnvFile)) {
    foreach (file($lfEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $lfLine) {
        $lfLine = trim($lfLine);
        if ($lfLine === '' || $lfLine[0] === '#' || !str_contains($lfLine, '=')) continue;
        [$lfK, $lfV] = array_map('trim', explode('=', $lfLine, 2));
        if (getenv($lfK) === false) putenv("$lfK=$lfV");
    }
}

set_exception_handler(function (Throwable $e): void {
    $msg = '[' . date('Y-m-d H:i:s') . '] Uncaught ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents(LF_LOG_FILE, $msg, FILE_APPEND);
    lf_error_response(500, '服务器内部错误', LF_ENV === 'dev' ? $e->getMessage() : null);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] Fatal: ' . $err['message']
        . ' in ' . $err['file'] . ':' . $err['line'] . "\n", FILE_APPEND);
    lf_error_response(500, '服务器内部错误', LF_ENV === 'dev' ? $err['message'] : null);
});

function lf_is_api_request(): bool
{
    return str_starts_with((string)($_SERVER['REQUEST_URI'] ?? ''), lf_url('/api/'))
        || (($_SERVER['HTTP_ACCEPT'] ?? '') !== '' && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
}

function lf_error_response(int $code, string $message, ?string $detail = null): void
{
    if (!headers_sent()) http_response_code($code);
    if (lf_is_api_request()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        $payload = ['ok' => false, 'error' => $message];
        if ($detail !== null) $payload['detail'] = $detail;
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>出错了 · LearnFlow</title></head>'
            . '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f5f4ee;color:#222">'
            . '<div style="text-align:center;max-width:420px;padding:40px">'
            . '<h1 style="font-size:20px;margin:0 0 8px">系统开小差了</h1>'
            . '<p style="color:#666;line-height:1.8">请稍后重试，问题已记录。</p>';
        if ($detail !== null) {
            echo '<pre style="text-align:left;overflow:auto;font-size:12px;color:#c0392b">' . htmlspecialchars($detail) . '</pre>';
        }
        echo '<a href="' . lf_url('/') . '" style="display:inline-block;margin-top:20px;padding:10px 22px;border-radius:999px;background:#2563eb;color:#fff;text-decoration:none">返回首页</a>'
            . '</div></body></html>';
    }
    exit;
}

function json_read(string $path): array
{
    if (!is_file($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function json_write(string $path, array $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp = $path . '.tmp.' . getmypid();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $path);
}

function json_update(string $path, callable $mutator): array
{
    $lock = $path . '.lock';
    $fp = @fopen($lock, 'c');
    if ($fp === false) {
        $data = $mutator(json_read($path));
        json_write($path, $data);
        return $data;
    }
    @flock($fp, LOCK_EX);
    $data = $mutator(json_read($path));
    json_write($path, $data);
    @flock($fp, LOCK_UN);
    @fclose($fp);
    @unlink($lock);
    return $data;
}

function lf_cache_get(string $key, int $ttl, callable $producer)
{
    $file = LF_CACHE_DIR . '/' . md5($key) . '.cache';
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data) && ($data['expires'] ?? 0) > time()) return $data['value'];
    }
    $value = $producer();
    @file_put_contents($file, json_encode(['expires' => time() + $ttl, 'value' => $value], JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $value;
}

function lf_cache_flush(): void
{
    foreach (glob(LF_CACHE_DIR . '/*.cache') ?: [] as $f) @unlink($f);
}

function lf_settings(): array
{
    return json_read(LF_DATA_DIR . '/settings.json');
}

function lf_setting_get(string $key, $default = null)
{
    $s = lf_settings();
    return $s[$key] ?? $default;
}

function lf_setting_set(string $key, $value): void
{
    json_update(LF_DATA_DIR . '/settings.json', function (array $s) use ($key, $value) {
        $s[$key] = $value;
        return $s;
    });
}

function lf_secret(): string
{
    $s = lf_settings();
    if (empty($s['secret'])) {
        $secret = bin2hex(random_bytes(32));
        lf_setting_set('secret', $secret);
        return $secret;
    }
    return (string)$s['secret'];
}

function lf_e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lf_base_path(): string
{
    static $base = null;
    if ($base !== null) return $base;
    $env = getenv('LF_BASE');
    if ($env !== false && $env !== '') {
        return $base = '/' . trim($env, '/');
    }
    if ($env === '') return $base = '';
    $root = str_replace('\\', '/', realpath(LF_ROOT) ?: LF_ROOT);
    $doc = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $docReal = $doc !== '' ? str_replace('\\', '/', realpath($doc) ?: $doc) : '';
    $docReal = rtrim($docReal, '/');
    if ($docReal !== '' && $root !== $docReal && str_starts_with($root, $docReal . '/')) {
        return $base = rtrim(substr($root, strlen($docReal)), '/');
    }
    return $base = '';
}

function lf_url(string $path = ''): string
{
    return lf_base_path() . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function lf_safe_next(string $candidate, string $default = '/'): string
{
    $candidate = trim($candidate);
    if ($candidate === '' || $candidate[0] !== '/' || str_starts_with($candidate, '//')) {
        return lf_url($default);
    }
    $base = lf_base_path();
    if ($base !== '' && $candidate !== $base && !str_starts_with($candidate, $base . '/')) {
        $candidate = $base . $candidate;
    }
    return $candidate;
}

function lf_abs_url(string $path = ''): string
{
    $base = rtrim((string)(lf_setting_get('site_url') ?: ''), '/');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . (($_SERVER['HTTP_HOST'] ?? 'localhost'));
    }
    return $base . lf_url($path);
}

function lf_csrf_token(): string
{
    if (empty($_SESSION['lf_csrf'])) $_SESSION['lf_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['lf_csrf'];
}

function lf_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . lf_e(lf_csrf_token()) . '">';
}

function lf_csrf_check(?string $token = null): bool
{
    $token ??= (string)($_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $token !== '' && hash_equals((string)($_SESSION['lf_csrf'] ?? ''), $token);
}

function lf_json_input(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $raw = file_get_contents('php://input');
    $data = $raw ? json_decode($raw, true) : null;
    return $cache = (is_array($data) ? $data : []);
}

function lf_json_out(array $payload, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(array_merge(['ok' => true], $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function lf_slugify(string $text): string
{
    $text = trim($text);
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? mb_strtolower($slug) : 'item';
}
