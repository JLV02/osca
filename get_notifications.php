<?php
/**
 * get_notifications.php
 * Returns pending-change count, last-backup time, and a recent history list.
 */
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Delete a single notification ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_notification') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
        exit;
    }
    $ok = osca_delete_notification($pdo, $id);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Notification deleted.' : 'Failed to delete notification.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_all_notifications') {
    $ok = osca_delete_all_notifications($pdo);
    echo json_encode(['success' => $ok, 'message' => $ok ? 'All notifications deleted.' : 'Failed to delete notifications.']);
    exit;
}

// ── Default: return notification state + recent list ────────
$limit = (int)($_GET['limit'] ?? 8);
$state = osca_get_notification_state($pdo);
$list  = osca_get_recent_notifications($pdo, $limit);

echo json_encode(['success' => true, 'list' => $list] + $state);