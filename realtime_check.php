<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
require_once 'db.php';
require_once 'render_row.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentRole = $_SESSION['admin_role'] ?? 'encoder';
$isAdmin     = ($currentRole === 'admin');

// ── Stats ─────────────────────────────────────────────────────
$total         = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE is_archived = 0 OR is_archived IS NULL")->fetchColumn();
$today         = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE DATE(created_at)=CURDATE() AND (is_archived = 0 OR is_archived IS NULL)")->fetchColumn();
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE is_archived = 1")->fetchColumn();

// ── Same filter params as dashboard.php ───────────────────────
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100, 0])) $limit = 10;
$offset    = ($limit > 0) ? ($page - 1) * $limit : 0;
$search    = mb_strtoupper(trim($_GET['search'] ?? ''), 'UTF-8');
$filter    = $_GET['filter'] ?? 'all';
$sexFilter = $_GET['sex'] ?? 'all';
$ageFilter = $_GET['age'] ?? 'all';
$pwdFilter = $_GET['pwd'] ?? 'all';

$where  = "WHERE (is_archived = 0 OR is_archived IS NULL)";
$params = [];

if ($search !== '') {
    $where .= " AND (
        lastnameApplicant   COLLATE utf8mb4_bin LIKE ? OR
        firstnameApplicant  COLLATE utf8mb4_bin LIKE ? OR
        middlenameApplicant COLLATE utf8mb4_bin LIKE ? OR
        osca_ID             COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,' ',firstnameApplicant) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,' ',firstnameApplicant,' ',COALESCE(middlenameApplicant,'')) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(firstnameApplicant,' ',lastnameApplicant) COLLATE utf8mb4_bin LIKE ?
        OR CONCAT(lastnameApplicant,', ',firstnameApplicant) COLLATE utf8mb4_bin LIKE ?
    )";
    $like = "%$search%";
    $params = array_merge($params, [$like,$like,$like,$like,$like,$like,$like,$like]);
}
if ($filter !== 'all' && $filter !== '') {
    $where .= " AND barangay = ?";
    $params[] = $filter;
}
if ($sexFilter !== 'all' && $sexFilter !== '') {
    $where .= " AND sex = ?";
    $params[] = $sexFilter;
}

if ($ageFilter !== 'all' && $ageFilter !== '') {
    $ageParts = explode('-', $ageFilter);
    $minAge = isset($ageParts[0]) && $ageParts[0] !== '' ? (int)$ageParts[0] : 60;
    $maxAge = isset($ageParts[1]) && $ageParts[1] !== '' ? (int)$ageParts[1] : null;

    if ($minAge >= 60 && ($maxAge === null || $maxAge >= $minAge)) {
        $ageCalc = "TIMESTAMPDIFF(YEAR,
            STR_TO_DATE(
                CONCAT(
                    `year`, '-',
                    LPAD(
                        FIELD(`month`,
                            'January','February','March','April','May','June',
                            'July','August','September','October','November','December'
                        ),
                    2, '0'),
                    '-',
                    LPAD(`date`, 2, '0')
                ),
            '%Y-%m-%d'),
            CURDATE()
        )";

        if ($maxAge === null) {
            $where .= " AND ($ageCalc) >= ?";
            $params[] = $minAge;
        } else {
            $where .= " AND ($ageCalc) BETWEEN ? AND ?";
            $params[] = $minAge;
            $params[] = $maxAge;
        }
    }
}
if ($pwdFilter !== 'all' && $pwdFilter !== '') {
    $where .= " AND personWithDisability = ?";
    $params[] = $pwdFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants $where");
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();
$totalPages = ($limit > 0) ? max(1, ceil($filteredTotal / $limit)) : 1;

$query = "SELECT * FROM applicants $where ORDER BY created_at DESC";
if ($limit > 0) $query .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ids      = [];
$rowsHtml = [];
$names    = [];

foreach ($rows as $i => $r) {
    $idKey = (string)$r['id'];
    $ids[] = (int)$r['id'];
    $rowsHtml[$idKey] = renderApplicantRow($r, $isAdmin, ($limit > 0 ? $offset : 0) + $i + 1);

    $suffix = (!empty($r['suffixApplicant']) && $r['suffixApplicant'] !== 'N/A') ? ' '.$r['suffixApplicant'] : '';
    $names[$idKey] = $r['lastnameApplicant'].', '.$r['firstnameApplicant'].$suffix;
}

echo json_encode([
    'success'        => true,
    'total'          => $total,
    'today'          => $today,
    'archivedCount'  => $archivedCount,
    'filteredTotal'  => $filteredTotal,
    'ids'            => $ids,
    'rowsHtml'       => $rowsHtml,
    'names'          => $names,
    'paginationHtml' => renderPaginationFooter($page, $limit, $offset, $filteredTotal, $totalPages, $filter, $search, $sexFilter, $ageFilter, $pwdFilter),
]);