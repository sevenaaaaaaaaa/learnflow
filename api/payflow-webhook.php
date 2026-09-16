<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lf_json_out(['ok' => false, 'error' => '仅支持 POST'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$signature = (string)($_SERVER['HTTP_X_PAYFLOW_SIGNATURE'] ?? ($_SERVER['HTTP_X_LF_SIGNATURE'] ?? ''));

$payload = json_decode($raw, true);
if (!is_array($payload)) {
    lf_json_out(['ok' => false, 'error' => '非法 JSON'], 400);
}

$event = (string)($payload['event'] ?? 'order.paid');
if ($event !== 'order.paid') {
    lf_json_out(['ok' => true, 'ignored' => $event]);
}

$order = (array)($payload['order'] ?? $payload);

$secretConfigured = payflow_config()['secret'] !== '';
if ($secretConfigured) {
    if ($signature === '' || !payflow_verify($raw, $signature)) {
        lf_json_out(['ok' => false, 'error' => '签名校验失败'], 401);
    }
}

$result = payflow_handle_order($order);
if (empty($result['ok'])) {
    lf_json_out(['ok' => false, 'error' => $result['error'] ?? '处理失败'], 422);
}

if (function_exists('lf_cache_flush')) lf_cache_flush();
lf_json_out(['ok' => true, 'enrolled' => $result]);
