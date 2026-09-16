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

function lf_admin_required(): string
{
    $u = lf_admin_current();
    if ($u === null) {
        $next = urlencode((string)($_SERVER['REQUEST_URI'] ?? '/admin/'));
        if (!headers_sent()) header('Location: /admin/login.php?next=' . $next);
        exit;
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
        if (!headers_sent()) header('Location: /login?next=' . $next);
        exit;
    }
    return $s;
}
