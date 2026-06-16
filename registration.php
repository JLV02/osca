<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#1d3246">
<title>New Senior Record — OSCA Registry</title>
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<script>
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        'primary':                  '#1d3246',
        'primary-container':        '#34495e',
        'on-primary':               '#ffffff',
        'secondary':                '#526162',
        'surface':                  '#fbf9fb',
        'surface-container':        '#efedef',
        'surface-container-low':    '#f5f3f5',
        'surface-container-high':   '#e9e8e9',
        'surface-container-lowest': '#ffffff',
        'on-surface':               '#1b1c1d',
        'on-surface-variant':       '#43474c',
        'outline':                  '#74777d',
        'outline-variant':          '#c3c7cd',
        'error':                    '#ba1a1a',
        'error-container':          '#ffdad6',
        'success':                  '#2e7d32',
      },
      fontFamily: {
        body:    ['Inter', 'sans-serif'],
        display: ['Hanken Grotesk', 'sans-serif'],
        mono:    ['JetBrains Mono', 'monospace'],
      },
    }
  }
}
</script>
<style>
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
  .form-step { display: none; }
  .form-step.active { display: block; }
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
  #success-screen { display: none; }
  select.input-focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%2374777d' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 34px;
    -webkit-appearance: none; appearance: none;
  }
  #ageDisplay { min-height: 20px; font-size: .72rem; font-family: JetBrains Mono,monospace; margin-top: 4px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .btn-spin { display:none; width:14px; height:14px; border:2px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation: spin .7s linear infinite; flex-shrink:0; }
  .loading .btn-spin { display:block; }
  .nav-active { background: #efedef; color: #1d3246; font-weight: 700; border-right: 3px solid #1d3246; }
  .na-box {
    width: 15px; height: 15px; border: 1px solid #95a5a6; border-radius: 3px;
    background: #fff; display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; transition: background .15s, border-color .15s; cursor: pointer;
  }
  .na-row.checked .na-box { background: #1d3246; border-color: #1d3246; }
  .na-check { opacity: 0; transform: scale(.5); transition: opacity .15s, transform .2s; }
  .na-row.checked .na-check { opacity: 1; transform: scale(1); }
</style>
</head>
<body class="bg-[#ECF0F1] font-body text-on-surface min-h-screen">

<!-- ── SIDEBAR ── -->
<aside class="fixed left-0 top-0 h-full w-64 bg-surface-container-lowest flex flex-col py-4 z-20" style="border-right:1px solid rgba(149,165,166,.30)">
  <div class="px-4 mb-8">
    <div class="flex items-center gap-3">
      <!-- Himamaylan City Logo -->
      <div class="w-14 h-14 rounded-xl flex items-center justify-center p-1.5 flex-shrink-0"
           style="background:rgba(29,50,70,0.07); border:1px solid rgba(149,165,166,0.25);">
        <img src="HimCity_Logo_nobg.png" alt="Himamaylan City Logo"
             class="w-full h-full object-contain"
             style="filter:drop-shadow(0 1px 3px rgba(29,50,70,0.15));">
      </div>
      <div>
        <h1 class="font-display font-bold text-primary text-base leading-tight">Registry Admin</h1>
        <p class="text-xs text-secondary font-mono">Senior Services</p>
      </div>
    </div>
  </div>
  <nav class="flex-1 px-2 space-y-1">
    <a href="dashboard.php" class="flex items-center gap-4 px-4 py-2 rounded-lg text-secondary hover:text-primary hover:bg-surface-container-high transition-colors text-sm">
      <span class="material-symbols-outlined text-xl">dashboard</span>
      Dashboard
    </a>
    <a href="#" class="nav-active flex items-center gap-4 px-4 py-2 rounded-lg text-sm transition-colors">
      <span class="material-symbols-outlined text-xl" style="font-variation-settings:'FILL' 1">how_to_reg</span>
      Registration Form
    </a>
  </nav>
</aside>

<!-- ── MAIN CONTENT ── -->
<div class="ml-64 flex flex-col min-h-screen">

  <!-- Top Bar -->
  <header class="sticky top-0 z-10 h-16 bg-surface flex items-center justify-between px-6" style="border-bottom:1px solid rgba(149,165,166,.30)">
    <h1 class="font-display font-bold text-2xl text-primary tracking-tight">New Senior Record</h1>
    <div class="flex items-center gap-3">
      <div class="w-px h-8 bg-outline-variant"></div>
      <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center">
        <span class="material-symbols-outlined text-on-primary text-sm" style="font-variation-settings:'FILL' 1">person</span>
      </div>
    </div>
  </header>

  <main class="flex-1 px-6 py-6 max-w-4xl w-full mx-auto">

    <!-- ── STEP PROGRESS ── -->
    <div class="flex items-center justify-center gap-2 mb-6">
      <div class="flex flex-col items-center gap-1" id="sn1">
        <div class="w-9 h-9 rounded-full border-2 border-primary bg-primary text-white flex items-center justify-center text-sm font-bold step-dot" id="dot1">1</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-primary font-semibold">Personal Info</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)" id="line1bar"><div class="h-full bg-success transition-all" id="line1fill" style="width:0"></div></div>
      <div class="flex flex-col items-center gap-1" id="sn2">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold step-dot" style="border-color:#95a5a6" id="dot2">2</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant">Family</span>
      </div>
      <div class="flex-1 h-0.5 mb-5 mx-2" style="background:rgba(149,165,166,.40)"><div class="h-full bg-success transition-all" id="line2fill" style="width:0"></div></div>
      <div class="flex flex-col items-center gap-1" id="sn3">
        <div class="w-9 h-9 rounded-full border-2 bg-surface text-on-surface-variant flex items-center justify-center text-sm font-bold step-dot" style="border-color:#95a5a6" id="dot3">3</div>
        <span class="text-[10px] font-mono uppercase tracking-widest text-on-surface-variant">Complete</span>
      </div>
    </div>

    <!-- ════════════════════════════════════
         STEP 1
    ════════════════════════════════════ -->
    <div class="form-step active" id="step1">

      <!-- Card: Identifying Information -->
      <div class="bg-surface-container-lowest rounded-lg overflow-hidden mb-4" style="border:1px solid rgba(149,165,166,.30)">
        <div class="bg-primary px-5 py-3 flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-white text-xl">badge</span>
            <h2 class="font-display font-semibold text-white text-sm">I. Identifying Information</h2>
          </div>
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 1 of 2</span>
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
            <div class="grid grid-cols-4 gap-4 items-end">
              <div class="space-y-1 min-w-0">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Lastname (Apelyido) <span class="text-error">*</span></label>
                <input type="text" name="lastnameApplicant" id="lastnameApplicant"
                       placeholder="E.G. DELA CRUZ" autocomplete="family-name" maxlength="50"
                       oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <span class="err-msg">Last name is required</span>
              </div>
              <div class="space-y-1 min-w-0">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Firstname (Pangalan) <span class="text-error">*</span></label>
                <input type="text" name="firstnameApplicant" id="firstnameApplicant"
                       placeholder="E.G. JUAN" autocomplete="given-name" maxlength="50"
                       oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <span class="err-msg">First name is required</span>
              </div>
              <div class="space-y-1 min-w-0">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Middlename (Gitnang Pangalan)</label>
                <input type="text" name="middlenameApplicant" id="middlenameApplicant"
                       placeholder="E.G. SANTOS (OPTIONAL)" autocomplete="additional-name" maxlength="50"
                       oninput="enforceAlphaUpper(this)" style="text-transform:uppercase"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="space-y-1 min-w-0">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary leading-tight block">Extension</label>
                <select name="suffixApplicant" id="suffixApplicant"
                        class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
                <select name="barangay" id="barangay"
                        class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">— Select Barangay —</option>
                  <?php
                  $barangays = ['Barangay I-Poblacion','Barangay II-Poblacion','Barangay III-Poblacion','Barangay IV-Poblacion','Aguisan','Buenavista','Cabadiangan','Cabanbanan','Carabalan','Caradioan','Libacao','Mahalang','Mambagaton','Nabalian','San Antonio','Saraet','Suay','Talaban','Tooy'];
                  sort($barangays);
                  foreach($barangays as $b) echo "<option>".htmlspecialchars($b)."</option>\n";
                  ?>
                </select>
                <span class="err-msg">Barangay is required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Purok / Zone / Sitio</label>
                <input type="text" name="purok" id="purok" placeholder="Purok / Zone"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Street / House No.</label>
                <input type="text" name="street" id="street" placeholder="House No. / Street"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
                <select name="month" id="month" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">Month</option>
                  <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                  <option><?= $m ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Day <span class="text-error">*</span></label>
                <select name="date" id="date" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">Day</option>
                  <?php for($d=1;$d<=31;$d++) echo "<option>$d</option>"; ?>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="col-span-4 space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Year <span class="text-error">*</span></label>
                <select name="year" id="year" class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Marital Status <span class="text-error">*</span></label>
                <select name="maritalStatus" id="maritalStatus" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">Select</option>
                  <option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Sex <span class="text-error">*</span></label>
                <select name="sex" id="sex" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">Select</option><option>Male</option><option>Female</option>
                </select>
                <span class="err-msg">Required</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Religion</label>
                <select name="religion" id="religion" class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <span class="err-msg">Must start with 09, exactly 11 digits</span>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Email Address <span class="text-error">*</span></label>
                <input type="email" name="emailAddress" id="emailAddress" placeholder="email@example.com"
                       autocomplete="email" inputmode="email"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <label class="flex items-center gap-2 cursor-pointer mt-1 na-row" id="emailNALabel">
                  <span class="na-box" id="emailNABox">
                    <svg class="na-check w-2.5 h-2" viewBox="0 0 12 10" fill="none">
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
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Ethnic Origin</label>
                <input type="text" name="ethnicOrigin" id="ethnicOrigin" placeholder="e.g. Cebuano, Ilocano"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Language Spoken</label>
                <input type="text" name="languageSpoken" id="languageSpoken" placeholder="e.g. Cebuano, Filipino"
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 6. Government IDs -->
          <div>
            <div class="sub-label"><span class="num">6.</span> Government IDs</div>
            <div class="grid grid-cols-3 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">OSCA ID No.</label>
                <input type="text" name="osca_ID" id="osca_ID" placeholder="OSCA-XXXXXX" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">GSIS / SSS No.</label>
                <input type="text" name="gsis_sss_ID" id="gsis_sss_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">TIN No.</label>
                <input type="text" name="tin_ID" id="tin_ID" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">PhilHealth ID</label>
                <input type="text" name="philHealth_ID" id="philHealth_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Senior Citizens Assoc. ID</label>
                <input type="text" name="sc_asso_ID" id="sc_asso_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Other Govt. ID</label>
                <input type="text" name="other_govt_ID" id="other_govt_ID" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
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
                       class="w-full px-3 py-2 text-sm bg-surface input-focus">
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Receiving Pension?</label>
                <select name="hasPension" id="hasPension" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                  <option value="">Select</option><option>Yes</option><option>No</option>
                </select>
              </div>
              <div class="space-y-1">
                <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Can Travel?</label>
                <select name="travelCapability" id="travelCapability" class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
          <div class="flex gap-3 bg-amber-50 rounded-lg px-4 py-3 mb-4" style="border:1px solid rgba(217,119,6,.30)">
            <span class="material-symbols-outlined text-amber-600 text-lg mt-0.5 shrink-0">info</span>
            <p class="text-xs text-amber-800">Leave blank to use today's date. Set a past date only for previously recorded registrations.</p>
          </div>
          <div class="grid grid-cols-3 gap-4 max-w-md">
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Month</label>
              <select name="reg_month" id="reg_month" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <option value="">— Today —</option>
                <?php foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $m): ?>
                <option value="<?= $m ?>"><?= $m ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Day</label>
              <select name="reg_day" id="reg_day" class="w-full px-3 py-2 text-sm bg-surface input-focus">
                <option value="">— Today —</option>
                <?php for($d=1;$d<=31;$d++): ?><option value="<?= $d ?>"><?= $d ?></option><?php endfor; ?>
              </select>
            </div>
            <div class="space-y-1">
              <label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Year</label>
              <select name="reg_year" id="reg_year" class="w-full px-3 py-2 text-sm bg-surface input-focus">
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
          <p class="text-xs text-secondary font-mono italic">Section 1 of 2: All asterisk (*) fields are mandatory</p>
          <button id="btnStep1" onclick="saveStep1()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            <span class="btn-spin"></span>
            Next Section
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
          <span class="text-[10px] font-mono uppercase bg-white/15 text-white px-3 py-1 rounded-full tracking-widest">Step 2 of 2</span>
        </div>

        <div class="p-5 space-y-6">

          <!-- 8. Spouse -->
          <div>
            <div class="sub-label"><span class="num">8.</span> Spouse Information</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameSpouse" id="lastnameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameSpouse" id="firstnameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameSpouse" id="middlenameSpouse" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixSpouse" id="suffixSpouse" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 9. Father -->
          <div>
            <div class="sub-label"><span class="num">9.</span> Father's Name</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameFather" id="lastnameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameFather" id="firstnameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameFather" id="middlenameFather" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixFather" id="suffixFather" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 10. Mother -->
          <div>
            <div class="sub-label"><span class="num">10.</span> Mother's Name</div>
            <div class="grid grid-cols-4 gap-4">
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Last Name</label>
                <input type="text" name="lastnameMother" id="lastnameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">First Name</label>
                <input type="text" name="firstnameMother" id="firstnameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Middle Name</label>
                <input type="text" name="middlenameMother" id="middlenameMother" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
              <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Suffix</label>
                <input type="text" name="suffixMother" id="suffixMother" placeholder="JR, SR…" class="w-full px-3 py-2 text-sm bg-surface input-focus"></div>
            </div>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 11. Children -->
          <div>
            <div class="sub-label"><span class="num">11.</span> Children (up to 5)</div>
            <?php for($i=1;$i<=5;$i++): ?>
            <div class="family-row">
              <div class="family-row-label">Child <?= $i ?></div>
              <div class="grid grid-cols-5 gap-3">
                <div class="space-y-1 col-span-2"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Full Name</label>
                  <input type="text" name="fullnameChild<?=$i?>" id="fullnameChild<?=$i?>" placeholder="Full name" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Occupation</label>
                  <input type="text" name="occupationChild<?=$i?>" id="occupationChild<?=$i?>" placeholder="Occupation" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Income</label>
                  <input type="number" name="incomeChild<?=$i?>" id="incomeChild<?=$i?>" placeholder="0.00" min="0" step="0.01" inputmode="decimal" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Age</label>
                    <input type="number" name="ageChild<?=$i?>" id="ageChild<?=$i?>" placeholder="Age" min="0" max="120" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Working?</label>
                    <select name="isWorkingChild<?=$i?>" id="isWorkingChild<?=$i?>" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"><option value="">—</option><option>Yes</option><option>No</option></select></div>
                </div>
              </div>
            </div>
            <?php endfor; ?>
          </div>

          <hr style="border-color:rgba(149,165,166,.30)">

          <!-- 12. Dependents -->
          <div>
            <div class="sub-label"><span class="num">12.</span> Dependents (up to 2)</div>
            <?php for($i=1;$i<=2;$i++): ?>
            <div class="family-row">
              <div class="family-row-label">Dependent <?= $i ?></div>
              <div class="grid grid-cols-5 gap-3">
                <div class="space-y-1 col-span-2"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Full Name</label>
                  <input type="text" name="fullnameDependent<?=$i?>" id="fullnameDependent<?=$i?>" placeholder="Full name" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Occupation</label>
                  <input type="text" name="occupationDependent<?=$i?>" id="occupationDependent<?=$i?>" placeholder="Occupation" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Income</label>
                  <input type="number" name="incomeDependent<?=$i?>" id="incomeDependent<?=$i?>" placeholder="0.00" min="0" step="0.01" inputmode="decimal" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                <div class="grid grid-cols-2 gap-2">
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Age</label>
                    <input type="number" name="ageDependent<?=$i?>" id="ageDependent<?=$i?>" placeholder="Age" min="0" max="120" inputmode="numeric" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"></div>
                  <div class="space-y-1"><label class="text-[11px] font-mono uppercase tracking-wider text-secondary">Working?</label>
                    <select name="isWorkingDependent<?=$i?>" id="isWorkingDependent<?=$i?>" class="w-full px-3 py-2 text-sm bg-surface-container-lowest input-focus"><option value="">—</option><option>Yes</option><option>No</option></select></div>
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
          <p class="text-xs text-secondary font-mono italic">Section 2 of 2: All asterisk (*) fields are mandatory</p>
          <button id="btnStep2" onclick="saveStep2()"
                  class="bg-primary text-white flex items-center gap-2 px-6 py-3 rounded-lg font-bold text-sm hover:bg-primary-container transition-all active:scale-95 disabled:opacity-60">
            <span class="btn-spin"></span>
            Submit Registration
            <span class="material-symbols-outlined text-lg">check_circle</span>
          </button>
        </div>
      </div>

    </div><!-- /step2 -->


    <!-- ════════════════════════════════════
         SUCCESS
    ════════════════════════════════════ -->
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
        <span class="material-symbols-outlined text-[80px] text-primary" style="font-variation-settings:'FILL' 1">shield_person</span>
        <p class="text-[10px] font-mono uppercase tracking-widest text-primary">Official OSCA Data Management Subsystem</p>
      </div>
    </div>

  </main>
</div><!-- /ml-64 -->

<div id="toast" role="status" aria-live="polite"></div>

<script>
let currentStep = 1;

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

function goStep(n){
  document.getElementById(`step${currentStep}`).classList.remove('active');
  currentStep = n;
  document.getElementById(`step${currentStep}`).classList.add('active');
  updateProgress();
  window.scrollTo({top:0, behavior:'smooth'});
}
function updateProgress(){
  for(let i=1;i<=3;i++){
    const dot = document.getElementById(`dot${i}`);
    dot.className = `w-9 h-9 rounded-full border-2 flex items-center justify-center text-sm font-bold step-dot `;
    if(i < currentStep){ dot.className += `border-success bg-surface text-success`; dot.style.borderColor='#2e7d32'; dot.innerHTML='<span class="material-symbols-outlined text-sm">check</span>'; }
    else if(i === currentStep){ dot.className += `border-primary bg-primary text-white`; dot.style.borderColor=''; dot.textContent=i; }
    else { dot.className += `bg-surface text-on-surface-variant`; dot.style.borderColor='#95a5a6'; dot.textContent=i; }
  }
  document.getElementById('line1fill').style.width = currentStep > 1 ? '100%' : '0';
  document.getElementById('line2fill').style.width = currentStep > 2 ? '100%' : '0';
}

function required(id){
  const el = document.getElementById(id);
  const val = el.value.trim();
  if(!val){ el.classList.add('error'); return false; }
  el.classList.remove('error'); return true;
}

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
  for(let i=1;i<=5;i++){
    d[`fullnameChild${i}`]=v(`fullnameChild${i}`); d[`occupationChild${i}`]=v(`occupationChild${i}`);
    d[`incomeChild${i}`]=v(`incomeChild${i}`); d[`ageChild${i}`]=v(`ageChild${i}`);
    d[`isWorkingChild${i}`]=v(`isWorkingChild${i}`);
  }
  for(let i=1;i<=2;i++){
    d[`fullnameDependent${i}`]=v(`fullnameDependent${i}`); d[`occupationDependent${i}`]=v(`occupationDependent${i}`);
    d[`incomeDependent${i}`]=v(`incomeDependent${i}`); d[`ageDependent${i}`]=v(`ageDependent${i}`);
    d[`isWorkingDependent${i}`]=v(`isWorkingDependent${i}`);
  }
  return d;
}

async function post(data){
  const body = new URLSearchParams(data);
  const res = await fetch('save.php', {method:'POST', body});
  return res.json();
}

async function saveStep1(){
  const ok = [
    required('lastnameApplicant'), required('firstnameApplicant'),
    required('month'), required('date'), required('year'),
    required('maritalStatus'), required('sex'), required('contactNumber'),
    (document.getElementById('emailNA').checked || required('emailAddress')),
    required('barangay'),
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

async function saveStep2(){
  const btn = document.getElementById('btnStep2');
  setLoading(btn, true);
  try {
    const res = await post(collectStep2());
    if(res.success){ toast('Registration complete!','success'); setTimeout(showSuccess,700); }
    else toast(res.message,'error');
  } catch(e){ toast('Network error. Please try again.','error'); }
  finally { setLoading(btn, false); }
}

function showSuccess(){
  document.getElementById('step2').classList.remove('active');
  document.getElementById('success-screen').style.display='block';
  currentStep=3; updateProgress();
  window.scrollTo({top:0, behavior:'smooth'});
}

function toast(msg, type='success'){
  const el = document.getElementById('toast');
  el.textContent=msg; el.className=`show ${type}`;
  clearTimeout(el._t);
  el._t=setTimeout(()=>el.classList.remove('show'),3500);
}
function setLoading(btn, on){
  btn.disabled=on; btn.classList.toggle('loading',on);
}

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
document.addEventListener('DOMContentLoaded', function(){
  const label=document.getElementById('emailNALabel');
  if(label) label.addEventListener('click', function(){
    const cb=document.getElementById('emailNA');
    cb.checked=!cb.checked; toggleEmailNA(cb);
  });
  document.querySelectorAll('input, select').forEach(el => {
    el.addEventListener('focus', ()=>{ el.parentElement.querySelector('label')?.classList.add('text-primary'); });
    el.addEventListener('blur', ()=>{ el.parentElement.querySelector('label')?.classList.remove('text-primary'); });
  });
});

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