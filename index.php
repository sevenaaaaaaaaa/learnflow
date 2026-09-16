<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (lf_admin_current() !== null) {
    header('Location: ' . lf_url('/admin/'));
    exit;
}
header('Location: ' . lf_url('/admin/login.php'));
