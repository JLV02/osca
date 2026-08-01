<?php
session_start();
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#1d3246">
  <title>Admin Login — OSCA Registry</title>
  <link rel="stylesheet" href="assets/css/fonts.css">
  <link rel="stylesheet" href="assets/css/tailwind.css">
  <link rel="stylesheet" href="login.css">
  <style>
    /* Hide native browser password-reveal icons so only our custom toggle shows */
    input[type="password"]::-ms-reveal,
    input[type="password"]::-ms-clear {
      display: none;
    }
    input[type="password"]::-webkit-credentials-auto-fill-button,
    input[type="password"]::-webkit-textfield-decoration-container {
      visibility: hidden;
      display: none !important;
      pointer-events: none;
    }
  </style>
</head>
<body class="bg-[#ECF0F1] font-body min-h-screen flex items-center justify-center p-4 md:p-6">

  <main class="w-full max-w-5xl bg-white rounded-lg overflow-hidden flex flex-col md:flex-row"
        style="min-height:600px; border:1px solid rgba(149,165,166,.30); box-shadow:0 4px 20px rgba(29,50,70,.10)">

    <!-- ── LEFT PANEL ── -->
    <section class="w-full md:w-[45%] flex flex-col justify-between relative overflow-hidden p-10"
             style="background: linear-gradient(135deg, #1d3246 0%, #34495e 100%);">
      <div class="absolute -top-12 -right-12 w-64 h-64 rounded-full border border-white/10 pointer-events-none"></div>
      <div class="absolute -bottom-24 -left-24 w-80 h-80 rounded-full border border-white/5 pointer-events-none"></div>

      <div class="relative z-10">
        <!-- Himamaylan City Logo -->
        <div class="mb-8">
          <div class="w-28 h-28 rounded-2xl flex items-center justify-center p-2"
               style="background:rgba(255,255,255,0.10); backdrop-filter:blur(4px); border:1px solid rgba(255,255,255,0.18);">
            <img src="HimCity_Logo_nobg.png" alt="Himamaylan City Seal"
                 class="w-full h-full object-contain"
                 style="filter:drop-shadow(0 2px 8px rgba(0,0,0,0.25));">
          </div>
        </div>
        <h1 class="font-display text-3xl font-extrabold text-white tracking-tight leading-tight mb-2">OSCA Registry</h1>
        <p class="text-white/80 text-base mb-1">Office for Senior Citizens Affairs</p>
        <p class="text-white/50 text-xs uppercase tracking-widest font-mono">Administration Portal</p>
      </div>

      <div class="relative z-10 border-t border-white/20 pt-5">
        <p class="text-white/80 text-xs font-semibold italic tracking-wider uppercase font-mono">
          Serving with Dignity and Care
        </p>
      </div>
    </section>

    <!-- ── RIGHT PANEL ── -->
    <section class="w-full md:w-[55%] bg-white flex flex-col justify-center p-10">
      <div class="max-w-sm mx-auto w-full">

        <header class="mb-8">
          <h2 class="font-display text-[2rem] font-bold text-primary leading-tight mb-1">Administrator Login</h2>
          <p class="text-on-surface-variant text-sm font-body">Sign in to access the registry dashboard</p>
        </header>

        <!-- alert -->
        <div id="loginAlert" class="hidden mb-5 px-4 py-3 rounded-lg bg-[#ffdad6] border-l-4 border-[#ba1a1a] text-[#ba1a1a] text-sm flex items-start gap-2">
          <span class="material-symbols-outlined text-base mt-0.5 shrink-0">error</span>
          <span id="loginAlertMsg"></span>
        </div>

        <div class="space-y-5">

          <!-- username -->
          <div class="space-y-1.5">
            <label for="username" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-on-surface-variant font-mono">
              <span class="material-symbols-outlined text-[16px]">person</span>
              Username
            </label>
            <input type="text" id="username" name="username"
                   placeholder="Enter your username"
                   autocomplete="username"
                   class="w-full bg-white text-sm text-on-surface placeholder:text-outline transition focus:outline-none"
                   style="border:1px solid #95a5a6; border-radius:0.375rem; padding:12px 16px;"
                   onfocus="this.style.borderColor='#1d3246';this.style.boxShadow='0 0 0 2px rgba(29,50,70,.20)'"
                   onblur="this.style.borderColor='#95a5a6';this.style.boxShadow='none'">
          </div>

          <!-- password -->
          <div class="space-y-1.5">
            <label for="password" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-on-surface-variant font-mono">
              <span class="material-symbols-outlined text-[16px]">lock</span>
              Password
            </label>
            <div class="relative">
              <input type="password" id="password" name="password"
                     placeholder="Enter your password"
                     autocomplete="current-password"
                     class="w-full bg-white text-sm text-on-surface placeholder:text-outline transition focus:outline-none pr-12"
                     style="border:1px solid #95a5a6; border-radius:0.375rem; padding:12px 48px 12px 16px;"
                     onfocus="this.style.borderColor='#1d3246';this.style.boxShadow='0 0 0 2px rgba(29,50,70,.20)'"
                     onblur="this.style.borderColor='#95a5a6';this.style.boxShadow='none'">
             <button type="button" id="togglePw"
                      class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-primary transition-colors p-1"
                      aria-label="Toggle password visibility">
                <span class="material-symbols-outlined text-xl" id="pwIcon">visibility</span>
              </button>
            </div>
            <div id="capsLockWarning" class="hidden items-center gap-1.5 text-xs font-mono text-amber-600 mt-1">
              <span class="material-symbols-outlined text-[15px]">warning</span>
              <span>Caps Lock is on</span>
            </div>
          </div>
          <!-- remember + forgot -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" id="rememberDevice"
                     class="w-4 h-4 cursor-pointer"
                     style="border:1px solid #95a5a6; border-radius:0.25rem; accent-color:#1d3246">
              <span class="text-xs font-mono text-on-surface-variant">Remember device</span>
            </label>
            <a href="#" onclick="openForgotPasswordModal(); return false;" class="text-xs font-mono text-primary hover:underline underline-offset-4 transition-colors">
              Forgot Password?
            </a>
          </div>

          <button id="btnLogin"
                  class="w-full flex items-center justify-center gap-2 text-white font-semibold text-sm mt-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  style="background:#1d3246; border:none; border-radius:0.5rem; padding:14px; box-shadow:none;"
                  onmouseover="if(!this.disabled) this.style.background='#34495e'"
                  onmouseout="if(!this.disabled) this.style.background='#1d3246'">
            <span class="btn-spinner hidden w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span id="loginBtnText">Sign In</span>
          </button>

        </div>

        <!-- footer -->
        <footer class="mt-8 pt-5 flex items-center justify-center gap-2"
                style="border-top:1px solid rgba(149,165,166,.30)">
          <span class="material-symbols-outlined text-primary text-[18px]"
                style="font-variation-settings:'FILL' 1">verified_user</span>
          <p class="text-xs text-on-surface-variant font-mono">Secure access — Authorized personnel only</p>
        </footer>

      </div>
    </section>

  </main>

  <!-- ── FORGOT PASSWORD MODAL ── -->
  <div id="forgotPasswordModal" style="display:none; position:fixed; inset:0; background:rgba(29,50,70,.45); z-index:1000; align-items:center; justify-content:center; padding:16px;">
    <div style="background:#fff; border-radius:0.5rem; border:1px solid rgba(149,165,166,.30); width:100%; max-width:400px; box-shadow:0 4px 20px rgba(29,50,70,.15);">
      <div style="background:#1d3246; color:#fff; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-radius:0.5rem 0.5rem 0 0;">
        <h3 style="font-family:'Hanken Grotesk',sans-serif; font-weight:700; font-size:1rem; margin:0;">Forgot Password?</h3>
        <button onclick="closeForgotPasswordModal()" style="background:none; border:none; color:rgba(255,255,255,.7); cursor:pointer; display:flex; align-items:center;">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div style="padding:22px 20px;">
        <div style="display:flex; gap:12px; align-items:flex-start; margin-bottom:14px;">
          <span class="material-symbols-outlined" style="color:#1d3246; font-size:28px; flex-shrink:0;">admin_panel_settings</span>
          <p style="font-size:.875rem; color:#1b1c1d; line-height:1.6; margin:0;">
            For security, passwords can only be reset by an administrator — there's no automatic email reset since this system runs offline.
          </p>
        </div>
        <div style="background:#f5f3f5; border-radius:0.5rem; padding:14px 16px; font-size:.82rem; color:#43474c; line-height:1.6;">
          <strong style="color:#1d3246;">What to do:</strong>
          <ol style="margin:8px 0 0; padding-left:18px;">
            <li>Contact any administrator in your office</li>
            <li>Ask them to reset your password from <em>Settings → Staff Accounts</em></li>
            <li>Sign in with the new temporary password they give you</li>
          </ol>
        </div>
      </div>
      <div style="padding:14px 20px; border-top:1px solid rgba(149,165,166,.30); display:flex; justify-content:flex-end;">
        <button onclick="closeForgotPasswordModal()" style="background:#1d3246; color:#fff; border:none; border-radius:0.5rem; padding:9px 18px; font-size:.85rem; font-weight:600; cursor:pointer;">
          Got it
        </button>
      </div>
    </div>
  </div>

  <script src="login.js"></script>
</body>
</html>