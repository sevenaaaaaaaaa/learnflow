<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once LF_ROOT . '/lib/ApiKey.php';
require_once LF_ROOT . '/lib/ApiActions.php';

header('Content-Type: application/json; charset=utf-8');

$token = api_bearer_token();
$key = $token !== '' ? api_key_authenticate($token) : null;
if ($key === null) {
    http_response_code(401);
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => '无效或缺失 API Key（Authorization: Bearer lf_xxx）']], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!api_rate_check((string)$key['id'], (int)($key['rate_limit'] ?? 120))) {
    http_response_code(429);
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32002, 'message' => '超出速率限制']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'name' => 'learnflow',
        'protocol' => 'mcp',
        'transport' => 'streamable-http',
        'auth' => 'Authorization: Bearer <api-key>',
        'hint' => 'POST JSON-RPC 2.0（initialize / tools/list / tools/call）',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$req = json_decode($raw, true);
if (!is_array($req)) {
    http_response_code(400);
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = $req['id'] ?? null;
$method = (string)($req['method'] ?? '');
$params = (array)($req['params'] ?? []);

$reply = function (?array $result, ?array $error = null) use ($id, $method, $key) {
    if ($id === null) {
        http_response_code(202);
        return;
    }
    $out = ['jsonrpc' => '2.0', 'id' => $id];
    if ($error !== null) $out['error'] = $error; else $out['result'] = $result;
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

if ($method === 'initialize') {
    $reply([
        'protocolVersion' => (string)($params['protocolVersion'] ?? '2025-06-18'),
        'capabilities' => ['tools' => ['listChanged' => false]],
        'serverInfo' => ['name' => 'learnflow', 'title' => 'LearnFlow 课程交付引擎', 'version' => '1.0.0'],
        'instructions' => 'LearnFlow 是课程交付与训练营运营系统。可用工具管理课程/课时、测验、作业与批改、报名与学员、通知、经营数据，以及 AI 出题/批改/周报。',
    ]);
    exit;
}

if ($method === 'notifications/initialized' || str_starts_with($method, 'notifications/')) {
    $reply(null);
    exit;
}

if ($method === 'ping') {
    $reply(['pong' => true]);
    exit;
}

if ($method === 'tools/list') {
    $reply(['tools' => lf_api_tool_list()]);
    exit;
}

if ($method === 'tools/call') {
    $name = (string)($params['name'] ?? '');
    $args = (array)($params['arguments'] ?? []);
    $start = microtime(true);
    $scopes = (array)($key['scopes'] ?? []);
    $result = lf_api_call($name, $args, ['key_id' => $key['id'], 'scopes' => $scopes, 'via' => 'mcp']);
    api_audit((string)$key['id'], $name, !empty($result['ok']), (int)round((microtime(true) - $start) * 1000));
    if (!empty($result['ok'])) {
        $reply(['content' => [['type' => 'text', 'text' => json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]], 'isError' => false]);
    } else {
        $reply(['content' => [['type' => 'text', 'text' => (string)($result['error'] ?? '调用失败')]], 'isError' => true]);
    }
    exit;
}

http_response_code(404);
$reply(null, ['code' => -32601, 'message' => 'Method not found: ' . $method]);
