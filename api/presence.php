<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$admin = lf_admin_current();
if ($admin === null) lf_json_out(['ok' => false, 'error' => '未登录'], 401);
if (!lf_csrf_check()) lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);

$key = (string)($_POST['key'] ?? '');
if ($key === '') lf_json_out(['ok' => false, 'error' => '缺少 key'], 422);
$key = mb_substr($key, 0, 120);
presence_touch($key, $admin);
lf_json_out(['ok' => true, 'others' => presence_others($key, $admin)]);
