<?php

function lf_admins_file(): string
{
    return LF_DATA_DIR . '/admins.json';
}

function lf_admins(): array
{
    return json_read(lf_admins_file());
}

function lf_admin_count(): int
{
    return count(lf_admins());
}

function lf_admin_create(string $username, string $password, string $name = ''): bool
{
    $username = trim($username);
    if ($username === '' || strlen($password) < 6) return false;
    json_update(lf_admins_file(), function (array $admins) use ($username, $password, $name) {
        $admins[$username] = [
            'name' => $name !== '' ? $name : $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        return $admins;
    });
    return true;
}

function lf_admin_authenticate(string $username, string $password): bool
{
    $admins = lf_admins();
    $row = $admins[$username] ?? null;
    if (!$row || empty($row['password_hash'])) return false;
    return password_verify($password, (string)$row['password_hash']);
}

function lf_admin_login(string $username): void
{
    $_SESSION['lf_admin'] = $username;
    session_regenerate_id(true);
}

function lf_admin_logout(): void
{
    unset($_SESSION['lf_admin']);
}

function lf_admin_current(): ?string
{
    $u = (string)($_SESSION['lf_admin'] ?? '');
    return $u !== '' ? $u : null;
}

function lf_throttle(string $key, int $max, int $windowSec): array
{
    $now = time();
    $result = ['allowed' => true, 'retry' => 0];
    json_update(LF_DATA_DIR . '/throttle.json', function (array $all) use ($key, $max, $windowSec, $now, &$result) {
        $bucket = array_values(array_filter((array)($all[$key] ?? []), fn($t) => (int)$t > $now - $windowSec));
        if (count($bucket) >= $max) {
            $result = ['allowed' => false, 'retry' => max(1, (int)($bucket[0] + $windowSec - $now))];
        }
        $bucket[] = $now;
        $all[$key] = $bucket;
        if (count($all) > 2000) $all = array_slice($all, -2000, null, true);
        return $all;
    });
    return $result;
}

function lf_throttle_reset(string $key): void
{
    json_update(LF_DATA_DIR . '/throttle.json', function (array $all) use ($key) {
        unset($all[$key]);
        return $all;
    });
}

function lf_admin_required(): string
{
    $u = lf_admin_current();
    if ($u === null) {
        $next = urlencode((string)($_SERVER['REQUEST_URI'] ?? '/admin/'));
        if (!headers_sent()) header('Location: /admin/login.php?next=' . $next);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && defined('LF_DATA_DIR')) {
        @json_update(LF_DATA_DIR . '/admin-audit.json', function (array $log) use ($u) {
            $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
            $log[] = [
                'at' => date('Y-m-d H:i:s'),
                'admin' => $u,
                'path' => $path,
                'action' => (string)($_POST['action'] ?? ''),
                'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            ];
            if (count($log) > 2000) $log = array_slice($log, -2000);
            return $log;
        });
    }
    return $u;
}

function lf_student_current(): ?array
{
    $id = (string)($_SESSION['lf_student'] ?? '');
    if ($id === '') return null;
    require_once LF_ROOT . '/lib/Student.php';
    return student_get($id);
}

function lf_student_login(string $studentId): void
{
    $_SESSION['lf_student'] = $studentId;
    session_regenerate_id(true);
}

function lf_student_logout(): void
{
    unset($_SESSION['lf_student']);
}

function lf_student_required(): array
{
    $s = lf_student_current();
    if ($s === null) {
        $next = urlencode((string)($_SERVER['REQUEST_URI'] ?? '/dashboard'));
        if (!headers_sent()) header('Location: ' . lf_url('/login?next=' . $next));
        exit;
    }
    return $s;
}
