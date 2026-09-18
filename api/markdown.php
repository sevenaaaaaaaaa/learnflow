<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (lf_admin_current() === null) {
    lf_json_out(['ok' => false, 'error' => '未登录'], 401);
}
if (!lf_csrf_check()) {
    lf_json_out(['ok' => false, 'error' => 'CSRF 校验失败'], 403);
}
$input = array_merge($_POST, lf_json_input());
$text = (string)($input['text'] ?? '');
lf_json_out(['ok' => true, 'html' => lf_md_to_html($text)]);
