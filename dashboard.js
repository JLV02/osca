// ── Sidebar toggle (mobile) ──────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');

function toggleSidebar() {
  const isOpen = sidebar.classList.toggle('open');
  if (backdrop) {
    backdrop.classList.toggle('show', isOpen);
    document.body.style.overflow = isOpen ? 'hidden' : '';
  }
}
function closeSidebar() {
  sidebar.classList.remove('open');
  if (backdrop) { backdrop.classList.remove('show'); document.body.style.overflow = ''; }
}
if (backdrop) backdrop.addEventListener('click', closeSidebar);
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ── Toast ────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.className = `show ${type}`;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3500);
}

// ── Incremental search (fetch-based — no page reload) ─────────
let _searchTimer  = null;
let _activeSearch = null; // AbortController for in-flight requests

const _searchInput = document.getElementById('searchInput');
const _searchWrap  = _searchInput ? _searchInput.closest('.search-wrap') : null;
const _clearBtn    = document.getElementById('searchClear');

// Init clear button visibility on page load
if (_searchInput && _clearBtn && _searchInput.value.trim().length > 0) {
  _clearBtn.classList.add('visible');
}

function handleIncrementalSearch(val) {
  // Toggle clear button
  if (_clearBtn) {
    val.length > 0 ? _clearBtn.classList.add('visible') : _clearBtn.classList.remove('visible');
  }

  // Cancel any pending timer or in-flight request
  clearTimeout(_searchTimer);
  if (_activeSearch) { _activeSearch.abort(); _activeSearch = null; }

  _searchTimer = setTimeout(() => _doSearch(val.trim()), 380);
}

async function _doSearch(val) {
  // Build query URL (same page, same filters, just update search param)
  const url = new URL(window.location.href);
  if (val) url.searchParams.set('search', val);
  else url.searchParams.delete('search');
  url.searchParams.set('page', 1);

  // Show spinner state
  if (_searchWrap) _searchWrap.classList.add('searching');

  // Fade existing table rows (not the whole wrap, keeps layout stable)
  _fadeRows(true);

  // Abort any previous fetch
  _activeSearch = new AbortController();
  try {
    const res  = await fetch(url.toString(), { signal: _activeSearch.signal });
    const html = await res.text();

    // Parse the returned HTML and extract just the tbody + pagination
    const parser = new DOMParser();
    const doc    = parser.parseFromString(html, 'text/html');

    // Swap tbody content
    const newTbody = doc.querySelector('.data-table tbody');
    const curTbody = document.querySelector('.data-table tbody');
    if (newTbody && curTbody) {
      curTbody.innerHTML = newTbody.innerHTML;
      _fadeRows(false); // fade new rows in
    }

    // Swap empty state if needed
    const newEmpty = doc.querySelector('.empty-state');
    const curWrap  = document.querySelector('.table-wrap');
    const curEmpty = document.querySelector('.empty-state');
    if (newEmpty && curWrap) {
      curWrap.remove();
      const section = document.querySelector('.table-section');
      if (section) section.appendChild(newEmpty);
    } else if (!newEmpty && curEmpty && doc.querySelector('.table-wrap')) {
      // Results came back, remove empty state and restore table
      curEmpty.remove();
      // Full swap in this edge case
      const section = document.querySelector('.table-section');
      const newWrap = doc.querySelector('.table-wrap');
      if (section && newWrap) section.insertBefore(newWrap, section.querySelector('.pagination') || null);
    }

    // Swap pagination
    const newPagination = doc.querySelector('.pagination');
    const curPagination = document.querySelector('.pagination');
    if (newPagination && curPagination) curPagination.outerHTML = newPagination.outerHTML;
    else if (!newPagination && curPagination) curPagination.remove();

    // Update browser URL bar silently (no reload)
    history.replaceState(null, '', url.toString());

  } catch (e) {
    if (e.name !== 'AbortError') {
      // Fallback: hard navigate if fetch fails
      window.location.href = url.toString();
    }
  } finally {
    if (_searchWrap) _searchWrap.classList.remove('searching');
    _activeSearch = null;
  }
}

function _fadeRows(out) {
  const rows = document.querySelectorAll('.data-table tbody .table-row');
  rows.forEach((row, i) => {
    row.style.transition = out
      ? 'opacity .15s ease'
      : `opacity .22s ease ${i * 0.03}s, transform .22s ease ${i * 0.03}s`;
    if (out) {
      row.style.opacity = '0';
    } else {
      row.style.opacity = '0';
      row.style.transform = 'translateY(5px)';
      // Force reflow then fade in
      void row.offsetHeight;
      row.style.opacity = '1';
      row.style.transform = 'translateY(0)';
    }
  });
}

function clearSearch() {
  if (_searchInput) {
    _searchInput.value = '';
    _searchInput.focus();
  }
  if (_clearBtn) _clearBtn.classList.remove('visible');
  _doSearch('');
}

// Press '/' anywhere to jump to search
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement !== _searchInput
      && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
    e.preventDefault();
    if (_searchInput) { _searchInput.focus(); _searchInput.select(); }
  }
});

// ── Filter helpers (CHANGE 4 & 5) ────────────────────────────
function applyBarangayFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('filter', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function applyLimit(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('limit', val);
  url.searchParams.set('page', 1);
  window.location.href = url.toString();
}

// ═══════════════════ VIEW MODAL — PAGINATED STEPS ════════════
let viewCurrentStep = 1;
const VIEW_TOTAL_STEPS = 2;

async function viewRecord(id) {
  openModal('viewModal');
  document.getElementById('modalBody').innerHTML = '<div class="modal-loading">⏳ Loading record…</div>';
  document.getElementById('modalTitle').textContent = 'Applicant Details';

  try {
    const res  = await fetch(`get_record.php?id=${id}`);
    const data = await res.json();
    if (!data.success) {
      document.getElementById('modalBody').innerHTML = `<p style="color:red">Error: ${data.message}</p>`;
      return;
    }
    const r  = data.record;
    const suffix = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';
    document.getElementById('modalTitle').textContent =
      `${r.lastnameApplicant}, ${r.firstnameApplicant} ${r.middlenameApplicant}${suffix ? ' ' + suffix : ''}`;

    viewCurrentStep = 1;
    renderViewModal(r);
  } catch (e) {
    document.getElementById('modalBody').innerHTML = '<p style="color:red">Failed to load record.</p>';
  }
}

function renderViewModal(r) {
  const na = v => v || '<span style="color:#aaa">—</span>';
  const suffix = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';


  const step1HTML = `
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="12" y2="17"/></svg> Identifying Information</div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Full Name</div>
          <div class="detail-value">${na(r.lastnameApplicant)}, ${na(r.firstnameApplicant)} ${na(r.middlenameApplicant)}${suffix ? ' <span class="suffix-badge">'+suffix+'</span>' : ''}</div>
        </div>
        <div class="detail-item"><div class="detail-label">Sex</div><div class="detail-value">${na(r.sex)}</div></div>
        <div class="detail-item">
          <div class="detail-label">Birthdate</div>
          <div class="detail-value">${r.month && r.date && r.year ? `${r.month} ${r.date}, ${r.year}` : '—'}</div>
        </div>
        <div class="detail-item"><div class="detail-label">Birthplace</div><div class="detail-value">${na(r.birthplace)}</div></div>
        <div class="detail-item"><div class="detail-label">Marital Status</div><div class="detail-value">${na(r.maritalStatus)}</div></div>
        <div class="detail-item"><div class="detail-label">Religion</div><div class="detail-value">${na(r.religion)}</div></div>
        <div class="detail-item"><div class="detail-label">Contact Number</div><div class="detail-value">${na(r.contactNumber)}</div></div>
        <div class="detail-item"><div class="detail-label">Email Address</div><div class="detail-value">${na(r.emailAddress)}</div></div>
        <div class="detail-item"><div class="detail-label">FB Messenger</div><div class="detail-value">${na(r.fbMessenger)}</div></div>
        <div class="detail-item"><div class="detail-label">Ethnic Origin</div><div class="detail-value">${na(r.ethnicOrigin)}</div></div>
        <div class="detail-item"><div class="detail-label">Language Spoken</div><div class="detail-value">${na(r.languageSpoken)}</div></div>
        <div class="detail-item"><div class="detail-label">Employment/Business</div><div class="detail-value">${na(r.employment_business)}</div></div>
        <div class="detail-item"><div class="detail-label">Has Pension</div><div class="detail-value">${na(r.hasPension)}</div></div>
        <div class="detail-item"><div class="detail-label">Can Travel</div><div class="detail-value">${na(r.travelCapability)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg> Government IDs</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">OSCA ID</div><div class="detail-value">${na(r.osca_ID)}</div></div>
        <div class="detail-item"><div class="detail-label">GSIS/SSS ID</div><div class="detail-value">${na(r.gsis_sss_ID)}</div></div>
        <div class="detail-item"><div class="detail-label">TIN ID</div><div class="detail-value">${na(r.tin_ID)}</div></div>
        <div class="detail-item"><div class="detail-label">PhilHealth ID</div><div class="detail-value">${na(r.philHealth_ID)}</div></div>
        <div class="detail-item"><div class="detail-label">SC Asso. ID</div><div class="detail-value">${na(r.sc_asso_ID)}</div></div>
        <div class="detail-item"><div class="detail-label">Other Govt. ID</div><div class="detail-value">${na(r.other_govt_ID)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg> Address</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Barangay</div><div class="detail-value">${na(r.barangay)}</div></div>
        <div class="detail-item"><div class="detail-label">Purok</div><div class="detail-value">${na(r.purok)}</div></div>
        <div class="detail-item"><div class="detail-label">Street</div><div class="detail-value">${na(r.street)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Record Info</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Registered On</div><div class="detail-value">${formatDate(r.created_at)}</div></div>
      </div>
    </div>`;

  const step2HTML = `
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/></svg> Spouse</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameSpouse)}</div></div>
        <div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameSpouse)}</div></div>
        <div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameSpouse)}</div></div>
        <div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixSpouse)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/><line x1="12" y1="3" x2="12" y2="5"/></svg> Father</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameFather)}</div></div>
        <div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameFather)}</div></div>
        <div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameFather)}</div></div>
        <div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixFather)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg> Mother</div>
      <div class="detail-grid">
        <div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameMother)}</div></div>
        <div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameMother)}</div></div>
        <div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameMother)}</div></div>
        <div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixMother)}</div></div>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="6" r="3"/><path d="M5 21v-1a7 7 0 0 1 14 0v1"/><path d="M9 21v-4h6v4"/></svg> Children</div>
      ${buildChildrenTable(r)}
    </div>
    <div class="detail-section">
      <div class="detail-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Dependents</div>
      ${buildDependentsTable(r)}
    </div>`;

  const steps = [step1HTML, step2HTML];
  const stepLabels = ['Step 1 — Identifying Info', 'Step 2 — Family Composition'];

  document.getElementById('modalBody').innerHTML = `
    <div class="step-tabs">
      <button class="step-tab ${viewCurrentStep===1?'active':''}" onclick="viewGoToStep(1, _viewRecord)">
        <span class="step-tab-num">1</span> Identifying Info
      </button>
      <button class="step-tab ${viewCurrentStep===2?'active':''}" onclick="viewGoToStep(2, _viewRecord)">
        <span class="step-tab-num">2</span> Family Composition
      </button>
    </div>
    <div class="step-label-bar">${stepLabels[viewCurrentStep-1]}</div>
    <div class="step-content" id="viewStepContent">
      ${steps[viewCurrentStep-1]}
    </div>
    <div class="step-nav">
      <button class="btn btn-outline step-prev-btn" onclick="viewGoToStep(${viewCurrentStep-1}, _viewRecord)" ${viewCurrentStep===1?'disabled':''}>← Previous</button>
      <span class="step-counter">Page ${viewCurrentStep} of ${VIEW_TOTAL_STEPS}</span>
      <button class="btn btn-primary step-next-btn" onclick="viewGoToStep(${viewCurrentStep+1}, _viewRecord)" ${viewCurrentStep===VIEW_TOTAL_STEPS?'disabled':''}>Next →</button>
    </div>`;

  // store record on window for re-render
  window._viewRecord = r;
}

function viewGoToStep(step, r) {
  if (step < 1 || step > VIEW_TOTAL_STEPS) return;
  viewCurrentStep = step;
  renderViewModal(r);
  document.getElementById('modalBody').scrollTop = 0;
}

function buildDependentsTable(r) {
  let rows = '';
  for (let i = 1; i <= 2; i++) {
    const name = r[`fullnameDependent${i}`];
    if (!name) continue;
    rows += `<tr>
      <td>${name}</td>
      <td>${r[`occupationDependent${i}`] || '—'}</td>
      <td>${r[`ageDependent${i}`] || '—'}</td>
      <td>${r[`isWorkingDependent${i}`] || '—'}</td>
    </tr>`;
  }
  if (!rows) return '<p style="color:#aaa;font-size:.82rem;margin-top:8px">No dependents listed.</p>';
  return `<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin-top:12px">
    <table style="width:100%;border-collapse:collapse;font-size:.83rem;min-width:340px">
      <thead><tr style="background:#f0f4f8">
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Name</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Occupation</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Age</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Working?</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>
  </div>`;
}

function buildChildrenTable(r) {
  let rows = '';
  for (let i = 1; i <= 5; i++) {
    const name = r[`fullnameChild${i}`];
    if (!name) continue;
    rows += `<tr>
      <td>${name}</td>
      <td>${r[`occupationChild${i}`] || '—'}</td>
      <td>${r[`ageChild${i}`] || '—'}</td>
      <td>${r[`isWorkingChild${i}`] || '—'}</td>
    </tr>`;
  }
  if (!rows) return '<p style="color:#aaa;font-size:.82rem;margin-top:8px">No children listed.</p>';
  return `<div style="overflow-x:auto;-webkit-overflow-scrolling:touch;margin-top:12px">
    <table style="width:100%;border-collapse:collapse;font-size:.83rem;min-width:340px">
      <thead><tr style="background:#f0f4f8">
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Name</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Occupation</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Age</th>
        <th style="padding:7px 10px;text-align:left;color:#7a8fa6;font-size:.7rem;text-transform:uppercase">Working?</th>
      </tr></thead>
      <tbody>${rows}</tbody>
    </table>
  </div>`;
}


function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
}

// ═══════════════════ EDIT MODAL — PAGINATED STEPS ════════════
let editingId = null;
let editCurrentStep = 1;
const EDIT_TOTAL_STEPS = 2;
let _editRecord = null;

const BARANGAYS = ['Aguisan','Barangay I-Poblacion','Barangay II-Poblacion','Barangay III-Poblacion','Barangay IV-Poblacion','Buenavista','Cabadiangan','Cabanbanan','Carabalan','Caradioan','Libacao','Mahalang','Mambagaton','Nabalian','San Antonio','Saraet','Suay','Talaban','Tooy'].sort();

async function editRecord(id) {
  editingId = id;
  editCurrentStep = 1;
  openModal('editModal');
  document.getElementById('editModalBody').innerHTML = '<div class="modal-loading">⏳ Loading record…</div>';
  document.getElementById('editModalTitle').textContent = 'Edit Record';
  document.getElementById('saveEditBtn').disabled = false;

  try {
    const res  = await fetch(`get_record.php?id=${id}`);
    const data = await res.json();
    if (!data.success) {
      document.getElementById('editModalBody').innerHTML = `<p style="color:red">Error: ${data.message}</p>`;
      return;
    }
    const r = data.record;

    // CHANGE 1: fix suffix — treat 'N/A' as blank
    const suffixVal = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';

    const suffixOptions = ['','JR','SR','I','II','III','IV','V','VI'].map(s =>
      `<option value="${s}" ${suffixVal===s?'selected':''}>${s || '— None —'}</option>`).join('');

    const barangayOptions = BARANGAYS.map(b =>
      `<option value="${b}" ${r.barangay===b?'selected':''}>${b}</option>`).join('');

    const monthOptions = ['','January','February','March','April','May','June','July','August','September','October','November','December']
      .map(m => `<option value="${m}" ${r.month===m?'selected':''}>${m||'Month'}</option>`).join('');

    const dayOptions = Array.from({length:31},(_,i)=>i+1)
      .map(d => `<option value="${d}" ${parseInt(r.date)===d?'selected':''}>${d}</option>`).join('');

    const yearStart = new Date().getFullYear()-60;
    const yearOptions = Array.from({length:yearStart-1919},(_,i)=>yearStart-i)
      .map(y => `<option value="${y}" ${parseInt(r.year)===y?'selected':''}>${y}</option>`).join('');

    document.getElementById('editModalTitle').textContent = `Editing: ${r.lastnameApplicant}, ${r.firstnameApplicant}`;
    _editRecord = r;
    renderEditModal(r, suffixOptions, barangayOptions, monthOptions, dayOptions, yearOptions);
  } catch (e) {
    document.getElementById('editModalBody').innerHTML = '<p style="color:red">Failed to load record.</p>';
  }
}

function renderEditModal(r, suffixOptions, barangayOptions, monthOptions, dayOptions, yearOptions) {
  // Re-build select options from stored record if coming from tab switch
  if (!suffixOptions) {
    const suffixVal = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';
    suffixOptions = ['','JR','SR','I','II','III','IV','V','VI'].map(s =>
      `<option value="${s}" ${suffixVal===s?'selected':''}>${s || '— None —'}</option>`).join('');
    barangayOptions = BARANGAYS.map(b =>
      `<option value="${b}" ${r.barangay===b?'selected':''}>${b}</option>`).join('');
    monthOptions = ['','January','February','March','April','May','June','July','August','September','October','November','December']
      .map(m => `<option value="${m}" ${r.month===m?'selected':''}>${m||'Month'}</option>`).join('');
    dayOptions = Array.from({length:31},(_,i)=>i+1)
      .map(d => `<option value="${d}" ${parseInt(r.date)===d?'selected':''}>${d}</option>`).join('');
    const yearStart = new Date().getFullYear()-60;
    yearOptions = Array.from({length:yearStart-1919},(_,i)=>yearStart-i)
      .map(y => `<option value="${y}" ${parseInt(r.year)===y?'selected':''}>${y}</option>`).join('');
  }

  const step1HTML = `
    <div class="edit-form">
      <div class="edit-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg> Full Name</div>
      <div class="edit-grid edit-grid-4">
        <div class="edit-field"><label>Last Name *</label><input id="e_lastname" type="text" value="${esc(r.lastnameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" placeholder="E.G. DELA CRUZ"><span class="edit-field-hint" id="hint_lastname"></span></div>
        <div class="edit-field"><label>First Name *</label><input id="e_firstname" type="text" value="${esc(r.firstnameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" placeholder="E.G. JUAN"><span class="edit-field-hint" id="hint_firstname"></span></div>
        <div class="edit-field"><label>Middle Name</label><input id="e_middlename" type="text" value="${esc(r.middlenameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" placeholder="E.G. SANTOS (optional)"></div>
        <div class="edit-field"><label>Suffix</label><select id="e_suffix" class="edit-input">${suffixOptions}</select></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg> Address</div>
      <div class="edit-grid edit-grid-3">
        <div class="edit-field"><label>Barangay *</label><select id="e_barangay" class="edit-input"><option value="">— Select —</option>${barangayOptions}</select></div>
        <div class="edit-field"><label>Purok / Zone</label><input id="e_purok" type="text" value="${esc(r.purok)}" class="edit-input"></div>
        <div class="edit-field"><label>Street / House No.</label><input id="e_street" type="text" value="${esc(r.street)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Birthdate &amp; Personal</div>
      <div class="edit-grid edit-grid-3">
        <div class="edit-field"><label>Month</label><select id="e_month" class="edit-input">${monthOptions}</select></div>
        <div class="edit-field"><label>Day</label><select id="e_day" class="edit-input"><option value="">Day</option>${dayOptions}</select></div>
        <div class="edit-field"><label>Year</label><select id="e_year" class="edit-input"><option value="">Year</option>${yearOptions}</select></div>
        <div class="edit-field"><label>Birthplace</label><input id="e_birthplace" type="text" value="${esc(r.birthplace)}" class="edit-input"></div>
        <div class="edit-field"><label>Sex</label>
          <select id="e_sex" class="edit-input">
            <option value="">Select</option>
            <option value="Male"   ${r.sex==='Male'?'selected':''}>Male</option>
            <option value="Female" ${r.sex==='Female'?'selected':''}>Female</option>
          </select>
        </div>
        <div class="edit-field"><label>Marital Status</label>
          <select id="e_marital" class="edit-input">
            <option value="">Select</option>
            ${['Single','Married','Widowed','Separated'].map(s=>`<option value="${s}" ${r.maritalStatus===s?'selected':''}>${s}</option>`).join('')}
          </select>
        </div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 1.18h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 8.7a16 16 0 0 0 6 6l.9-.9a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21.72 16z"/></svg> Contact</div>
      <div class="edit-grid edit-grid-3">
        <div class="edit-field"><label>Contact Number *</label><input id="e_contact" type="tel" value="${esc(r.contactNumber)}" class="edit-input" maxlength="11" inputmode="numeric" oninput="enforceContactField(this)" placeholder="09XXXXXXXXX"><span class="edit-field-hint" id="hint_contact"></span></div>
        <div class="edit-field"><label>Email Address *</label><input id="e_email" type="email" value="${esc(r.emailAddress)}" class="edit-input"></div>
        <div class="edit-field"><label>FB Messenger</label><input id="e_fb" type="text" value="${esc(r.fbMessenger)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg> Government IDs</div>
      <div class="edit-grid edit-grid-3">
        <div class="edit-field"><label>OSCA ID</label><input id="e_osca"  type="text" value="${esc(r.osca_ID)}" class="edit-input"></div>
        <div class="edit-field"><label>GSIS/SSS ID</label><input id="e_gsis" type="text" value="${esc(r.gsis_sss_ID)}" class="edit-input"></div>
        <div class="edit-field"><label>TIN ID</label><input id="e_tin" type="text" value="${esc(r.tin_ID)}" class="edit-input"></div>
        <div class="edit-field"><label>PhilHealth ID</label><input id="e_phil" type="text" value="${esc(r.philHealth_ID)}" class="edit-input"></div>
        <div class="edit-field"><label>SC Asso. ID</label><input id="e_sc" type="text" value="${esc(r.sc_asso_ID)}" class="edit-input"></div>
        <div class="edit-field"><label>Other Govt. ID</label><input id="e_other" type="text" value="${esc(r.other_govt_ID)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg> Other Info</div>
      <div class="edit-grid edit-grid-3">
        <div class="edit-field"><label>Employment / Business</label><input id="e_employment" type="text" value="${esc(r.employment_business)}" class="edit-input"></div>
        <div class="edit-field"><label>Has Pension</label>
          <select id="e_pension" class="edit-input">
            <option value="">Select</option>
            <option value="Yes" ${r.hasPension==='Yes'?'selected':''}>Yes</option>
            <option value="No"  ${r.hasPension==='No'?'selected':''}>No</option>
          </select>
        </div>
        <div class="edit-field"><label>Can Travel</label>
          <select id="e_travel" class="edit-input">
            <option value="">Select</option>
            <option value="Yes" ${r.travelCapability==='Yes'?'selected':''}>Yes</option>
            <option value="No"  ${r.travelCapability==='No'?'selected':''}>No</option>
          </select>
        </div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Registration Date</div>
      <p style="font-size:.78rem;color:var(--muted);margin-bottom:10px">Change only to correct the recorded registration date. Leave as-is to keep the current date.</p>
      <div class="edit-grid edit-grid-3" style="max-width:420px">
        <div class="edit-field"><label>Month</label>
          <select id="e_reg_month" class="edit-input">
            <option value="">— Keep current —</option>
            ${['January','February','March','April','May','June','July','August','September','October','November','December'].map((m, idx) => {
              const cur = r.created_at ? new Date(r.created_at).getMonth() : -1;
              return `<option value="${m}" ${cur===idx?'selected':''}>${m}</option>`;
            }).join('')}
          </select>
        </div>
        <div class="edit-field"><label>Day</label>
          <select id="e_reg_day" class="edit-input">
            <option value="">— Keep current —</option>
            ${Array.from({length:31},(_,i)=>i+1).map(d => {
              const cur = r.created_at ? new Date(r.created_at).getDate() : 0;
              return `<option value="${d}" ${cur===d?'selected':''}>${d}</option>`;
            }).join('')}
          </select>
        </div>
        <div class="edit-field"><label>Year</label>
          <select id="e_reg_year" class="edit-input">
            <option value="">— Keep current —</option>
            ${Array.from({length:new Date().getFullYear()-1989},(_,i)=>new Date().getFullYear()-i).map(y => {
              const cur = r.created_at ? new Date(r.created_at).getFullYear() : 0;
              return `<option value="${y}" ${cur===y?'selected':''}>${y}</option>`;
            }).join('')}
          </select>
        </div>
      </div>
    </div>`;

  const step2HTML = `
    <div class="edit-form">
      <div class="edit-section-title"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/></svg> Spouse</div>
      <div class="edit-grid edit-grid-4">
        <div class="edit-field"><label>Last Name</label><input id="e_spouse_last" type="text" value="${esc(r.lastnameSpouse)}" class="edit-input"></div>
        <div class="edit-field"><label>First Name</label><input id="e_spouse_first" type="text" value="${esc(r.firstnameSpouse)}" class="edit-input"></div>
        <div class="edit-field"><label>Middle Name</label><input id="e_spouse_middle" type="text" value="${esc(r.middlenameSpouse)}" class="edit-input"></div>
        <div class="edit-field"><label>Suffix</label><input id="e_spouse_suffix" type="text" value="${esc(r.suffixSpouse)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/><line x1="12" y1="3" x2="12" y2="5"/></svg> Father</div>
      <div class="edit-grid edit-grid-4">
        <div class="edit-field"><label>Last Name</label><input id="e_father_last" type="text" value="${esc(r.lastnameFather)}" class="edit-input"></div>
        <div class="edit-field"><label>First Name</label><input id="e_father_first" type="text" value="${esc(r.firstnameFather)}" class="edit-input"></div>
        <div class="edit-field"><label>Middle Name</label><input id="e_father_middle" type="text" value="${esc(r.middlenameFather)}" class="edit-input"></div>
        <div class="edit-field"><label>Suffix</label><input id="e_father_suffix" type="text" value="${esc(r.suffixFather)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M4 21v-1a8 8 0 0 1 16 0v1"/></svg> Mother</div>
      <div class="edit-grid edit-grid-4">
        <div class="edit-field"><label>Last Name</label><input id="e_mother_last" type="text" value="${esc(r.lastnameMother)}" class="edit-input"></div>
        <div class="edit-field"><label>First Name</label><input id="e_mother_first" type="text" value="${esc(r.firstnameMother)}" class="edit-input"></div>
        <div class="edit-field"><label>Middle Name</label><input id="e_mother_middle" type="text" value="${esc(r.middlenameMother)}" class="edit-input"></div>
        <div class="edit-field"><label>Suffix</label><input id="e_mother_suffix" type="text" value="${esc(r.suffixMother)}" class="edit-input"></div>
      </div>
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="6" r="3"/><path d="M5 21v-1a7 7 0 0 1 14 0v1"/><path d="M9 21v-4h6v4"/></svg> Children</div>
      ${[1,2,3,4,5].map(i => `
      <div class="child-row-label">Child ${i}</div>
      <div class="edit-grid edit-grid-5 child-row" style="margin-bottom:10px">
        <div class="edit-field"><label>Full Name</label><input id="e_child${i}_name" type="text" value="${esc(r['fullnameChild'+i])}" class="edit-input"></div>
        <div class="edit-field"><label>Occupation</label><input id="e_child${i}_occ" type="text" value="${esc(r['occupationChild'+i])}" class="edit-input"></div>
        <div class="edit-field"><label>Income</label><input id="e_child${i}_income" type="number" step="0.01" value="${r['incomeChild'+i]||''}" class="edit-input"></div>
        <div class="edit-field"><label>Age</label><input id="e_child${i}_age" type="number" value="${r['ageChild'+i]||''}" class="edit-input"></div>
        <div class="edit-field"><label>Working?</label>
          <select id="e_child${i}_working" class="edit-input">
            <option value="">—</option>
            <option value="Yes" ${r['isWorkingChild'+i]==='Yes'?'selected':''}>Yes</option>
            <option value="No"  ${r['isWorkingChild'+i]==='No'?'selected':''}>No</option>
          </select>
        </div>
      </div>`).join('')}
      <div class="edit-section-title" style="margin-top:16px"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Dependents</div>
      ${[1,2].map(i => `
      <div class="child-row-label">Dependent ${i}</div>
      <div class="edit-grid edit-grid-5 child-row" style="margin-bottom:10px">
        <div class="edit-field"><label>Full Name</label><input id="e_dep${i}_name" type="text" value="${esc(r['fullnameDependent'+i])}" class="edit-input"></div>
        <div class="edit-field"><label>Occupation</label><input id="e_dep${i}_occ" type="text" value="${esc(r['occupationDependent'+i])}" class="edit-input"></div>
        <div class="edit-field"><label>Income</label><input id="e_dep${i}_income" type="number" step="0.01" value="${r['incomeDependent'+i]||''}" class="edit-input"></div>
        <div class="edit-field"><label>Age</label><input id="e_dep${i}_age" type="number" value="${r['ageDependent'+i]||''}" class="edit-input"></div>
        <div class="edit-field"><label>Working?</label>
          <select id="e_dep${i}_working" class="edit-input">
            <option value="">—</option>
            <option value="Yes" ${r['isWorkingDependent'+i]==='Yes'?'selected':''}>Yes</option>
            <option value="No"  ${r['isWorkingDependent'+i]==='No'?'selected':''}>No</option>
          </select>
        </div>
      </div>`).join('')}
    </div>`;

  const steps = [step1HTML, step2HTML];
  const stepLabels = ['Step 1 — Identifying Information', 'Step 2 — Family Composition'];
  const isLast = editCurrentStep === EDIT_TOTAL_STEPS;

  document.getElementById('editModalBody').innerHTML = `
    <div class="step-tabs">
      <button class="step-tab ${editCurrentStep===1?'active':''}" onclick="editSaveAndGoToStep(1)">
        <span class="step-tab-num">1</span> Identifying Info
      </button>
      <button class="step-tab ${editCurrentStep===2?'active':''}" onclick="editSaveAndGoToStep(2)">
        <span class="step-tab-num">2</span> Family Composition
      </button>
    </div>
    <div class="step-label-bar">${stepLabels[editCurrentStep-1]}</div>
    <div class="step-content" id="editStepContent">
      ${steps[editCurrentStep-1]}
    </div>
    <div class="step-nav">
      <button class="btn btn-outline step-prev-btn" onclick="editSaveAndGoToStep(${editCurrentStep-1})" ${editCurrentStep===1?'disabled':''}>← Previous</button>
      <span class="step-counter">Page ${editCurrentStep} of ${EDIT_TOTAL_STEPS}</span>
      <button class="btn btn-primary step-next-btn" onclick="editSaveAndGoToStep(${editCurrentStep+1})" ${isLast?'disabled':''}>Next →</button>
    </div>`;

  // Update footer save button visibility
  document.getElementById('saveEditBtn').style.display = isLast ? 'inline-flex' : 'none';
  document.getElementById('editNextBtn').style.display = !isLast ? 'inline-flex' : 'none';
}

function editSaveAndGoToStep(step) {
  if (step < 1 || step > EDIT_TOTAL_STEPS || !_editRecord) return;
  // Capture current field values back into _editRecord before switching
  captureEditStep(_editRecord, editCurrentStep);
  editCurrentStep = step;
  renderEditModal(_editRecord, null, null, null, null, null);
  document.getElementById('editModalBody').scrollTop = 0;
}

function captureEditStep(r, step) {
  const val = id => { const el = document.getElementById(id); return el ? el.value : ''; };
  if (step === 1) {
    r.lastnameApplicant   = val('e_lastname');
    r.firstnameApplicant  = val('e_firstname');
    r.middlenameApplicant = val('e_middlename');
    r.suffixApplicant     = val('e_suffix');
    r.barangay            = val('e_barangay');
    r.purok               = val('e_purok');
    r.street              = val('e_street');
    r.month               = val('e_month');
    r.date                = val('e_day');
    r.year                = val('e_year');
    r.birthplace          = val('e_birthplace');
    r.sex                 = val('e_sex');
    r.maritalStatus       = val('e_marital');
    r.contactNumber       = val('e_contact');
    r.emailAddress        = val('e_email');
    r.fbMessenger         = val('e_fb');
    r.osca_ID             = val('e_osca');
    r.gsis_sss_ID         = val('e_gsis');
    r.tin_ID              = val('e_tin');
    r.philHealth_ID       = val('e_phil');
    r.sc_asso_ID          = val('e_sc');
    r.other_govt_ID       = val('e_other');
    r.employment_business = val('e_employment');
    r.hasPension          = val('e_pension');
    r.travelCapability    = val('e_travel');
    r.reg_month           = val('e_reg_month');
    r.reg_day             = val('e_reg_day');
    r.reg_year            = val('e_reg_year');
  } else if (step === 2) {
    r.lastnameSpouse   = val('e_spouse_last');
    r.firstnameSpouse  = val('e_spouse_first');
    r.middlenameSpouse = val('e_spouse_middle');
    r.suffixSpouse     = val('e_spouse_suffix');
    r.lastnameFather   = val('e_father_last');
    r.firstnameFather  = val('e_father_first');
    r.middlenameFather = val('e_father_middle');
    r.suffixFather     = val('e_father_suffix');
    r.lastnameMother   = val('e_mother_last');
    r.firstnameMother  = val('e_mother_first');
    r.middlenameMother = val('e_mother_middle');
    r.suffixMother     = val('e_mother_suffix');
    for (let i = 1; i <= 5; i++) {
      r['fullnameChild'+i]   = val(`e_child${i}_name`);
      r['occupationChild'+i] = val(`e_child${i}_occ`);
      r['incomeChild'+i]     = val(`e_child${i}_income`);
      r['ageChild'+i]        = val(`e_child${i}_age`);
      r['isWorkingChild'+i]  = val(`e_child${i}_working`);
    }
    for (let i = 1; i <= 2; i++) {
      r['fullnameDependent'+i]   = val(`e_dep${i}_name`);
      r['occupationDependent'+i] = val(`e_dep${i}_occ`);
      r['incomeDependent'+i]     = val(`e_dep${i}_income`);
      r['ageDependent'+i]        = val(`e_dep${i}_age`);
      r['isWorkingDependent'+i]  = val(`e_dep${i}_working`);
    }
  }
}

function esc(v) {
  if (!v) return '';
  return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Input enforcement helpers ─────────────────────────────────

/**
 * enforceNameField — forces ALL CAPS, A-Z and spaces only, max 50 chars.
 * Attached via oninput on last/first/middle name inputs in the edit modal.
 */
function enforceNameField(input) {
  const hint = document.getElementById('hint_' + input.id.replace('e_', ''));
  // Allow A-Z, Ñ, and spaces only (auto-uppercase)
  const final = input.value.toUpperCase().replace(/[^A-ZÑ ]/g, '');
  if (input.value !== final) input.value = final;

  // Show hint if at character limit
  if (final.length === 50) {
    showEditHint(hint, '⚠ Maximum 50 characters reached', 'warn');
  } else if (final.length > 0) {
    showEditHint(hint, '✓ Valid — uppercase letters only', 'ok');
  } else {
    clearEditHint(hint);
  }
}

/**
 * enforceContactField — forces digits only, must start with 09, max 11 digits.
 * Attached via oninput on the contact number input in the edit modal.
 */
function enforceContactField(input) {
  const hint = document.getElementById('hint_contact');
  // Keep digits only
  let digits = input.value.replace(/\D/g, '');
  // Enforce leading "09"
  if (digits.length >= 1 && digits[0] !== '0') digits = '0' + digits;
  if (digits.length >= 2 && digits[1] !== '9') digits = '09' + digits.replace(/^0*9?/, '');
  // Clamp to 11 digits
  if (digits.length > 11) digits = digits.slice(0, 11);
  if (input.value !== digits) input.value = digits;

  if (digits.length === 0) {
    clearEditHint(hint);
  } else if (digits.length === 11 && /^09\d{9}$/.test(digits)) {
    showEditHint(hint, '✓ Valid Philippine mobile number', 'ok');
  } else if (digits.length < 11) {
    showEditHint(hint, `${11 - digits.length} more digit(s) needed — must start with 09`, 'warn');
  } else {
    showEditHint(hint, '✗ Must be 11 digits starting with 09', 'error');
  }
}

function showEditHint(el, msg, type) {
  if (!el) return;
  el.textContent = msg;
  el.className = 'edit-field-hint hint-' + type;
}
function clearEditHint(el) {
  if (!el) return;
  el.textContent = '';
  el.className = 'edit-field-hint';
}

async function saveEdit() {
  if (!editingId || !_editRecord) return;
  // Capture whatever step is currently visible
  captureEditStep(_editRecord, editCurrentStep);
  const r = _editRecord;

  // Basic validation from step 1 fields
  if (!r.lastnameApplicant || !r.firstnameApplicant) {
    toast('Last name and first name are required.', 'error'); return;
  }
  // Name pattern: uppercase A-Z, Ñ, and spaces only, max 50 chars
  const namePattern = /^[A-ZÑ ]{1,50}$/;
  if (!namePattern.test(r.lastnameApplicant)) {
    toast('Last name must be all-caps letters only (A–Z, Ñ), max 50 characters.', 'error'); return;
  }
  if (!namePattern.test(r.firstnameApplicant)) {
    toast('First name must be all-caps letters only (A–Z, Ñ), max 50 characters.', 'error'); return;
  }
  if (r.middlenameApplicant && !namePattern.test(r.middlenameApplicant)) {
    toast('Middle name must be all-caps letters only (A–Z, Ñ), max 50 characters.', 'error'); return;
  }
  if (!r.barangay) {
    toast('Barangay is required.', 'error'); return;
  }
  if (!r.contactNumber || !r.emailAddress) {
    toast('Contact number and email are required.', 'error'); return;
  }
  // Contact pattern: starts with 09, exactly 11 digits
  if (!/^09\d{9}$/.test(r.contactNumber)) {
    toast('Contact number must be 11 digits starting with 09 (e.g. 09123456789).', 'error'); return;
  }

  const btn = document.getElementById('saveEditBtn');
  btn.disabled = true;
  btn.textContent = '⏳ Saving…';

  const body = new URLSearchParams({
    action: 'update_record',
    id: editingId,
    lastnameApplicant:   r.lastnameApplicant,
    firstnameApplicant:  r.firstnameApplicant,
    middlenameApplicant: r.middlenameApplicant,
    suffixApplicant:     r.suffixApplicant || '',
    barangay:            r.barangay,
    purok:               r.purok || '',
    street:              r.street || '',
    month:               r.month || '',
    date:                r.date || '',
    year:                r.year || '',
    birthplace:          r.birthplace || '',
    sex:                 r.sex || '',
    maritalStatus:       r.maritalStatus || '',
    contactNumber:       r.contactNumber,
    emailAddress:        r.emailAddress,
    fbMessenger:         r.fbMessenger || '',
    osca_ID:             r.osca_ID || '',
    gsis_sss_ID:         r.gsis_sss_ID || '',
    tin_ID:              r.tin_ID || '',
    philHealth_ID:       r.philHealth_ID || '',
    sc_asso_ID:          r.sc_asso_ID || '',
    other_govt_ID:       r.other_govt_ID || '',
    employment_business: r.employment_business || '',
    hasPension:          r.hasPension || '',
    travelCapability:    r.travelCapability || '',
    reg_month:           r.reg_month || '',
    reg_day:             r.reg_day || '',
    reg_year:            r.reg_year || '',
  });
  // Append Step 2 family fields
  body.append('lastnameSpouse',    r.lastnameSpouse    || '');
  body.append('firstnameSpouse',   r.firstnameSpouse   || '');
  body.append('middlenameSpouse',  r.middlenameSpouse  || '');
  body.append('suffixSpouse',      r.suffixSpouse      || '');
  body.append('lastnameFather',    r.lastnameFather    || '');
  body.append('firstnameFather',   r.firstnameFather   || '');
  body.append('middlenameFather',  r.middlenameFather  || '');
  body.append('suffixFather',      r.suffixFather      || '');
  body.append('lastnameMother',    r.lastnameMother    || '');
  body.append('firstnameMother',   r.firstnameMother   || '');
  body.append('middlenameMother',  r.middlenameMother  || '');
  body.append('suffixMother',      r.suffixMother      || '');
  for (let i = 1; i <= 5; i++) {
    body.append(`fullnameChild${i}`,   r[`fullnameChild${i}`]   || '');
    body.append(`occupationChild${i}`, r[`occupationChild${i}`] || '');
    body.append(`incomeChild${i}`,     r[`incomeChild${i}`]     || '');
    body.append(`ageChild${i}`,        r[`ageChild${i}`]        || '');
    body.append(`isWorkingChild${i}`,  r[`isWorkingChild${i}`]  || '');
  }
  for (let i = 1; i <= 2; i++) {
    body.append(`fullnameDependent${i}`,   r[`fullnameDependent${i}`]   || '');
    body.append(`occupationDependent${i}`, r[`occupationDependent${i}`] || '');
    body.append(`incomeDependent${i}`,     r[`incomeDependent${i}`]     || '');
    body.append(`ageDependent${i}`,        r[`ageDependent${i}`]        || '');
    body.append(`isWorkingDependent${i}`,  r[`isWorkingDependent${i}`]  || '');
  }

  try {
    const res  = await fetch('save.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      toast('Record updated successfully.', 'success');
      closeEditModal();
      setTimeout(() => location.reload(), 800);
    } else {
      toast(data.message || 'Update failed.', 'error');
    }
  } catch (e) {
    toast('Network error.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = '💾 Save Changes';
  }
}

function closeEditModal() {
  document.getElementById('editModal').classList.remove('open');
  document.body.style.overflow = '';
  editingId = null;
  _editRecord = null;
  editCurrentStep = 1;
}

// ═══════════════════ DELETE (CHANGE 3) ═══════════════════════
let pendingDeleteId   = null;
let pendingDeleteName = '';

function confirmDelete(id, name) {
  pendingDeleteId   = id;
  pendingDeleteName = name.split(',')[0].trim().toUpperCase(); // last name only
  document.getElementById('deleteName').textContent = name;
  document.getElementById('deleteConfirmInput').value = '';
  document.getElementById('deleteConfirmHint').textContent = '';
  document.getElementById('confirmDeleteBtn').disabled = true;
  openModal('deleteModal');
  setTimeout(() => document.getElementById('deleteConfirmInput').focus(), 300);
}

function checkDeleteConfirm() {
  const input  = document.getElementById('deleteConfirmInput');
  const hint   = document.getElementById('deleteConfirmHint');
  const btn    = document.getElementById('confirmDeleteBtn');

  // Enforce: ALL CAPS, A-Z, Ñ, and spaces only, max 50 chars
  const cleaned = input.value.toUpperCase().replace(/[^A-ZÑ ]/g, '').slice(0, 50);
  if (input.value !== cleaned) input.value = cleaned;

  const typed = cleaned.trim();

  if (typed.length === 0) {
    btn.disabled = true;
    hint.textContent = '';
    input.style.borderColor = '';
  } else if (typed === pendingDeleteName) {
    btn.disabled = false;
    hint.textContent = '✓ Name matches';
    hint.style.color = '#27ae60';
    input.style.borderColor = '#27ae60';
  } else {
    btn.disabled = true;
    hint.textContent = '✗ Name does not match';
    hint.style.color = '#c0392b';
    input.style.borderColor = '#c0392b';
  }
}

document.getElementById('confirmDeleteBtn').addEventListener('click', async function () {
  if (!pendingDeleteId) return;
  const deletingId = pendingDeleteId; // capture before it gets nulled
  this.disabled = true;
  this.textContent = 'Deleting…';

  try {
    const body = new URLSearchParams({ id: deletingId });
    const res  = await fetch('delete_record.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      closeDeleteModal();
      toast('Record deleted successfully.', 'success');
      const row = document.querySelector(`tr[data-id="${deletingId}"]`);
      if (row) {
        row.style.transition = 'opacity .3s, transform .3s';
        row.style.opacity    = '0';
        row.style.transform  = 'translateX(20px)';
        setTimeout(() => {
          row.remove();
          updateRowNumbers();
          updateStatCounts();
          checkEmptyState();
        }, 300);
      }
    } else {
      toast(data.message || 'Delete failed.', 'error');
      closeDeleteModal();
    }
  } catch (e) {
    toast('Network error.', 'error');
    closeDeleteModal();
  } finally {
    this.disabled    = false;
    this.textContent = 'Delete Record';
    pendingDeleteId  = null;
  }
});

function updateStatCounts() {
  // Decrement Total Registrants card
  const totalEl = document.querySelector('.stat-card:nth-child(1) .stat-value');
  if (totalEl) {
    const current = parseInt(totalEl.textContent.replace(/,/g, ''), 10);
    if (!isNaN(current) && current > 0) {
      totalEl.textContent = (current - 1).toLocaleString();
    }
  }
  // Decrement Today card if the deleted row was registered today
  const todayEl = document.querySelector('.stat-card.today .stat-value');
  if (todayEl) {
    const current = parseInt(todayEl.textContent.replace(/,/g, ''), 10);
    if (!isNaN(current) && current > 0) {
      todayEl.textContent = (current - 1).toLocaleString();
    }
  }
}

function checkEmptyState() {
  const tbody = document.querySelector('.data-table tbody');
  if (!tbody) return;
  const remaining = tbody.querySelectorAll('tr.table-row').length;
  if (remaining === 0) {
    // Replace the whole table section content with the empty state
    const tableWrap = document.querySelector('.table-wrap');
    const pagination = document.querySelector('.pagination');
    if (tableWrap) tableWrap.remove();
    if (pagination) pagination.remove();
    const section = document.querySelector('.table-section');
    if (section) {
      const emptyDiv = document.createElement('div');
      emptyDiv.className = 'empty-state';
      emptyDiv.innerHTML = `
        <div class="empty-icon"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
        <h3>No records found</h3>
        <p>No applicants registered yet.</p>
        <a href="registration.php" class="btn btn-gold">＋ Add First Record</a>`;
      section.appendChild(emptyDiv);
    }
  }
}

function updateRowNumbers() {
  document.querySelectorAll('.table-row').forEach((row, i) => {
    const td = row.querySelector('.td-id');
    if (td) td.textContent = i + 1;
  });
}

// ═══════════════════ MODAL HELPERS ═══════════════════════════
function openModal(id) {
  document.getElementById(id).classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('viewModal').classList.remove('open');
  document.body.style.overflow = '';
}
function closeDeleteModal() {
  document.getElementById('deleteModal').classList.remove('open');
  document.body.style.overflow = '';
  pendingDeleteId = null;
}

// CHANGE 6: logout modal
function openLogoutModal()  { openModal('logoutModal'); }
function closeLogoutModal() {
  document.getElementById('logoutModal').classList.remove('open');
  document.body.style.overflow = '';
}

// Close modals on backdrop click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function (e) {
    if (e.target === this) {
      this.classList.remove('open');
      document.body.style.overflow = '';
      pendingDeleteId = null;
    }
  });
});

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    closeModal();
    closeDeleteModal();
    closeEditModal();
    closeLogoutModal();
  }
});