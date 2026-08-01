<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    exit;
}

require_once 'db.php';

$id   = (int)($_GET['id']   ?? 0);
$type = $_GET['type'] ?? '';

if ($id <= 0 || !in_array($type, ['osca','photo'])) {
    http_response_code(400);
    exit;
}

$col      = $type === 'osca' ? 'oscaID'      : 'photoLatest';
$colType  = $type === 'osca' ? 'oscaID_type' : 'photoLatest_type';

try {
    $stmt = $pdo->prepare("SELECT `$col`, `$colType` FROM applicants WHERE id = ?");
    $stmt->execute([$id]);
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row[$col])) {
        http_response_code(404);
        exit;
    }

    $mime = $row[$colType] ?: 'image/jpeg';
    header("Content-Type: $mime");
    header("Cache-Control: private, max-age=3600");
    echo $row[$col];
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}
?>