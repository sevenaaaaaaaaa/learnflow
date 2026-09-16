<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && lf_csrf_check()) {
    lf_admin_logout();
}
header('Location: /admin/login.php');
