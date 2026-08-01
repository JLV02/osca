<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

$currentDisplayName = $_SESSION['display_name'] ?? ($_SESSION['admin_username'] ?? 'Staff');

// ── Read filters (same params/logic as dashboard.php) ──────────
$filter    = $_GET['filter'] ?? 'all';   // barangay
$sexFilter = $_GET['sex']    ?? 'all';
$ageFilter = $_GET['age']    ?? 'all';
$pwdFilter = $_GET['pwd']    ?? 'all';

$where  = "WHERE (is_archived = 0 OR is_archived IS NULL)";
$params = [];

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

$query = "SELECT lastnameApplicant, firstnameApplicant, middlenameApplicant, suffixApplicant,
                  barangay, purok, sex, month, date, year, personWithDisability, osca_ID, contactNumber
           FROM applicants
           $where
           ORDER BY barangay ASC, lastnameApplicant ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

function calcAge($month, $date, $year) {
    if (!$month || !$date || !$year) return '—';
    $dob = DateTime::createFromFormat('F j Y', "$month $date $year");
    return $dob ? $dob->diff(new DateTime())->y : '—';
}

// ── Build a readable filter summary for the header ──────────────
$filterLabels = [];
if ($filter !== 'all' && $filter !== '')    $filterLabels[] = "Barangay: " . htmlspecialchars($filter);
if ($sexFilter !== 'all' && $sexFilter!=='')$filterLabels[] = "Sex: " . htmlspecialchars($sexFilter);
if ($ageFilter !== 'all' && $ageFilter!=='')$filterLabels[] = "Age: " . htmlspecialchars($ageFilter);
if ($pwdFilter === 'Yes')                   $filterLabels[] = "PWD Only";
$filterSummary = $filterLabels ? implode(' · ', $filterLabels) : 'All Registrants';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OSCA Master List — Print</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#1b1c1d; margin: 24px; }
  .print-header { text-align:center; margin-bottom: 18px; border-bottom: 2px solid #1d3246; padding-bottom: 12px; }
  .print-header img { height: 60px; margin-bottom: 6px; }
  .print-header h1 { font-size: 1.15rem; margin: 4px 0 2px; }
  .print-header p  { font-size: 0.8rem; margin: 2px 0; color:#43474c; }
  .meta-row { display:flex; justify-content:space-between; font-size:0.75rem; color:#43474c; margin-bottom: 10px; }
  table { width:100%; border-collapse: collapse; font-size: 0.78rem; }
  th, td { border: 1px solid #999; padding: 5px 7px; text-align:left; }
  th { background:#e9edf1; font-size:0.68rem; text-transform:uppercase; letter-spacing:.03em; }
  tr:nth-child(even) { background:#fafafa; }
  .no-print { margin-bottom: 16px; }
  .no-print button {
    background:#1d3246; color:#fff; border:none; padding:9px 18px;
    border-radius:6px; font-size:0.85rem; cursor:pointer;
  }
  .footer-note { margin-top: 14px; font-size:0.72rem; color:#74777d; text-align:right; }
  @media print {
    .no-print { display:none; }
    body { margin: 10mm; }
  }
</style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()">🖨 Print This List</button>
  </div>

  <div class="print-header">
    <img src="HimCity_Logo_nobg.png" alt="Logo">
    <h1>Office of Senior Citizens Affairs — Master Registry</h1>
    <p>Himamaylan City</p>
  </div>

  <div class="meta-row">
    <span><strong>Filter:</strong> <?= $filterSummary ?></span>
    <span><strong>Generated:</strong> <?= date('F j, Y g:i A') ?> by <?= htmlspecialchars($currentDisplayName) ?></span>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th><th>Full Name</th><th>Sex</th><th>Age</th>
        <th>Barangay</th><th>Purok</th><th>PWD</th><th>OSCA ID</th><th>Contact No.</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($records as $i => $r):
        $suffix = (!empty($r['suffixApplicant']) && $r['suffixApplicant'] !== 'N/A') ? ' '.$r['suffixApplicant'] : '';
        $middle = (!empty($r['middlenameApplicant']) && $r['middlenameApplicant'] !== 'N/A') ? ' '.$r['middlenameApplicant'] : '';
        $fullName = $r['lastnameApplicant'].', '.$r['firstnameApplicant'].$middle.$suffix;
      ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($fullName) ?></td>
        <td><?= htmlspecialchars($r['sex'] ?? '—') ?></td>
        <td><?= calcAge($r['month'], $r['date'], $r['year']) ?></td>
        <td><?= htmlspecialchars($r['barangay'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['purok'] ?? '—') ?></td>
        <td><?= ($r['personWithDisability'] ?? '') === 'Yes' ? 'Yes' : 'No' ?></td>
        <td><?= htmlspecialchars($r['osca_ID'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['contactNumber'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($records)): ?>
      <tr><td colspan="9" style="text-align:center;padding:20px;color:#888">No records match this filter.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p class="footer-note">Total Records: <?= count($records) ?></p>

</body>
</html>