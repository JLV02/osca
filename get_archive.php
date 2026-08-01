<?php
/**
 * get_archive.php
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = (int)($_GET['limit'] ?? 15);
if (!in_array($limit, [10, 15, 25, 50])) $limit = 15;
$offset = ($page - 1) * $limit;

$search = mb_strtoupper(trim($_GET['search'] ?? ''), 'UTF-8');

$where  = "WHERE is_archived = 1";
$params = [];

if ($search !== '') {
    $where .= " AND (
        lastnameApplicant   COLLATE utf8mb4_bin LIKE ? OR
        firstnameApplicant  COLLATE utf8mb4_bin LIKE ? OR
        middlenameApplicant COLLATE utf8mb4_bin LIKE ? OR
        osca_ID              COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,' ',firstnameApplicant) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,' ',firstnameApplicant,' ',COALESCE(middlenameApplicant,'')) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(firstnameApplicant,' ',lastnameApplicant) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,', ',firstnameApplicant) COLLATE utf8mb4_bin LIKE ?
    )";
    $like = "%" . $search . "%";
    $params = [$like,$like,$like,$like,$like,$like,$like,$like];
}

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants $where");
    $countStmt->execute($params);
    $total      = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $limit));

    $stmt = $pdo->prepare("
        SELECT
            id,
            lastnameApplicant, firstnameApplicant, middlenameApplicant, suffixApplicant,
            sex, month, date, year,
            osca_ID, barangay,
            archive_reason, archived_at, archived_by,
            created_at
        FROM applicants
        $where
        ORDER BY archived_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'records'    => $records,
        'total'      => $total,
        'page'       => $page,
        'totalPages' => $totalPages,
        'limit'      => $limit,
    ]);
} catch (PDOException $e) {
    error_log('[get_archive] PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}