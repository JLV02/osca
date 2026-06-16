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
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Hanken+Grotesk:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="login.css">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            'primary':                  '#1d3246',
            'primary-container':        '#34495e',
            'on-primary':               '#ffffff',
            'surface':                  '#fbf9fb',
            'surface-container-low':    '#f5f3f5',
            'surface-container-lowest': '#ffffff',
            'on-surface':               '#1b1c1d',
            'on-surface-variant':       '#43474c',
            'outline-variant':          '#95a5a6',
            'outline':                  '#74777d',
            'error':                    '#ba1a1a',
            'error-container':          '#ffdad6',
          },
          fontFamily: {
            'body':    ['Inter', 'sans-serif'],
            'display': ['Hanken Grotesk', 'sans-serif'],
            'mono':    ['JetBrains Mono', 'monospace'],
          },
          borderRadius: {
            'sm':      '0.125rem',
            'DEFAULT': '0.25rem',
            'md':      '0.375rem',
            'lg':      '0.5rem',
            'xl':      '0.75rem',
            'full':    '9999px',
          },
        }
      }
    }
  </script>
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
          </div>

          <!-- remember + forgot -->
          <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <input type="checkbox" id="rememberDevice"
                     class="w-4 h-4 cursor-pointer"
                     style="border:1px solid #95a5a6; border-radius:0.25rem; accent-color:#1d3246">
              <span class="text-xs font-mono text-on-surface-variant">Remember device</span>
            </label>
            <a href="#" class="text-xs font-mono text-primary hover:underline underline-offset-4 transition-colors">
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

  <script src="login.js"></script>
</body>
</html>