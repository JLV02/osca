<?php
session_start();
require_once 'db.php';
require_once 'audit_log_helper.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    osca_log_audit($pdo, 'logout', 'staff', $_SESSION['staff_id'] ?? null, $_SESSION['display_name'] ?? $_SESSION['admin_username'] ?? null);
}

session_destroy();
header('Location: login.php');
exit;
?>