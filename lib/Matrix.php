<?php

function matrix_config(): array
{
    $defaults = [
        'userloop' => ['enabled' => false, 'base_url' => 'http://127.0.0.1:8600/userloop', 'token' => '', 'health' => '/api/v1/overview'],
        'mflow' => ['enabled' => false, 'base_url' => 'http://127.0.0.1:8088', 'token' => '', 'health' => '/api/health'],
        'inflow' => ['enabled' => false, 'base_url' => 'http://127.0.0.1:8400', 'token' => '', 'health' => '/api/v1/insights'],
        'openflow' => ['enabled' => false, 'base_url' => 'https://nownexts.com', 'token' => '', 'health' => '/api/live.php?action=status&room_id=probe'],
        'websflow' => ['enabled' => false, 'base_url' => 'http://127.0.0.1:3001', 'token' => '', 'health' => '/api/billing/catalog'],
        'payflow' => ['enabled' => false, 'base_url' => 'https://nownexts.com/payflow', 'token' => '', 'health' => '/'],
    ];
    $saved = (array)(lf_setting_get('matrix') ?: []);
    foreach ($saved as $k => $v) if (isset($defaults[$k])) $defaults[$k] = array_merge($defaults[$k], (array)$v);
    return $defaults;
}

function matrix_product(string $product): array
{
    $cfg = matrix_config();
    return $cfg[$product] ?? ['enabled' => false, 'base_url' => '', 'token' => '', 'health' => ''];
}

function matrix_call(string $product, string $path, array $payload = [], string $method = 'POST', int $timeout = 6): array
{
    require_once __DIR__ . '/Events.php';
    $cfg = matrix_product($product);
    if (empty($cfg['enabled']) || $cfg['base_url'] === '') {
        return ['ok' => false, 'code' => 0, 'body' => '', 'error' => '未启用或未配置 base_url'];
    }
    $url = rtrim((string)$cfg['base_url'], '/') . '/' . ltrim($path, '/');
    $headers = [];
    if ($cfg['token'] !== '') {
        $headers[] = $product === 'userloop' ? ('X-UserLoop-Token: ' . $cfg['token']) : ('Authorization: Bearer ' . $cfg['token']);
    }
    if (strtoupper($method) === 'GET') return lf_get_json($url, $headers, $timeout);
    return lf_post_json($url, $payload, '', $timeout, $headers);
}

function matrix_status(): array
{
    $out = [];
    foreach (matrix_config() as $product => $cfg) {
        if (empty($cfg['enabled'])) { $out[$product] = ['enabled' => false, 'ok' => null, 'detail' => '未启用']; continue; }
        $res = matrix_call($product, (string)$cfg['health'], [], 'GET', 4);
        $out[$product] = ['enabled' => true, 'ok' => !empty($res['ok']), 'detail' => 'HTTP ' . (int)($res['code'] ?? 0)];
    }
    return $out;
}

function matrix_reengage(string $studentId, string $courseId, string $segment = 'at_risk'): void
{
    require_once __DIR__ . '/Events.php';
    $student = function_exists('student_get') ? student_get($studentId) : null;
    $course = function_exists('course_find') ? course_find($courseId) : null;
    lf_emit('reengage.requested', [
        'student_id' => $studentId,
        'name' => (string)($student['name'] ?? ''),
        'email' => (string)($student['email'] ?? ''),
        'course_id' => $courseId,
        'course_title' => (string)($course['title'] ?? ''),
        'segment' => $segment,
    ]);
}
