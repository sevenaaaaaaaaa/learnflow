<?php

function api_keys_file(): string
{
    return LF_DATA_DIR . '/api-keys.json';
}

function api_scopes(): array
{
    return ['read', 'write', 'ai'];
}

function api_key_hash(string $token): string
{
    return hash_hmac('sha256', $token, lf_secret());
}

function api_key_all(): array
{
    return json_read(api_keys_file());
}

function api_key_create(string $name, array $scopes = [], int $rateLimit = 120): array
{
    $name = trim($name) ?: '未命名密钥';
    $scopes = array_values(array_intersect(api_scopes(), $scopes));
    if (!$scopes) $scopes = ['read'];
    $token = 'lf_' . bin2hex(random_bytes(24));
    $id = 'key_' . bin2hex(random_bytes(5));
    $record = [
        'id' => $id,
        'name' => mb_substr($name, 0, 60),
        'prefix' => substr($token, 0, 11),
        'hash' => api_key_hash($token),
        'scopes' => $scopes,
        'rate_limit' => max(10, min(6000, $rateLimit)),
        'enabled' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'last_used_at' => '',
        'calls' => 0,
    ];
    json_update(api_keys_file(), function (array $all) use ($id, $record) {
        $all[$id] = $record;
        return $all;
    });
    return ['id' => $id, 'token' => $token, 'record' => $record];
}

function api_key_revoke(string $id): void
{
    json_update(api_keys_file(), function (array $all) use ($id) {
        if (isset($all[$id])) $all[$id]['enabled'] = false;
        return $all;
    });
}

function api_key_delete(string $id): void
{
    json_update(api_keys_file(), function (array $all) use ($id) {
        unset($all[$id]);
        return $all;
    });
}

function api_key_authenticate(string $token): ?array
{
    $token = trim($token);
    if (!str_starts_with($token, 'lf_') || strlen($token) < 20) return null;
    $hash = api_key_hash($token);
    $found = null;
    foreach (api_key_all() as $id => $rec) {
        if (!empty($rec['enabled']) && hash_equals((string)($rec['hash'] ?? ''), $hash)) {
            $found = array_merge(['id' => (string)$id], $rec);
            break;
        }
    }
    if ($found === null) return null;
    json_update(api_keys_file(), function (array $all) use ($id) {
        if (isset($all[$id])) {
            $all[$id]['last_used_at'] = date('Y-m-d H:i:s');
            $all[$id]['calls'] = (int)($all[$id]['calls'] ?? 0) + 1;
        }
        return $all;
    });
    return $found;
}

function api_bearer_token(): string
{
    $header = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
    if ($header !== '' && preg_match('/Bearer\s+(\S+)/i', $header, $m)) return $m[1];
    if (function_exists('getallheaders')) {
        foreach ((array)getallheaders() as $k => $v) {
            if (strcasecmp((string)$k, 'Authorization') === 0 && preg_match('/Bearer\s+(\S+)/i', (string)$v, $m)) return $m[1];
            if (strcasecmp((string)$k, 'X-Api-Key') === 0 && $v !== '') return (string)$v;
        }
    }
    $alt = (string)($_SERVER['HTTP_X_API_KEY'] ?? '');
    if ($alt !== '') return $alt;
    return (string)($_GET['key'] ?? ($_POST['key'] ?? ''));
}

function api_rate_check(string $keyId, int $limitPerMin): bool
{
    $file = LF_DATA_DIR . '/api-rate.json';
    $window = (int)floor(time() / 60);
    $ok = true;
    json_update($file, function (array $all) use ($keyId, $window, $limitPerMin, &$ok) {
        $bucket = (array)($all[$keyId] ?? []);
        $count = ((int)($bucket['w'] ?? 0) === $window) ? (int)($bucket['c'] ?? 0) : 0;
        $ok = $count < $limitPerMin;
        $all[$keyId] = ['w' => $window, 'c' => $count + 1];
        if (count($all) > 500) $all = array_slice($all, -500, null, true);
        return $all;
    });
    return $ok;
}

function api_audit(string $keyId, string $tool, bool $ok, int $ms): void
{
    json_update(LF_DATA_DIR . '/api-log.json', function (array $log) use ($keyId, $tool, $ok, $ms) {
        $log[] = [
            'at' => date('Y-m-d H:i:s'),
            'key' => $keyId,
            'tool' => $tool,
            'ok' => $ok,
            'ms' => $ms,
            'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        if (count($log) > 1000) $log = array_slice($log, -1000);
        return $log;
    });
}
