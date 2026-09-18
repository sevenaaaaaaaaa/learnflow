<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once LF_ROOT . '/lib/StudentToken.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lf_json_out(['ok' => false, 'error' => '仅支持 POST'], 405);
}
$input = array_merge($_POST, lf_json_input());
$action = (string)($input['action'] ?? 'login');
$email = trim((string)($input['email'] ?? ''));
$password = (string)($input['password'] ?? '');

$rl = lf_throttle('apilogin:' . ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . strtolower($email), 12, 600);
if (empty($rl['allowed'])) lf_json_out(['ok' => false, 'error' => '尝试过于频繁'], 429);

if ($action === 'register') {
    try {
        if (strlen($password) < 6) throw new InvalidArgumentException('密码至少 6 位');
        $student = student_create(['email' => $email, 'password' => $password, 'name' => (string)($input['name'] ?? ''), 'source' => 'app']);
        lf_json_out(['ok' => true, 'token' => student_token_issue((string)$student['id']), 'student' => ['id' => $student['id'], 'name' => $student['name'], 'email' => $student['email']]]);
    } catch (Throwable $e) {
        lf_json_out(['ok' => false, 'error' => $e->getMessage()], 400);
    }
}

$student = student_verify($email, $password);
if ($student === null) lf_json_out(['ok' => false, 'error' => '邮箱或密码不正确'], 401);
lf_throttle_reset('apilogin:' . ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . strtolower($email));
student_touch_login((string)$student['id']);
lf_json_out(['ok' => true, 'token' => student_token_issue((string)$student['id']), 'student' => ['id' => $student['id'], 'name' => $student['name'], 'email' => $student['email']]]);
