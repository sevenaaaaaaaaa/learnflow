<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !lf_csrf_check()) {
    if (!headers_sent()) header('Location: ' . lf_url('/'));
    exit;
}
lf_student_logout();
lf_flash('ok', '已退出登录。');
header('Location: ' . lf_url('/'));
