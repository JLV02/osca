// ═══════════════════ REALTIME DASHBOARD SYNC ═════════════════
(function () {
  const POLL_INTERVAL_MS = 1500;

  let _pollTimer = null;
  let _inFlight  = false;
  let _firstPoll = true;
  let _knownIds  = new Set(
    Array.from(document.querySelectorAll('.data-table tbody tr.table-row'))
         .map(tr => tr.dataset.id)
  );

  function isAnyModalOpen() {
    return !!document.querySelector('.modal-overlay.open');
  }

  async function pollOnce() {
    if (_inFlight) return;
    if (isAnyModalOpen()) return;
    if (typeof _searchWrap !== 'undefined' && _searchWrap && _searchWrap.classList.contains('searching')) return;

    _inFlight = true;
    try {
      const current = new URL(window.location.href);
      const url = new URL('realtime_check.php', window.location.href);
      ['page', 'limit', 'search', 'filter', 'sex', 'age', 'pwd'].forEach(function (k) {
        if (current.searchParams.has(k)) url.searchParams.set(k, current.searchParams.get(k));
      });

      const res  = await fetch(url.toString());
      const data = await res.json();
      if (!data.success) return;

      reconcileStats(data);
      reconcileTable(data);
      reconcilePagination(data);
    } catch (e) {
      // silent fail — next interval will retry
    } finally {
      _inFlight  = false;
      _firstPoll = false;
    }
  }

  function reconcilePagination(data) {
    const footer = document.getElementById('paginationFooter');
    if (!footer || typeof data.paginationHtml !== 'string') return;
    footer.innerHTML = data.paginationHtml;
  }

  function reconcileStats(data) {
    const totalEl = document.querySelector('.stat-total');
    if (totalEl) totalEl.textContent = data.total.toLocaleString();

    const todayEl = document.querySelector('.stat-today');
    if (todayEl) todayEl.textContent = data.today.toLocaleString();

    if (window.OSCA) window.OSCA.archivedCount = data.archivedCount;

    const badge = document.getElementById('settingsArchivedBadge');
    if (badge) {
      badge.textContent = data.archivedCount;
      badge.classList.toggle('hidden', data.archivedCount <= 0);
    }

    const popupBadge = document.getElementById('settingsPopupArchiveBadge');
    if (popupBadge) {
      popupBadge.textContent = data.archivedCount;
      popupBadge.classList.toggle('hidden', data.archivedCount <= 0);
    }
  }

  function reconcileTable(data) {
    const tbody = document.querySelector('.data-table tbody');
    if (!tbody) return;

    const incomingIds = data.ids.map(String);
    const existing = {};
    tbody.querySelectorAll('tr.table-row').forEach(function (tr) {
      existing[tr.dataset.id] = tr;
    });

    Object.keys(existing).forEach(function (id) {
      if (incomingIds.indexOf(id) === -1) {
        existing[id].remove();
        delete existing[id];
      }
    });

    let prevNode = null;
    let newlyAdded = [];

    incomingIds.forEach(function (id) {
      let tr = existing[id];
      const isNew = !tr;

      if (isNew) {
        const wrap = document.createElement('tbody');
        wrap.innerHTML = data.rowsHtml[id] || '';
        tr = wrap.firstElementChild;
        if (!tr) return;
        if (!_knownIds.has(id)) newlyAdded.push(id);
      }

      const afterNode = prevNode ? prevNode.nextSibling : tbody.firstChild;
      if (afterNode !== tr) tbody.insertBefore(tr, afterNode);

      prevNode = tr;
    });

    if (typeof updateRowNumbers === 'function') updateRowNumbers();

    _knownIds = new Set(incomingIds);
  }

  function startPolling() {
    stopPolling();
    _pollTimer = setInterval(pollOnce, POLL_INTERVAL_MS);
  }

  function stopPolling() {
    if (_pollTimer) clearInterval(_pollTimer);
    _pollTimer = null;
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stopPolling();
    } else {
      pollOnce();
      startPolling();
    }
  });

  window.OSCA_triggerSync = pollOnce;

  startPolling();
})();