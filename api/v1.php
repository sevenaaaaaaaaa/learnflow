<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LF_ROOT . '/lib/ApiKey.php';
require_once LF_ROOT . '/lib/ApiActions.php';

header('Content-Type: application/json; charset=utf-8');

$token = api_bearer_token();
$key = $token !== '' ? api_key_authenticate($token) : null;
if ($key === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => '无效或缺失 API Key（Authorization: Bearer lf_xxx）'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!api_rate_check((string)$key['id'], (int)($key['rate_limit'] ?? 120))) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => '超出速率限制'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'ok' => true,
        'server' => 'learnflow',
        'usage' => 'POST JSON {"tool":"course.list","params":{...}}，或使用 MCP 端点 /mcp',
        'tools' => array_column(lf_api_tool_list(), 'name'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

$tool = (string)($input['tool'] ?? ($input['action'] ?? ''));
$params = (array)($input['params'] ?? ($input['arguments'] ?? []));

$start = microtime(true);
$scopes = (array)($key['scopes'] ?? []);
$result = lf_api_call($tool, $params, ['key_id' => $key['id'], 'scopes' => $scopes, 'via' => 'rest']);
$ms = (int)round((microtime(true) - $start) * 1000);
api_audit((string)$key['id'], $tool, !empty($result['ok']), $ms);

http_response_code(!empty($result['ok']) ? 200 : (int)($result['code'] ?? 400));
if (!empty($result['ok'])) {
    echo json_encode(['ok' => true, 'data' => $result['data'], 'ms' => $ms], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode(['ok' => false, 'error' => $result['error'] ?? '调用失败', 'ms' => $ms], JSON_UNESCAPED_UNICODE);
}
