<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'notifications_helper.php';

// ── Admin-only guard ──────────────────────────────────────────
$currentRole = $_SESSION['admin_role'] ?? 'encoder';
if ($currentRole !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$currentDisplayName = $_SESSION['display_name'] ?? ($_SESSION['admin_username'] ?? 'Staff');
$isAdmin = true;
$notifState = osca_get_notification_state($pdo);
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE is_archived = 1")->fetchColumn();

// ── Action label / icon / color map ───────────────────────────
function auditActionMeta(string $action): array {
    $map = [
        'login_success'        => ['Logged In',                 'login',           '#059669'],
        'login_failed'         => ['Failed Login Attempt',       'gpp_bad',         '#ba1a1a'],
        'logout'               => ['Logged Out',                 'logout',          '#74777d'],
        'create_registration'  => ['Created Registration',       'person_add',      '#1d3246'],
        'update_registration'  => ['Updated Registration',       'edit',            '#3b6a8f'],
        'update_record'        => ['Updated Record',             'edit',            '#3b6a8f'],
        'complete_registration'=> ['Completed Registration',     'task_alt',        '#059669'],
        'archive_record'       => ['Archived Record',            'inventory_2',     '#92400e'],
        'restore_record'       => ['Restored Record',            'restore',         '#065f46'],
        'purge_record'         => ['Permanently Deleted Record', 'delete_forever',  '#ba1a1a'],
        'delete_record'        => ['Deleted Record',             'delete_forever',  '#ba1a1a'],
        'toggle_ncsc'          => ['Toggled NCSC Status',        'toggle_on',       '#3b6a8f'],
        'create_staff'         => ['Created Staff Account',      'person_add',      '#1d3246'],
        'toggle_staff_active'  => ['Toggled Staff Status',       'manage_accounts', '#3b6a8f'],
        'reset_staff_password' => ['Reset Staff Password',       'key',             '#92400e'],
        'delete_staff'         => ['Deleted Staff Account',      'person_remove',   '#ba1a1a'],
    ];
    return $map[$action] ?? [ucwords(str_replace('_', ' ', $action)), 'history', '#74777d'];
}

// ── General category grouping for the Filter dropdown ─────────
function auditCategoryMap(): array {
    return [
        'auth'         => ['login_success', 'login_failed', 'logout'],
        'registration' => ['create_registration', 'update_registration', 'update_record', 'complete_registration'],
        'archive'      => ['archive_record', 'restore_record', 'purge_record', 'delete_record'],
        'ncsc'         => ['toggle_ncsc'],
        'staff'        => ['create_staff', 'toggle_staff_active', 'reset_staff_password', 'delete_staff'],
    ];
}

// ── Data sensitivity classification — flags whether an action
//    touched a senior citizen's personal/health data ────────────
function auditDataSensitivity(string $action): array {
    // Actions that touch a senior citizen's personal/health record
    $sensitive = [
        'create_registration', 'update_registration', 'update_record',
        'complete_registration', 'archive_record', 'restore_record',
        'purge_record', 'delete_record', 'toggle_ncsc',
    ];

    if (in_array($action, $sensitive, true)) {
        return ['Sensitive Data', 'lock', '#92400e', '#fef3c7'];
    }
    return ['No Personal Data', 'shield', '#43474c', '#eef1f5'];
}

function auditCategoryLabels(): array {
    return [
        'auth'         => 'Login &amp; Logout',
        'registration' => 'Registration &amp; Records',
        'archive'      => 'Archive, Restore &amp; Delete',
        'ncsc'         => 'NCSC Status',
        'staff'        => 'Staff Management',
    ];
}

// ── Renders the table + pagination fragment — used for both the
//    full page load AND the AJAX incremental-search response ────
function renderAuditResultsBlock(array $logs, int $limit, int $offset, $filteredTotal, int $totalPages, int $page): void {
    if (empty($logs)) {
        ?>
        <div class="flex flex-col items-center justify-center py-20 text-center">
          <span class="material-symbols-outlined text-6xl text-outline-variant mb-3">history_toggle_off</span>
          <h3 class="font-display font-bold text-primary text-base mb-1">No activity found</h3>
          <p class="text-sm text-on-surface-variant">No logged actions match the selected filters.</p>
        </div>
        <?php
        return;
    }
    ?>
    <div class="overflow-x-auto">
      <table class="data-table text-sm border-collapse">
        <thead>
          <tr style="border-bottom:1px solid rgba(149,165,166,.30)">
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Date &amp; Time</th>
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Staff Member</th>
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Action</th>
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Target</th>
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Details</th>
            <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Data Sensitivity</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log):
              $meta = auditActionMeta($log['action']);
              $initial = strtoupper(substr($log['display_name'] ?? $log['username'] ?? '?', 0, 1));
              $roleColor = $log['role'] === 'admin' ? '#1d3246' : '#059669';
          ?>
          <tr style="border-bottom:1px solid rgba(149,165,166,.12)">
            <td style="font-family:'JetBrains Mono',monospace;font-size:.8rem;color:#1b1c1d"><?= date('M j, Y — g:i A', strtotime($log['created_at'])) ?></td>
            <td>
              <div class="audit-user-chip">
                <div class="audit-user-avatar"><?= htmlspecialchars($initial) ?></div>
                <div>
                  <div style="font-size:.83rem;font-weight:600;color:#1b1c1d"><?= htmlspecialchars($log['display_name'] ?? $log['username'] ?? 'Unknown') ?></div>
                  <?php if ($log['role']): ?>
                  <span class="audit-role-badge" style="background:<?= $roleColor ?>1a;color:<?= $roleColor ?>"><?= htmlspecialchars($log['role']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <span class="audit-action-pill" style="color:<?= $meta[2] ?>">
                <span class="audit-action-dot" style="background:<?= $meta[2] ?>"></span>
                <span class="material-symbols-outlined" style="font-size:15px"><?= $meta[1] ?></span>
                <?= htmlspecialchars($meta[0]) ?>
              </span>
            </td>
            <td class="text-on-surface"><?= htmlspecialchars($log['target_name'] ?? '—') ?></td>
            <td class="audit-details-cell" title="<?= htmlspecialchars($log['details'] ?? '') ?>"><?= htmlspecialchars($log['details'] ?? '—') ?></td>
            <?php $sens = auditDataSensitivity($log['action']); ?>
            <td>
              <span style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;font-family:'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.03em;color:<?= $sens[2] ?>;background:<?= $sens[3] ?>;padding:3px 10px;border-radius:999px">
                <span class="material-symbols-outlined" style="font-size:13px"><?= $sens[1] ?></span>
                <?= htmlspecialchars($sens[0]) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Footer -->
    <div class="px-5 py-3 bg-[#f5f3f5] flex items-center justify-between gap-4 text-xs flex-wrap" style="border-top:1px solid rgba(149,165,166,.30)">
     <div class="flex items-center gap-3 text-on-surface-variant">
        <span class="flex items-center gap-2">
          Show
          <select onchange="handleAuditLimitChange(this.value)"
                  class="appearance-none rounded-md pl-2 pr-6 bg-white text-xs text-on-surface focus:outline-none cursor-pointer font-mono" style="border:1px solid #95a5a6; height:30px;">
            <option value="10"  <?= $limit===10?'selected':'' ?>>10</option>
            <option value="25"  <?= $limit===25?'selected':'' ?>>25</option>
            <option value="50"  <?= $limit===50?'selected':'' ?>>50</option>
            <option value="100" <?= $limit===100?'selected':'' ?>>100</option>
            <option value="0"   <?= $limit===0?'selected':'' ?>>All</option>
          </select>
          Results per page
        </span>
        <span style="color:rgba(149,165,166,.6)">|</span>
        <span>
          Showing <strong class="text-on-surface"><?= $limit > 0 ? ($offset + 1) : 1 ?></strong> to <strong class="text-on-surface"><?= $limit > 0 ? min($offset + $limit, $filteredTotal) : $filteredTotal ?></strong> of <strong class="text-on-surface"><?= number_format($filteredTotal) ?></strong> entries
        </span>
      </div>
      <?php if ($limit > 0 && $totalPages > 1): ?>
      <div class="flex items-center gap-1">
        <?php
          // Strip 'ajax' so a plain (non-JS) click on these links still renders the full page
          $qp = $_GET;
          unset($qp['ajax']);

          $prevQp = $qp; $prevQp['page'] = max(1, $page - 1);
          $prevHref = '?' . http_build_query($prevQp);
          $prevDisabled = $page <= 1;
        ?>
        <?php if (!$prevDisabled): ?>
        <a href="<?= $prevHref ?>"
           class="audit-page-link px-3 py-1.5 rounded-md font-mono">Previous</a>
        <?php endif; ?>

        <?php
          for ($p = 1; $p <= $totalPages; $p++):
              $qp['page'] = $p;
              $href = '?' . http_build_query($qp);
        ?>
        <a href="<?= $href ?>"
           class="px-3 py-1.5 rounded-md font-mono transition-colors duration-150 <?= $p === $page ? 'bg-primary text-white' : 'text-on-surface-variant hover:bg-primary/10 hover:text-primary' ?>"><?= $p ?></a>
        <?php endfor; ?>

        <?php
          $nextQp = $qp; $nextQp['page'] = min($totalPages, $page + 1);
          $nextHref = '?' . http_build_query($nextQp);
          $nextDisabled = $page >= $totalPages;
        ?>
        <?php if (!$nextDisabled): ?>
        <a href="<?= $nextHref ?>"
           class="audit-page-link px-3 py-1.5 rounded-md font-mono">Next</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php
}

// ── Filters ────────────────────────────────────────────────────
$actionFilter = $_GET['action_type'] ?? 'all';
$staffFilter  = $_GET['staff'] ?? 'all';
$timeFilter   = $_GET['time_range'] ?? 'all';
$dateFrom     = trim($_GET['date_from'] ?? '');
$dateTo       = trim($_GET['date_to'] ?? '');
$search       = trim($_GET['search'] ?? '');

// Preset time ranges override manual date_from/date_to
switch ($timeFilter) {
    case 'today':
        $dateFrom = $dateTo = date('Y-m-d');
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo   = date('Y-m-d');
        break;
    case 'month':
        $dateFrom = date('Y-m-01');
        $dateTo   = date('Y-m-d');
        break;
    case 'custom':
        // keep whatever date_from / date_to were submitted
        break;
    case 'all':
    default:
        $dateFrom = $dateTo = '';
        break;
}

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100, 0])) $limit = 10;
$offset = ($limit > 0) ? ($page - 1) * $limit : 0;

$where  = "WHERE 1=1";
$params = [];

if ($actionFilter !== 'all' && $actionFilter !== '') {
    $categoryActions = auditCategoryMap()[$actionFilter] ?? null;
    if ($categoryActions) {
        $placeholders = implode(',', array_fill(0, count($categoryActions), '?'));
        $where .= " AND action IN ($placeholders)";
        $params = array_merge($params, $categoryActions);
    }
}
if ($staffFilter !== 'all' && $staffFilter !== '') {
    $where .= " AND username = ?";
    $params[] = $staffFilter;
}
if ($dateFrom !== '') {
    $where .= " AND DATE(created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where .= " AND DATE(created_at) <= ?";
    $params[] = $dateTo;
}
if ($search !== '') {
    $where .= " AND (target_name LIKE ? OR display_name LIKE ? OR username LIKE ? OR details LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like, $like]);
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log $where");
$countStmt->execute($params);
$filteredTotal = $countStmt->fetchColumn();
$totalPages    = ($limit > 0) ? max(1, ceil($filteredTotal / $limit)) : 1;

$query = "SELECT * FROM audit_log $where ORDER BY created_at DESC";
if ($limit > 0) $query .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── AJAX incremental search: return just the table+pagination
//    fragment, skip the full page render ──────────────────────
if (($_GET['ajax'] ?? '') === '1') {
    renderAuditResultsBlock($logs, $limit, $offset, $filteredTotal, $totalPages, $page);
    exit;
}

// ── Distinct staff list for the filter dropdown ───────────────
$staffList = $pdo->query("SELECT username, display_name FROM staff ORDER BY display_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ── Today / this-week counters for the stat cards ─────────────
$totalLogs = (int)$pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$todayLogs = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$failedLogins = (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'login_failed' AND DATE(created_at) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1d3246">
  <title>Audit Log — OSCA Registry</title>
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <link rel="stylesheet" href="dashboard.css">
  <style>
    .data-table { table-layout: auto; width: 100%; white-space: nowrap; }
    .data-table th, .data-table td { padding: 10px 10px; }
    .data-table th:first-child, .data-table td:first-child { padding-left: 16px; }
    .data-table th:last-child, .data-table td:last-child { padding-right: 12px; }

    .audit-action-pill {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: .78rem; font-weight: 600; font-family: 'Inter', sans-serif;
      white-space: nowrap;
    }
    .audit-action-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .audit-user-chip {
      display: inline-flex; align-items: center; gap: 8px;
    }
    .audit-user-avatar {
      width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
      background: #1d3246; color: #fff; display: flex; align-items: center; justify-content: center;
      font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: .68rem;
    }
    .audit-role-badge {
      font-size: 9px; font-family: 'JetBrains Mono', monospace; font-weight: 700;
      text-transform: uppercase; letter-spacing: .04em; padding: 1px 6px; border-radius: 999px;
    }
    .audit-details-cell {
      max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
      color: #74777d; font-size: .78rem;
    }
    .audit-search-spinner {
      display: none; position: absolute; right: 30px;
      width: 14px; height: 14px; border-radius: 50%;
      border: 2px solid rgba(29,50,70,.20); border-top-color: #1d3246;
      animation: audit-spin .6s linear infinite;
    }
    .audit-search-spinner.active { display: inline-block; }
    @keyframes audit-spin { to { transform: rotate(360deg); } }
    .audit-page-link {
      color: #1d3246;
      transition: background-color .15s ease, color .15s ease;
    }
    .audit-page-link:hover {
      background-color: #1d3246 !important;
      color: #ffffff !important;
    }
  </style>
</head>
<body class="bg-[#ECF0F1] font-body text-on-surface min-h-screen overflow-hidden">

<!-- ── SIDEBAR ── -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-surface border-r flex flex-col justify-between py-6 z-50" style="border-right:1px solid rgba(149,165,166,.30)">
  <div>
    <div class="px-6 mb-8">
      <div class="flex items-center gap-3">
        <div class="w-14 h-14 rounded-xl flex items-center justify-center p-1.5 flex-shrink-0"
             style="background:rgba(29,50,70,0.07); border:1px solid rgba(149,165,166,0.25);">
          <img src="HimCity_Logo_nobg.png" alt="Himamaylan City Seal"
               class="w-full h-full object-contain"
               style="filter:drop-shadow(0 1px 3px rgba(29,50,70,0.15));">
        </div>
        <div>
          <h1 class="font-display font-bold text-primary text-base leading-tight">Registry Admin</h1>
          <p class="text-xs font-mono text-outline opacity-80">Enterprise Portal</p>
        </div>
      </div>
    </div>
    <nav class="space-y-1">
      <a href="dashboard.php" class="flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined">dashboard</span>
        <span class="text-sm">Dashboard</span>
      </a>
      <a href="registration.php" class="flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined">app_registration</span>
        <span class="text-sm">Registration Form</span>
      </a>
      <a href="audit_log.php" class="flex items-center gap-4 px-6 py-3 text-primary font-bold border-r-2 border-primary transition-colors">
        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">history</span>
        <span class="text-sm">Audit Log</span>
      </a>
    </nav>
  </div>
  <div>
    <div class="px-6 py-3 mb-1" style="border-top:1px solid rgba(149,165,166,.20)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
          <span class="text-white text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'], 0, 1)) ?></span>
        </div>
        <div class="min-w-0 flex items-center gap-2">
          <p class="text-sm font-semibold text-on-surface truncate"><?= htmlspecialchars($currentDisplayName) ?></p>
          <span class="inline-block text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-primary/15 text-primary flex-shrink-0">Admin</span>
        </div>
      </div>
    </div>
    <nav class="space-y-1">
      <a href="dashboard.php" class="w-full flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors text-left">
        <span class="material-symbols-outlined">arrow_back</span>
        <span class="text-sm">Back to Dashboard</span>
      </a>
      <button onclick="openLogoutModal()"
              class="w-full flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors text-left">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm">Logout</span>
      </button>
    </nav>
  </div>
</aside>

<!-- ── MAIN CONTENT ── -->
<div class="ml-64 flex flex-col h-screen overflow-hidden">

  <header class="flex justify-between items-center h-16 px-6 bg-surface-container-lowest border-b z-40 flex-shrink-0" style="border-bottom:1px solid rgba(149,165,166,.30)">
    <h2 class="font-display font-bold text-2xl text-on-surface">Activity History (Audit Log)</h2>
    <p class="text-xs text-on-surface-variant font-mono">Every login, change, and action — recorded automatically</p>
  </header>

  <main class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-5">

    <!-- ── STAT CARDS ── -->
    <div class="grid grid-cols-3 gap-5">
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Total Logged Actions</p>
          <p class="font-display font-bold text-3xl text-on-surface"><?= number_format($totalLogs) ?></p>
          <p class="text-xs text-outline mt-1">All Time</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-blue-500 text-2xl" style="font-variation-settings:'FILL' 1">history</span>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Actions Today</p>
          <p class="font-display font-bold text-3xl text-on-surface"><?= number_format($todayLogs) ?></p>
          <p class="text-xs text-outline mt-1">Since Midnight</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-emerald-600 text-2xl" style="font-variation-settings:'FILL' 1">today</span>
        </div>
      </div>
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Failed Logins Today</p>
          <p class="font-display font-bold text-3xl <?= $failedLogins > 0 ? 'text-[#ba1a1a]' : 'text-on-surface' ?>"><?= number_format($failedLogins) ?></p>
          <p class="text-xs text-outline mt-1">Security Watch</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-red-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-red-500 text-2xl" style="font-variation-settings:'FILL' 1">gpp_bad</span>
        </div>
      </div>
    </div>

    <!-- ── TABLE SECTION ── -->
    <div class="bg-surface-container-lowest rounded-lg overflow-hidden" style="border:1px solid rgba(149,165,166,.30)">

      <form method="GET" action="audit_log.php" class="px-5 py-4" style="border-bottom:1px solid rgba(149,165,166,.30)">
        <div class="flex items-center justify-between gap-4 mb-3">
          <h3 class="font-display font-bold text-base text-primary whitespace-nowrap">Activity Records</h3>
          <div class="flex items-center gap-3">
            <div class="search-wrap relative flex items-center">
              <span class="absolute left-3 text-outline pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" name="search" id="auditSearchInput" value="<?= htmlspecialchars($search) ?>"
                     placeholder="Search name, user, details..."
                     autocomplete="off" oninput="handleAuditIncrementalSearch(this.value)"
                     onkeydown="if(event.key==='Enter'){event.preventDefault();}"
                     class="text-sm bg-surface-container-lowest w-56 transition focus:outline-none"
                     style="border:1px solid #95a5a6; border-radius:0.5rem; padding:7px 32px 7px 36px; height:36px;">
              <span class="audit-search-spinner" id="auditSearchSpinner"></span>
              <button type="button" id="auditSearchClear" onclick="clearAuditSearch()" title="Clear"
                      class="absolute right-2 text-outline hover:text-primary <?= $search ? '' : 'hidden' ?>">
                <span class="material-symbols-outlined text-[16px]">close</span>
              </button>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-on-surface-variant">
              Show
              <select name="limit" onchange="this.form.submit()"
                      class="appearance-none rounded-md pl-2 pr-6 bg-white text-xs text-on-surface focus:outline-none cursor-pointer font-mono" style="border:1px solid #95a5a6; height:36px;">
                <option value="10"  <?= $limit===10?'selected':'' ?>>10</option>
                <option value="25"  <?= $limit===25?'selected':'' ?>>25</option>
                <option value="50"  <?= $limit===50?'selected':'' ?>>50</option>
                <option value="100" <?= $limit===100?'selected':'' ?>>100</option>
                <option value="0"   <?= $limit===0?'selected':'' ?>>All</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex items-end gap-2 flex-wrap">
          <span class="text-[11px] font-mono text-on-surface-variant uppercase tracking-wider mr-1 mb-1">Filter:</span>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">bolt</span>
            <select name="action_type" onchange="this.form.submit()" class="filter-select-el">
              <option value="all" <?= $actionFilter==='all'?'selected':'' ?>>All Actions</option>
              <?php foreach (auditCategoryLabels() as $catKey => $catLabel): ?>
              <option value="<?= $catKey ?>" <?= $actionFilter===$catKey?'selected':'' ?>><?= $catLabel ?></option>
              <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">person</span>
            <select name="staff" onchange="this.form.submit()" class="filter-select-el">
              <option value="all" <?= $staffFilter==='all'?'selected':'' ?>>All Staff</option>
              <?php foreach ($staffList as $s): ?>
              <option value="<?= htmlspecialchars($s['username']) ?>" <?= $staffFilter===$s['username']?'selected':'' ?>><?= htmlspecialchars($s['display_name']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

          <span class="text-[11px] font-mono text-on-surface-variant uppercase tracking-wider mr-1 mb-1" style="align-self:center">Filter by Time:</span>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">event</span>
            <select name="time_range" id="auditTimeRangeSelect" onchange="toggleAuditCustomRange(this.value); this.form.submit()" class="filter-select-el">
              <option value="all"   <?= $timeFilter==='all'?'selected':'' ?>>All</option>
              <option value="today" <?= $timeFilter==='today'?'selected':'' ?>>Today</option>
              <option value="week"  <?= $timeFilter==='week'?'selected':'' ?>>This Week</option>
              <option value="month" <?= $timeFilter==='month'?'selected':'' ?>>This Month</option>
              <option value="custom"<?= $timeFilter==='custom'?'selected':'' ?>>Custom Range</option>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

          <div id="auditCustomRangeWrap" style="display:<?= $timeFilter==='custom' ? 'flex' : 'none' ?>; gap:8px;">
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:.65rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#74777d">From</span>
              <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" onchange="this.form.submit()"
                     style="border:1px solid #95a5a6;border-radius:0.4rem;padding:6px 8px;font-size:.78rem;font-family:'JetBrains Mono',monospace;color:#43474c">
            </div>
            <div style="display:flex;flex-direction:column;gap:2px">
              <span style="font-size:.65rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#74777d">To</span>
              <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" onchange="this.form.submit()"
                     style="border:1px solid #95a5a6;border-radius:0.4rem;padding:6px 8px;font-size:.78rem;font-family:'JetBrains Mono',monospace;color:#43474c">
            </div>
          </div>

          <?php if ($actionFilter !== 'all' || $staffFilter !== 'all' || $dateFrom !== '' || $dateTo !== '' || $search !== ''): ?>
          <a href="audit_log.php" class="text-xs text-outline hover:text-error transition-colors font-medium ml-1" style="align-self:center">Clear all</a>
          <?php endif; ?>
        </div>
      </form>

      <div id="auditResultsWrap">
        <?php renderAuditResultsBlock($logs, $limit, $offset, $filteredTotal, $totalPages, $page); ?>
      </div>
    </div>

  </main>
</div>

<!-- Watermark -->
<div class="fixed bottom-4 right-4 pointer-events-none opacity-5 z-0">
  <img src="HimCity_Logo_nobg.png" alt="" class="w-32 h-32 object-contain">
</div>

<!-- ══ LOGOUT MODAL ══ -->
<div class="modal-overlay" id="logoutModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header" style="background:#1d3246">
      <h3>Confirm Sign Out</h3>
      <button class="modal-close" onclick="closeLogoutModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg" style="text-align:center;padding:10px 0">
        <span style="display:block;margin-bottom:12px">
          <span class="material-symbols-outlined text-4xl text-on-surface-variant">logout</span>
        </span>
        Are you sure you want to sign out of the OSCA Registry admin portal?
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeLogoutModal()">Stay Logged In</button>
      <a href="logout.php" class="btn btn-primary">Yes, Sign Out</a>
    </div>
  </div>
</div>

<script>
  function toggleAuditCustomRange(value) {
    const wrap = document.getElementById('auditCustomRangeWrap');
    if (wrap) wrap.style.display = (value === 'custom') ? 'flex' : 'none';
  }
  function openLogoutModal() {
    document.getElementById('logoutModal').classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeLogoutModal() {
    document.getElementById('logoutModal').classList.remove('open');
    document.body.style.overflow = '';
  }

  // ── Incremental Audit Log search ──────────────────────────────
  let _auditSearchTimer  = null;
  let _auditSearchAbort  = null;

  function handleAuditIncrementalSearch(value) {
    const clearBtn = document.getElementById('auditSearchClear');
    if (clearBtn) clearBtn.classList.toggle('hidden', value.length === 0);

    clearTimeout(_auditSearchTimer);
    _auditSearchTimer = setTimeout(() => {
      const url = new URL(window.location.href);
      url.searchParams.set('search', value);
      url.searchParams.set('page', 1);
      loadAuditResults(url);
    }, 350);
  }

  function handleAuditLimitChange(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('limit', value);
    url.searchParams.set('page', 1);
    loadAuditResults(url);
  }

  function clearAuditSearch() {
    const input = document.getElementById('auditSearchInput');
    if (input) input.value = '';
    const clearBtn = document.getElementById('auditSearchClear');
    if (clearBtn) clearBtn.classList.add('hidden');

    const url = new URL(window.location.href);
    url.searchParams.set('search', '');
    url.searchParams.set('page', 1);
    loadAuditResults(url);
  }

  function loadAuditResults(url) {
    const spinner = document.getElementById('auditSearchSpinner');
    if (spinner) spinner.classList.add('active');

    if (_auditSearchAbort) _auditSearchAbort.abort();
    _auditSearchAbort = new AbortController();

    const fetchUrl = new URL(url.toString());
    fetchUrl.searchParams.set('ajax', '1');

    return fetch(fetchUrl.toString(), { signal: _auditSearchAbort.signal })
      .then(res => res.text())
      .then(html => {
        const wrap = document.getElementById('auditResultsWrap');
        if (wrap) wrap.innerHTML = html;

        // Keep the address bar (and back button) in sync, without reloading
        const displayUrl = new URL(url.toString());
        displayUrl.searchParams.delete('ajax');
        window.history.replaceState({}, '', displayUrl.pathname + displayUrl.search);
      })
      .catch(err => {
        if (err.name !== 'AbortError') console.error('Audit search failed:', err);
      })
      .finally(() => {
        if (spinner) spinner.classList.remove('active');
      });
  }

  // Intercept pagination clicks inside the AJAX-loaded fragment so
  // paging through search results doesn't trigger a full page reload
  document.addEventListener('click', function (e) {
    const link = e.target.closest('#auditResultsWrap a[href]');
    if (!link) return;
    e.preventDefault();
    const url = new URL(link.getAttribute('href'), window.location.href);
    loadAuditResults(url);
  });
</script>

</body>
</html>