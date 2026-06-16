<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

// ── Stats ─────────────────────────────────────────────────────
$total = $pdo->query("SELECT COUNT(*) FROM applicants")->fetchColumn();
$today = $pdo->query("SELECT COUNT(*) FROM applicants WHERE DATE(created_at)=CURDATE()")->fetchColumn();

$activeFilter = $_GET['filter'] ?? 'all';
if ($activeFilter !== 'all' && $activeFilter !== '') {
    $brgyStmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE barangay = ?");
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
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = (int)($_GET['limit'] ?? 10);
if (!in_array($limit, [10, 25, 50, 100, 0])) $limit = 10;
$offset = ($limit > 0) ? ($page - 1) * $limit : 0;
$search = mb_strtoupper(trim($_GET['search'] ?? ''), 'UTF-8');
$filter = $_GET['filter'] ?? 'all';
$sexFilter = $_GET['sex'] ?? 'all';

$where  = "WHERE 1=1";
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
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@500;600;700;800&family=JetBrains+Mono:wght@500&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dashboard.css">
  <script>
  tailwind.config = {
    darkMode: 'class',
    theme: {
      extend: {
        colors: {
          'primary':                  '#1d3246',
          'primary-container':        '#34495e',
          'on-primary':               '#ffffff',
          'on-primary-container':     '#a2b8d1',
          'secondary':                '#526162',
          'surface':                  '#fbf9fb',
          'surface-container':        '#efedef',
          'surface-container-low':    '#f5f3f5',
          'surface-container-high':   '#e9e8e9',
          'surface-container-lowest': '#ffffff',
          'on-surface':               '#1b1c1d',
          'on-surface-variant':       '#43474c',
          'outline':                  '#74777d',
          'outline-variant':          '#95a5a6',
          'error':                    '#ba1a1a',
          'error-container':          '#ffdad6',
          'surface-tint':             '#4b6076',
        },
        fontFamily: {
          body:    ['Inter','sans-serif'],
          display: ['Hanken Grotesk','sans-serif'],
          mono:    ['JetBrains Mono','monospace'],
        },
      }
    }
  }
  </script>
</head>
<body class="bg-[#ECF0F1] font-body text-on-surface min-h-screen overflow-hidden">

<!-- ── SIDEBAR ── -->
<aside class="fixed left-0 top-0 h-screen w-64 bg-surface border-r flex flex-col justify-between py-6 z-50" style="border-right:1px solid rgba(149,165,166,.30)">
  <div>
    <!-- Brand -->
    <div class="px-6 mb-8">
      <div class="flex items-center gap-3">
        <!-- Himamaylan City Logo -->
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
    </nav>
  </div>
  <!-- Footer nav -->
  <nav class="space-y-1">
    <a href="#" class="flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors">
      <span class="material-symbols-outlined">settings</span>
      <span class="text-sm">Settings</span>
    </a>
    <button onclick="openLogoutModal()" class="w-full flex items-center gap-4 px-6 py-3 text-on-surface-variant hover:bg-surface-container-low transition-colors text-left">
      <span class="material-symbols-outlined">logout</span>
      <span class="text-sm">Logout</span>
    </button>
  </nav>
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
      <button class="w-9 h-9 flex items-center justify-center text-on-surface-variant hover:text-primary rounded-lg hover:bg-surface-container transition-colors">
        <span class="material-symbols-outlined">notifications</span>
      </button>
      <div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center">
        <span class="text-on-primary text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'],0,1)) ?></span>
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

    <!-- ── TABLE SECTION ── -->
    <div class="bg-surface-container-lowest rounded-lg overflow-hidden" style="border:1px solid rgba(149,165,166,.30)">

      <!-- Table Header -->
      <div class="flex items-center justify-between px-5 py-4 gap-4" style="border-bottom:1px solid rgba(149,165,166,.30)">
        <h3 class="font-display font-bold text-base text-primary whitespace-nowrap">Registrant's Records</h3>
        <div class="flex items-center gap-3 flex-wrap">

          <!-- Search -->
          <div class="search-wrap relative flex items-center">
            <span class="absolute left-3 text-outline pointer-events-none">
              <span class="material-symbols-outlined text-[18px]">search</span>
            </span>
            <input type="text" id="searchInput" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search records..."
                   autocomplete="off" oninput="handleIncrementalSearch(this.value)"
                   class="text-sm bg-surface-container-lowest w-52 transition focus:outline-none" style="border:1px solid #95a5a6; border-radius:0.5rem; padding:7px 32px 7px 36px; height:36px;" onfocus="this.style.borderColor='#1d3246';this.style.boxShadow='0 0 0 2px rgba(29,50,70,.20)'" onblur="this.style.borderColor='#95a5a6';this.style.boxShadow='none'">
            <span class="search-spinner absolute right-8 hidden"></span>
            <button id="searchClear" onclick="clearSearch()" title="Clear"
                    class="absolute right-2 text-outline hover:text-primary <?= $search ? '' : 'hidden' ?>">
              <span class="material-symbols-outlined text-[16px]">close</span>
            </button>
          </div>

          <!-- Barangay filter -->
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

          <!-- Sex filter -->
          <div class="filter-select-wrap">
            <span class="material-symbols-outlined filter-select-icon">wc</span>
            <select id="sexFilter" onchange="applySexFilter(this.value)" class="filter-select-el">
              <option value="all" <?= $sexFilter==='all'?'selected':'' ?>>All Sex</option>
              <option value="Male"   <?= $sexFilter==='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= $sexFilter==='Female'?'selected':'' ?>>Female</option>
            </select>
            <span class="material-symbols-outlined filter-select-arrow">expand_more</span>
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

        </div><!-- /controls -->
      </div>

      <?php if (empty($applicants)): ?>
      <!-- Empty State -->
      <div class="flex flex-col items-center justify-center py-20 text-center">
        <span class="material-symbols-outlined text-6xl text-outline-variant mb-3">manage_search</span>
        <h3 class="font-display font-bold text-primary text-base mb-1">No records found</h3>
        <p class="text-sm text-on-surface-variant mb-4">
          <?= $search ? "No applicants match \"".htmlspecialchars($search)."\"." : "No applicants registered yet." ?>
        </p>
        <a href="registration.php" class="flex items-center gap-2 bg-primary text-white text-sm font-semibold px-4 py-2.5 rounded-lg hover:bg-primary-container transition-all">
          <span class="material-symbols-outlined text-lg">add</span>Add First Record
        </a>
      </div>
      <?php else: ?>

      <!-- Table -->
      <div class="table-wrap overflow-x-auto">
        <table class="w-full text-sm border-collapse data-table">
          <thead>
            <tr class="border-b" style="border-bottom:1px solid rgba(149,165,166,.30)">
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">#</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Full Name</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Sex</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Birthdate</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Age</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">OSCA ID</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Barangay</th>
              <th class="px-5 py-3 text-left text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Registered</th>
              <th class="px-5 py-3 text-center text-[11px] font-mono uppercase tracking-wider text-on-surface-variant font-medium">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($applicants as $i => $r):
              $suffix = (!empty($r['suffixApplicant']) && $r['suffixApplicant'] !== 'N/A') ? $r['suffixApplicant'] : '';
              $fullName = htmlspecialchars($r['lastnameApplicant'].', '.$r['firstnameApplicant'].' '.$r['middlenameApplicant']);
              $jsName   = addslashes($r['lastnameApplicant'].', '.$r['firstnameApplicant']);
              $age = '—';
              if ($r['month'] && $r['date'] && $r['year']) {
                $dob = DateTime::createFromFormat('F j Y', $r['month'].' '.$r['date'].' '.$r['year']);
                if ($dob) $age = $dob->diff(new DateTime())->y;
              }
              $isToday = date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d');
            ?>
            <tr class="table-row hover:bg-surface-container-low transition-colors <?= $isToday ? 'bg-primary/[0.03]' : '' ?>"
                data-id="<?= $r['id'] ?>">
              <td class="td-id px-5 py-3.5 text-on-surface-variant text-xs"><?= ($limit>0?$offset:0)+$i+1 ?></td>
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center flex-shrink-0">
                    <span class="text-on-primary-container text-xs font-bold font-mono"><?= strtoupper(substr($r['lastnameApplicant'],0,1)) ?></span>
                  </div>
                  <div>
                    <div class="font-semibold text-on-surface text-sm leading-tight">
                      <?= $fullName ?><?php if($suffix): ?> <?= htmlspecialchars($suffix) ?><?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 text-on-surface-variant"><?= htmlspecialchars($r['sex']??'—') ?></td>
              <td class="px-5 py-3.5 text-on-surface-variant"><?= $r['month']&&$r['date']&&$r['year'] ? htmlspecialchars("{$r['month']} {$r['date']}, {$r['year']}") : '—' ?></td>
              <td class="px-5 py-3.5 text-on-surface-variant"><?= $age ?></td>
              <td class="px-5 py-3.5 font-mono text-xs text-on-surface-variant"><?= htmlspecialchars($r['osca_ID']??'—') ?></td>
              <td class="px-5 py-3.5 text-on-surface-variant"><?= htmlspecialchars($r['barangay']??'—') ?></td>
              <td class="px-5 py-3.5 <?= $isToday ? 'font-bold text-primary' : 'text-on-surface-variant' ?>">
                <?= $isToday ? 'Today' : date('M j, Y', strtotime($r['created_at'])) ?>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center justify-center gap-2">
                  <button onclick="viewRecord(<?= $r['id'] ?>)" title="View"
                          class="w-8 h-8 flex items-center justify-center text-primary hover:bg-primary/10 rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-xl">visibility</span>
                  </button>
                  <button onclick="editRecord(<?= $r['id'] ?>)" title="Edit"
                          class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-xl">edit_square</span>
                  </button>
                  <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= $jsName ?>')" title="Delete"
                          class="w-8 h-8 flex items-center justify-center text-error hover:bg-error-container rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-xl">delete_forever</span>
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div class="px-5 py-3 bg-[#f5f3f5] flex items-center justify-between gap-4 text-xs flex-wrap" style="border-top:1px solid rgba(149,165,166,.30)">

        <!-- Left: Show select + entries info -->
        <div class="flex items-center gap-3 text-on-surface-variant flex-wrap">
          <div class="flex items-center gap-2">
            <span>Show</span>
            <div class="relative">
              <select id="limitSelect" onchange="applyLimit(this.value)"
                      class="appearance-none rounded-md pl-2 pr-6 bg-surface-container-lowest text-xs text-on-surface focus:outline-none cursor-pointer font-mono" style="border:1px solid #95a5a6; height:36px;">
                <option value="10"  <?= $limit===10?'selected':'' ?>>10</option>
                <option value="25"  <?= $limit===25?'selected':'' ?>>25</option>
                <option value="50"  <?= $limit===50?'selected':'' ?>>50</option>
                <option value="100" <?= $limit===100?'selected':'' ?>>100</option>
                <option value="0"   <?= $limit===0?'selected':'' ?>>All</option>
              </select>
              <span class="material-symbols-outlined absolute right-1 top-1/2 -translate-y-1/2 text-outline pointer-events-none" style="font-size:14px">expand_more</span>
            </div>
            <span>Results per page</span>
          </div>
          <div class="w-px h-4 bg-outline-variant"></div>
          <?php if ($limit > 0): ?>
          <span>Showing <strong class="text-on-surface"><?= $offset+1 ?></strong> to <strong class="text-on-surface"><?= min($offset+$limit,$filteredTotal) ?></strong> of <strong class="text-on-surface"><?= $filteredTotal ?></strong> entries</span>
          <?php else: ?>
          <span>Showing all <strong class="text-on-surface"><?= $filteredTotal ?></strong> records</span>
          <?php endif; ?>
        </div>

        <!-- Right: Previous · pages · Next -->
        <div class="flex items-center gap-1">
          <?php if ($page > 1): ?>
          <a href="?page=<?=$page-1?>&filter=<?=urlencode($filter)?>&search=<?=urlencode($search)?>&limit=<?=$limit?>&sex=<?=urlencode($sexFilter)?>"
             class="px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-md transition-colors font-semibold">Previous</a>
          <?php else: ?>
          <span class="px-3 py-1.5 text-outline/40 font-semibold cursor-not-allowed select-none">Previous</span>
          <?php endif; ?>

          <?php if ($limit > 0 && $totalPages > 1): ?>
          <div class="flex items-center gap-1 mx-1">
            <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
            <a href="?page=<?=$p?>&filter=<?=urlencode($filter)?>&search=<?=urlencode($search)?>&limit=<?=$limit?>&sex=<?=urlencode($sexFilter)?>"
               class="w-8 h-8 flex items-center justify-center rounded-md text-xs font-bold transition-colors
                      <?= $p===$page
                        ? 'bg-primary text-white'
                        : 'text-on-surface hover:bg-[#efedef]' ?>">
              <?= $p ?>
            </a>
            <?php endfor; ?>
          </div>
          <?php endif; ?>

          <?php if ($limit > 0 && $page < $totalPages): ?>
          <a href="?page=<?=$page+1?>&filter=<?=urlencode($filter)?>&search=<?=urlencode($search)?>&limit=<?=$limit?>&sex=<?=urlencode($sexFilter)?>"
             class="px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-md transition-colors font-semibold">Next</a>
          <?php else: ?>
          <span class="px-3 py-1.5 text-outline/40 font-semibold cursor-not-allowed select-none">Next</span>
          <?php endif; ?>
        </div>

      </div>

      <?php endif; ?>
    </div><!-- /table card -->

  </main>
</div><!-- /ml-64 -->

<!-- Watermark -->
<div class="fixed bottom-4 right-4 pointer-events-none opacity-5 z-0">
  <img src="HimCity_Logo_nobg.png" alt="" class="w-32 h-32 object-contain">
</div>

<!-- ══ VIEW MODAL ══ -->
<div class="modal-overlay" id="viewModal" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modalTitle">Applicant Details</h3>
      <button class="modal-close" onclick="closeModal()" aria-label="Close">
        <span class="material-symbols-outlined text-lg">close</span>
      </button>
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
      <button class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
      <button class="btn btn-primary" id="editNextBtn" onclick="editSaveAndGoToStep(2)" style="display:none">Next →</button>
      <button class="btn btn-primary" id="saveEditBtn" onclick="saveEdit()">
        <span class="material-symbols-outlined text-lg">save</span> Save Changes
      </button>
    </div>
  </div>
</div>

<!-- ══ DELETE MODAL ══ -->
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

<div id="toast" role="status" aria-live="polite"></div>

<script src="dashboard.js"></script>
<script>
function applySexFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('sex', val);
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
document.addEventListener('DOMContentLoaded', function() {
  const top = document.getElementById('limitSelectTop');
  const bot = document.getElementById('limitSelect');
  if (top && bot) {
    top.addEventListener('change', () => { bot.value = top.value; });
    bot.addEventListener('change', () => { top.value = bot.value; });
  }
});
</script>
</body>
</html>