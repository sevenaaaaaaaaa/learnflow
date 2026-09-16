<?php

function students_file(): string
{
    return LF_DATA_DIR . '/students.json';
}

function student_all(): array
{
    return json_read(students_file());
}

function student_get(string $id): ?array
{
    $all = student_all();
    if (!isset($all[$id])) return null;
    return array_merge(['id' => $id], $all[$id]);
}

function student_by_email(string $email): ?array
{
    $email = mb_strtolower(trim($email));
    if ($email === '') return null;
    foreach (student_all() as $id => $s) {
        if (mb_strtolower((string)($s['email'] ?? '')) === $email) {
            return array_merge(['id' => (string)$id], $s);
        }
    }
    return null;
}

function student_is_email_taken(string $email, string $exceptId = ''): bool
{
    $existing = student_by_email($email);
    return $existing !== null && ($existing['id'] ?? '') !== $exceptId;
}

function student_create(array $data): array
{
    $email = mb_strtolower(trim((string)($data['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('邮箱格式不正确');
    }
    if (student_is_email_taken($email)) {
        throw new InvalidArgumentException('该邮箱已注册');
    }
    $id = 'stu_' . bin2hex(random_bytes(6));
    $row = [
        'name' => trim((string)($data['name'] ?? '')) ?: mb_substr($email, 0, strpos($email, '@') ?: null),
        'email' => $email,
        'phone' => trim((string)($data['phone'] ?? '')),
        'password_hash' => !empty($data['password']) ? password_hash((string)$data['password'], PASSWORD_DEFAULT) : '',
        'avatar' => (string)($data['avatar'] ?? ''),
        'source' => (string)($data['source'] ?? 'direct'),
        'created_at' => date('Y-m-d H:i:s'),
        'last_login_at' => '',
    ];
    json_update(students_file(), function (array $all) use ($id, $row) {
        $all[$id] = $row;
        return $all;
    });
    return array_merge(['id' => $id], $row);
}

function student_update(string $id, array $patch): ?array
{
    $updated = null;
    json_update(students_file(), function (array $all) use ($id, $patch, &$updated) {
        if (!isset($all[$id])) return $all;
        foreach ($patch as $k => $v) {
            if ($k === 'id' || $k === 'password_hash') continue;
            $all[$id][$k] = $v;
        }
        $updated = array_merge(['id' => $id], $all[$id]);
        return $all;
    });
    return $updated;
}

function student_set_password(string $id, string $password): void
{
    json_update(students_file(), function (array $all) use ($id, $password) {
        if (isset($all[$id])) $all[$id]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        return $all;
    });
}

function student_find_or_create(array $data): array
{
    $existing = student_by_email((string)($data['email'] ?? ''));
    if ($existing !== null) {
        $patch = [];
        if (!empty($data['name']) && empty($existing['name'])) $patch['name'] = $data['name'];
        if (!empty($data['phone']) && empty($existing['phone'])) $patch['phone'] = $data['phone'];
        return $patch ? (student_update($existing['id'], $patch) ?? $existing) : $existing;
    }
    return student_create($data);
}

function student_touch_login(string $id): void
{
    json_update(students_file(), function (array $all) use ($id) {
        if (isset($all[$id])) $all[$id]['last_login_at'] = date('Y-m-d H:i:s');
        return $all;
    });
}

function student_verify(string $email, string $password): ?array
{
    $s = student_by_email($email);
    if (!$s || empty($s['password_hash'])) return null;
    if (!password_verify($password, (string)$s['password_hash'])) return null;
    return $s;
}
