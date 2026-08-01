<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once 'db.php';
header('Content-Type: application/json');
header('X-Requested-With: XMLHttpRequest');

try {
    $total = (int) $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
    $today = (int) $pdo->query("SELECT COUNT(*) FROM applicants WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    // Barangay breakdown (useful for the barangay stat card)
    $barangay = isset($_GET['barangay']) && $_GET['barangay'] !== 'all' ? trim($_GET['barangay']) : null;
    $barangayCount = null;
    if ($barangay) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE barangay = ?");
        $stmt->execute([$barangay]);
        $barangayCount = (int) $stmt->fetchColumn();
    }

    echo json_encode([
        'success'        => true,
        'total'          => $total,
        'today'          => $today,
        'barangayCount'  => $barangayCount,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error']);
}