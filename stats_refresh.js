// ── Live stat refresh ─────────────────────────────────────────
// Call this after any operation that changes the record count
// (delete, registration submit, etc.)

(function () {

  // Read the active barangay filter from the URL so the barangay card
  // also updates correctly after a delete.
  function getActiveBarangay() {
    return new URLSearchParams(window.location.search).get('filter') || 'all';
  }

  // ── Animated counter ──────────────────────────────────────────
  function animateCount(el, from, to) {
    if (from === to || !el) return;
    const duration = 350; // ms
    const start    = performance.now();
    function step(now) {
      const t   = Math.min((now - start) / duration, 1);
      const val = Math.round(from + (to - from) * t);
      el.textContent = val.toLocaleString();
      if (t < 1) requestAnimationFrame(step);
      else el.textContent = to.toLocaleString();
    }
    requestAnimationFrame(step);
  }

  // ── Fetch & apply stats ───────────────────────────────────────
  window.refreshStats = async function () {
    const brgy = getActiveBarangay();
    const url  = 'get_stats.php?barangay=' + encodeURIComponent(brgy) +
                 '&_=' + Date.now(); // cache-bust
    try {
      const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      const data = await res.json();
      if (!data.success) return;

      // Total registrants
      const totalEl = document.querySelector('.stat-total');
      if (totalEl) {
        const from = parseInt(totalEl.textContent.replace(/,/g, '')) || 0;
        animateCount(totalEl, from, data.total);
      }

      // Registered today
      const todayEl = document.querySelector('.stat-today');
      if (todayEl) {
        const from = parseInt(todayEl.textContent.replace(/,/g, '')) || 0;
        animateCount(todayEl, from, data.today);
      }

      // Barangay count (only when a barangay filter is active)
      if (data.barangayCount !== null) {
        // The barangay card shows the big number as the second .stat-* sibling
        const brgyEl = document.querySelectorAll('.font-display.font-bold.text-3xl')[1];
        if (brgyEl) {
          const from = parseInt(brgyEl.textContent.replace(/[^0-9]/g, '')) || 0;
          animateCount(brgyEl, from, data.barangayCount);
        }
      }
    } catch (_) { /* silent fail — network blip, no crash */ }
  };

  // ── Hook into the existing delete success path ────────────────
  // dashboard.js calls window.deleteSuccess (or similar) — we patch
  // the fetch inside confirmDeleteBtn click instead via MutationObserver
  // on the toast, which fires after every successful operation.
  const toast = document.getElementById('toast');
  if (toast) {
    const mo = new MutationObserver(() => {
      if (toast.classList.contains('success')) {
        // A success toast just appeared — refresh stats
        refreshStats();
      }
    });
    mo.observe(toast, { attributes: true, attributeFilter: ['class'] });
  }

  // ── Periodic polling (30 s) — keeps the tab live ──────────────
  setInterval(refreshStats, 30_000);

})();