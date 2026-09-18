<?php

function lf_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function lf_b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return (string)base64_decode($data, true);
}

function student_token_issue(string $studentId, int $ttl = 2592000): string
{
    $payload = lf_b64url_encode(json_encode(['sid' => $studentId, 'exp' => time() + $ttl], JSON_UNESCAPED_UNICODE));
    $sig = substr(hash_hmac('sha256', $payload, lf_secret()), 0, 32);
    return $payload . '.' . $sig;
}

function student_token_verify(string $token): ?string
{
    if (!str_contains($token, '.')) return null;
    [$payload, $sig] = explode('.', $token, 2);
    $expected = substr(hash_hmac('sha256', $payload, lf_secret()), 0, 32);
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(lf_b64url_decode($payload), true);
    if (!is_array($data) || empty($data['sid'])) return null;
    if ((int)($data['exp'] ?? 0) < time()) return null;
    return (string)$data['sid'];
}

function student_from_request(): ?array
{
    require_once __DIR__ . '/ApiKey.php';
    $token = api_bearer_token();
    if ($token === '') return null;
    $sid = student_token_verify($token);
    if ($sid === null) return null;
    require_once __DIR__ . '/Student.php';
    return student_get($sid);
}
