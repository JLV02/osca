<?php
session_start();
unset($_SESSION['applicant_id']);
$currentDisplayName = $_SESSION['display_name'] ?? ($_SESSION['admin_username'] ?? 'Staff');
$currentRole = $_SESSION['admin_role'] ?? 'encoder';
$isAdmin     = ($currentRole === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#1d3246">
<title>New Senior Record — OSCA Registry</title>
<link rel="stylesheet" href="assets/css/fonts.css">
<link rel="stylesheet" href="assets/css/tailwind.css">
<link rel="stylesheet" href="dashboard.css">
<style>
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
  .material-symbols-outlined {
    font-family: 'Material Symbols Outlined';
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    display: inline-block; vertical-align: middle; line-height: 1;
  }
  .input-focus { transition: border-color .15s, box-shadow .15s; border: 1px solid #95a5a6 !important; border-radius: 0.375rem; }
  .input-focus:focus {
    outline: none;
    border-color: #1d3246 !important;
    box-shadow: 0 0 0 2px rgba(29,50,70,.20);
  }
  .input-focus.error { border-color: #ba1a1a !important; }
  .err-msg { display: none; font-size: .72rem; color: #ba1a1a; margin-top: 2px; }
  .input-focus.error ~ .err-msg { display: block; }
  /* step panels */
  .form-step { display: none; }
  .form-step.active { display: block; }
  /* section dividers */
  .sub-label {
    font-family: 'Hanken Grotesk', sans-serif;
    font-weight: 700; font-size: .9rem;
    color: #1d3246;
    padding-bottom: 6px;
    border-bottom: 2px solid rgba(149,165,166,.40);
    margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
  }
  .sub-label span.num {
    font-size: .8rem; font-weight: 800;
    color: #1d3246; min-width: 18px;
  }
  /* family rows */
  .family-row {
    background: #f5f3f5;
    border: 1px solid rgba(149,165,166,.30);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 10px;
  }
  .family-row-label {
    font-size: .7rem; font-weight: 700; font-family: 'JetBrains Mono', monospace;
    text-transform: uppercase; letter-spacing: .06em;
    color: #526162; margin-bottom: 10px;
  }
  /* toast */
  #toast {
    position: fixed; bottom: 20px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    padding: 11px 20px; border-radius: 8px;
    font-size: .875rem; font-weight: 600; color: #fff;
    box-shadow: 0 6px 24px rgba(0,0,0,.2);
    opacity: 0; transition: all .3s; z-index: 9999;
    max-width: calc(100vw - 32px); text-align: center;
    pointer-events: none;
  }
  #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
  #toast.success { background: #2e7d32; }
  #toast.error   { background: #ba1a1a; }
  /* success screen */
  #success-screen { display: none; }
  /* select arrow */
  select.input-focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2374777d' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
    -webkit-appearance: none; appearance: none;
  }
  /* age display */
  #ageDisplay { min-height: 20px; font-size: .72rem; font-family: JetBrains Mono,monospace; margin-top: 4px; }
  /* btn spinner */
  @keyframes spin { to { transform: rotate(360deg); } }
  .btn-spin { display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation: spin .7s linear infinite; flex-shrink:0; } .loading .btn-spin { display:inline-block; }
  .loading .btn-spin { display:block; }
  /* sidebar active */
  .nav-active { background: #efedef; color: #1d3246; font-weight: 700; border-right: 3px solid #1d3246; }

  /* ── Custom Radio Buttons ── */
  .radio-box {
    width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid #95a5a6; background: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: border-color .15s, background .15s; cursor: pointer;
  }
  .radio-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #fff; transform: scale(0); transition: transform .2s;
  }
  .radio-box.checked { background: #1d3246; border-color: #1d3246; }
  .radio-box.checked .radio-dot { transform: scale(1); }

  /* ── Custom Checkboxes ── */
  .chk-box {
    width: 18px; height: 18px; border-radius: 4px;
    border: 2px solid #95a5a6; background: #fff;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: background .15s, border-color .15s; cursor: pointer;
  }
  .chk-icon { opacity: 0; transform: scale(.5); transition: opacity .15s, transform .2s; }
  .chk-box.checked { background: #1d3246; border-color: #1d3246; }
  .chk-box.checked .chk-icon { opacity: 1; transform: scale(1); }

  /* ── Checkbox option card hover ── */
  .checkbox-option:hover { border-color: #1d3246 !important; background: #efedef !important; }
  .checkbox-option.selected { border-color: #1d3246 !important; background: rgba(29,50,70,.06) !important; }
  /* custom NA checkbox */
  .na-box {
    width: 15px; height: 15px; border: 1px solid #95a5a6; border-radius: 3px;
    background: #fff; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: background .15s, border-color .15s; cursor: pointer;
  }
  .na-row.checked .na-box { background: #1d3246; border-color: #1d3246; }
  .na-check { opacity: 0; transform: scale(.5); transition: opacity .15s, transform .2s; }
  .na-row.checked .na-check { opacity: 1; transform: scale(1); }

  /* ── Barangay searchable combobox ── */
  #barangayDropdown {
    padding: 4px 0;
  }
  .barangay-option {
    padding: 9px 14px;
    font-size: .8125rem;
    color: #28323a;
    cursor: pointer;
    transition: background .1s;
  }
  .barangay-option:hover,
  .barangay-option.active {
    background: #efedef;
    color: #1d3246;
  }
  .barangay-option.selected {
    background: rgba(29,50,70,.07);
    font-weight: 600;
    color: #1d3246;
  }
  .barangay-empty {
    padding: 10px 14px;
    font-size: .75rem;
    color: #95a5a6;
    font-style: italic;
  }
</style>
</head>
<body class="bg-[#ECF0F1] font-body text-on-surface min-h-screen">

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
      <a href="registration.php" class="flex items-center gap-4 px-6 py-3 text-primary font-bold border-r-2 border-primary transition-colors">
        <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">app_registration</span>
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
  <div>
    <div class="px-6 py-3 mb-1" style="border-top:1px solid rgba(149,165,166,.20)">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
          <span class="text-white text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'S', 0, 1)) ?></span>
        </div>
        <div class="min-w-0 flex items-center gap-2">
          <p class="text-sm font-semibold text-on-surface truncate"><?= htmlspecialchars($currentDisplayName) ?></p>
          <span class="inline-block text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-primary/15 text-primary flex-shrink-0"><?= htmlspecialchars($_SESSION['admin_role'] ?? 'Staff') ?></span>
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
<div class="ml-64 flex flex-col min-h-screen">

  <!-- Top Bar -->
  <header class="sticky top-0 z-10 h-16 bg-surface flex items-center justify-between px-6" style="border-bottom:1px solid rgba(149,165,166,.30)">
    <h1 class="font-display font-bold text-2xl text-primary tracking-tight">New Senior Record</h1>
    <div class="flex items-center gap-3">
      <div class="w-px h-8 bg-outline-variant"></div>
      <div class="relative" id="profileWrap">
        <button onclick="toggleProfilePanel(event)" class="w-9 h-9 rounded-full flex items-center justify-center cursor-pointer" style="background:#1d3246">
          <span class="text-white text-sm font-bold font-mono"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'S', 0, 1)) ?></span>
        </button>
        <div class="profile-panel" id="profilePanel">
          <div class="profile-panel-header">
            <div class="profile-panel-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'S', 0, 1)) ?></div>
            <div>
              <div class="profile-panel-name"><?= htmlspecialchars($currentDisplayName) ?></div>
              <div class="profile-panel-role"><?= $isAdmin ? 'Administrator' : 'Encoder' ?></div>
            </div>
          </div>
          <div class="profile-panel-menu">
            <button class="profile-panel-item" onclick="closeProfilePanel();window.location.href='dashboard.php'">
              <span class="material-symbols-outlined">dashboard</span> Back to Dashboard
            </button>
            <button class="profile-panel-item danger" onclick="closeProfilePanel();openLogoutModal()">
              <span class="material-symbols-outlined">logout</span> Sign Out
            </button>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="flex-1 px-6 py-6 max-w-4xl w-full mx-auto">

    <!-- ── STEP PROGRESS ── -->
    <div class="flex items-center justify-center gap-2 mb-6">
      <!-- Step 1 -->
      <div class="flex flex-col items-center gap-1" id="sn1">
        <div class="w-9 h-9 rounded-full border-2 border-primary bg-primary text-white flex items-center justify-center text-sm font-bold reg-step-dot" id="dot1">1</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl1">Personal Info</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)" id="line1bar"><div class="h-full bg-success transition-all" id="line1fill" style="width:0"></div></div>
      <!-- Step 2 -->
      <div class="flex flex-col items-center gap-1" id="sn2">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot2">2</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl2">Family</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line2fill" style="width:0"></div></div>
      <!-- Step 3 -->
      <div class="flex flex-col items-center gap-1" id="sn3">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot3">3</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl3">Living</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line3fill" style="width:0"></div></div>
      <!-- Step 4 -->
      <div class="flex flex-col items-center gap-1" id="sn4">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot4">4</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl4">Education</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line4fill" style="width:0"></div></div>
      <!-- Step 5 -->
      <div class="flex flex-col items-center gap-1" id="sn5">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot5">5</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl5">Economic</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line5fill" style="width:0"></div></div>
      <!-- Step 6 -->
      <div class="flex flex-col items-center gap-1" id="sn6">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot6">6</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl6">Health</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line6fill" style="width:0"></div></div>
      <!-- Step 7 -->
      <div class="flex flex-col items-center gap-1" id="sn7">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold reg-step-dot" style="border-color:#95a5a6" id="dot7">7</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant" id="sl7">ID &amp; Photo</span>
      </div>
    </div>

    <!-- ════════════════════════════════════
         STEP 1
    ════════════════════════════════════ -->
    <div class="form-step active" id="step1">

      <!-- Card: Identifying Information -->
      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <!-- Card Header -->
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">badge</span>
            <h2 class="font-display font-semibold text-white text-sm">I. Identifying Information</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 1 of 7</span>
        </div>

        <div class="p-5 space-y-6">

          <!-- Notice -->
          <div class="flex gap-3 bg-amber-50 rounded-lg px-4 py-3" style="border:1px solid rgba(217,119,6,.30)">
            <span class="material-symbols-outlined text-amber-600 text-lg mt-0.5 shrink-0">warning</span>
            <p class="text-xs text-amber-800 leading-relaxed">
              <strong>Notice:</strong> Do not include special characters like * ! @ $ % ^ & etc. in your name entry.
              Extensions like <em>SR., JR.,</em> etc. must be selected separately from the dropdown below.
            </p>
          </div>

        <!-- 1. Full Name -->
<div>
  <div class="sub-label"><span class="num">1.</span> Full Name</div>
  <div class="grid grid-cols-4 gap-4 items-start">
    
    <!-- Lastname -->
    <div class="space-y-1 min-w-0">
      <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Last Name <span class="text-error">*</span></label>
      <input type="text" name="lastnameApplicant" id="lastnameApplicant"
             placeholder="E.G. DELA CRUZ" autocomplete="family-name" maxlength="50"
             oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
             class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
      <span class="err-msg">Last name is required</span>
    </div>

    <!-- Firstname -->
    <div class="space-y-1 min-w-0">
      <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">First Name <span class="text-error">*</span></label>
      <input type="text" name="firstnameApplicant" id="firstnameApplicant"
             placeholder="E.G. JUAN" autocomplete="given-name" maxlength="50"
             oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
             class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
      <span class="err-msg">First name is required</span>
    </div>

    <!-- Middlename -->
    <div class="space-y-1 min-w-0">
      <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Middle Name <span class="text-error">*</span></label>
      <input type="text" name="middlenameApplicant" id="middlenameApplicant"
             placeholder="E.G. SANTOS" autocomplete="additional-name" maxlength="50"
             oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
             class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
      <label class="flex items-center gap-2 cursor-pointer mt-1 na-row" id="middlenameNALabel">
        <span class="na-box" id="middlenameNABox">
          <svg class="na-check w-2.5 h-2" viewBox="0 0 12 10" fill="none">
            <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <input type="checkbox" id="middlenameNA" onchange="toggleMiddlenameNA(this)" style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
        <span class="text-[11px] text-on-surface-variant">No middle name (N/A)</span>
      </label>
      <span class="err-msg">Middle name is required</span>
    </div>

    <!-- Extension -->
    <div class="space-y-1 min-w-0">
      <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Extension</label>
      <select name="suffixApplicant" id="suffixApplicant"
              class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
        <option value="">Select Extension</option>
        <option>N/A</option><option>JR</option><option>SR</option>
        <option>I</option><option>II</option><option>III</option>
        <option>IV</option><option>V</option><option>VI</option>
      </select>
    </div>

  </div>
</div>

<hr style="border-color:rgba(149,165,166,.30)">
          <!-- 2. Address -->
          <div>
            <div class="sub-label"><span class="num">2.</span> Current Address</div>
            <div class="grid grid-cols-12 gap-4">
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Barangay <span class="text-error">*</span></label>
                <div class="relative">
                  <input type="text" name="barangay" id="barangay" autocomplete="off"
                         placeholder="Type to search barangay…"
                         oninput="filterBarangay(this)" onfocus="filterBarangay(this)"
                         onkeydown="barangayKeydown(event)" onblur="setTimeout(closeBarangayDropdown,150)"
                         class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem; padding-right:34px">
                  <span class="material-symbols-outlined" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#95a5a6; font-size:20px; pointer-events:none">search</span>
                  <div id="barangayDropdown" class="hidden absolute left-0 right-0 mt-1 bg-white rounded-lg overflow-y-auto z-30"
                       style="border:1px solid rgba(149,165,166,.4); max-height:200px; box-shadow:0 8px 24px rgba(0,0,0,.15)"></div>
                </div>
                <span class="err-msg">Barangay is required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Purok / Zone / Sitio <span class="text-error">*</span></label>
                <input type="text" name="purok" id="purok" placeholder="Purok / Zone"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <span class="err-msg">Purok / Zone is required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Street / House No.</label>
                <input type="text" name="street" id="street" placeholder="House No. / Street"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 3. Date of Birth -->
          <div>
            <div class="sub-label"><span class="num">3.</span> Date of Birth</div>
            <div class="grid grid-cols-12 gap-4">
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Month <span class="text-error">*</span></label>
                <select name="month" id="month" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Month</option>
                  <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                  <option><?= $m ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Day <span class="text-error">*</span></label>
                <select name="date" id="date" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Day</option>
                  <?php for($d=1;$d<=31;$d++) echo "<option>$d</option>"; ?>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Year <span class="text-error">*</span></label>
                <select name="year" id="year" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Year</option>
                  <?php for($y=date('Y')-60; $y>=1920; $y--) echo "<option>$y</option>"; ?>
                </select>
                <span class="err-msg">Required</span>
              </div>
            </div>
            <div id="ageDisplay"></div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 4. Personal Details -->
          <div>
            <div class="sub-label"><span class="num">4.</span> Personal Details</div>
            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Birthplace</label>
                <input type="text" name="birthplace" id="birthplace" placeholder="City / Municipality"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Marital Status <span class="text-error">*</span></label>
                <select name="maritalStatus" id="maritalStatus" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option>
                  <option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Sex <span class="text-error">*</span></label>
                <select name="sex" id="sex" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option><option>Male</option><option>Female</option>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Religion</label>
                <select name="religion" id="religion" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option>
                  <?php foreach(['Catholic','Islam','Iglesia ni Cristo','Evangelicals','Protestants','Seventh-day Adventist','Bible Baptist','Church','Aglipayan','UCCP',"Jehovah's Witnesses",'Others'] as $r): ?>
                  <option><?= htmlspecialchars($r) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 5. Contact Information -->
          <div>
            <div class="sub-label"><span class="num">5.</span> Contact Information</div>
            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Contact Number <span class="text-error">*</span></label>
                <input type="tel" name="contactNumber" id="contactNumber" placeholder="09XXXXXXXXX"
                       autocomplete="tel" inputmode="numeric" maxlength="11"
                       oninput="enforceContact(this)"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <span class="err-msg">Must start with 09, exactly 11 digits</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Email Address <span class="text-error">*</span></label>
                <input type="email" name="emailAddress" id="emailAddress" placeholder="email@example.com"
                       autocomplete="email" inputmode="email"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <label class="flex items-center gap-2 cursor-pointer mt-1 na-row" id="emailNALabel">
                  <span class="na-box" id="emailNABox">
                    <svg class="na-check w-2.5 h-2 " viewBox="0 0 12 10" fill="none">
                      <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                  </span>
                  <input type="checkbox" id="emailNA" onchange="toggleEmailNA(this)" style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                  <span class="text-[11px] text-on-surface-variant">No email address (N/A)</span>
                </label>
                <span class="err-msg">Required</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">FB Messenger</label>
                <input type="text" name="fbMessenger" id="fbMessenger" placeholder="Facebook name or link"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Ethnic Origin</label>
                <input type="text" name="ethnicOrigin" id="ethnicOrigin" placeholder="e.g. Cebuano, Ilocano"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Language Spoken</label>
                <input type="text" name="languageSpoken" id="languageSpoken" placeholder="e.g. Cebuano, Filipino"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 6. Government IDs -->
          <div>
            <div class="sub-label"><span class="num">6.</span> Government IDs</div>
            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">OSCA ID No.</label>
                <input type="text" name="osca_ID" id="osca_ID" placeholder="OSCA-XXXXXX" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">GSIS / SSS No.</label>
                <input type="text" name="gsis_sss_ID" id="gsis_sss_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">TIN No.</label>
                <input type="text" name="tin_ID" id="tin_ID" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">PhilHealth ID</label>
                <input type="text" name="philHealth_ID" id="philHealth_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Senior Citizens Assoc. ID</label>
                <input type="text" name="sc_asso_ID" id="sc_asso_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Other Govt. ID</label>
                <input type="text" name="other_govt_ID" id="other_govt_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 7. Other Information -->
          <div>
            <div class="sub-label"><span class="num">7.</span> Other Information</div>
            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Employment / Business</label>
                <input type="text" name="employment_business" id="employment_business" placeholder="Retired, Farmer, etc."
                       class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Receiving Pension?</label>
                <select name="hasPension" id="hasPension" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option><option>Yes</option><option>No</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Can Travel?</label>
                <select name="travelCapability" id="travelCapability" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option><option>Yes</option><option>No</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Person with Disability?</label>
                <select name="personWithDisability" id="personWithDisability" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                  <option value="">Select</option><option>Yes</option><option>No</option>
                </select>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Card: Registration Date -->
      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-5" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center gap-3">
          <span class="material-symbols-outlined text-white text-xl">calendar_month</span>
          <h2 class="font-display font-semibold text-white text-sm">Registration Date</h2>
        </div>
        <div class="p-5">
          <div class="flex gap-3 bg-amber-50 rounded-lg px-4 py-3" style="border:1px solid rgba(217,119,6,.30) mb-4">
            <span class="material-symbols-outlined text-amber-600 text-lg mt-0.5 shrink-0">info</span>
            <p class="text-xs text-amber-800">Leave blank to use today's date. Set a past date only for previously recorded registrations.</p>
          </div>
          <div class="grid grid-cols-3 gap-4 max-w-md">
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Month</label>
              <select name="reg_month" id="reg_month" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <option value="">— Today —</option>
                <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Day</label>
              <select name="reg_day" id="reg_day" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <option value="">— Today —</option>
                <?php for($d=1;$d<=31;$d++): ?><option value="<?= $d ?>"><?= $d ?></option><?php endfor; ?>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Year</label>
              <select name="reg_year" id="reg_year" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
                <option value="">— Today —</option>
                <?php for($y=date('Y');$y>=2000;$y--): ?><option value="<?= $y ?>"><?= $y ?></option><?php endfor; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Actions -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="window.location.href='dashboard.php'"
                class="flex items-center gap-2 text-error hover:bg-error-container px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">delete_sweep</span>Cancel
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 1 of 7: All asterisk (*) fields are mandatory</p>
          <button id="btnStep1" onclick="saveStep1()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            <span class="btn-spin"></span>
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step1 -->


    <!-- ════════════════════════════════════
         STEP 2
    ════════════════════════════════════ -->
    <div class="form-step" id="step2">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">group</span>
            <h2 class="font-display font-semibold text-white text-sm">II. Family Composition</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 2 of 7</span>
        </div>

        <div class="p-5 space-y-6">

          <!-- 8. Spouse -->
          <div>
            <div class="sub-label"><span class="num">8.</span> Spouse Information</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameSpouse" id="lastnameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameSpouse" id="firstnameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameSpouse" id="middlenameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixSpouse" id="suffixSpouse" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 9. Father -->
          <div>
            <div class="sub-label"><span class="num">9.</span> Father's Name</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameFather" id="lastnameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameFather" id="firstnameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameFather" id="middlenameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixFather" id="suffixFather" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 10. Mother -->
          <div>
            <div class="sub-label"><span class="num">10.</span> Mother's Name</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameMother" id="lastnameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameMother" id="firstnameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameMother" id="middlenameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixMother" id="suffixMother" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 11. Children -->
<div>
  <div class="sub-label flex items-center justify-between">
    <span class="flex items-center gap-2"><span class="num">11.</span> Children</span>
    <button type="button" id="addChildBtn" onclick="addChildRow()"
            class="flex items-center gap-1 text-xs font-semibold text-primary hover:bg-primary/10 px-3 py-1.5 rounded-lg transition-colors">
      <span class="material-symbols-outlined text-base">add</span> Add Child
    </button>
  </div>
  <div id="childrenContainer">
  <?php for($i=1;$i<=5;$i++): ?>
  <div class="family-row" id="childRow<?=$i?>">
    <div class="family-row-label">Child <?= $i ?></div>
    <div class="grid grid-cols-5 gap-3">
      <div class="space-y-1 col-span-2"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Full Name</label>
        <input type="text" name="fullnameChild<?=$i?>" id="fullnameChild<?=$i?>" placeholder="Full name" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
      <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Occupation</label>
        <input type="text" name="occupationChild<?=$i?>" id="occupationChild<?=$i?>" placeholder="Occupation" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
      <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Income</label>
        <input type="number" name="incomeChild<?=$i?>" id="incomeChild<?=$i?>" placeholder="0.00" min="0" step="0.01" inputmode="decimal" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
      <div class="grid grid-cols-2 gap-2">
        <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Age</label>
          <input type="number" name="ageChild<?=$i?>" id="ageChild<?=$i?>" placeholder="Age" min="0" max="120" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
        <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Working?</label>
          <select name="isWorkingChild<?=$i?>" id="isWorkingChild<?=$i?>" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"><option value="">—</option><option>Yes</option><option>No</option></select></div>
      </div>
    </div>
  </div>
  <?php endfor; ?>
  </div>
</div>

          <!-- 12. Dependents -->
          <div>
            <div class="sub-label"><span class="num">12.</span> Dependents (up to 2)</div>
            <?php for($i=1;$i<=2;$i++): ?>
            <div class="family-row">
              <div class="family-row-label">Dependent <?= $i ?></div>
              <div class="grid grid-cols-5 gap-3">
                <div class="space-y-1 col-span-2"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Full Name</label>
                  <input type="text" name="fullnameDependent<?=$i?>" id="fullnameDependent<?=$i?>" placeholder="Full name" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Occupation</label>
                  <input type="text" name="occupationDependent<?=$i?>" id="occupationDependent<?=$i?>" placeholder="Occupation" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Income</label>
                  <input type="number" name="incomeDependent<?=$i?>" id="incomeDependent<?=$i?>" placeholder="0.00" min="0" step="0.01" inputmode="decimal" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Age</label>
                    <input type="number" name="ageDependent<?=$i?>" id="ageDependent<?=$i?>" placeholder="Age" min="0" max="120" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"></div>
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Working?</label>
                    <select name="isWorkingDependent<?=$i?>" id="isWorkingDependent<?=$i?>" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem"><option value="">—</option><option>Yes</option><option>No</option></select></div>
                </div>
              </div>
            </div>
            <?php endfor; ?>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 2 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(1)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 2 of 7: Family Composition</p>
          <button id="btnStep2" onclick="saveStep2()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step2 -->


    <!-- ════════════════════════════════════
         STEP 3 — LIVING SITUATION
    ════════════════════════════════════ -->
    <div class="form-step" id="step3">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">home</span>
            <h2 class="font-display font-semibold text-white text-sm">III. Living Situation</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 3 of 7</span>
        </div>

        <div class="p-5 space-y-8">

          <!-- 13. Living Alone -->
          <div>
            <div class="sub-label"><span class="num">13.</span> Living Situation <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(Check all applicable)</span></div>
            <div class="flex gap-6">
              <label class="radio-option flex items-center gap-3 cursor-pointer group">
                <span class="radio-box" id="rb-livingAlone-yes">
                  <span class="radio-dot"></span>
                </span>
                <input type="radio" name="livingAlone" id="livingAlone_yes" value="Yes"
                       onchange="handleLivingAlone(this)"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm font-semibold text-on-surface group-hover:text-primary transition-colors">Living Alone</span>
              </label>
              <label class="radio-option flex items-center gap-3 cursor-pointer group">
                <span class="radio-box" id="rb-livingAlone-no">
                  <span class="radio-dot"></span>
                </span>
                <input type="radio" name="livingAlone" id="livingAlone_no" value="No"
                       onchange="handleLivingAlone(this)"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm font-semibold text-on-surface group-hover:text-primary transition-colors">Living with</span>
              </label>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 14. Living With -->
          <div id="livingWithSection">
            <div class="sub-label"><span class="num">14.</span> Living with <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php
              $livingWithOptions = ['Grand Children','Common Law Spouse','Spouse','In-laws','Care Institution','Children','Relatives','Friends','Others'];
              foreach($livingWithOptions as $opt): ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all" style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('lwcb_<?= md5($opt) ?>', 'livingWithSection')">
                <span class="chk-box flex-shrink-0" id="lwcb_<?= md5($opt) ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="livingWith[]" value="<?= htmlspecialchars($opt) ?>"
                       id="lw_<?= md5($opt) ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       onchange="<?= $opt==='Others' ? 'toggleOtherField(this,\'livingWithOthersWrap\')' : '' ?>">
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others free text -->
            <div id="livingWithOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="livingWithOthers" id="livingWithOthers"
                     placeholder="Specify who you live with…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 15. Living Condition -->
          <div>
            <div class="sub-label"><span class="num">15.</span> Living Condition <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php
              $livingConditions = ['No privacy','Overcrowded in home','Informal Settler','No permanent house','High cost of rent','Longing for independent living quiet atmosphere','Others'];
              foreach($livingConditions as $opt): ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all" style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('lccb_<?= md5($opt) ?>', 'livingCondSection')">
                <span class="chk-box flex-shrink-0" id="lccb_<?= md5($opt) ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="livingCondition[]" value="<?= htmlspecialchars($opt) ?>"
                       id="lc_<?= md5($opt) ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       onchange="<?= $opt==='Others' ? 'toggleOtherField(this,\'livingCondOthersWrap\')' : '' ?>">
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others free text -->
            <div id="livingCondOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="livingConditionOthers" id="livingConditionOthers"
                     placeholder="Specify living condition…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 3 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(2)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 3 of 7: Living Situation</p>
          <button id="btnStep3" onclick="saveStep3()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step3 -->


    <!-- ════════════════════════════════════
         STEP 4 — EDUCATION / HR PROFILE
    ════════════════════════════════════ -->
    <div class="form-step" id="step4">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">school</span>
            <h2 class="font-display font-semibold text-white text-sm">IV. Education / HR Profile</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 4 of 7</span>
        </div>

        <div class="p-5 space-y-8">

          <!-- Q27: Highest Educational Attainment -->
          <div>
            <div class="sub-label"><span class="num">16.</span> Highest Educational Attainment</div>
            <?php
            $educationOptions = [
              'Not Attended School',
              'Elementary Level',
              'Elementary Graduate',
              'High School Level',
              'High School Graduate',
              'Vocational',
              'College Level',
              'College Graduate',
              'Post Graduate',
              'Others',
            ];
            ?>
            <div class="grid grid-cols-2 gap-3">
              <?php foreach($educationOptions as $opt):
                $eid = 'edu_' . md5($opt); ?>
              <label class="radio-card flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="selectRadioCard('educationHighest','<?= htmlspecialchars($opt, ENT_QUOTES) ?>','<?= $eid ?>','educationOthersWrap')">
                <span class="radio-box flex-shrink-0" id="rb_<?= $eid ?>">
                  <span class="radio-dot"></span>
                </span>
                <input type="radio" name="educationHighest" id="<?= $eid ?>" value="<?= htmlspecialchars($opt) ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="educationOthersWrap" class="hidden mt-3">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify:</label>
              <input type="text" name="educationHighestOthers" id="educationHighestOthers"
                     placeholder="Specify educational attainment…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q28: Specialization / Technical Skills -->
          <div>
            <div class="sub-label"><span class="num">17.</span> Specialization / Technical Skills <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $skillOptions = [
              'Medical','Dental','Fishing','Engineering','Barber',
              'Evangelization','Millwright','Teaching','Counseling','Cooking',
              'Carpenter','Mason','Tailor','Legal Services','Farming',
              'Arts','Plumber','Shoemaker','Chef/Cook','Information Technology',
              'Others',
            ];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($skillOptions as $opt):
                $sid = 'sk_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $sid ?>','step4')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $sid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="skills[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $sid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt === 'Others' ? 'onchange="toggleOtherField(this,\'skillsOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="skillsOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="skillsOthers" id="skillsOthers"
                     placeholder="Specify skill…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q29: Shared Skills -->
          <div>
            <div class="sub-label"><span class="num">18.</span> Shared Skills <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(separate each skill with a comma)</span></div>
            <textarea name="sharedSkills" id="sharedSkills" rows="3"
                      placeholder="e.g. Gardening, Cooking, Knitting, Public Speaking…"
                      class="w-full px-3 py-2 text-sm bg-surface input-focus resize-none" style="border:1px solid #95a5a6; border-radius:0.375rem"></textarea>
            <p class="text-[11px] font-mono text-outline mt-1">Enter skills you are willing to share with the community, separated by commas.</p>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q30: Involvement in Community Activities -->
          <div>
            <div class="sub-label"><span class="num">19.</span> Involvement in Community Activities <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $communityOptions = [
              'Medical','Resource Volunteer','Community Beautification',
              'Community / Organization Leader','Dental','Friendly Visits',
              'Neighborhood Support Services','Legal Services','Religious',
              'Counselling / Referral','Sponsorship','Others',
            ];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($communityOptions as $opt):
                $cid = 'ci_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $cid ?>','step4')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $cid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="communityInvolvement[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $cid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt === 'Others' ? 'onchange="toggleOtherField(this,\'communityOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="communityOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="communityInvolvementOthers" id="communityInvolvementOthers"
                     placeholder="Specify activity…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 4 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(3)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 4 of 7: Education / HR Profile</p>
          <button id="btnStep4" onclick="saveStep4()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step4 -->


    <!-- ════════════════════════════════════
         STEP 5 — ECONOMIC PROFILE
    ════════════════════════════════════ -->
    <div class="form-step" id="step5">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">payments</span>
            <h2 class="font-display font-semibold text-white text-sm">V. Economic Profile</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 5 of 7</span>
        </div>

        <div class="p-5 space-y-8">

          <!-- Q31: Source of Income and Assistance -->
          <div>
            <div class="sub-label"><span class="num">20.</span> Source of Income and Assistance <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $sourceIncomeOptions = [
              'Own earnings, salary / wages','Own Pension','Stocks / Dividends',
              'Dependent on children / relatives',"Spouse's salary",'Spouse Pension',
              'Insurance','Rental / Sharecorp','Savings',
              'Livestock / orchard / farm','Fishing','Others',
            ];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($sourceIncomeOptions as $opt): $sid = 'si_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $sid ?>','step5')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $sid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="sourceIncome[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $sid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt === 'Others' ? 'onchange="toggleOtherField(this,\'sourceIncomeOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="sourceIncomeOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="sourceIncomeOthers" id="sourceIncomeOthers"
                     placeholder="Specify source of income…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q32a: Assets — Real and Immovable Properties -->
          <div>
            <div class="sub-label"><span class="num">21.</span> Assets — Real and Immovable Properties <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $assetsRealOptions = ['House','Lot / Farmland','House & Lot','Commercial Building','Fishpond /resort','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($assetsRealOptions as $opt): $sid = 'ar_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $sid ?>','step5')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $sid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="assetsReal[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $sid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt === 'Others' ? 'onchange="toggleOtherField(this,\'assetsRealOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="assetsRealOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="assetsRealOthers" id="assetsRealOthers"
                     placeholder="Specify property…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q32b: Assets — Personal and Movable Properties -->
          <div>
            <div class="sub-label"><span class="num">22.</span> Assets — Personal and Movable Properties <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $assetsPersonalOptions = ['Automobile','Personal Computer','Boats','Heavy equipment','Laptops','Drones','Motorcycle','Mobile phones','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($assetsPersonalOptions as $opt): $sid = 'ap_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $sid ?>','step5')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $sid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="assetsPersonal[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $sid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt === 'Others' ? 'onchange="toggleOtherField(this,\'assetsPersonalOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Others specify -->
            <div id="assetsPersonalOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="assetsPersonalOthers" id="assetsPersonalOthers"
                     placeholder="Specify property…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q33: Average Monthly Income -->
          <div>
            <div class="sub-label"><span class="num">23.</span> Average Monthly Income</div>
            <?php
            $incomeBrackets = [
              ['60k and above','₱60,000 and above'],
              ['50k to 60k','₱50,000 – ₱60,000'],
              ['40k to 50k','₱40,000 – ₱50,000'],
              ['30k to 40k','₱30,000 – ₱40,000'],
              ['20k to 30k','₱20,000 – ₱30,000'],
              ['10k to 20k','₱10,000 – ₱20,000'],
              ['5k to 10k','₱5,000 – ₱10,000'],
              ['below 5k','Below ₱5,000'],
              ['None','No Income'],
            ];
            ?>
            <div class="grid grid-cols-3 gap-3">
              <?php foreach($incomeBrackets as [$val,$label]): $iid = 'inc_' . md5($val); ?>
              <label class="radio-card flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="selectRadioCard('incomeMonthly','<?= htmlspecialchars($val, ENT_QUOTES) ?>','<?= $iid ?>')">
                <span class="radio-box flex-shrink-0" id="rb_<?= $iid ?>">
                  <span class="radio-dot"></span>
                </span>
                <input type="radio" name="incomeMonthly" id="<?= $iid ?>" value="<?= htmlspecialchars($val) ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($label) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q34: Problems / Needs Commonly Encountered -->
          <div>
            <div class="sub-label"><span class="num">24.</span> Problems / Needs Commonly Encountered <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $problemsNeedsOptions = [
             'Lack of income / resources',
                'Loss of income / resources',
                'Skills / capability training',
                'Livelihood Opportunities (specify)',
                'Others',
            ];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($problemsNeedsOptions as $opt):
                $sid = 'pn_' . md5($opt);
                $extra = '';
                if($opt === 'Livelihood Opportunities (specify)') $extra = 'onchange="toggleOtherField(this,\'problemsLivelihoodWrap\')"';
                if($opt === 'Others') $extra = 'onchange="toggleOtherField(this,\'problemsOthersWrap\')"';
              ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $sid ?>','step5')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $sid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="problemsNeeds[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $sid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $extra ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <!-- Livelihood specify -->
            <div id="problemsLivelihoodWrap" class="hidden mt-2 mb-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Specify livelihood opportunity needed:</label>
              <input type="text" name="problemsNeedsLivelihood" id="problemsNeedsLivelihood"
                     placeholder="e.g. Sari-sari store capital, handicraft materials…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
            <!-- Others specify -->
            <div id="problemsOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="problemsNeedsOthers" id="problemsNeedsOthers"
                     placeholder="Specify other need…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 5 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(4)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 5 of 7: Economic Profile</p>
          <button id="btnStep5" onclick="saveStep5()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step5 -->


    <!-- ════════════════════════════════════
         STEP 6 — HEALTH PROFILE
    ════════════════════════════════════ -->
    <div class="form-step" id="step6">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">favorite</span>
            <h2 class="font-display font-semibold text-white text-sm">VI. Health Profile</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 6 of 7</span>
        </div>

        <div class="p-5 space-y-8">

          <!-- Q35a: Blood Type — REQUIRED -->
          <div>
            <div class="sub-label">
              <span class="num">25.</span> Blood Type <span class="text-error ml-1">*</span>
            </div>
            <?php
            $bloodTypes = ['O','O+','O-','A','A+','A-','B','B+','B-','AB','AB+','AB-','Unknown'];
            ?>
            <div class="grid grid-cols-4 gap-3">
              <?php foreach($bloodTypes as $bt):
                $bid = 'bt_' . md5($bt); ?>
              <label class="radio-card flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="selectRadioCard('bloodType','<?= htmlspecialchars($bt, ENT_QUOTES) ?>','<?= $bid ?>')">
                <span class="radio-box flex-shrink-0" id="rb_<?= $bid ?>">
                  <span class="radio-dot"></span>
                </span>
                <input type="radio" name="bloodType" id="<?= $bid ?>" value="<?= htmlspecialchars($bt) ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors font-semibold"><?= htmlspecialchars($bt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <span class="err-msg" id="bloodTypeErr" style="display:none; font-size:.72rem; color:#ba1a1a; margin-top:4px;">Blood type is required.</span>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35a: Physical Disability -->
          <div>
            <div class="sub-label"><span class="num">26.</span> Physical Disability <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(if any, describe)</span></div>
            <textarea name="physicalDisability" id="physicalDisability" rows="2"
                      placeholder="e.g. Difficulty walking, loss of limb, visual impairment…"
                      class="w-full px-3 py-2 text-sm bg-surface input-focus resize-none" style="border:1px solid #95a5a6; border-radius:0.375rem"></textarea>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35a: Health Problems -->
          <div>
            <div class="sub-label"><span class="num">27.</span> Health Problems <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $healthProblemOptions = [
              'Hypertension','Diabetes Mellitus','Heart Disease','Arthritis',
              'Osteoporosis','Asthma','COPD','Kidney Disease',
              'Cancer','Stroke','Alzheimer\'s / Dementia','Depression',
              'Malnutrition','Tuberculosis','Others',
            ];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($healthProblemOptions as $opt):
                $hid = 'hp_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $hid ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $hid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="healthProblems[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $hid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'healthProblemsOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="healthProblemsOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="healthProblemsOthers" id="healthProblemsOthers"
                     placeholder="Specify health problem…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35b: Dental Concern -->
          <div>
            <div class="sub-label"><span class="num">28.</span> Dental Concern <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $dentalOptions = ['Toothache','Missing Teeth','Dentures Needed','Gum Disease','Oral Cancer Screening','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($dentalOptions as $opt):
                $did = 'dc_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $did ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $did ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="dentalConcern[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $did ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'dentalConcernOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="dentalConcernOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="dentalConcernOthers" id="dentalConcernOthers"
                     placeholder="Specify dental concern…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35c: Visual Concern -->
          <div>
            <div class="sub-label"><span class="num">29.</span> Visual Concern <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $visualOptions = ['Blurred Vision','Cataract','Glaucoma','Color Blindness','Night Blindness','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($visualOptions as $opt):
                $vid = 'vc_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $vid ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $vid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="visualConcern[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $vid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'visualConcernOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="visualConcernOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="visualConcernOthers" id="visualConcernOthers"
                     placeholder="Specify visual concern…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35d: Aural / Hearing Concern -->
          <div>
            <div class="sub-label"><span class="num">30.</span> Aural / Hearing Concern <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $auralOptions = ['Partial Hearing Loss','Total Hearing Loss','Tinnitus','Needs Hearing Aid','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($auralOptions as $opt):
                $aid = 'ac_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $aid ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $aid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="auralConcern[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $aid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'auralConcernOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="auralConcernOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="auralConcernOthers" id="auralConcernOthers"
                     placeholder="Specify hearing concern…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35e: Social / Emotional Concern -->
          <div>
            <div class="sub-label"><span class="num">31.</span> Social / Emotional Concern <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $socialOptions = ['Loneliness / Isolation','Depression / Anxiety','Grief / Bereavement','Family Conflict','Abuse / Neglect','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($socialOptions as $opt):
                $scid = 'sc_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $scid ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $scid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="socialConcern[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $scid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'socialConcernOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="socialConcernOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="socialConcernOthers" id="socialConcernOthers"
                     placeholder="Specify social/emotional concern…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q35f: Area of Difficulty -->
          <div>
            <div class="sub-label"><span class="num">32.</span> Area of Difficulty <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(check all that apply)</span></div>
            <?php
            $difficultyOptions = ['Walking / Mobility','Self-Care / Bathing','Dressing','Eating','Climbing Stairs','Communicating','Remembering / Cognition','Others'];
            ?>
            <div class="grid grid-cols-3 gap-3 mb-3">
              <?php foreach($difficultyOptions as $opt):
                $adid = 'ad_' . md5($opt); ?>
              <label class="checkbox-option flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                     style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                     onclick="toggleCheckbox('chkb_<?= $adid ?>','step6')">
                <span class="chk-box flex-shrink-0" id="chkb_<?= $adid ?>">
                  <svg class="chk-icon w-2.5 h-2" viewBox="0 0 12 10" fill="none">
                    <polyline points="1.5,5 4.5,8.5 10.5,1.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </span>
                <input type="checkbox" name="areaDifficulty[]" value="<?= htmlspecialchars($opt) ?>"
                       id="<?= $adid ?>"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none"
                       <?= $opt==='Others' ? 'onchange="toggleOtherField(this,\'areaDifficultyOthersWrap\')"' : '' ?>>
                <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($opt) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div id="areaDifficultyOthersWrap" class="hidden mt-2">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-1">Please specify others:</label>
              <input type="text" name="areaDifficultyOthers" id="areaDifficultyOthers"
                     placeholder="Specify area of difficulty…"
                     class="w-full max-w-sm px-3 py-2 text-sm bg-surface input-focus" style="border:1px solid #95a5a6; border-radius:0.375rem">
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q36: List of Medicines for Maintenance -->
          <div>
            <div class="sub-label"><span class="num">33.</span> List of Medicines for Maintenance <span class="text-xs font-body font-normal text-outline normal-case tracking-normal">(separate each with a comma)</span></div>
            <textarea name="listOfMedicines" id="listOfMedicines" rows="3"
                      placeholder="e.g. Amlodipine, Metformin, Atorvastatin…"
                      class="w-full px-3 py-2 text-sm bg-surface input-focus resize-none" style="border:1px solid #95a5a6; border-radius:0.375rem"></textarea>
            <p class="text-[11px] font-mono text-outline mt-1">List all medicines taken regularly for maintenance.</p>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q37: Scheduled Checkup -->
          <div>
            <div class="sub-label"><span class="num">34.</span> Scheduled Checkup</div>
            <div class="flex gap-6 mb-4">
              <label class="radio-option flex items-center gap-3 cursor-pointer group">
                <span class="radio-box" id="rb-checkup-yes"><span class="radio-dot"></span></span>
                <input type="radio" name="scheduledCheckup" id="checkup_yes" value="Yes"
                       onchange="handleCheckup(this)"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm font-semibold text-on-surface group-hover:text-primary transition-colors">Yes</span>
              </label>
              <label class="radio-option flex items-center gap-3 cursor-pointer group">
                <span class="radio-box" id="rb-checkup-no"><span class="radio-dot"></span></span>
                <input type="radio" name="scheduledCheckup" id="checkup_no" value="No"
                       onchange="handleCheckup(this)"
                       style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                <span class="text-sm font-semibold text-on-surface group-hover:text-primary transition-colors">No</span>
              </label>
            </div>
            <!-- Frequency — shown only if Yes -->
            <div id="checkupFreqWrap" class="hidden">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary block mb-2">How often?</label>
              <div class="grid grid-cols-2 gap-3 max-w-sm">
                <?php foreach(['Monthly','Every 3 months','Every 6 months','Annually'] as $freq):
                  $fid = 'freq_' . md5($freq); ?>
                <label class="radio-card flex items-center gap-3 p-3 rounded-lg cursor-pointer group transition-all"
                       style="border:1px solid rgba(149,165,166,.40); background:#f5f3f5"
                       onclick="selectRadioCard('scheduledCheckupYes','<?= htmlspecialchars($freq, ENT_QUOTES) ?>','<?= $fid ?>')">
                  <span class="radio-box flex-shrink-0" id="rb_<?= $fid ?>">
                    <span class="radio-dot"></span>
                  </span>
                  <input type="radio" name="scheduledCheckupYes" id="<?= $fid ?>" value="<?= htmlspecialchars($freq) ?>"
                         style="position:absolute;opacity:0;width:0;height:0;pointer-events:none">
                  <span class="text-sm text-on-surface group-hover:text-primary transition-colors"><?= htmlspecialchars($freq) ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 6 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(5)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 6 of 7: Health Profile</p>
          <button id="btnStep6" onclick="saveStep6()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            Save &amp; Continue
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
          </button>
        </div>
      </div>

    </div><!-- /step6 -->


    <!-- ════════════════════════════════════
         STEP 7 — ID & PHOTO UPLOAD
    ════════════════════════════════════ -->
    <div class="form-step" id="step7">

      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">badge</span>
            <h2 class="font-display font-semibold text-white text-sm">VII. ID & Photo Upload</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 7 of 7</span>
        </div>

        <div class="p-5 space-y-8">

          <!-- Notice -->
          <div class="flex gap-3 bg-amber-50 rounded-lg px-4 py-3" style="border:1px solid rgba(217,119,6,.30)">
            <span class="material-symbols-outlined text-amber-600 text-lg mt-0.5 shrink-0">info</span>
            <p class="text-xs text-amber-800 leading-relaxed">
              Accepted formats: <strong>JPG, PNG, WEBP</strong>. Maximum file size per upload: <strong>5MB</strong>.
              Both fields are <strong>required</strong> to complete registration.
            </p>
          </div>

          <!-- Q38: OSCA ID Photo -->
          <div>
            <div class="sub-label">
              <span class="num">35.</span> OSCA ID Photo <span class="text-error ml-1">*</span>
            </div>
            <p class="text-xs text-outline mb-3 font-mono">Upload a clear photo or scan of the senior citizen's OSCA ID card.</p>

            <div id="oscaDropZone"
                 class="relative flex flex-col items-center justify-center gap-3 rounded-lg cursor-pointer transition-all"
                 style="border:2px dashed #95a5a6; background:#f5f3f5; padding:40px 20px; min-height:160px"
                 onclick="document.getElementById('oscaIDInput').click()"
                 ondragover="handleDragOver(event,'oscaDropZone')"
                 ondragleave="handleDragLeave('oscaDropZone')"
                 ondrop="handleDrop(event,'oscaIDInput','oscaDropZone','oscaPreview','oscaFileName')">
              <span class="material-symbols-outlined text-4xl text-outline-variant" id="oscaUploadIcon">id_card</span>
              <div class="text-center">
                <p class="text-sm font-semibold text-on-surface">Click to upload or drag & drop</p>
                <p class="text-xs text-outline mt-1">OSCA ID card photo</p>
              </div>
              <input type="file" id="oscaIDInput" name="oscaID" accept="image/jpeg,image/png,image/webp"
                     class="hidden" onchange="previewFile(this,'oscaPreview','oscaFileName','oscaDropZone','oscaUploadIcon')">
            </div>

            <!-- Preview -->
            <div id="oscaPreview" class="hidden mt-3 flex items-center gap-4 p-3 rounded-lg" style="border:1px solid rgba(149,165,166,.30); background:#fff">
              <img id="oscaPreviewImg" src="" alt="OSCA ID Preview"
                   class="w-40 h-28 object-contain rounded-md" style="border:1px solid rgba(149,165,166,.30); background:#f5f3f5">
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-on-surface truncate" id="oscaFileName"></p>
                <p class="text-[11px] text-outline font-mono mt-0.5">OSCA ID Photo</p>
              </div>
              <button type="button" onclick="clearFile('oscaIDInput','oscaPreview','oscaDropZone','oscaUploadIcon')"
                      class="text-error hover:bg-error-container p-1.5 rounded-lg transition-colors flex-shrink-0">
                <span class="material-symbols-outlined text-lg">delete</span>
              </button>
            </div>
            <span class="err-msg" id="oscaIDErr" style="display:none; font-size:.72rem; color:#ba1a1a; margin-top:4px;">OSCA ID photo is required.</span>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- Q39: Latest 2x2 Photo -->
          <div>
            <div class="sub-label">
              <span class="num">36.</span> Latest 2×2 Photo <span class="text-error ml-1">*</span>
            </div>
            <p class="text-xs text-outline mb-3 font-mono">Upload the senior citizen's latest 2×2 ID picture (white background preferred).</p>

            <div id="photoDropZone"
                 class="relative flex flex-col items-center justify-center gap-3 rounded-lg cursor-pointer transition-all"
                 style="border:2px dashed #95a5a6; background:#f5f3f5; padding:40px 20px; min-height:160px"
                 onclick="document.getElementById('photoLatestInput').click()"
                 ondragover="handleDragOver(event,'photoDropZone')"
                 ondragleave="handleDragLeave('photoDropZone')"
                 ondrop="handleDrop(event,'photoLatestInput','photoDropZone','photoPreview','photoFileName')">
              <span class="material-symbols-outlined text-4xl text-outline-variant" id="photoUploadIcon">person_pin</span>
              <div class="text-center">
                <p class="text-sm font-semibold text-on-surface">Click to upload or drag & drop</p>
                <p class="text-xs text-outline mt-1">Latest 2×2 photo</p>
              </div>
              <input type="file" id="photoLatestInput" name="photoLatest" accept="image/jpeg,image/png,image/webp"
                     class="hidden" onchange="previewFile(this,'photoPreview','photoFileName','photoDropZone','photoUploadIcon')">
            </div>

            <!-- Preview -->
            <div id="photoPreview" class="hidden mt-3 flex items-center gap-4 p-3 rounded-lg" style="border:1px solid rgba(149,165,166,.30); background:#fff">
              <img id="photoPreviewImg" src="" alt="Photo Preview"
                   class="w-28 h-36 object-contain rounded-md" style="border:1px solid rgba(149,165,166,.30); background:#f5f3f5">
              <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-on-surface truncate" id="photoFileName"></p>
                <p class="text-[11px] text-outline font-mono mt-0.5">2×2 Photo</p>
              </div>
              <button type="button" onclick="clearFile('photoLatestInput','photoPreview','photoDropZone','photoUploadIcon')"
                      class="text-error hover:bg-error-container p-1.5 rounded-lg transition-colors flex-shrink-0">
                <span class="material-symbols-outlined text-lg">delete</span>
              </button>
            </div>
            <span class="err-msg" id="photoLatestErr" style="display:none; font-size:.72rem; color:#ba1a1a; margin-top:4px;">2×2 photo is required.</span>
          </div>

        </div>
      </div>

      <!-- Footer Actions Step 7 -->
      <div class="rounded-lg px-5 py-4 flex items-center justify-between" style="background:#fff; border:1px solid rgba(149,165,166,.30)">
        <button onclick="goStep(6)"
                class="flex items-center gap-2 text-secondary hover:bg-surface-container-high px-4 py-2 rounded-lg transition-colors text-sm font-semibold">
          <span class="material-symbols-outlined text-lg">arrow_back</span>Back
        </button>
        <div class="flex items-center gap-4">
          <p class="text-xs text-secondary font-mono italic">Section 7 of 7: ID & Photo Upload</p>
          <button id="btnStep7" onclick="saveStep7()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            <span class="btn-spin"></span>
            Submit Registration
            <span class="material-symbols-outlined text-lg">task_alt</span>
          </button>
        </div>
      </div>

    </div><!-- /step7 -->


    <div id="success-screen" class="text-center py-16">
      <div class="w-20 h-20 rounded-full bg-success flex items-center justify-center mx-auto mb-5">
        <span class="material-symbols-outlined text-white text-4xl">check_circle</span>
      </div>
      <h2 class="font-display font-bold text-2xl text-primary mb-2">Registration Complete!</h2>
      <p class="text-on-surface-variant mb-6">The application has been successfully submitted.</p>
      <a href="dashboard.php" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all">
        <span class="material-symbols-outlined text-lg">arrow_back</span>Back to Dashboard
      </a>
    </div>

    <!-- Watermark -->
    <div class="flex justify-center items-center py-10 opacity-10 pointer-events-none select-none">
      <div class="text-center space-y-2">
        <img src="HimCity_Logo_nobg.png" alt="" class="w-20 h-20 object-contain mx-auto">
        <p class="text-[10px] font-mono uppercase tracking-widest text-primary">Official OSCA Data Management Subsystem</p>
      </div>
    </div>

  </main>
</div><!-- /ml-64 -->

<div id="toast" role="status" aria-live="polite"></div>

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

  function toggleProfilePanel(e) {
  e.stopPropagation();
  const panel = document.getElementById('profilePanel');
  if (panel) panel.classList.toggle('open');
}
function closeProfilePanel() {
  const panel = document.getElementById('profilePanel');
  if (panel) panel.classList.remove('open');
}
document.addEventListener('click', (e) => {
  const wrap  = document.getElementById('profileWrap');
  const panel = document.getElementById('profilePanel');
  if (!wrap || !panel) return;
  if (!wrap.contains(e.target)) panel.classList.remove('open');
});
let currentStep = 1;

function openLogoutModal() {
  document.getElementById('logoutModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Barangay searchable combobox ──────
const BARANGAY_LIST = <?php
  $barangays = ['Barangay I-Poblacion','Barangay II-Poblacion','Barangay III-Poblacion','Barangay IV-Poblacion','Aguisan','Buenavista','Cabadiangan','Cabanbanan','Carabalan','Caradioan','Libacao','Mahalang','Mambagaton','Nabalian','San Antonio','Saraet','Suay','Talaban','Tooy'];
  sort($barangays);
  echo json_encode($barangays);
?>;
let _bIndex = -1;
let _bFiltered = [];

function renderBarangayDropdown(list){
  const dd = document.getElementById('barangayDropdown');
  const currentVal = document.getElementById('barangay').value;
  _bFiltered = list;
  _bIndex = -1;
  if(!list.length){
    dd.innerHTML = `<div class="barangay-empty">No matching barangay</div>`;
  } else {
    dd.innerHTML = list.map((b,i) => {
      const sel = b === currentVal ? ' selected' : '';
      const safe = b.replace(/'/g, "\\'");
      return `<div class="barangay-option${sel}" data-idx="${i}" onmousedown="selectBarangay('${safe}')">${b}</div>`;
    }).join('');
  }
  dd.classList.remove('hidden');
}

function filterBarangay(input){
  const q = input.value.trim().toLowerCase();
  const list = q ? BARANGAY_LIST.filter(b => b.toLowerCase().includes(q)) : BARANGAY_LIST;
  renderBarangayDropdown(list);
  input.classList.remove('error');
}

function selectBarangay(value){
  const input = document.getElementById('barangay');
  input.value = value;
  input.classList.remove('error');
  closeBarangayDropdown();
}

function closeBarangayDropdown(){
  const dd = document.getElementById('barangayDropdown');
  if(dd) dd.classList.add('hidden');
}

function barangayKeydown(e){
  const dd = document.getElementById('barangayDropdown');
  if(!dd || dd.classList.contains('hidden')) return;
  const options = dd.querySelectorAll('.barangay-option');
  if(!options.length) return;

  if(e.key === 'ArrowDown'){
    e.preventDefault();
    _bIndex = Math.min(_bIndex + 1, options.length - 1);
    highlightBarangay(options);
  } else if(e.key === 'ArrowUp'){
    e.preventDefault();
    _bIndex = Math.max(_bIndex - 1, 0);
    highlightBarangay(options);
  } else if(e.key === 'Enter'){
    e.preventDefault();
    if(_bIndex >= 0 && _bFiltered[_bIndex]) selectBarangay(_bFiltered[_bIndex]);
  } else if(e.key === 'Escape'){
    closeBarangayDropdown();
  }
}

function highlightBarangay(options){
  options.forEach(o => o.classList.remove('active'));
  if(options[_bIndex]){
    options[_bIndex].classList.add('active');
    options[_bIndex].scrollIntoView({block:'nearest'});
  }
}

document.addEventListener('click', function(e){
  const barangayInput = document.getElementById('barangay');
  if(!barangayInput) return;
  const wrap = barangayInput.closest('.relative');
  if(wrap && !wrap.contains(e.target)) closeBarangayDropdown();
});

// ── Dynamic child rows ─────────────────────────────────────────
let _childCount = 5;
function addChildRow() {
  _childCount++;
  const container = document.getElementById('childrenContainer');
  const row = document.createElement('div');
  row.className = 'family-row';
  row.id = `childRow${_childCount}`;
  row.innerHTML = `
    <div class="family-row-label" style="display:flex;justify-content:space-between;align-items:center">
      <span>Child ${_childCount}</span>
      <button type="button" onclick="removeChildRow(${_childCount})"
              style="font-size:.7rem;color:#ba1a1a;background:none;border:none;cursor:pointer;font-family:Inter,sans-serif;font-weight:600">
        ✕ Remove
      </button>
    </div>
    <div class="grid grid-cols-5 gap-3">
      <div class="space-y-1 col-span-2"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Full Name</label>
        <input type="text" name="fullnameChild${_childCount}" id="fullnameChild${_childCount}" placeholder="Full name" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6;border-radius:0.375rem"></div>
      <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Occupation</label>
        <input type="text" name="occupationChild${_childCount}" id="occupationChild${_childCount}" placeholder="Occupation" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6;border-radius:0.375rem"></div>
      <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Income</label>
        <input type="number" name="incomeChild${_childCount}" id="incomeChild${_childCount}" placeholder="0.00" min="0" step="0.01" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6;border-radius:0.375rem"></div>
      <div class="grid grid-cols-2 gap-2">
        <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Age</label>
          <input type="number" name="ageChild${_childCount}" id="ageChild${_childCount}" placeholder="Age" min="0" max="120" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6;border-radius:0.375rem"></div>
        <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Working?</label>
          <select name="isWorkingChild${_childCount}" id="isWorkingChild${_childCount}" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus" style="border:1px solid #95a5a6;border-radius:0.375rem"><option value="">—</option><option>Yes</option><option>No</option></select></div>
      </div>
    </div>`;
  container.appendChild(row);
}

function removeChildRow(n) {
  const row = document.getElementById(`childRow${n}`);
  if (row) row.remove();
}
// ── Age display ───────────────────────
['month','date','year'].forEach(id => document.getElementById(id).addEventListener('change', updateAge));
function updateAge(){
  const m = document.getElementById('month').value;
  const d = document.getElementById('date').value;
  const y = document.getElementById('year').value;
  const el = document.getElementById('ageDisplay');
  if (!m||!d||!y){ el.textContent=''; return; }
  const months=['January','February','March','April','May','June','July','August','September','October','November','December'];
  const bday = new Date(y, months.indexOf(m), parseInt(d));
  const now = new Date();
  let age = now.getFullYear() - bday.getFullYear();
  if (now < new Date(now.getFullYear(), bday.getMonth(), bday.getDate())) age--;
  if (age < 60) {
    el.innerHTML = `<span class="text-error flex items-center gap-1"><span class="material-symbols-outlined text-base">warning</span>Computed age: ${age} years — Must be 60 or above to register.</span>`;
  } else {
    el.innerHTML = `<span style="color:#2e7d32" class="flex items-center gap-1"><span class="material-symbols-outlined text-base">check_circle</span>Computed age: ${age} years — Eligible for registration.</span>`;
  }
}

// ── Navigation ────────────────────────
function goStep(n){
  // n=8 means success — delegate to showSuccess() instead
  if(n > 7){ showSuccess(); return; }
  const prev = document.getElementById(`step${currentStep}`);
  if(prev) prev.classList.remove('active');
  currentStep = n;
  const next = document.getElementById(`step${currentStep}`);
  if(next) next.classList.add('active');
  updateProgress();
  window.scrollTo({top:0, behavior:'smooth'});
}
function updateProgress(){
  for(let i=1;i<=7;i++){
    const dot   = document.getElementById(`dot${i}`);
    const label = document.getElementById(`sl${i}`);
    if(!dot) continue;

    // Reset base classes
    dot.className = `w-9 h-9 rounded-full border-2 flex items-center justify-center text-sm font-bold reg-step-dot `;

    if(i < currentStep){
      // Completed — solid green fill, white checkmark
      dot.className   += `border-success bg-success text-white`;
      dot.style.borderColor = '';
      dot.innerHTML    = '<span class="material-symbols-outlined text-sm" style="font-size:16px;color:#fff">check</span>';
      if(label){ label.style.color='#2e7d32'; label.style.fontWeight='700'; }
    } else if(i === currentStep){
      // Active — solid navy fill, white number
      dot.className   += `border-primary bg-primary text-white`;
      dot.style.borderColor = '';
      dot.textContent  = i;
      if(label){ label.style.color='#1d3246'; label.style.fontWeight='700'; }
    } else {
      // Upcoming — white fill, grey border + number
      dot.className   += `bg-surface text-on-surface-variant`;
      dot.style.borderColor = '#95a5a6';
      dot.textContent  = i;
      if(label){ label.style.color='#74777d'; label.style.fontWeight='400'; }
    }
  }
  document.getElementById('line1fill').style.width = currentStep > 1 ? '100%' : '0';
  document.getElementById('line2fill').style.width = currentStep > 2 ? '100%' : '0';
  const l3 = document.getElementById('line3fill'); if(l3) l3.style.width = currentStep > 3 ? '100%' : '0';
  const l4 = document.getElementById('line4fill'); if(l4) l4.style.width = currentStep > 4 ? '100%' : '0';
  const l5 = document.getElementById('line5fill'); if(l5) l5.style.width = currentStep > 5 ? '100%' : '0';
  const l6 = document.getElementById('line6fill'); if(l6) l6.style.width = currentStep > 6 ? '100%' : '0';
}

// ── Validation ────────────────────────
function required(id){
  const el = document.getElementById(id);
  const val = el.value.trim();
  if(!val){ el.classList.add('error'); return false; }
  el.classList.remove('error'); return true;
}

// ── Data collect ──────────────────────
function v(id){ const el=document.getElementById(id); return el?el.value:''; }
function collectStep1(){
  return {
    action:'save_step1',
    lastnameApplicant:v('lastnameApplicant'), firstnameApplicant:v('firstnameApplicant'),
    middlenameApplicant:v('middlenameApplicant'), suffixApplicant:v('suffixApplicant'),
    sex:v('sex'), month:v('month'), date:v('date'), year:v('year'),
    birthplace:v('birthplace'), maritalStatus:v('maritalStatus'), religion:v('religion'),
    contactNumber:v('contactNumber'),
    emailAddress:document.getElementById('emailNA').checked?'N/A':v('emailAddress'),
    fbMessenger:v('fbMessenger'), ethnicOrigin:v('ethnicOrigin'), languageSpoken:v('languageSpoken'),
    osca_ID:v('osca_ID'), gsis_sss_ID:v('gsis_sss_ID'), tin_ID:v('tin_ID'),
    philHealth_ID:v('philHealth_ID'), sc_asso_ID:v('sc_asso_ID'), other_govt_ID:v('other_govt_ID'),
    employment_business:v('employment_business'), hasPension:v('hasPension'), travelCapability:v('travelCapability'),
    personWithDisability:v('personWithDisability'),
    barangay:v('barangay'), purok:v('purok'), street:v('street'),
    reg_month:v('reg_month'), reg_day:v('reg_day'), reg_year:v('reg_year'),
  };
}
function collectStep2(){
  const d = {
    action:'save_step2',
    lastnameSpouse:v('lastnameSpouse'), firstnameSpouse:v('firstnameSpouse'),
    middlenameSpouse:v('middlenameSpouse'), suffixSpouse:v('suffixSpouse'),
    lastnameFather:v('lastnameFather'), firstnameFather:v('firstnameFather'),
    middlenameFather:v('middlenameFather'), suffixFather:v('suffixFather'),
    lastnameMother:v('lastnameMother'), firstnameMother:v('firstnameMother'),
    middlenameMother:v('middlenameMother'), suffixMother:v('suffixMother'),
  };
  for(let i=1;i<=Math.max(5,_childCount);i++){
  if(document.getElementById(`fullnameChild${i}`)){
    d[`fullnameChild${i}`]=v(`fullnameChild${i}`);
    d[`occupationChild${i}`]=v(`occupationChild${i}`);
    d[`incomeChild${i}`]=v(`incomeChild${i}`);
    d[`ageChild${i}`]=v(`ageChild${i}`);
    d[`isWorkingChild${i}`]=v(`isWorkingChild${i}`);
  }
}
  for(let i=1;i<=2;i++){
    d[`fullnameDependent${i}`]=v(`fullnameDependent${i}`); d[`occupationDependent${i}`]=v(`occupationDependent${i}`);
    d[`incomeDependent${i}`]=v(`incomeDependent${i}`); d[`ageDependent${i}`]=v(`ageDependent${i}`);
    d[`isWorkingDependent${i}`]=v(`isWorkingDependent${i}`);
  }
  return d;
}

// ── AJAX post ────────────────────────
async function post(data){
  const body = new URLSearchParams(data);
  const res = await fetch('save.php', {method:'POST', body});
  return res.json();
}

// ── Step 1 save ───────────────────────
async function saveStep1(){
  const ok = [
    required('lastnameApplicant'), required('firstnameApplicant'), (document.getElementById('middlenameNA').checked || required('middlenameApplicant')),,
    required('month'), required('date'), required('year'),
    required('maritalStatus'), required('sex'), required('contactNumber'),
    (document.getElementById('emailNA').checked || required('emailAddress')),
    required('barangay'), required('purok'),
  ].every(Boolean);
  if(!ok){ toast('Please fill in all required fields.','error'); return; }
  if(!/^09\d{9}$/.test(v('contactNumber'))){
    document.getElementById('contactNumber').classList.add('error');
    toast('Contact number must start with 09 and be exactly 11 digits.','error'); return;
  }
  const btn = document.getElementById('btnStep1');
  setLoading(btn, true);
  try {
    const res = await post(collectStep1());
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(2),600); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

// ── Step 2 save ───────────────────────
async function saveStep2(){
  const btn = document.getElementById('btnStep2');
  setLoading(btn, true);
  try {
    const res = await post(collectStep2());
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(3),500); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

// ── Step 3 save ───────────────────────
async function saveStep3(){
  const btn = document.getElementById('btnStep3');
  setLoading(btn, true);
  try {
    const res = await post(collectStep3());
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(4),500); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

// ── Step 4 save ───────────────────────
async function saveStep4(){
  const btn = document.getElementById('btnStep4');
  setLoading(btn, true);
  try {
    const res = await post(collectStep4());
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(5),500); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

function collectStep4(){
  const skillsChecked = [...document.querySelectorAll('input[name="skills[]"]:checked')].map(c=>c.value);
  const ciChecked     = [...document.querySelectorAll('input[name="communityInvolvement[]"]:checked')].map(c=>c.value);
  const eduEl         = document.querySelector('input[name="educationHighest"]:checked');
  return {
    action:                     'save_step4',
    educationHighest:           eduEl ? eduEl.value : '',
    educationHighestOthers:     v('educationHighestOthers'),
    skills:                     skillsChecked.join(','),
    skillsOthers:               v('skillsOthers'),
    sharedSkills:               v('sharedSkills'),
    communityInvolvement:       ciChecked.join(','),
    communityInvolvementOthers: v('communityInvolvementOthers'),
  };
}

// ── Radio card selector (for single-select styled as cards) ──
function selectRadioCard(radioName, value, inputId, othersWrapId){
  // Visually reset all radio boxes in the group
  document.querySelectorAll(`input[name="${radioName}"]`).forEach(r => {
    const rb = document.getElementById(`rb_${r.id}`);
    if(rb) rb.classList.remove('checked');
    r.closest('label')?.classList.remove('selected');
  });
  // Check the clicked one
  const input = document.getElementById(inputId);
  if(input){ input.checked = true; }
  const rb = document.getElementById(`rb_${inputId}`);
  if(rb) rb.classList.add('checked');
  input?.closest('label')?.classList.add('selected');
  // Show/hide an "Others" field, if this radio group has one
  if(othersWrapId){
    const wrap = document.getElementById(othersWrapId);
    if(wrap) wrap.classList.toggle('hidden', value !== 'Others');
  }
}

function collectStep3(){
  // Gather checked livingWith checkboxes
  const lwChecked = [...document.querySelectorAll('input[name="livingWith[]"]:checked')].map(c=>c.value);
  const lcChecked = [...document.querySelectorAll('input[name="livingCondition[]"]:checked')].map(c=>c.value);

  // Get radio value
  const laEl = document.querySelector('input[name="livingAlone"]:checked');

  return {
    action: 'save_step3',
    livingAlone:           laEl ? laEl.value : '',
    livingWith:            lwChecked.join(','),
    livingWithOthers:      v('livingWithOthers'),
    livingCondition:       lcChecked.join(','),
    livingConditionOthers: v('livingConditionOthers'),
  };
}

// ── Step 5 collect ────────────────────
function collectStep5(){
  const siChecked = [...document.querySelectorAll('input[name="sourceIncome[]"]:checked')].map(c=>c.value);
  const arChecked = [...document.querySelectorAll('input[name="assetsReal[]"]:checked')].map(c=>c.value);
  const apChecked = [...document.querySelectorAll('input[name="assetsPersonal[]"]:checked')].map(c=>c.value);
  const pnChecked = [...document.querySelectorAll('input[name="problemsNeeds[]"]:checked')].map(c=>c.value);
  const incEl = document.querySelector('input[name="incomeMonthly"]:checked');
  return {
    action:                   'save_step5',
    sourceIncome:             siChecked.join(','),
    sourceIncomeOthers:       v('sourceIncomeOthers'),
    assetsReal:               arChecked.join(','),
    assetsRealOthers:         v('assetsRealOthers'),
    assetsPersonal:           apChecked.join(','),
    assetsPersonalOthers:     v('assetsPersonalOthers'),
    incomeMonthly:            incEl ? incEl.value : '',
    problemsNeeds:            pnChecked.join(','),
    problemsNeedsLivelihood:  v('problemsNeedsLivelihood'),
    problemsNeedsOthers:      v('problemsNeedsOthers'),
  };
}

// ── Step 5 save ───────────────────────
async function saveStep5(){
  const btn = document.getElementById('btnStep5');
  setLoading(btn, true);
  try {
    const res = await post(collectStep5());
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(6),500); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

// ── Step 6 validate + save ──────────────
async function saveStep6(){
  const btEl = document.querySelector('input[name="bloodType"]:checked');
  const errEl = document.getElementById('bloodTypeErr');
  if(!btEl){
    errEl.style.display = 'block';
    toast('Blood type is required.', 'error');
    return;
  }
  errEl.style.display = 'none';

  const btn = document.getElementById('btnStep6');
  setLoading(btn, true);
  try {
    const data = collectStep6();
    data.action = 'save_step6';
    const res = await post(data);
    if(res.success){ toast(res.message,'success'); setTimeout(()=>goStep(7),500); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

// ── Step 7: File upload helpers ───────
function previewFile(input, previewWrapId, fileNameId, dropZoneId, iconId){
  const file = input.files[0];
  if(!file) return;
  if(file.size > 5 * 1024 * 1024){
    toast('File too large. Maximum size is 5MB.', 'error');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = function(e){
    const wrap = document.getElementById(previewWrapId);
    const img  = document.getElementById(previewWrapId + 'Img');
    const name = document.getElementById(fileNameId);
    if(img) img.src = e.target.result;
    if(name) name.textContent = file.name;
    if(wrap) wrap.classList.remove('hidden');
    // Update drop zone style
    const dz = document.getElementById(dropZoneId);
    if(dz){ dz.style.border = '2px dashed #1d3246'; dz.style.background = 'rgba(29,50,70,.04)'; }
    const icon = document.getElementById(iconId);
    if(icon){ icon.textContent = 'check_circle'; icon.style.color = '#2e7d32'; }
    // Clear error
    const errId = input.id.replace('Input','Err');
    const errEl = document.getElementById(errId);
    if(errEl) errEl.style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function clearFile(inputId, previewWrapId, dropZoneId, iconId){
  const input = document.getElementById(inputId);
  if(input) input.value = '';
  const wrap = document.getElementById(previewWrapId);
  if(wrap) wrap.classList.add('hidden');
  const dz = document.getElementById(dropZoneId);
  if(dz){ dz.style.border = '2px dashed #95a5a6'; dz.style.background = '#f5f3f5'; }
  const icon = document.getElementById(iconId);
  if(icon){ icon.style.color = ''; icon.textContent = inputId === 'oscaIDInput' ? 'id_card' : 'person_pin'; }
}

function handleDragOver(e, dropZoneId){
  e.preventDefault();
  const dz = document.getElementById(dropZoneId);
  if(dz){ dz.style.border = '2px dashed #1d3246'; dz.style.background = 'rgba(29,50,70,.06)'; }
}
function handleDragLeave(dropZoneId){
  const dz = document.getElementById(dropZoneId);
  if(dz){ dz.style.border = '2px dashed #95a5a6'; dz.style.background = '#f5f3f5'; }
}
function handleDrop(e, inputId, dropZoneId, previewWrapId, fileNameId){
  e.preventDefault();
  handleDragLeave(dropZoneId);
  const input = document.getElementById(inputId);
  const iconId = inputId.replace('Input','UploadIcon').replace('oscaID','osca').replace('photoLatest','photo');
  if(e.dataTransfer.files.length){
    const dt = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    input.files = dt.files;
    previewFile(input, previewWrapId, fileNameId, dropZoneId, iconId);
  }
}

// ── Step 7 validate + final submit ────
async function saveStep7(){
  const oscaFile  = document.getElementById('oscaIDInput').files[0];
  const photoFile = document.getElementById('photoLatestInput').files[0];
  let valid = true;

  const oscaErr  = document.getElementById('oscaIDErr');
  const photoErr = document.getElementById('photoLatestErr');

  if(!oscaFile){  oscaErr.style.display  = 'block'; valid = false; } else oscaErr.style.display  = 'none';
  if(!photoFile){ photoErr.style.display = 'block'; valid = false; } else photoErr.style.display = 'none';

  if(!valid){ toast('Please upload both required photos.', 'error'); return; }

  const btn = document.getElementById('btnStep7');
  setLoading(btn, true);

  try {
    // Steps 1–6 are already saved at this point — Step 7 just uploads the
    // two required photos and marks the registration complete.
    const fd = new FormData();
    fd.append('action', 'submit_registration');
    fd.append('oscaID', oscaFile);
    fd.append('photoLatest', photoFile);

    const res  = await fetch('save.php', { method: 'POST', body: fd });
    const data = await res.json();
    if(data.success){ toast(data.message, 'success'); setTimeout(showSuccess, 600); }
    else toast(data.message, 'error');
  } catch(e){ toast('Network error. Please try again.', 'error'); }
  finally { setLoading(btn, false); }
}

// ── Final submit (kept for compatibility) ─────────────
async function submitFinal(){ await saveStep7(); }

function showSuccess(){
  for(let i=1;i<=7;i++){
    const s = document.getElementById(`step${i}`);
    if(s) s.classList.remove('active');
  }
  document.getElementById('success-screen').style.display='block';
  // Mark all 7 steps complete in the progress bar
  currentStep=8; updateProgress();
  window.scrollTo({top:0, behavior:'smooth'});
}

// ── Scheduled checkup toggle ──────────
function handleCheckup(radio){
  document.querySelectorAll('[id^="rb-checkup-"]').forEach(rb => rb.classList.remove('checked'));
  document.getElementById(`rb-checkup-${radio.value.toLowerCase()}`).classList.add('checked');
  const wrap = document.getElementById('checkupFreqWrap');
  if(radio.value === 'Yes'){ wrap.classList.remove('hidden'); }
  else {
    wrap.classList.add('hidden');
    document.querySelectorAll('input[name="scheduledCheckupYes"]').forEach(r => {
      r.checked = false;
      const rb = document.getElementById(`rb_${r.id}`);
      if(rb){ rb.classList.remove('checked'); r.closest('label')?.classList.remove('selected'); }
    });
  }
}
function collectStep6(){
  const hpChecked  = [...document.querySelectorAll('input[name="healthProblems[]"]:checked')].map(c=>c.value);
  const dcChecked  = [...document.querySelectorAll('input[name="dentalConcern[]"]:checked')].map(c=>c.value);
  const vcChecked  = [...document.querySelectorAll('input[name="visualConcern[]"]:checked')].map(c=>c.value);
  const acChecked  = [...document.querySelectorAll('input[name="auralConcern[]"]:checked')].map(c=>c.value);
  const scChecked  = [...document.querySelectorAll('input[name="socialConcern[]"]:checked')].map(c=>c.value);
  const adChecked  = [...document.querySelectorAll('input[name="areaDifficulty[]"]:checked')].map(c=>c.value);
  const btEl       = document.querySelector('input[name="bloodType"]:checked');
  const scYesEl    = document.querySelector('input[name="scheduledCheckupYes"]:checked');
  const scupEl     = document.querySelector('input[name="scheduledCheckup"]:checked');
  return {
    action:                  'save_step6',
    bloodType:               btEl ? btEl.value : '',
    physicalDisability:      v('physicalDisability'),
    healthProblems:          hpChecked.join(','),
    healthProblemsOthers:    v('healthProblemsOthers'),
    dentalConcern:           dcChecked.join(','),
    dentalConcernOthers:     v('dentalConcernOthers'),
    visualConcern:           vcChecked.join(','),
    visualConcernOthers:     v('visualConcernOthers'),
    auralConcern:            acChecked.join(','),
    auralConcernOthers:      v('auralConcernOthers'),
    socialConcern:           scChecked.join(','),
    socialConcernOthers:     v('socialConcernOthers'),
    areaDifficulty:          adChecked.join(','),
    areaDifficultyOthers:    v('areaDifficultyOthers'),
    listOfMedicines:         v('listOfMedicines'),
    scheduledCheckup:        scupEl ? scupEl.value : '',
    scheduledCheckupYes:     scYesEl ? scYesEl.value : '',
  };
}

// ── Toast ─────────────────────────────
function toast(msg, type='success'){
  const el = document.getElementById('toast');
  el.textContent=msg; el.className=`show ${type}`;
  clearTimeout(el._t);
  el._t=setTimeout(()=>el.classList.remove('show'),3500);
}
function setLoading(btn, on){
  btn.disabled=on; btn.classList.toggle('loading',on);
}

// ── Email N/A toggle ──
function toggleEmailNA(cb){
  const input=document.getElementById('emailAddress');
  const label=document.getElementById('emailNALabel');
  if(cb.checked){
    input.value=''; input.disabled=true;
    input.placeholder='N/A — No email address';
    input.classList.remove('error');
    label.classList.add('checked');
  } else {
    input.disabled=false;
    input.placeholder='email@example.com';
    label.classList.remove('checked');
  }
}
function toggleMiddlenameNA(cb) {
  const input = document.getElementById('middlenameApplicant');
  const label = document.getElementById('middlenameNALabel');
  if (cb.checked) {
    input.value = 'N/A';
    input.disabled = true;
    input.placeholder = 'N/A — No middle name';
    input.classList.remove('error');
    label.classList.add('checked');
  } else {
    input.value = '';
    input.disabled = false;
    input.placeholder = 'E.G. SANTOS';
    label.classList.remove('checked');
  }
}
document.addEventListener('DOMContentLoaded', function(){
  const label=document.getElementById('emailNALabel');
  if(label) label.addEventListener('click', function(){
    const cb=document.getElementById('emailNA');
    cb.checked=!cb.checked; toggleEmailNA(cb);
    const middleLabel = document.getElementById('middlenameNALabel');
if (middleLabel) middleLabel.addEventListener('click', function() {
  const cb = document.getElementById('middlenameNA');
  cb.checked = !cb.checked;
  toggleMiddlenameNA(cb);
});
  });
  // label focus input highlight
  document.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('focus', ()=>{ el.parentElement.querySelector('label')?.classList.add('text-primary'); });
    el.addEventListener('blur', ()=>{ el.parentElement.querySelector('label')?.classList.remove('text-primary'); });
  });
});

// ── Step 3 Helpers ────────────────────

// Handle "Living Alone" radio toggle
function handleLivingAlone(radio){
  document.querySelectorAll('[id^="rb-livingAlone-"]').forEach(rb => rb.classList.remove('checked'));
  document.getElementById(`rb-livingAlone-${radio.value.toLowerCase()}`).classList.add('checked');
  // If "Yes", disable/clear livingWith section
  const lwSection = document.getElementById('livingWithSection');
  if(radio.value === 'Yes'){
    lwSection.style.opacity = '0.4';
    lwSection.style.pointerEvents = 'none';
    // Uncheck all livingWith checkboxes
    document.querySelectorAll('input[name="livingWith[]"]').forEach(cb => {
      if(cb.checked){ cb.checked = false; cb.dispatchEvent(new Event('change')); }
      const boxId = cb.id.replace('lw_','lwcb_');
      const box = document.getElementById(boxId);
      if(box){ box.classList.remove('checked'); cb.closest('.checkbox-option')?.classList.remove('selected'); }
    });
    document.getElementById('livingWithOthersWrap').classList.add('hidden');
  } else {
    lwSection.style.opacity = '1';
    lwSection.style.pointerEvents = '';
  }
}

// Toggle checkbox UI and real input
function toggleCheckbox(boxId, sectionId){
  const box = document.getElementById(boxId);
  if(!box) return;

  // Derive the real input id by stripping known box prefixes
  let inputId = boxId
    .replace('lwcb_', 'lw_')
    .replace('lccb_', 'lc_')
    .replace('chkb_', '');   // Step 4: chkb_sk_xxx → sk_xxx, chkb_ci_xxx → ci_xxx

  const input = document.getElementById(inputId);
  if(!input) return;

  input.checked = !input.checked;
  box.classList.toggle('checked', input.checked);
  box.closest('.checkbox-option')?.classList.toggle('selected', input.checked);
  input.dispatchEvent(new Event('change'));
}

// Show/hide "Others" free-text field
function toggleOtherField(cb, wrapId){
  const wrap = document.getElementById(wrapId);
  if(!wrap) return;
  if(cb.checked){ wrap.classList.remove('hidden'); }
  else { wrap.classList.add('hidden'); const inp = wrap.querySelector('input'); if(inp) inp.value=''; }
}

// ── Input enforcement ──
function enforceAlphaUpper(input){
  let val=input.value.toUpperCase().replace(/[^A-ZÑ\s]/g,'');
  if(val.length>50) val=val.slice(0,50);
  input.value=val;
}
function enforceContact(input){
  let val=input.value.replace(/\D/g,'');
  if(val.length>=1&&val[0]!=='0') val='0'+val;
  if(val.length>=2&&val[1]!=='9') val='09'+val.replace(/^0*9?/,'');
  if(val.length>11) val=val.slice(0,11);
  input.value=val;
}
</script>

</body>
</html>