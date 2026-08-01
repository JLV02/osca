<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';
require_once 'render_row.php';
require_once 'notifications_helper.php';

$notifState = osca_get_notification_state($pdo); 

// ── Role variables ────────────────────────────────────────────
$currentRole        = $_SESSION['admin_role']   ?? 'encoder';
$currentDisplayName = $_SESSION['display_name'] ?? ($_SESSION['admin_username'] ?? 'Staff');
$isAdmin            = ($currentRole === 'admin');

// ── Stats ─────────────────────────────────────────────────────
$total         = $pdo->query("SELECT COUNT(*) FROM applicants WHERE is_archived = 0 OR is_archived IS NULL")->fetchColumn();
$today         = $pdo->query("SELECT COUNT(*) FROM applicants WHERE DATE(created_at)=CURDATE() AND (is_archived = 0 OR is_archived IS NULL)")->fetchColumn();
$archivedCount = (int)$pdo->query("SELECT COUNT(*) FROM applicants WHERE is_archived = 1")->fetchColumn();

$activeFilter = $_GET['filter'] ?? 'all';
if ($activeFilter !== 'all' && $activeFilter !== '') {
    $brgyStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE barangay = ? AND (is_archived = 0 OR is_archived IS NULL)");
    $brgyStmt->execute([$activeFilter]);
    $barangayCount = $brgyStmt->fetchColumn();
    $barangayLabel = htmlspecialchars($activeFilter);
} else {
    $barangayCount = '—';
    $barangayLabel = 'Select a Barangay';
}

$barangays = ['Aguisan','Barangay I-Poblacion','Barangay II-Poblacion','Barangay III-Poblacion','Barangay IV-Poblacion','Buenavista','Cabadiangan','Cabanbanan','Carabalan','Caradioan','Libacao','Mahalang','Mambagaton','Nabalian','San Antonio','Saraet','Suay','Talaban','Tooy'];
sort($barangays);

// ── Fetch applicants ──────────────────────────────────────────
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100, 0])) $limit = 10;
$offset    = ($limit > 0) ? ($page - 1) * $limit : 0;
$search    = mb_strtoupper(trim($_GET['search'] ?? ''), 'UTF-8');
$filter    = $_GET['filter'] ?? 'all';
$sexFilter = $_GET['sex'] ?? 'all';
$ageFilter = $_GET['age'] ?? 'all';
$pwdFilter = $_GET['pwd'] ?? 'all';

// ── Always exclude archived records from dashboard ────────────
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

// ── Age filter ────────────────────────────────────────────────
// ── Age filter (custom range e.g. "60-80") ────────────────────
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

// ── PWD filter ────────────────────────────────────────────────
if ($pwdFilter !== 'all' && $pwdFilter !== '') {
    $where .= " AND personWithDisability = ?";
    $params[] = $pwdFilter;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants $where");
$countStmt->execute($params);
$filteredTotal = $countStmt->fetchColumn();
$totalPages    = ($limit > 0) ? max(1, ceil($filteredTotal / $limit)) : 1;

$query = "SELECT * FROM applicants $where ORDER BY created_at DESC";
if ($limit > 0) $query .= " LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1d3246">
  <title>Dashboard — OSCA Registry</title>
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <link rel="stylesheet" href="dashboard.css">
  
  <style>
    /* ── Archive reason chips ── */
    .reason-chip {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 13px;
      border-radius: 999px;
      border: 1.5px solid #92400e;
      background: #fff;
      color: #92400e;
      font-size: 0.78rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      cursor: pointer;
      transition: background 0.15s, color 0.15s, border-color 0.15s, box-shadow 0.15s;
      white-space: nowrap;
      line-height: 1;
    }
    .reason-chip:hover {
      background: #fef3c7;
      border-color: #78350f;
      color: #78350f;
    }
    .reason-chip.active {
      background: #92400e;
      color: #fff;
      border-color: #92400e;
      box-shadow: 0 1px 4px rgba(146,64,14,0.25);
    }
    .reason-chip.active:hover {
      background: #78350f;
      border-color: #78350f;
    }
    .ncsc-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    padding: 2px 0;
    font-size: 0.78rem;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: opacity 0.15s;
  }
  .ncsc-pill:hover { opacity: 0.7; }
  .ncsc-pill:active { opacity: 0.5; }
  .ncsc-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

  .ncsc-pill-yes { color: #059669; }
  .ncsc-pill-yes .ncsc-dot { background: #059669; }

  .ncsc-pill-no { color: #9ca3af; }
  .ncsc-pill-no .ncsc-dot { background: #d1d5db; }

    /* ── Compact table ── */
    .data-table {
      table-layout: auto;
      width: 100%;
      white-space: nowrap;
    }
    .data-table th,
    .data-table td {
      padding: 10px 10px;
    }
    .data-table th:first-child,
    .data-table td:first-child {
      padding-left: 16px;
    }
    .data-table th:last-child,
    .data-table td:last-child {
      padding-right: 12px;
    }

    /* ── Change notifications bell ── */
    #notifBellBtn {
      background: none;
      width: 40px;
      height: 40px;
    }
    #notifBellIcon {
      color: #1d3246;
      font-variation-settings: 'FILL' 1;
      font-size: 22px;
    }
    .notif-badge {
      position: absolute; top: 2px; right: 2px;
      min-width: 16px; height: 16px; padding: 0 3px;
      border-radius: 999px; background: #ea1c26; color: #fff;
      font-size: 10px; font-weight: 700; font-family: 'JetBrains Mono', monospace;
      display: flex; align-items: center; justify-content: center;
      line-height: 1; box-shadow: 0 0 0 2px #eceef5;
    }
      #notifBadge.hidden { display: none !important; }
    .notif-panel {
      position: absolute; top: calc(100% + 10px); right: 0; width: 300px;
      background: #fff; border: 1px solid rgba(149,165,166,.20); border-radius: 0.75rem;
      box-shadow: 0 12px 32px rgba(0,0,0,.14); opacity: 0; visibility: hidden;
      transform: translateY(-6px); transition: opacity .15s, transform .15s, visibility .15s;
      z-index: 100; overflow: hidden; padding: 16px 18px 14px;
    }
    .notif-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
    .notif-panel-title { font-family:'Poppins',sans-serif; font-weight:600; font-size:1.05rem; color:#1b1c1d; margin-bottom:12px; }
    .notif-panel-divider { height:1px; background:rgba(149,165,166,.25); margin:0 -18px 6px; }
    .notif-list { max-height: 320px; overflow-y: auto; }
    .notif-item { display:flex; align-items:flex-start; gap:12px; padding:12px 0; border-bottom:1px solid rgba(149,165,166,.15); }
    .notif-item:last-child { border-bottom: none; }
    .notif-avatar {
      width:38px; height:38px; border-radius:50%; flex-shrink:0;
      display:flex; align-items:center; justify-content:center;
      font-family:'JetBrains Mono',monospace; font-weight:700; font-size:.78rem;
    }
    .notif-item-body { min-width:0; }
    .notif-item-title {
      font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em;
      color:#3b6a8f; line-height:1.3; margin-bottom:3px;
    }
    .notif-item-meta { font-size:.74rem; color:#9aa0a6; }
    .notif-empty { padding:24px 0; text-align:center; color:#9aa0a6; font-size:.85rem; }
    .notif-panel-footer { margin-top: 8px; }
    .notif-viewall-btn {
      width:100%; background:#1d3246; color:#fff; border:none; padding:11px 0;
      border-radius:999px; font-size:.8rem; font-weight:700; letter-spacing:.03em;
      text-transform:uppercase; cursor:pointer; transition:background .15s;
    }
    .notif-viewall-btn:hover { background:#2a4560; }

    /* ── Notifications modal (sidebar layout) ── */
    .modal-notif { max-width: 760px; width: 92vw; }
    .notif-modal-body { display: flex; height: 520px; padding: 0; }
    .notif-sidebar {
      width: 220px; flex-shrink: 0; background: #fafafa;
      border-right: 1px solid rgba(149,165,166,.25);
      padding: 18px 0; overflow-y: auto;
    }
    .notif-sidebar-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 20px; cursor: pointer; font-size: .85rem;
      color: #43474c; font-weight: 500; transition: background .12s, color .12s;
    }
    .notif-sidebar-item:hover { background: #eef1f5; }
    .notif-sidebar-item.active { color: #1d3246; font-weight: 700; background: #eef1f5; }
    .notif-sidebar-item .material-symbols-outlined { font-size: 19px; color: #74777d; }
    .notif-sidebar-item.active .material-symbols-outlined { color: #1d3246; }
    .notif-sidebar-count {
      margin-left: auto; background: #eef1f5; color: #74777d;
      font-size: .68rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
      min-width: 20px; height: 20px; border-radius: 999px;
      display: flex; align-items: center; justify-content: center; padding: 0 5px;
    }
    .notif-sidebar-item.active .notif-sidebar-count { background: #1d3246; color: #fff; }
    .notif-sidebar-divider {
      margin: 14px 20px 8px; font-size: .68rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em; color: #95a5a6;
      font-family: 'JetBrains Mono', monospace;
    }
    .notif-label-item {
      display: flex; align-items: center; gap: 9px;
      padding: 7px 20px; cursor: pointer; font-size: .82rem; color: #43474c;
    }
    .notif-label-item:hover { background: #eef1f5; }
    .notif-label-item.active { color: #1d3246; font-weight: 700; }
    .notif-label-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .notif-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
    .notif-search-bar {
      display: flex; align-items: center; gap: 8px;
      padding: 14px 20px; border-bottom: 1px solid rgba(149,165,166,.20);
    }
    .notif-search-bar input {
      flex: 1; border: none; outline: none; font-size: .85rem;
      font-family: 'Inter', sans-serif; color: #43474c; background: transparent;
    }
    .notif-search-bar .material-symbols-outlined { color: #95a5a6; font-size: 19px; }
    .notif-list-scroll { flex: 1; overflow-y: auto; }
    .notif-row {
      display: flex; align-items: center; gap: 14px; padding: 14px 20px;
      border-bottom: 1px solid rgba(149,165,166,.12); transition: background .12s;
    }
    .notif-row:hover { background: #f8f9fb; }
    .notif-row.unread { background: #f5f8ff; }
    .notif-row-avatar {
      width: 38px; height: 38px; border-radius: 50%; display: flex;
      align-items: center; justify-content: center; font-family: 'JetBrains Mono', monospace;
      font-weight: 700; font-size: .78rem; flex-shrink: 0;
    }
    .notif-row-body { flex: 1; min-width: 0; display: flex; align-items: baseline; gap: 10px; }
    .notif-row-title {
      font-weight: 700; font-size: .85rem; color: #1b1c1d;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .notif-row-sub {
      font-size: .8rem; color: #74777d; flex: 1;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .notif-row-time { font-size: .76rem; color: #9aa0a6; white-space: nowrap; flex-shrink: 0; }
    .notif-row-unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; margin-right: 2px; }
    .notif-list-empty { text-align: center; padding: 60px 20px; color: #9aa0a6; font-size: .85rem; }
    /* ── N/A toggle buttons (middle name / email) ── */
    .na-toggle-btn {
      transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .na-toggle-btn:hover {
      background: #1d3246;
      color: #fff;
      border-color: #1d3246;
    }
    /* ── Profile popup ── */
    .profile-panel {
      position: absolute; top: calc(100% + 10px); right: 0; width: 240px;
      background: #fff; border: 1px solid rgba(149,165,166,.20); border-radius: 0.75rem;
      box-shadow: 0 12px 32px rgba(0,0,0,.14); opacity: 0; visibility: hidden;
      transform: translateY(-6px); transition: opacity .15s, transform .15s, visibility .15s;
      z-index: 100; overflow: hidden;
    }
    .profile-panel.open { opacity: 1; visibility: visible; transform: translateY(0); }
    .profile-panel-header {
      padding: 18px 18px 14px; display: flex; align-items: center; gap: 12px;
      border-bottom: 1px solid rgba(149,165,166,.20);
    }
    .profile-panel-avatar {
      width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
      background: #1d3246; display: flex; align-items: center; justify-content: center;
      color: #fff; font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 1rem;
    }
    .profile-panel-name { font-family:'Poppins',sans-serif; font-weight:600; font-size:.92rem; color:#1b1c1d; line-height:1.3; }
    .profile-panel-role { font-size:.72rem; color:#74777d; margin-top:2px; }
    .profile-panel-menu { padding: 8px 0; }
    .profile-panel-item {
      display: flex; align-items: center; gap: 10px; width: 100%;
      padding: 10px 18px; background: none; border: none; cursor: pointer;
      font-size: .85rem; color: #43474c; text-align: left; transition: background .12s;
    }
    .profile-panel-item:hover { background: #eef1f5; }
    .profile-panel-item .material-symbols-outlined { font-size: 19px; color: #74777d; }
    .profile-panel-item.danger { color: #ba1a1a; }
    .profile-panel-item.danger .material-symbols-outlined { color: #ba1a1a; }
  </style>

  <!-- ── Role + archive count exposed to dashboard.js ── -->
  <script>
    window.OSCA = {
      role:          <?= json_encode($currentRole) ?>,
      displayName:   <?= json_encode($currentDisplayName) ?>,
      isAdmin:       <?= $isAdmin ? 'true' : 'false' ?>,
      archivedCount: <?= $archivedCount ?>,
      currentOffset: <?= ($limit > 0 ? $offset : 0) ?>,
      pendingChanges: <?= (int)$notifState['pending_changes'] ?>,
      lastBackupAt:  <?= json_encode($notifState['last_backup_at']) ?>,
    };
  </script>
</head>
<body class="bg-[#ECF0F1] font-body text-on-surface min-h-screen overflow-hidden">

<!-- ── SIDEBAR ── -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-surface border-r flex flex-col justify-between py-6 z-50" style="border-right:1px solid rgba(149,165,166,.30)">
  <div>
    <!-- Brand -->
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
    <!-- Main nav -->
    <nav class="space-y-1">
      <a href="dashboard.php" class="flex items-center gap-4 px-6 py-3 text-primary font-bold border-r-2 border-primary transition-colors">
        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">dashboard</span>
        <span class="text-sm">Dashboard</span>
      </a>
      <a href="registration.php" class="flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors">
        <span class="material-symbols-outlined">app_registration</span>
        <span class="text-sm">Registration Form</span>
      </a>
      <?php if ($isAdmin): ?>
      <a href="audit_log.php" class="flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors">
  <span class="material-symbols-outlined">history</span>
  <span class="text-sm">Audit Log</span>
      </a>
      <?php endif; ?>
    </nav>
  </div>
  <!-- Footer nav: user info + logout -->
  <div>
    <div class="px-6 py-3 mb-1" style="border-top:1px solid rgba(149,165,166,.20)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
          <span class="text-white text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'], 0, 1)) ?></span>
        </div>
        <div class="min-w-0 flex items-center gap-2">
          <p class="text-sm font-semibold text-on-surface truncate" id="sidebarDisplayName"><?= htmlspecialchars($currentDisplayName) ?></p>
          <span id="roleBadge" class="inline-block text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full flex-shrink-0
            <?= $isAdmin ? 'bg-primary/15 text-primary' : 'bg-emerald-100 text-emerald-700' ?>">
            <?= $isAdmin ? 'Admin' : 'Encoder' ?>
          </span>
        </div>
      </div>
    </div>
    <nav class="space-y-1">
      <div class="relative" id="settingsMenuWrap">
        <button onclick="toggleSettingsMenu(event)" class="w-full flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors text-left">
          <span class="material-symbols-outlined">settings</span>
          <span class="text-sm">Settings</span>
          </span>
        </button>
        <div class="settings-popup-menu" id="settingsPopupMenu">
          <button class="settings-popup-item" onclick="selectSettingsMenuItem('staff')">
            <span class="material-symbols-outlined">manage_accounts</span>
            <span>Staff Accounts</span>
          </button>
          <button class="settings-popup-item" onclick="selectSettingsMenuItem('archive')">
            <span class="material-symbols-outlined">inventory_2</span>
            <span>Archive</span>
            <span id="settingsPopupArchiveBadge" class="settings-popup-badge <?= $archivedCount > 0 ? '' : 'hidden' ?>"><?= $archivedCount ?></span>
          </button>
          <button class="settings-popup-item" onclick="openPrintModal()">
            <span class="material-symbols-outlined">print</span>
            <span>Print Reports</span>
          </button>
          <?php if ($isAdmin): ?>
          <button class="settings-popup-item" onclick="openExportBackupModal()">
              <span class="material-symbols-outlined">cloud_download</span>
              Export Backup
            </button>
            <button class="settings-popup-item" onclick="openImportBackupModal()">
              <span class="material-symbols-outlined">cloud_upload</span>
              Import Backup
            </button>
          <?php endif; ?>
        </div>
      </div>
      <button onclick="openLogoutModal()" class="w-full flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors text-left">
        <span class="material-symbols-outlined">logout</span>
        <span class="text-sm">Logout</span>
      </button>
    </nav>
  </div>
</aside>

<!-- ── MAIN CONTENT ── -->
<div class="ml-64 flex flex-col h-screen overflow-hidden">

  <!-- Top Bar -->
  <header class="flex justify-between items-center h-16 px-6 bg-surface-container-lowest border-b z-40 flex-shrink-0" style="border-bottom:1px solid rgba(149,165,166,.30)">
    <h2 class="font-display font-bold text-2xl text-on-surface">Senior Citizen's Dashboard</h2>
    <div class="flex items-center gap-3">
      <a href="registration.php"
         class="flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-primary-container transition-all active:scale-95">
        <span class="material-symbols-outlined text-lg">add</span>
        Add New Record
      </a>
     <div class="w-px h-8 bg-outline-variant"></div>
<div class="relative" id="notifBellWrap">
  <button id="notifBellBtn" onclick="toggleNotifPanel(event)"
        class="flex items-center justify-center transition-colors relative">
  <span class="material-symbols-outlined" id="notifBellIcon">notifications</span>
  <span id="notifBadge" class="notif-badge hidden">0</span>
</button>
  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-title">Notification</div>
    <div class="notif-panel-divider"></div>
    <div class="notif-list" id="notifList">
      <div class="notif-empty" id="notifEmpty">No notifications yet.</div>
    </div>
    <div class="notif-panel-footer">
      <button class="notif-viewall-btn" onclick="openNotifModal()">View All Notifications</button>
    </div>
  </div>
</div>
      <div class="relative" id="profileWrap">
        <button onclick="toggleProfilePanel(event)" class="w-9 h-9 rounded-full flex items-center justify-center cursor-pointer" style="background:#1d3246">
          <span class="text-white text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></span>
        </button>
        <div class="profile-panel" id="profilePanel">
          <div class="profile-panel-header">
            <div class="profile-panel-avatar"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></div>
            <div>
              <div class="profile-panel-name"><?= htmlspecialchars($currentDisplayName) ?></div>
              <div class="profile-panel-role"><?= $isAdmin ? 'Administrator' : 'Encoder' ?></div>
            </div>
          </div>
          <div class="profile-panel-menu">
            <button class="profile-panel-item" onclick="closeProfilePanel();openSettingsModal('staff')">
              <span class="material-symbols-outlined">settings</span> Staff Accounts
            </button>
            <button class="profile-panel-item danger" onclick="closeProfilePanel();openLogoutModal()">
              <span class="material-symbols-outlined">logout</span> Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <!-- Scrollable area -->
  <main class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-5">

    <!-- ── STAT CARDS ── -->
    <div class="grid grid-cols-3 gap-5">

      <!-- Total Registrants -->
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Total Registrants</p>
          <p class="font-display font-bold text-3xl text-on-surface stat-total"><?= number_format($total) ?></p>
          <p class="text-xs text-outline mt-1">All Time Records</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-blue-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-blue-500 text-2xl" style="font-variation-settings:'FILL' 1">group</span>
        </div>
      </div>

      <!-- Barangay Registrants -->
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Barangay Registrants</p>
          <div class="flex items-center gap-2">
            <p class="font-display font-bold text-3xl text-on-surface"><?= $barangayCount === '—' ? '—' : number_format($barangayCount) ?></p>
            <?php if ($filter !== 'all' && $filter !== ''): ?>
            <span class="text-[10px] font-mono uppercase bg-primary/10 text-primary px-2 py-0.5 rounded-full"><?= htmlspecialchars(strtoupper($filter)) ?></span>
            <?php endif; ?>
          </div>
          <p class="text-xs text-outline mt-1"><?= $filter !== 'all' ? 'Active Filter Applied' : 'Select a Barangay' ?></p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-emerald-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-emerald-600 text-2xl" style="font-variation-settings:'FILL' 1">location_on</span>
        </div>
      </div>

      <!-- Registered Today -->
      <div class="bg-surface-container-lowest rounded-lg p-5 flex items-center justify-between" style="border:1px solid rgba(149,165,166,.30)">
        <div>
          <p class="text-[11px] font-mono uppercase tracking-widest text-on-surface-variant mb-1">Registered Today</p>
          <p class="font-display font-bold text-3xl text-on-surface stat-today"><?= number_format($today) ?></p>
          <p class="text-xs text-outline mt-1">New Applicants</p>
        </div>
        <div class="w-12 h-12 rounded-lg bg-amber-50 flex items-center justify-center">
          <span class="material-symbols-outlined text-amber-500 text-2xl" style="font-variation-settings:'FILL' 1">calendar_today</span>
        </div>
      </div>

    </div>

    <!-- ── ACTIVE FILTER PILLS ── -->
    <?php
    $activeFilters = [];
    if ($filter !== 'all' && $filter !== '') $activeFilters[] = ['label' => 'Barangay: '.$filter, 'clear' => 'filter'];
    if ($sexFilter !== 'all' && $sexFilter !== '') $activeFilters[] = ['label' => 'Sex: '.$sexFilter, 'clear' => 'sex'];
    if ($ageFilter !== 'all' && $ageFilter !== '') $activeFilters[] = ['label' => 'Age: '.$ageFilter, 'clear' => 'age'];
    if ($pwdFilter === 'Yes') $activeFilters[] = ['label' => 'PWD Only', 'clear' => 'pwd'];
    if ($search !== '') $activeFilters[] = ['label' => 'Search: '.mb_strtolower($search,'UTF-8'), 'clear' => 'search'];
    ?>
    <?php if (!empty($activeFilters)): ?>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-xs font-mono text-on-surface-variant uppercase tracking-wider">Active Filters:</span>
      <?php foreach ($activeFilters as $af): ?>
      <?php
        $urlParams = ['page'=>1,'filter'=>$filter,'search'=>$search,'limit'=>$limit,'sex'=>$sexFilter,'age'=>$ageFilter,'pwd'=>$pwdFilter];
        $urlParams[$af['clear']] = ($af['clear'] === 'filter' ? 'all' : ($af['clear'] === 'search' ? '' : 'all'));
        $clearHref = '?'.http_build_query($urlParams);
      ?>
      <a href="<?= $clearHref ?>"
         class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary hover:bg-primary/20 transition-colors">
        <?= htmlspecialchars($af['label']) ?>
        <span class="material-symbols-outlined" style="font-size:13px">close</span>
      </a>
      <?php endforeach; ?>
      <a href="dashboard.php" class="text-xs text-outline hover:text-error transition-colors font-medium ml-1">Clear all</a>
    </div>
    <?php endif; ?>

    <!-- ── TABLE SECTION ── -->
    <div class="bg-surface-container-lowest rounded-lg overflow-hidden" style="border:1px solid rgba(149,165,166,.30)">

      <!-- Table Header / Controls -->
      <div class="px-5 py-4" style="border-bottom:1px solid rgba(149,165,166,.30)">
        <!-- Row 1: Title + Search + Show per page -->
        <div class="flex items-center justify-between gap-4 mb-3">
          <h3 class="font-display font-bold text-base text-primary whitespace-nowrap">Registrant's Records</h3>
          <div class="flex items-center gap-3">
            <!-- Search -->
            <div class="search-wrap relative flex items-center">
              <span class="absolute left-3 text-outline pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="searchInput" value="<?= htmlspecialchars($search) ?>"
                     placeholder="Search records..."
                     autocomplete="off" oninput="handleIncrementalSearch(this.value)"
                     class="text-sm bg-surface-container-lowest w-52 transition focus:outline-none"
                     style="border:1px solid #95a5a6; border-radius:0.5rem; padding:7px 32px 7px 36px; height:36px;"
                     onfocus="this.style.borderColor='#1d3246';this.style.boxShadow='0 0 0 2px rgba(29,50,70,.20)'"
                     onblur="this.style.borderColor='#95a5a6';this.style.boxShadow='none'">
              <span class="search-spinner absolute right-8 hidden"></span>
              <button id="searchClear" onclick="clearSearch()" title="Clear"
                      class="absolute right-2 text-outline hover:text-primary <?= $search ? '' : 'hidden' ?>">
                <span class="material-symbols-outlined text-[16px]">close</span>
              </button>
            </div>
            <!-- Show per page -->
            <div class="flex items-center gap-1.5 text-xs text-on-surface-variant">
              Show
              <div class="relative">
                <select id="limitSelectTop" onchange="applyLimit(this.value)"
                        class="appearance-none rounded-md pl-2 pr-6 bg-white text-xs text-on-surface focus:outline-none cursor-pointer font-mono" style="border:1px solid #95a5a6; height:36px;">
                  <option value="10"  <?= $limit===10?'selected':'' ?>>10</option>
                  <option value="25"  <?= $limit===25?'selected':'' ?>>25</option>
                  <option value="50"  <?= $limit===50?'selected':'' ?>>50</option>
                  <option value="100" <?= $limit===100?'selected':'' ?>>100</option>
                  <option value="0"   <?= $limit===0?'selected':'' ?>>All</option>
                </select>
                <span class="material-symbols-outlined absolute right-1 top-1/2 -translate-y-1/2 text-outline pointer-events-none" style="font-size:14px">expand_more</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Row 2: Filter dropdowns -->
        <div class="flex items-end gap-2 flex-wrap">
        <span class="text-[11px] font-mono text-on-surface-variant uppercase tracking-wider mr-1 mb-1">Filter:</span>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">location_on</span>
            <select id="barangayFilter" onchange="applyBarangayFilter(this.value)" class="filter-select-el">
              <option value="all" <?= $filter==='all'?'selected':'' ?>>All Barangays</option>
              <?php foreach($barangays as $b): ?>
              <option value="<?= htmlspecialchars($b) ?>" <?= $filter===$b?'selected':'' ?>><?= htmlspecialchars($b) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">wc</span>
            <select id="sexFilter" onchange="applySexFilter(this.value)" class="filter-select-el">
              <option value="all" <?= $sexFilter==='all'?'selected':'' ?>>All Sex</option>
              <option value="Male"   <?= $sexFilter==='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= $sexFilter==='Female'?'selected':'' ?>>Female</option>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

          <?php
    $ageFrom = 60; $ageTo = '';
if ($ageFilter !== 'all' && $ageFilter !== '') {
    $ageParts = explode('-', $ageFilter);
    $ageFrom  = isset($ageParts[0]) && $ageParts[0] !== '' ? (int)$ageParts[0] : 60;
    $ageTo    = isset($ageParts[1]) && $ageParts[1] !== '' ? (int)$ageParts[1] : '';
}
?>
<div style="display:flex;flex-direction:column;gap:2px">
  <span style="font-size:.65rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#74777d">Age Range</span>
<div class="filter-select-wrap" id="ageFilterWrap" style="gap:4px;padding:0 8px;">
  <span class="material-symbols-outlined filter-select-icon">cake</span>
  <input type="number" id="ageFrom" min="60" max="150"
       value="<?= $ageFrom ?>"
       placeholder="60"
       oninput="validateAgeInputs(); debounceAgeFilter()"
       onblur="enforceAgeMin(this); applyAgeRangeFilter()"
       class="filter-select-el"
       style="width:48px;text-align:center;padding-right:0">

<input type="number" id="ageTo" min="60" max="150"
     <?= ($ageTo !== '' && $ageTo > 0) ? 'value="'.$ageTo.'"' : '' ?>
     placeholder="All"
     oninput="validateAgeInputs(); debounceAgeFilter()"
     onblur="enforceAgeMin(this); applyAgeRangeFilter()"
     class="filter-select-el"
     style="width:48px;text-align:center;padding-right:0">
  <button onclick="clearAgeFilter()" title="Clear age filter" id="ageClearBtn"
          style="background:none;border:none;cursor:pointer;padding:0;display:<?= $ageFilter !== 'all' && $ageFilter !== '' ? 'flex' : 'none' ?>;align-items:center;flex-shrink:0">
    <span class="material-symbols-outlined" style="font-size:14px;color:#74777d">close</span>
  </button>
</div>
</div>
<span id="ageError"style="font-size:.72rem;color:#ba1a1a;font-family:'JetBrains Mono',monospace;display:none;align-self:center">From ≤ To</span>

          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">accessible</span>
            <select id="pwdFilter" onchange="applyPwdFilter(this.value)" class="filter-select-el">
              <option value="all" <?= ($pwdFilter==='all'||$pwdFilter==='No')?'selected':'' ?>>All</option>
              <option value="Yes" <?= $pwdFilter==='Yes'?'selected':'' ?>>PWD</option>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
          </div>

        </div>
      </div>

      <?php if (empty($applicants)): ?>
      <div class="flex flex-col items-center justify-center py-20 text-center">
        <span class="material-symbols-outlined text-6xl text-outline-variant mb-3">manage_search</span>
        <h3 class="font-display font-bold text-primary text-base mb-1">No records found</h3>
        <p class="text-sm text-on-surface-variant mb-4">
          <?= $search ? "No applicants match \"".htmlspecialchars($search)."\"." : "No applicants match the selected filters." ?>
        </p>
        <a href="registration.php" class="flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-primary-container transition-all">
          <span class="material-symbols-outlined text-lg">add</span>Add First Record
        </a>
      </div>
      <?php else: ?>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="data-table text-sm border-collapse">
          <thead>
            <tr style="border-bottom:1px solid rgba(149,165,166,.30)">
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">#</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Full Name</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">NCSC</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Sex</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Birthdate</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Age</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">PWD</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">OSCA ID</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Barangay</th>
              <th class="text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Registered</th>
              <th class="text-center text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applicants as $i => $r): ?>
              <?= renderApplicantRow($r, $isAdmin, ($limit > 0 ? $offset : 0) + $i + 1) ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-5 py-3 bg-[#f5f3f5] flex items-center justify-between gap-4 text-xs flex-wrap" id="paginationFooter" style="border-top:1px solid rgba(149,165,166,.30)">
        <?= renderPaginationFooter($page, $limit, $offset, $filteredTotal, $totalPages, $filter, $search, $sexFilter, $ageFilter, $pwdFilter) ?>
      </div>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Watermark -->
<div class="fixed bottom-4 right-4 pointer-events-none opacity-5 z-0">
  <img src="HimCity_Logo_nobg.png" alt="" class="w-32 h-32 object-contain">
</div>

<!-- ══ VIEW MODAL ══ -->
<div class="modal-overlay" id="viewModal" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px">
      <h3 id="modalTitle" style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Applicant Details</h3>
      <div style="display:flex;align-items:center;gap:16px;flex-shrink:0">
        <button onclick="printCurrentProfile()" title="Print Profile"
                style="display:inline-flex;align-items:center;gap:6px;background:#fff;color:#1d3246;border:1px solid rgba(255,255,255,.6);padding:7px 14px;border-radius:999px;font-size:.82rem;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s"
                onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='#fff'">
          <span class="material-symbols-outlined" style="font-size:17px">print</span>
          Print Profile
        </button>
        <button class="modal-close" onclick="closeModal()" aria-label="Close">
          <span class="material-symbols-outlined text-lg">close</span>
        </button>
      </div>
    </div>
    <div class="modal-body" id="modalBody"><div class="modal-loading">Loading…</div></div>
  </div>
</div>

<!-- ══ EDIT MODAL ══ -->
<div class="modal-overlay" id="editModal" role="dialog" aria-modal="true">
  <div class="modal modal-edit">
    <div class="modal-header">
      <h3 id="editModalTitle">Edit Record</h3>
      <button class="modal-close" onclick="closeEditModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body" id="editModalBody"><div class="modal-loading">Loading…</div></div>
    <div class="modal-footer">
      <button class="btn btn-primary" id="editNextBtn" onclick="editSaveAndGoToStep(2)" style="display:none">Next →</button>
      <button class="btn btn-primary" id="saveEditBtn" onclick="saveEdit()">
        <span class="material-symbols-outlined text-lg">save</span> Save Changes
      </button>
    </div>
  </div>
</div>

<!-- ══ ARCHIVE MODAL (all roles) ══ -->
<div class="modal-overlay" id="archiveModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header" style="background:#92400e">
      <h3>Archive Record</h3>
      <button class="modal-close" onclick="closeArchiveModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">
        You are moving <strong id="archiveName"></strong> to the archive.
        Archived records are hidden from the dashboard but can be restored later.
      </p>

      <div style="margin:16px 0 4px">
        <p class="delete-confirm-label" style="margin-bottom:10px; font-size:0.72rem; text-transform:uppercase; letter-spacing:.06em; color:#74777d;">
          Select a reason <span style="color:#ba1a1a">*</span>
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px" id="reasonChips">
          <button type="button" class="reason-chip" onclick="selectReasonChip(this,'Deceased')">✕ Deceased</button>
          <button type="button" class="reason-chip" onclick="selectReasonChip(this,'Moved out of Himamaylan City')">↗ Moved out</button>
          <button type="button" class="reason-chip" onclick="selectReasonChip(this,'Duplicate entry')">⊕ Duplicate entry</button>
          <button type="button" class="reason-chip" onclick="selectReasonChip(this,'Data entry error')">✎ Data entry error</button>
          <button type="button" class="reason-chip" onclick="selectReasonChip(this,'No longer eligible')">⊘ No longer eligible</button>
        </div>
      </div>

      <div style="margin-top:14px">
        <label class="delete-confirm-label" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:.06em;color:#74777d;display:block;margin-bottom:6px">
          Additional notes <span style="font-weight:400;text-transform:none;letter-spacing:0">(optional)</span>
        </label>
        <textarea id="archiveReasonInput" class="delete-confirm-input" rows="2"
                  placeholder="Add more detail if needed…"
                  oninput="onArchiveTextareaInput()"
                  maxlength="1000"
                  style="resize:vertical; min-height:60px"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px">
          <span id="archiveReasonError" style="font-size:.72rem;color:#ba1a1a"></span>
          <span style="font-size:.72rem;font-family:'JetBrains Mono',monospace;color:#74777d" id="archiveCharCount">0 / 1000</span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeArchiveModal()">Cancel</button>
      <button class="btn" id="confirmArchiveBtn" onclick="executeArchive()" disabled
              style="background:#92400e;color:#fff;border:none">
        <span class="material-symbols-outlined text-lg">inventory_2</span> Move to Archive
      </button>
    </div>
  </div>
</div>

<!-- ══ RESTORE MODAL (all roles) ══ -->
<div class="modal-overlay" id="restoreModal" role="dialog" aria-modal="true" style="z-index:9999">
  <div class="modal modal-sm">
    <div class="modal-header" style="background:#065f46">
      <h3>Restore Record</h3>
      <button class="modal-close" onclick="closeRestoreModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body" style="padding:20px 22px">

      <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px">
        <div style="width:48px;height:48px;border-radius:999px;background:#d1fae5;border:2px solid #6ee7b7;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(6,95,70,.12)">
          <span class="material-symbols-outlined" style="color:#065f46;font-size:26px;font-variation-settings:'FILL' 1">restore</span>
        </div>
        <div>
          <p style="margin:0 0 4px;font-size:.93rem;color:#1b1c1d;line-height:1.5">
            You are restoring <strong id="restoreName" style="color:#065f46"></strong> back to the active registry.
          </p>
          <p style="margin:0;font-size:.78rem;color:#74777d">It will reappear on the dashboard immediately.</p>
        </div>
      </div>

      <div style="height:1px;background:rgba(149,165,166,.20);margin-bottom:16px"></div>

      <div style="border:1px solid rgba(6,95,70,.20);border-radius:0.6rem;background:#f0fdf4;overflow:hidden">
        <div style="padding:8px 14px;background:rgba(6,95,70,.07);border-bottom:1px solid rgba(6,95,70,.12);display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:.68rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#065f46">Record Details</span>
          <span id="restoreMeta" style="font-size:.78rem;color:#065f46;font-weight:700;font-family:'JetBrains Mono',monospace"></span>
        </div>
        <div style="padding:12px 14px">
          <div style="display:flex;align-items:flex-start;gap:8px">
            <span class="material-symbols-outlined" style="font-size:16px;color:#74777d;margin-top:1px;flex-shrink:0">inventory_2</span>
            <div>
              <div style="font-size:.67rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#74777d;margin-bottom:3px">Originally Archived Because</div>
              <div id="restoreReason" style="font-size:.85rem;color:#1b1c1d;font-weight:500"></div>
            </div>
          </div>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:7px;margin-top:14px;padding:9px 12px;border-radius:0.5rem;background:rgba(6,95,70,.05);border:1px solid rgba(6,95,70,.12)">
        <span class="material-symbols-outlined" style="font-size:16px;color:#065f46;flex-shrink:0">info</span>
        <span style="font-size:.75rem;color:#43474c">All original data will be fully restored — no information was changed during archiving.</span>
      </div>

    </div>
    <div class="modal-footer" style="justify-content:flex-end;gap:10px">
      <button class="btn btn-outline" onclick="closeRestoreModal()">Cancel</button>
      <button class="btn" id="confirmRestoreBtn" onclick="executeRestore()"
              style="background:#065f46;color:#fff;border:none;display:inline-flex;align-items:center;gap:7px;font-weight:600;padding:9px 20px;border-radius:0.5rem;transition:background .15s"
              onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#065f46'">
        <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">restore</span>
        Restore to Active List
      </button>
    </div>
  </div>
</div>

<!-- ══ PURGE / PERMANENT DELETE MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="purgeModal" role="dialog" aria-modal="true" style="z-index:9999">
  <div class="modal modal-sm">
    <div class="modal-header danger">
      <h3>Permanently Delete Record</h3>
      <button class="modal-close" onclick="closePurgeModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body" style="padding:20px 22px">

      <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px">
        <div style="width:48px;height:48px;border-radius:999px;background:#ffdad6;border:2px solid #ffb4ab;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(186,26,26,.12)">
          <span class="material-symbols-outlined" style="color:#ba1a1a;font-size:26px;font-variation-settings:'FILL' 1">delete_forever</span>
        </div>
        <div>
          <p style="margin:0 0 4px;font-size:.93rem;color:#1b1c1d;line-height:1.5">
            You are permanently deleting <strong id="purgeName" style="color:#ba1a1a"></strong>.
          </p>
          <p style="margin:0;font-size:.78rem;color:#74777d">All data and photos will be erased. <strong style="color:#ba1a1a">This cannot be undone.</strong></p>
        </div>
      </div>

      <div style="height:1px;background:rgba(149,165,166,.20);margin-bottom:16px"></div>

      <div style="border:1px solid rgba(186,26,26,.25);border-radius:0.6rem;background:#fff8f7;padding:12px 14px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
          <span class="material-symbols-outlined" style="font-size:16px;color:#ba1a1a">warning</span>
          <span style="font-size:.72rem;font-family:'JetBrains Mono',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#ba1a1a">What will be deleted</span>
        </div>
        <ul style="margin:0;padding-left:18px;font-size:.80rem;color:#43474c;line-height:1.7">
          <li>All personal and family information</li>
          <li>Health, economic, and education records</li>
          <li>Uploaded OSCA ID photo and 2×2 photo</li>
          <li>Registration history and timestamps</li>
        </ul>
      </div>

      <div class="delete-confirm-wrap">
        <label class="delete-confirm-label">Type the senior citizen's last name to confirm:</label>
        <input type="text" id="purgeConfirmInput" class="delete-confirm-input"
               placeholder="Type last name here…" autocomplete="off"
               oninput="checkPurgeConfirm()">
        <span class="delete-confirm-hint" id="purgeConfirmHint"></span>
      </div>

    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closePurgeModal()">Cancel</button>
      <button class="btn btn-danger" id="confirmPurgeBtn" disabled onclick="executePurge()">
        <span class="material-symbols-outlined text-lg">delete_forever</span> Delete Permanently
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ DELETE MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="deleteModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header danger">
      <h3>Confirm Deletion</h3>
      <button class="modal-close" onclick="closeDeleteModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">You are about to permanently delete <strong id="deleteName"></strong>. This action <strong>cannot be undone</strong>.</p>
      <div class="delete-confirm-wrap">
        <label class="delete-confirm-label">Type the senior citizen's last name to confirm:</label>
        <input type="text" id="deleteConfirmInput" class="delete-confirm-input"
               placeholder="Type last name here…" autocomplete="off" oninput="checkDeleteConfirm()">
        <span class="delete-confirm-hint" id="deleteConfirmHint"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn btn-danger" id="confirmDeleteBtn" disabled>Delete Record</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ RESET PASSWORD MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="resetPasswordModal" role="dialog" aria-modal="true" style="z-index:9999">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3>Reset Password</h3>
      <button class="modal-close" onclick="closeResetPasswordModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">
        Set a new password for <strong id="resetPasswordUsername"></strong>.
        Share it with them directly — this bypasses the need for their old password.
      </p>
      <div class="edit-field" style="margin-bottom:14px;">
        <label>New Password</label>
        <div class="pw-input-wrap">
          <input type="password" id="resetPasswordNew" class="edit-input" placeholder="At least 8 characters">
          <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('resetPasswordNew', this)" tabindex="-1">
            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
          </button>
        </div>
      </div>
      <div class="edit-field">
        <label>Confirm New Password</label>
        <div class="pw-input-wrap">
          <input type="password" id="resetPasswordConfirm" class="edit-input" placeholder="Re-type password">
          <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('resetPasswordConfirm', this)" tabindex="-1">
            <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
          </button>
        </div>
        <div class="edit-field-hint" id="resetPasswordHint"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeResetPasswordModal()">Cancel</button>
      <button class="btn btn-primary" id="confirmResetPasswordBtn" onclick="executeResetPassword()">Reset Password</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ DELETE STAFF MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="deleteStaffModal" role="dialog" aria-modal="true" style="z-index:9999">
  <div class="modal modal-sm">
    <div class="modal-header danger">
      <h3>Delete Staff Account</h3>
      <button class="modal-close" onclick="closeDeleteStaffModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">You are about to permanently delete the account <strong id="deleteStaffUsername"></strong>. This action <strong>cannot be undone</strong>.</p>
      <div class="delete-confirm-wrap">
        <label class="delete-confirm-label">Type the username to confirm:</label>
        <input type="text" id="deleteStaffConfirmInput" class="delete-confirm-input"
               placeholder="Type username here…" autocomplete="off" oninput="checkDeleteStaffConfirm()">
        <span class="delete-confirm-hint" id="deleteStaffConfirmHint"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDeleteStaffModal()">Cancel</button>
      <button class="btn btn-danger" id="confirmDeleteStaffBtn" disabled onclick="executeDeleteStaff()">Delete Account</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ PRINT REPORTS MODAL ══ -->
<div class="modal-overlay" id="printModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3>Print Master List</h3>
      <button class="modal-close" onclick="closePrintModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body" style="padding:20px 22px">
      <p style="font-size:.85rem;color:#43474c;margin-bottom:16px">
        Choose filters for the printed list, or leave as "All" for the full master registry.
      </p>

      <div class="edit-grid edit-grid-2">
        <div class="edit-field">
          <label>Barangay</label>
          <select id="print_barangay" class="edit-input">
            <option value="all">All Barangays</option>
            <?php foreach ($barangays as $b): ?>
            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="edit-field">
          <label>Sex</label>
          <select id="print_sex" class="edit-input">
            <option value="all">All</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
              <div class="edit-field">
  <label>Age From</label>
  <input type="number" id="print_age_from" class="edit-input" min="60" placeholder="60"
         oninput="checkPrintAgeError()" onblur="enforcePrintAgeMin(this)">
</div>
<div class="edit-field">
  <label>Age To</label>
  <input type="number" id="print_age_to" class="edit-input" min="60" placeholder="All"
         oninput="checkPrintAgeError()" onblur="enforcePrintAgeMin(this)">
  <span id="printAgeError" style="display:none;font-size:.72rem;color:#ba1a1a;margin-top:4px">
    Age From must be 60 or higher, and Age To cannot be less than Age From.
  </span>
</div>
        <div class="edit-field">
          <label>PWD</label>
          <select id="print_pwd" class="edit-input">
            <option value="all">All</option>
            <option value="Yes">PWD Only</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closePrintModal()">Cancel</button>
      <button class="btn btn-primary" onclick="generatePrintReport()">
        <span class="material-symbols-outlined text-lg">print</span> Generate & Print
      </button>
    </div>
  </div>
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

<!-- ══ EXPORT BACKUP MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="exportBackupModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3>Export Backup</h3>
      <button class="modal-close" onclick="closeExportBackupModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">
        This creates an encrypted, password-protected copy of the entire database.
        Save it to a USB flash drive and keep the password safe — without it, the
        backup cannot be opened by anyone, including you.
      </p>
      <div class="edit-field" style="margin-bottom:14px;">
        <label>Backup Password</label>
        <div class="pw-input-wrap">
          <input type="password" id="exportBackupPassword" class="edit-input" placeholder="At least 8 characters" minlength="8">
          <button type="button" class="pw-toggle-btn" onclick="toggleBackupPwVisibility('exportBackupPassword', this)">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
      </div>
      <div class="edit-field">
        <label>Confirm Password</label>
        <div class="pw-input-wrap">
          <input type="password" id="exportBackupPasswordConfirm" class="edit-input" placeholder="Re-enter password">
          <button type="button" class="pw-toggle-btn" onclick="toggleBackupPwVisibility('exportBackupPasswordConfirm', this)">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
        <div class="edit-field-hint" id="exportBackupHint"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeExportBackupModal()">Cancel</button>
      <button class="btn btn-primary" id="exportBackupSubmitBtn" onclick="submitExportBackup()">
        <span class="material-symbols-outlined">cloud_download</span> Export
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ══ IMPORT / RESTORE BACKUP MODAL — admin only ══ -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="importBackupModal" role="dialog" aria-modal="true" style="z-index:9999">
  <div class="modal modal-sm">
    <div class="modal-header danger">
      <h3>Import Backup (Restore)</h3>
      <button class="modal-close" onclick="closeImportBackupModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">
        <strong>Warning:</strong> restoring a backup replaces <u>all current data</u>
        with the contents of the backup file. This cannot be undone.
      </p>
      <div class="edit-field" style="margin-bottom:14px;">
        <label>Backup File (.oscabak)</label>
        <input type="file" id="importBackupFile" class="edit-input" accept=".oscabak">
      </div>
      <div class="edit-field" style="margin-bottom:14px;">
        <label>Backup Password</label>
        <div class="pw-input-wrap">
          <input type="password" id="importBackupPassword" class="edit-input" placeholder="Password used when the backup was created">
          <button type="button" class="pw-toggle-btn" onclick="toggleBackupPwVisibility('importBackupPassword', this)">
            <span class="material-symbols-outlined">visibility</span>
          </button>
        </div>
      </div>
      <div class="delete-confirm-wrap">
        <label class="delete-confirm-label">Type RESTORE to confirm</label>
        <input type="text" id="importBackupConfirmText" class="delete-confirm-input" placeholder="RESTORE" style="text-transform:uppercase" oninput="this.value = this.value.toUpperCase()">
        <div class="delete-confirm-hint" id="importBackupHint"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeImportBackupModal()">Cancel</button>
      <button class="btn btn-danger" id="importBackupSubmitBtn" onclick="submitImportBackup()">
        <span class="material-symbols-outlined">cloud_upload</span> Restore Database
      </button>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="toast" role="status" aria-live="polite"></div>

<!-- ══ NOTIFICATIONS MODAL ══ -->
<div class="modal-overlay" id="notifModal" role="dialog" aria-modal="true">
  <div class="modal modal-notif">
    <div class="modal-header">
      <h3>Notifications</h3>
      <button class="modal-close" onclick="closeNotifModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="notif-modal-body">
      <div class="notif-sidebar">
        <div class="notif-sidebar-item active" data-cat="all" onclick="setNotifCategory('all')">
          <span class="material-symbols-outlined">menu_book</span>
          <span>All Notifications</span>
          <span class="notif-sidebar-count" id="notifCountAll">0</span>
        </div>
        <div class="notif-sidebar-item" data-cat="transaction" onclick="setNotifCategory('transaction')">
          <span class="material-symbols-outlined">sync_alt</span>
          <span>Transactions</span>
          <span class="notif-sidebar-count" id="notifCountTransaction">0</span>
        </div>
        <div class="notif-sidebar-divider">Labels</div>
        <div class="notif-label-item" data-label="unread" onclick="setNotifLabel('unread')">
          <span class="notif-label-dot" style="background:#3b82f6"></span>
          <span>Unread Notification</span>
        </div>
        <div class="notif-label-item active" data-label="all" onclick="setNotifLabel('all')">
          <span class="notif-label-dot" style="background:#e0a458"></span>
          <span>All Labels</span>
        </div>
        <div class="notif-sidebar-divider">Filter by Time</div>
<div style="padding:2px 20px 10px;display:flex;flex-direction:column;gap:6px">
  <div class="filter-select-wrap" style="width:100%">
    <span class="material-symbols-outlined filter-select-icon">event</span>
    <select id="notifTimeRangeSelect" onchange="setNotifTimeRange(this.value)" class="filter-select-el" style="width:100%">
      <option value="all">All</option>
      <option value="today">Today</option>
      <option value="week">This Week</option>
      <option value="month">This Month</option>
      <option value="custom">Custom Range</option>
    </select>
    <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
  </div>
  <div id="notifCustomRangeWrap" style="display:none;flex-direction:column;gap:6px">
    <input type="date" id="notifDateFrom" onchange="applyNotifDateFilter()"
           style="border:1px solid #95a5a6;border-radius:0.4rem;padding:6px 8px;font-size:.78rem;font-family:'JetBrains Mono',monospace;color:#43474c">
    <input type="date" id="notifDateTo" onchange="applyNotifDateFilter()"
           style="border:1px solid #95a5a6;border-radius:0.4rem;padding:6px 8px;font-size:.78rem;font-family:'JetBrains Mono',monospace;color:#43474c">
  </div>
  <button type="button" id="notifDateClearBtn" onclick="clearNotifDateFilter()"
          style="display:none;background:none;border:none;color:#74777d;font-size:.72rem;cursor:pointer;text-align:left;padding:0;text-decoration:underline">
    Clear date filter
  </button>
</div>
      </div>
      <div class="notif-main">
        <div class="notif-search-bar">
          <span class="material-symbols-outlined">search</span>
          <input type="text" id="notifSearchInput" placeholder="Search notification" oninput="filterNotifModal()">
          <button class="btn btn-danger" style="padding:6px 12px;font-size:.78rem;white-space:nowrap" onclick="deleteAllNotifications()">
  <span class="material-symbols-outlined" style="font-size:16px">delete_sweep</span> Delete All
</button>
        </div>
        <div class="notif-list-scroll" id="notifModalList">
          <div class="notif-list-empty">Loading…</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- == DELETE ALL NOTIFICATIONS CONFIRM MODAL == -->
<div class="modal-overlay" id="deleteAllNotifModal" role="dialog" aria-modal="true">
  <div class="modal modal-sm">
    <div class="modal-header danger">
      <h3>Delete All Notifications</h3>
      <button class="modal-close" onclick="closeDeleteAllNotifModal()" aria-label="Close">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div class="modal-body">
      <p class="delete-msg">
        This will permanently delete <strong>all</strong> notifications from the history. This action cannot be undone.
      </p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" onclick="closeDeleteAllNotifModal()">Cancel</button>
      <button class="btn btn-danger" id="confirmDeleteAllNotifBtn" onclick="executeDeleteAllNotifications()">
        <span class="material-symbols-outlined" style="font-size:18px">delete_sweep</span> Delete All
      </button>
    </div>
  </div>
</div>

<!-- ══ SETTINGS MODAL ══ -->
<div class="modal-overlay" id="settingsModal" role="dialog" aria-modal="true">
  <div class="modal modal-edit">
    <div class="modal-header">
      <h3>Settings</h3>
      <button class="modal-close" onclick="closeSettingsModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
    </div>
    <div class="modal-body" id="settingsModalBody">
      <div class="modal-loading">Loading…</div>
    </div>
  </div>
</div>

<script src="dashboard.js"></script>
<script src="stats_refresh.js"></script>
<script src="realtime.js"></script>
<script>
function applySexFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('sex', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function validateAgeInputs() {
  const fromEl = document.getElementById('ageFrom');
  const toEl   = document.getElementById('ageTo');
  const err    = document.getElementById('ageError');
  const wrap   = document.getElementById('ageFilterWrap');

  const fromRaw = fromEl ? fromEl.value : '';
  const toRaw   = toEl   ? toEl.value   : '';

  // Only validate the pair once BOTH are filled — a single filled field is not an error yet, just incomplete
  if (fromRaw === '' || toRaw === '') {
    if (wrap) wrap.style.borderColor = '#95a5a6';
    if (err && err.textContent !== 'From ≤ To' && err.style.display === 'inline') {
      // leave the "enter both" message alone if applyAgeRangeFilter just set it
    } else if (err) {
      err.style.display = 'none';
    }
    return true;
  }

  const from = parseInt(fromRaw);
  const to   = parseInt(toRaw);

  if (err) err.textContent = 'From ≤ To';

  if (to < from) {
    if (err)  err.style.display  = 'inline';
    if (wrap) wrap.style.borderColor = '#ba1a1a';
    return false;
  }

  if (err)  err.style.display  = 'none';
  if (wrap) wrap.style.borderColor = '#95a5a6';
  return true;
}

function enforceAgeMin(input) {
  if (input.value !== '' && parseInt(input.value) < 60) {
    input.value = 60;
  }
  validateAgeInputs();
}

function enforcePrintAgeMin(input) {
  if (input.value !== '' && parseInt(input.value) < 60) {
    input.value = 60;
  }
  checkPrintAgeError();
}

function checkPrintAgeError() {
  const fromEl = document.getElementById('print_age_from');
  const toEl   = document.getElementById('print_age_to');
  const errEl  = document.getElementById('printAgeError');
  if (!fromEl || !toEl || !errEl) return true;

  const from = fromEl.value !== '' ? parseInt(fromEl.value) : null;
  const to   = toEl.value   !== '' ? parseInt(toEl.value)   : null;

  if (from !== null && to !== null && to < from) {
    errEl.style.display = 'block';
    fromEl.style.borderColor = '#ba1a1a';
    toEl.style.borderColor   = '#ba1a1a';
    return false;
  }

  errEl.style.display = 'none';
  fromEl.style.borderColor = '';
  toEl.style.borderColor   = '';
  return true;
}

function openPrintModal() {
  const menu = document.getElementById('settingsPopupMenu');
  if (menu) menu.classList.remove('open');
  openModal('printModal');
}
function closePrintModal() {
  document.getElementById('printModal').classList.remove('open');
  document.body.style.overflow = '';
}
function generatePrintReport() {
  const fromEl = document.getElementById('print_age_from');
  const toEl   = document.getElementById('print_age_to');

  enforcePrintAgeMin(fromEl);
  enforcePrintAgeMin(toEl);

  if (!checkPrintAgeError()) {
    toast('Please fix the age range before generating the report.', 'error');
    return;
  }

  const barangay = document.getElementById('print_barangay').value;
  const sex       = document.getElementById('print_sex').value;
  const pwd       = document.getElementById('print_pwd').value;

  let from = fromEl.value !== '' ? parseInt(fromEl.value) : (toEl.value !== '' ? 60 : null);
  let to   = toEl.value   !== '' ? parseInt(toEl.value)   : null;

  let age = 'all';
  if (from !== null) age = to !== null ? `${from}-${to}` : `${from}-`;

  const params = new URLSearchParams({ filter: barangay, sex, pwd, age });
  window.open('print_report.php?' + params.toString(), '_blank');
  closePrintModal();
}
let _ageFilterTimer = null;
function debounceAgeFilter() {
  clearTimeout(_ageFilterTimer);
  _ageFilterTimer = setTimeout(() => {
    applyAgeRangeFilter();
  }, 2000); // gives you more time to finish typing both digits before it navigates
}
function applyAgeRangeFilter() {
  const fromEl = document.getElementById('ageFrom');
  const toEl   = document.getElementById('ageTo');
  const err    = document.getElementById('ageError');

  const fromRaw = fromEl ? fromEl.value : '';
  const toRaw   = toEl   ? toEl.value   : '';

  // Require BOTH fields before applying the filter
  if (fromRaw === '' || toRaw === '') {
    if (err) { err.textContent = 'Enter both From and To'; err.style.display = 'inline'; }
    return;
  }

  let from = parseInt(fromRaw);
  let to   = parseInt(toRaw);

  if (from < 60) { from = 60; fromEl.value = 60; }
  if (to   < 60) { to   = 60; toEl.value   = 60; }

  if (!validateAgeInputs()) return;

  const url = new URL(window.location.href);
  url.searchParams.set('age', `${from}-${to}`);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}

function clearAgeFilter() {
  const url = new URL(window.location.href);
  url.searchParams.delete('age');
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function applyPwdFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('pwd', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function applyLimit(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('limit', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function applyBarangayFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('filter', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
document.addEventListener('change', function(e) {
  if (e.target && e.target.id === 'limitSelectTop') {
    const bot = document.getElementById('limitSelect');
    if (bot) bot.value = e.target.value;
  }
  if (e.target && e.target.id === 'limitSelect') {
    const top = document.getElementById('limitSelectTop');
    if (top) top.value = e.target.value;
  }
});

// ── Archive modal logic ──────────────────────────────────────
window._archiveSelectedReason = '';

function selectReasonChip(el, reason) {
  const chips = document.querySelectorAll('#reasonChips .reason-chip');
  const wasActive = el.classList.contains('active');
  chips.forEach(c => c.classList.remove('active'));

  const ta  = document.getElementById('archiveReasonInput');
  const btn = document.getElementById('confirmArchiveBtn');

  if (!wasActive) {
    el.classList.add('active');
    window._archiveSelectedReason = reason;
    if (ta) {
      ta.dataset.chipValue = reason;
      if (!ta.value.trim() || ta.dataset.fromChip === '1') {
        ta.value = reason;
        ta.dataset.fromChip = '1';
      }
    }
  } else {
    window._archiveSelectedReason = '';
    if (ta && ta.dataset.fromChip === '1') {
      ta.value = '';
      ta.dataset.fromChip = '0';
    }
  }

  updateArchiveCharCount();
  if (btn) btn.disabled = !(ta && ta.value.trim().length > 0);
}

function onArchiveTextareaInput() {
  const ta = document.getElementById('archiveReasonInput');
  if (!ta) return;
  if (ta.dataset.fromChip !== '1') {
    window._archiveSelectedReason = '';
    document.querySelectorAll('#reasonChips .reason-chip').forEach(c => c.classList.remove('active'));
  }
  ta.dataset.fromChip = '0';
  updateArchiveCharCount();
}

function updateArchiveCharCount() {
  const ta      = document.getElementById('archiveReasonInput');
  const counter = document.getElementById('archiveCharCount');
  const btn     = document.getElementById('confirmArchiveBtn');
  if (counter && ta) counter.textContent = ta.value.length + ' / 1000';
  if (btn && ta) btn.disabled = ta.value.trim().length === 0;
}

function validateArchiveReason() {
  const ta  = document.getElementById('archiveReasonInput');
  const btn = document.getElementById('confirmArchiveBtn');
  if (btn && ta) btn.disabled = ta.value.trim().length === 0;
}

function getArchiveReason() {
  const typed = (document.getElementById('archiveReasonInput')?.value || '').trim();
  if (window._archiveSelectedReason && typed) {
    return window._archiveSelectedReason + ': ' + typed;
  }
  return window._archiveSelectedReason || typed;
}

function resetArchiveModal() {
  window._archiveSelectedReason = '';
  document.querySelectorAll('#reasonChips .reason-chip').forEach(c => c.classList.remove('active'));
  const ta = document.getElementById('archiveReasonInput');
  if (ta) { ta.value = ''; ta.dataset.fromChip = '0'; }
  const counter = document.getElementById('archiveCharCount');
  if (counter) counter.textContent = '0 / 1000';
  const btn = document.getElementById('confirmArchiveBtn');
  if (btn) btn.disabled = true;
  const errEl = document.getElementById('archiveReasonError');
  if (errEl) errEl.textContent = '';
}

document.addEventListener('DOMContentLoaded', function () {
  const origClose = window.closeArchiveModal;
  window.closeArchiveModal = function () {
    resetArchiveModal();
    if (typeof origClose === 'function') origClose();
  };
});

// ── Backup & Restore ──────────────────────────────────────────
function toggleBackupPwVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const icon = btn.querySelector('.material-symbols-outlined');
  if (input.type === 'password') { input.type = 'text'; icon.textContent = 'visibility_off'; }
  else { input.type = 'password'; icon.textContent = 'visibility'; }
}

function openExportBackupModal() {
  const menu = document.getElementById('settingsPopupMenu');
  if (menu) menu.classList.remove('open');
  document.getElementById('exportBackupPassword').value = '';
  document.getElementById('exportBackupPasswordConfirm').value = '';
  document.getElementById('exportBackupHint').textContent = '';
  openModal('exportBackupModal');
}
function closeExportBackupModal() {
  document.getElementById('exportBackupModal').classList.remove('open');
  document.body.style.overflow = '';
}

async function submitExportBackup() {
  const pw = document.getElementById('exportBackupPassword').value;
  const pwConfirm = document.getElementById('exportBackupPasswordConfirm').value;
  const hint = document.getElementById('exportBackupHint');
  const btn = document.getElementById('exportBackupSubmitBtn');

  if (pw.length < 8) { hint.textContent = 'Password must be at least 8 characters.'; hint.className = 'edit-field-hint hint-error'; return; }
  if (pw !== pwConfirm) { hint.textContent = 'Passwords do not match.'; hint.className = 'edit-field-hint hint-error'; return; }

  btn.disabled = true;
  btn.textContent = 'Exporting...';

  try {
    const formData = new FormData();
    formData.append('password', pw);
    formData.append('confirm_password', pwConfirm);

    const res = await fetch('export_backup.php', { method: 'POST', body: formData });
    if (!res.ok) {
      const errJson = await res.json().catch(() => null);
      throw new Error(errJson?.message || 'Export failed.');
    }

    const blob = await res.blob();
    const filename = res.headers.get('X-OSCA-Filename') || ('osca_backup_' + Date.now() + '.oscabak');
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);

    toast('Backup exported successfully. Save it somewhere safe!', 'success');
    closeExportBackupModal();
  } catch (err) {
    toast(err.message || 'Backup export failed.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined">cloud_download</span> Export';
  }
}

function openImportBackupModal() {
  const menu = document.getElementById('settingsPopupMenu');
  if (menu) menu.classList.remove('open');
  document.getElementById('importBackupFile').value = '';
  document.getElementById('importBackupPassword').value = '';
  document.getElementById('importBackupConfirmText').value = '';
  document.getElementById('importBackupHint').textContent = '';
  openModal('importBackupModal');
}
function closeImportBackupModal() {
  document.getElementById('importBackupModal').classList.remove('open');
  document.body.style.overflow = '';
}

async function submitImportBackup() {
  const fileInput = document.getElementById('importBackupFile');
  const pw = document.getElementById('importBackupPassword').value;
  const confirmText = document.getElementById('importBackupConfirmText').value.trim();
  const hint = document.getElementById('importBackupHint');
  const btn = document.getElementById('importBackupSubmitBtn');

  if (!fileInput.files.length) { hint.textContent = 'Please choose a backup file.'; hint.className = 'delete-confirm-hint hint-error'; return; }
  if (!pw) { hint.textContent = 'Please enter the backup password.'; hint.className = 'delete-confirm-hint hint-error'; return; }
  if (confirmText !== 'RESTORE') { hint.textContent = 'Please type RESTORE exactly to confirm.'; hint.className = 'delete-confirm-hint hint-error'; return; }

  btn.disabled = true;
  btn.textContent = 'Restoring...';

  try {
    const formData = new FormData();
    formData.append('backup_file', fileInput.files[0]);
    formData.append('password', pw);
    formData.append('confirm_text', confirmText);

    const res = await fetch('import_backup.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Restore failed.');

    toast(data.message || 'Database restored successfully.', 'success');
    closeImportBackupModal();
    setTimeout(() => location.reload(), 1200);
  } catch (err) {
    toast(err.message || 'Restore failed.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined">cloud_upload</span> Restore Database';
  }
}
</script>
