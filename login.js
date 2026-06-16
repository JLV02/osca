// ── Toggle password visibility ────────────────────────────────
const toggleBtn = document.getElementById('togglePw');
const pwIcon    = document.getElementById('pwIcon');

toggleBtn.addEventListener('click', function () {
  const pw = document.getElementById('password');
  const isPassword = pw.type === 'password';
  pw.type = isPassword ? 'text' : 'password';
  pwIcon.textContent = isPassword ? 'visibility_off' : 'visibility';
});

// ── Enter key submits ─────────────────────────────────────────
document.addEventListener('keydown', function (e) {
  if (e.key === 'Enter') handleLogin();
});

// ── Login handler ─────────────────────────────────────────────
async function handleLogin() {
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  const btn      = document.getElementById('btnLogin');
  const spinner  = btn.querySelector('.btn-spinner');

  hideAlert();

  if (!username || !password) {
    showAlert('Please enter both username and password.');
    shakeInputs();
    return;
  }

  // Loading state
  btn.disabled = true;
  spinner.classList.remove('hidden');
  document.getElementById('loginBtnText').textContent = 'Signing in…';

  try {
    const body = new URLSearchParams({ username, password });
    const res  = await fetch('auth.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      document.getElementById('loginBtnText').textContent = 'Authenticating…';
      setTimeout(() => { window.location.href = 'dashboard.php'; }, 900);
    } else {
      showAlert(data.message || 'Invalid username or password.');
      shakeInputs();
      btn.disabled = false;
      spinner.classList.add('hidden');
      document.getElementById('loginBtnText').textContent = 'Sign In';
    }
  } catch (e) {
    showAlert('Connection error. Please try again.');
    btn.disabled = false;
    spinner.classList.add('hidden');
    document.getElementById('loginBtnText').textContent = 'Sign In';
  }
}

function showAlert(msg) {
  const el = document.getElementById('loginAlert');
  document.getElementById('loginAlertMsg').textContent = msg;
  el.classList.remove('hidden');
}

function hideAlert() {
  document.getElementById('loginAlert').classList.add('hidden');
}

function shakeInputs() {
  ['username', 'password'].forEach(id => {
    const el = document.getElementById(id);
    el.classList.remove('shake');
    void el.offsetWidth;
    el.classList.add('shake');
    el.addEventListener('animationend', () => el.classList.remove('shake'), { once: true });
  });
}

// ── Attach button click ───────────────────────────────────────
document.getElementById('btnLogin').addEventListener('click', handleLogin);