// ── Sidebar toggle (mobile) ──────────────────────────────────
const sidebar  = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebarBackdrop');

function toggleSidebar() {
  const isOpen = sidebar.classList.toggle('open');
  if (backdrop) { backdrop.classList.toggle('show', isOpen); document.body.style.overflow = isOpen ? 'hidden' : ''; }
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

// ── Incremental search ────────────────────────────────────────
let _searchTimer  = null;
let _activeSearch = null;
const _searchInput = document.getElementById('searchInput');
const _searchWrap  = _searchInput ? _searchInput.closest('.search-wrap') : null;
const _clearBtn    = document.getElementById('searchClear');
if (_searchInput && _clearBtn && _searchInput.value.trim().length > 0) _clearBtn.classList.add('visible');

function handleIncrementalSearch(val) {
  if (_clearBtn) val.length > 0 ? _clearBtn.classList.add('visible') : _clearBtn.classList.remove('visible');
  clearTimeout(_searchTimer);
  if (_activeSearch) { _activeSearch.abort(); _activeSearch = null; }
  _searchTimer = setTimeout(() => _doSearch(val.trim()), 380);
}

async function _doSearch(val) {
  const url = new URL(window.location.href);
  if (val) url.searchParams.set('search', val); else url.searchParams.delete('search');
  url.searchParams.set('page', 1);
  if (_searchWrap) _searchWrap.classList.add('searching');
  _fadeRows(true);
  _activeSearch = new AbortController();
  try {
    const res  = await fetch(url.toString(), { signal: _activeSearch.signal });
    const html = await res.text();
    const parser = new DOMParser();
    const doc    = parser.parseFromString(html, 'text/html');
    const newTbody = doc.querySelector('.data-table tbody');
    const curTbody = document.querySelector('.data-table tbody');
    if (newTbody && curTbody) { curTbody.innerHTML = newTbody.innerHTML; _fadeRows(false); }
    history.replaceState(null, '', url.toString());
  } catch (e) {
    if (e.name !== 'AbortError') window.location.href = url.toString();
  } finally {
    if (_searchWrap) _searchWrap.classList.remove('searching');
    _activeSearch = null;
  }
}

function _fadeRows(out) {
  document.querySelectorAll('.data-table tbody .table-row').forEach((row, i) => {
    row.style.transition = out ? 'opacity .15s ease' : `opacity .22s ease ${i * 0.03}s, transform .22s ease ${i * 0.03}s`;
    if (out) { row.style.opacity = '0'; }
    else { row.style.opacity='0'; row.style.transform='translateY(5px)'; void row.offsetHeight; row.style.opacity='1'; row.style.transform='translateY(0)'; }
  });
}

function clearSearch() {
  if (_searchInput) { _searchInput.value = ''; _searchInput.focus(); }
  if (_clearBtn) _clearBtn.classList.remove('visible');
  _doSearch('');
}

document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement !== _searchInput
      && !['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) {
    e.preventDefault();
    if (_searchInput) { _searchInput.focus(); _searchInput.select(); }
  }
});

// ── Filter helpers ────────────────────────────────────────────
function applyBarangayFilter(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('filter', val); url.searchParams.set('page', 1);
  window.location.href = url.toString();
}
function applyLimit(val) {
  const url = new URL(window.location.href);
  url.searchParams.set('limit', val); url.searchParams.set('page', 1);
  window.location.href = url.toString();
}

// ── Icon helper ───────────────────────────────────────────────
function icon(name, extraClass = '') {
  return `<span class="material-symbols-outlined section-icon ${extraClass}" aria-hidden="true">${name}</span>`;
}

// ═══════════════════ VIEW MODAL ══════════════════════════════
let viewCurrentStep = 1;
const VIEW_TOTAL_STEPS = 7;
let _viewRecord = null;

async function viewRecord(id) {
  openModal('viewModal');
  document.getElementById('modalBody').innerHTML = `<div class="modal-loading">${icon('progress_activity','spin')} Loading record…</div>`;
  document.getElementById('modalTitle').textContent = 'Applicant Details';
  try {
    const res  = await fetch(`get_record.php?id=${id}`);
    const data = await res.json();
    if (!data.success) { document.getElementById('modalBody').innerHTML = `<p style="color:red">Error: ${data.message}</p>`; return; }
    const r = data.record;
    const suffix = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';
    document.getElementById('modalTitle').textContent = `${r.lastnameApplicant}, ${r.firstnameApplicant} ${r.middlenameApplicant}${suffix ? ' '+suffix : ''}`;
    viewCurrentStep = 1; _viewRecord = r;
    renderViewModal(r);
  } catch(e) {
    document.getElementById('modalBody').innerHTML = '<p style="color:red">Failed to load record.</p>';
  }
}

function renderViewModal(r) {
  const na  = v => v ? v : '<span class="value-empty">—</span>';
  const csv = v => v ? v.replace(/,/g, ', ') : '<span class="value-empty">—</span>';

 const step1HTML = `<div class="detail-section"><div class="detail-section-title">${icon('person')} Identifying Information</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">NCSC Portal Status</div><div class="detail-value" style="display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${r.ncsc_encoded === 'Yes' ? '#059669' : '#d1d5db'}"></span><span style="color:${r.ncsc_encoded === 'Yes' ? '#059669' : '#6b7280'};font-weight:600">${r.ncsc_encoded === 'Yes' ? 'Encoded to NCSC Portal' : 'Not yet encoded'}</span></div></div><div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameApplicant)}</div></div><div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameApplicant)}</div></div><div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameApplicant)}</div></div><div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixApplicant)}</div></div><div class="detail-item"><div class="detail-label">Sex</div><div class="detail-value">${na(r.sex)}</div></div><div class="detail-item"><div class="detail-label">Marital Status</div><div class="detail-value">${na(r.maritalStatus)}</div></div><div class="detail-item"><div class="detail-label">Religion</div><div class="detail-value">${na(r.religion)}</div></div><div class="detail-item"><div class="detail-label">Ethnic Origin</div><div class="detail-value">${na(r.ethnicOrigin)}</div></div><div class="detail-item"><div class="detail-label">Language Spoken</div><div class="detail-value">${na(r.languageSpoken)}</div></div><div class="detail-item"><div class="detail-label">Birthdate</div><div class="detail-value">${r.month&&r.date&&r.year?`${r.month} ${r.date}, ${r.year}`:'—'}</div></div><div class="detail-item"><div class="detail-label">Birthplace</div><div class="detail-value">${na(r.birthplace)}</div></div><div class="detail-item"><div class="detail-label">Contact Number</div><div class="detail-value">${na(r.contactNumber)}</div></div><div class="detail-item"><div class="detail-label">Email Address</div><div class="detail-value">${na(r.emailAddress)}</div></div><div class="detail-item"><div class="detail-label">FB Messenger</div><div class="detail-value">${na(r.fbMessenger)}</div></div><div class="detail-item"><div class="detail-label">Employment/Business</div><div class="detail-value">${na(r.employment_business)}</div></div><div class="detail-item"><div class="detail-label">Has Pension</div><div class="detail-value">${na(r.hasPension)}</div></div><div class="detail-item"><div class="detail-label">Can Travel</div><div class="detail-value">${na(r.travelCapability)}</div></div><div class="detail-item"><div class="detail-label">Person with Disability</div><div class="detail-value">${na(r.personWithDisability)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('home')} Address</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Barangay</div><div class="detail-value">${na(r.barangay)}</div></div><div class="detail-item"><div class="detail-label">Purok</div><div class="detail-value">${na(r.purok)}</div></div><div class="detail-item"><div class="detail-label">Street / House No.</div><div class="detail-value">${na(r.street)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('badge')} Government IDs</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">OSCA ID</div><div class="detail-value">${na(r.osca_ID)}</div></div><div class="detail-item"><div class="detail-label">GSIS/SSS ID</div><div class="detail-value">${na(r.gsis_sss_ID)}</div></div><div class="detail-item"><div class="detail-label">TIN ID</div><div class="detail-value">${na(r.tin_ID)}</div></div><div class="detail-item"><div class="detail-label">PhilHealth ID</div><div class="detail-value">${na(r.philHealth_ID)}</div></div><div class="detail-item"><div class="detail-label">SC Asso. ID</div><div class="detail-value">${na(r.sc_asso_ID)}</div></div><div class="detail-item"><div class="detail-label">Other Govt. ID</div><div class="detail-value">${na(r.other_govt_ID)}</div></div></div></div>`;
  const step2HTML = `<div class="detail-section"><div class="detail-section-title">${icon('favorite')} Spouse</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameSpouse)}</div></div><div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameSpouse)}</div></div><div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameSpouse)}</div></div><div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixSpouse)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('man')} Father</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameFather)}</div></div><div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameFather)}</div></div><div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameFather)}</div></div><div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixFather)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('woman')} Mother</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Last Name</div><div class="detail-value">${na(r.lastnameMother)}</div></div><div class="detail-item"><div class="detail-label">First Name</div><div class="detail-value">${na(r.firstnameMother)}</div></div><div class="detail-item"><div class="detail-label">Middle Name</div><div class="detail-value">${na(r.middlenameMother)}</div></div><div class="detail-item"><div class="detail-label">Suffix</div><div class="detail-value">${na(r.suffixMother)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('child_care')} Children</div>${buildChildrenTable(r)}</div><div class="detail-section"><div class="detail-section-title">${icon('group')} Dependents</div>${buildDependentsTable(r)}</div>`;
  const step3HTML = `<div class="detail-section"><div class="detail-section-title">${icon('cottage')} Living Situation</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Living Alone</div><div class="detail-value">${na(r.livingAlone)}</div></div><div class="detail-item"><div class="detail-label">Living With</div><div class="detail-value">${csv(r.livingWith)}</div></div><div class="detail-item"><div class="detail-label">Living With (Others)</div><div class="detail-value">${na(r.livingWithOthers)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('apartment')} Living Condition</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Conditions</div><div class="detail-value">${csv(r.livingCondition)}</div></div><div class="detail-item"><div class="detail-label">Others (specify)</div><div class="detail-value">${na(r.livingConditionOthers)}</div></div></div></div>`;
  const step4HTML = `<div class="detail-section"><div class="detail-section-title">${icon('school')} Education</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Highest Attainment</div><div class="detail-value">${na(r.educationHighest)}</div></div><div class="detail-item"><div class="detail-label">Others (specify)</div><div class="detail-value">${na(r.educationHighestOthers)}</div></div></div></div><div class="detail-section"><div class="detail-section-title">${icon('build')} Skills &amp; Community</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Technical Skills</div><div class="detail-value">${csv(r.skills)}</div></div><div class="detail-item"><div class="detail-label">Skills Others</div><div class="detail-value">${na(r.skillsOthers)}</div></div><div class="detail-item"><div class="detail-label">Shared Skills</div><div class="detail-value">${csv(r.sharedSkills)}</div></div><div class="detail-item"><div class="detail-label">Community Involvement</div><div class="detail-value">${csv(r.communityInvolvement)}</div></div><div class="detail-item"><div class="detail-label">Community Others</div><div class="detail-value">${na(r.communityInvolvementOthers)}</div></div></div></div>`;
  const step5HTML = `<div class="detail-section"><div class="detail-section-title">${icon('payments')} Income &amp; Assets</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Source of Income</div><div class="detail-value">${csv(r.sourceIncome)}</div></div><div class="detail-item"><div class="detail-label">Source Others</div><div class="detail-value">${na(r.sourceIncomeOthers)}</div></div><div class="detail-item"><div class="detail-label">Monthly Income</div><div class="detail-value">${na(r.incomeMonthly)}</div></div><div class="detail-item"><div class="detail-label">Real Properties</div><div class="detail-value">${csv(r.assetsReal)}</div></div><div class="detail-item"><div class="detail-label">Personal Properties</div><div class="detail-value">${csv(r.assetsPersonal)}</div></div></div></div>`;
  const step6HTML = `<div class="detail-section"><div class="detail-section-title">${icon('monitor_heart')} Health Information</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Blood Type</div><div class="detail-value">${na(r.bloodType)}</div></div><div class="detail-item"><div class="detail-label">Physical Disability</div><div class="detail-value">${na(r.physicalDisability)}</div></div><div class="detail-item"><div class="detail-label">Health Problems</div><div class="detail-value">${csv(r.healthProblems)}</div></div><div class="detail-item"><div class="detail-label">Dental Concern</div><div class="detail-value">${csv(r.dentalConcern)}</div></div><div class="detail-item"><div class="detail-label">Visual Concern</div><div class="detail-value">${csv(r.visualConcern)}</div></div><div class="detail-item"><div class="detail-label">Aural Concern</div><div class="detail-value">${csv(r.auralConcern)}</div></div><div class="detail-item"><div class="detail-label">Maintenance Medicines</div><div class="detail-value">${csv(r.listOfMedicines)}</div></div><div class="detail-item"><div class="detail-label">Scheduled Checkup</div><div class="detail-value">${na(r.scheduledCheckup)}</div></div><div class="detail-item"><div class="detail-label">Checkup Frequency</div><div class="detail-value">${na(r.scheduledCheckupYes)}</div></div></div></div>`;
  const oscaImg  = r.oscaID_type    ? `<img src="get_image.php?id=${r.id}&type=osca"  style="max-width:260px;max-height:180px;object-fit:contain;border-radius:6px;border:1px solid rgba(149,165,166,.30);background:#f5f3f5;margin-top:6px">` : '<span class="value-empty">—</span>';
  const photoImg = r.photoLatest_type ? `<img src="get_image.php?id=${r.id}&type=photo" style="max-width:160px;max-height:200px;object-fit:contain;border-radius:6px;border:1px solid rgba(149,165,166,.30);background:#f5f3f5;margin-top:6px">` : '<span class="value-empty">—</span>';
  const step7HTML = `<div class="detail-section"><div class="detail-section-title">${icon('badge')} OSCA ID Photo</div><div>${oscaImg}</div></div><div class="detail-section"><div class="detail-section-title">${icon('photo_camera')} Latest 2×2 Photo</div><div>${photoImg}</div></div><div class="detail-section"><div class="detail-section-title">${icon('info')} Record Info</div><div class="detail-grid"><div class="detail-item"><div class="detail-label">Registered On</div><div class="detail-value">${formatDate(r.created_at)}</div></div><div class="detail-item"><div class="detail-label">Last Updated</div><div class="detail-value">${formatDate(r.updated_at)}</div></div></div></div>`;

  const steps = [step1HTML,step2HTML,step3HTML,step4HTML,step5HTML,step6HTML,step7HTML];
  const stepLabels = ['Step 1 — Identifying Information','Step 2 — Family Composition','Step 3 — Dependency Profile','Step 4 — Education / HR Profile','Step 5 — Economic Profile','Step 6 — Health Profile','Step 7 — ID & Photo'];
  const tabIcons = ['badge','diversity_3','cottage','school','payments','monitor_heart','photo_camera'];
  const tabNames = ['Identifying','Family','Dependency','Education','Economic','Health','ID & Photo'];

  document.getElementById('modalBody').innerHTML = `
    <div class="step-tabs">${tabNames.map((name,i) => `<button class="step-tab ${viewCurrentStep===i+1?'active':''}" onclick="viewGoToStep(${i+1}, _viewRecord)"><span class="step-tab-num">${i+1}</span>${icon(tabIcons[i],'step-tab-icon')}<span class="step-tab-label">${name}</span></button>`).join('')}</div>
    <div class="step-label-bar">${stepLabels[viewCurrentStep-1]}</div>
    <div class="step-content" id="viewStepContent">${steps[viewCurrentStep-1]}</div>
    <div class="step-nav">
      <button class="btn btn-outline step-prev-btn" onclick="viewGoToStep(${viewCurrentStep-1}, _viewRecord)" ${viewCurrentStep===1?'disabled':''}>${icon('arrow_back')} Previous</button>
      <div class="step-counter">
  <div class="step-dots">
    ${Array.from({length: VIEW_TOTAL_STEPS}, (_,i) => `<div class="step-dot ${i+1===viewCurrentStep?'active':i+1<viewCurrentStep?'done':''}"></div>`).join('')}
  </div>
  <span class="step-counter-label">Step ${viewCurrentStep} of ${VIEW_TOTAL_STEPS}</span>
</div>
      ${viewCurrentStep === VIEW_TOTAL_STEPS
        ? `<a href="dashboard.php" class="btn btn-primary step-next-btn">${icon('dashboard')} Back to Dashboard</a>`
        : `<button class="btn btn-primary step-next-btn" onclick="viewGoToStep(${viewCurrentStep+1}, _viewRecord)">Next ${icon('arrow_forward')}</button>`}
    </div>`;
    requestAnimationFrame(() => {
    const activeTab = document.querySelector('#modalBody .step-tab.active');
    if (activeTab) activeTab.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  });
} 

function viewGoToStep(step, r) {
  if (step < 1 || step > VIEW_TOTAL_STEPS) return;
  document.activeElement?.blur();   
  viewCurrentStep = step; renderViewModal(r);
  document.getElementById('modalBody').scrollTop = 0;
}

function buildChildrenTable(r) {
  let rows = '';
  for (let i = 1; i <= (r.childCount || 5); i++) {
    const name = r[`fullnameChild${i}`]; if (!name) continue;
    rows += `<tr><td style="padding:9px 14px;color:#1b1c1d;border-bottom:1px solid rgba(149,165,166,.18)">${name}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`occupationChild${i}`]||'—'}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`ageChild${i}`]||'—'}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`isWorkingChild${i}`]||'—'}</td></tr>`;
  }
  if (!rows) return '<p class="empty-note">No children listed.</p>';
  return `<div style="overflow-x:auto;margin-top:10px;border:1px solid rgba(149,165,166,.25);border-radius:0.5rem;"><table style="width:100%;border-collapse:collapse;font-size:.83rem"><thead><tr><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Name</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Occupation</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Age</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Working?</th></tr></thead><tbody>${rows}</tbody></table></div>`;
}

function buildDependentsTable(r) {
  let rows = '';
  for (let i = 1; i <= 2; i++) {
    const name = r[`fullnameDependent${i}`]; if (!name) continue;
    rows += `<tr><td style="padding:9px 14px;color:#1b1c1d;border-bottom:1px solid rgba(149,165,166,.18)">${name}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`occupationDependent${i}`]||'—'}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`ageDependent${i}`]||'—'}</td><td style="padding:9px 14px;color:#74777d;border-bottom:1px solid rgba(149,165,166,.18)">${r[`isWorkingDependent${i}`]||'—'}</td></tr>`;
  }
  if (!rows) return '<p class="empty-note">No dependents listed.</p>';
  return `<div style="overflow-x:auto;margin-top:10px;border:1px solid rgba(149,165,166,.25);border-radius:0.5rem;"><table style="width:100%;border-collapse:collapse;font-size:.83rem"><thead><tr><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Name</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Occupation</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Age</th><th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Working?</th></tr></thead><tbody>${rows}</tbody></table></div>`;
}

function formatDate(dt) {
  if (!dt) return '—';
  return new Date(dt).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
}


// ═══════════════════ ARCHIVE MODAL ═══════════════════════════
let pendingArchiveId   = null;
let pendingArchiveName = '';

function confirmArchive(id, name) {
  pendingArchiveId   = id;
  pendingArchiveName = name;
  document.getElementById('archiveName').textContent = name;
  document.getElementById('archiveReasonInput').value = '';
  document.getElementById('archiveReasonError').textContent = '';
  document.getElementById('archiveCharCount').textContent = '0 / 1000';
  document.getElementById('confirmArchiveBtn').disabled = true;
  document.querySelectorAll('.reason-chip').forEach(c => c.classList.remove('selected'));
  openModal('archiveModal');
  setTimeout(() => document.getElementById('archiveReasonInput').focus(), 300);
}

function selectReasonChip(btn, text) {
  const alreadySelected = btn.classList.contains('selected');
  document.querySelectorAll('.reason-chip').forEach(c => c.classList.remove('selected'));
  const textarea = document.getElementById('archiveReasonInput');
  if (alreadySelected) {
    textarea.value = '';
  } else {
    btn.classList.add('selected');
    textarea.value = text;
  }
  updateArchiveCharCount();
  validateArchiveReason();
}

function updateArchiveCharCount() {
  const val = document.getElementById('archiveReasonInput').value;
  document.getElementById('archiveCharCount').textContent = `${val.length} / 1000`;
}

function validateArchiveReason() {
  const val = document.getElementById('archiveReasonInput').value.trim();
  const btn = document.getElementById('confirmArchiveBtn');
  const err = document.getElementById('archiveReasonError');
  if (val.length === 0) {
    btn.disabled = true;
    err.textContent = '';
  } else if (val.length < 5) {
    btn.disabled = true;
    err.textContent = 'Reason is too short.';
  } else {
    btn.disabled = false;
    err.textContent = '';
  }
}

async function executeArchive() {
  if (!pendingArchiveId) return;
  const reason = document.getElementById('archiveReasonInput').value.trim();
  if (!reason) { toast('Please provide a reason for archiving.', 'error'); return; }

  const btn = document.getElementById('confirmArchiveBtn');
  btn.disabled = true;
  btn.innerHTML = `${icon('progress_activity','spin')} Moving…`;

  try {
    const body = new URLSearchParams({ action: 'archive', id: pendingArchiveId, reason });
    const res  = await fetch('archive_record.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      closeArchiveModal();
      toast('Record archived successfully. Remember to export a new backup.', 'success');
      window.OSCA_triggerSync?.();
      refreshNotifState();
      const row = document.querySelector(`tr[data-id="${pendingArchiveId}"]`);
      if (row) {
        row.style.transition = 'opacity .35s, transform .35s';
        row.style.opacity    = '0';
        row.style.transform  = 'translateX(-20px)';
        setTimeout(() => { row.remove(); updateRowNumbers(); checkEmptyState(); }, 360);
      }
      const totalEl = document.querySelector('.stat-total');
      if (totalEl) {
        const cur = parseInt(totalEl.textContent.replace(/,/g,''), 10);
        if (!isNaN(cur) && cur > 0) totalEl.textContent = (cur - 1).toLocaleString();
      }
    } else {
      toast(data.message || 'Archive failed.', 'error');
    }
  } catch(e) {
    toast('Network error.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `${icon('inventory_2')} Move to Archive`;
    pendingArchiveId = null;
  }
}

function closeArchiveModal() {
  document.getElementById('archiveModal').classList.remove('open');
  document.body.style.overflow = '';
  pendingArchiveId = null;
}


// ═══════════════════ EDIT MODAL ══════════════════════════════
let editingId = null;
let editCurrentStep = 1;
const EDIT_TOTAL_STEPS = 7;
let _editRecord = null;

const BARANGAYS = ['Aguisan','Barangay I-Poblacion','Barangay II-Poblacion','Barangay III-Poblacion','Barangay IV-Poblacion','Buenavista','Cabadiangan','Cabanbanan','Carabalan','Caradioan','Libacao','Mahalang','Mambagaton','Nabalian','San Antonio','Saraet','Suay','Talaban','Tooy'].sort();

async function editRecord(id) {
  editingId = id; editCurrentStep = 1;
  openModal('editModal');
  document.getElementById('editModalBody').innerHTML = `<div class="modal-loading">${icon('progress_activity','spin')} Loading record…</div>`;
  document.getElementById('editModalTitle').textContent = 'Edit Record';
  document.getElementById('saveEditBtn').style.display = 'none';
  try {
    const res  = await fetch(`get_record.php?id=${id}`);
    const data = await res.json();
    if (!data.success) { document.getElementById('editModalBody').innerHTML = `<p style="color:red">Error: ${data.message}</p>`; return; }
    _editRecord = data.record;
    document.getElementById('editModalTitle').textContent = `Editing: ${_editRecord.lastnameApplicant}, ${_editRecord.firstnameApplicant}`;
    renderEditModal(_editRecord);
  } catch(e) {
    document.getElementById('editModalBody').innerHTML = '<p style="color:red">Failed to load record.</p>';
  }
}

function buildSelectOptions(options, current, placeholder) {
  return `<option value="">${placeholder||'— Select —'}</option>` + options.map(o=>`<option value="${o}" ${current===o?'selected':''}>${o}</option>`).join('');
}

function renderEditModal(r) {
  const isAdmin = window.OSCA?.isAdmin ?? false;
  const midIsNA = (r.middlenameApplicant||'').trim().toUpperCase() === 'N/A';
  const emailIsNA = (r.emailAddress||'').trim().toUpperCase() === 'N/A';
  const suffixVal = (r.suffixApplicant && r.suffixApplicant !== 'N/A') ? r.suffixApplicant : '';
  const suffixOpts = ['','JR','SR','I','II','III','IV','V','VI'].map(s=>`<option value="${s}" ${suffixVal===s?'selected':''}>${s||'— None —'}</option>`).join('');
  const barangayOpts = BARANGAYS.map(b=>`<option value="${b}" ${r.barangay===b?'selected':''}>${b}</option>`).join('');
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const monthOpts = `<option value="">Month</option>`+months.map(m=>`<option value="${m}" ${r.month===m?'selected':''}>${m}</option>`).join('');
  const dayOpts = `<option value="">Day</option>`+Array.from({length:31},(_,i)=>i+1).map(d=>`<option value="${d}" ${parseInt(r.date)===d?'selected':''}>${d}</option>`).join('');
  const yearStart = new Date().getFullYear()-60;
  const yearOpts = `<option value="">Year</option>`+Array.from({length:yearStart-1919},(_,i)=>yearStart-i).map(y=>`<option value="${y}" ${parseInt(r.year)===y?'selected':''}>${y}</option>`).join('');
  const regDateHTML = isAdmin ? `<div class="edit-section-title">${icon('calendar_month')} Registration Date</div><div class="edit-grid edit-grid-3" style="max-width:420px"><div class="edit-field"><label>Month</label><select id="e_reg_month" class="edit-input"><option value="">— Keep current —</option>${months.map((m,idx)=>{const cur=r.created_at?new Date(r.created_at).getMonth():-1;return`<option value="${m}" ${cur===idx?'selected':''}>${m}</option>`;}).join('')}</select></div><div class="edit-field"><label>Day</label><select id="e_reg_day" class="edit-input"><option value="">— Keep current —</option>${Array.from({length:31},(_,i)=>i+1).map(d=>{const cur=r.created_at?new Date(r.created_at).getDate():0;return`<option value="${d}" ${cur===d?'selected':''}>${d}</option>`;}).join('')}</select></div><div class="edit-field"><label>Year</label><select id="e_reg_year" class="edit-input"><option value="">— Keep current —</option>${Array.from({length:new Date().getFullYear()-1989},(_,i)=>new Date().getFullYear()-i).map(y=>{const cur=r.created_at?new Date(r.created_at).getFullYear():0;return`<option value="${y}" ${cur===y?'selected':''}>${y}</option>`;}).join('')}</select></div></div>` : '';

  const step1HTML = `<div class="edit-form"><div class="edit-section-title">${icon('person')} Full Name</div><div class="edit-grid edit-grid-4"><div class="edit-field"><label>Last Name *</label><input id="e_lastname" type="text" value="${esc(r.lastnameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" placeholder="E.G. DELA CRUZ"><span class="edit-field-hint" id="hint_lastname"></span></div><div class="edit-field"><label>First Name *</label><input id="e_firstname" type="text" value="${esc(r.firstnameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" placeholder="E.G. JUAN"><span class="edit-field-hint" id="hint_firstname"></span></div><div class="edit-field"><label>Middle Name *</label><div style="display:flex;gap:6px;align-items:center"><input id="e_middlename" type="text" value="${esc(r.middlenameApplicant)}" class="edit-input" maxlength="50" oninput="enforceNameField(this)" style="flex:1${midIsNA?';background:#f5f3f5':''}" ${midIsNA?'readonly':''}><button type="button" class="btn btn-outline na-toggle-btn" style="padding:6px 10px;font-size:.72rem;white-space:nowrap;flex-shrink:0" onclick="toggleMiddleNameNA(this)">${midIsNA?'Undo':'N/A'}</button></div><span class="edit-field-hint" id="hint_middlename"></span></div><div class="edit-field"><label>Suffix</label><select id="e_suffix" class="edit-input">${suffixOpts}</select></div></div><div class="edit-section-title">${icon('home')} Address</div><div class="edit-grid edit-grid-3"><div class="edit-field"><label>Barangay *</label><select id="e_barangay" class="edit-input"><option value="">— Select —</option>${barangayOpts}</select></div><div class="edit-field"><label>Purok / Zone *</label><input id="e_purok" type="text" value="${esc(r.purok)}" class="edit-input"></div><div class="edit-field"><label>Street / House No.</label><input id="e_street" type="text" value="${esc(r.street)}" class="edit-input"></div></div><div class="edit-section-title">${icon('cake')} Birthdate &amp; Personal</div><div class="edit-grid edit-grid-3"><div class="edit-field"><label>Month *</label><select id="e_month" class="edit-input">${monthOpts}</select></div><div class="edit-field"><label>Day *</label><select id="e_day" class="edit-input">${dayOpts}</select></div><div class="edit-field"><label>Year *</label><select id="e_year" class="edit-input">${yearOpts}</select></div><div class="edit-field"><label>Birthplace</label><input id="e_birthplace" type="text" value="${esc(r.birthplace)}" class="edit-input"></div><div class="edit-field"><label>Sex *</label><select id="e_sex" class="edit-input"><option value="">Select</option><option value="Male" ${r.sex==='Male'?'selected':''}>Male</option><option value="Female" ${r.sex==='Female'?'selected':''}>Female</option></select></div><div class="edit-field"><label>Marital Status *</label><select id="e_marital" class="edit-input">${buildSelectOptions(['Single','Married','Widowed','Separated'],r.maritalStatus,'Select')}</select></div><div class="edit-field"><label>Religion</label><select id="e_religion" class="edit-input">${buildSelectOptions(['Catholic','Islam','Iglesia ni Cristo','Evangelicals','Protestants','Seventh-day Adventist','Bible Baptist','Church','Aglipayan','UCCP',"Jehovah's Witnesses",'Others'],r.religion,'Select')}</select></div><div class="edit-field"><label>Ethnic Origin</label><input id="e_ethnic" type="text" value="${esc(r.ethnicOrigin)}" class="edit-input"></div><div class="edit-field"><label>Language Spoken</label><input id="e_language" type="text" value="${esc(r.languageSpoken)}" class="edit-input"></div></div><div class="edit-section-title">${icon('call')} Contact</div><div class="edit-grid edit-grid-3"><div class="edit-field"><label>Contact Number *</label><input id="e_contact" type="tel" value="${esc(r.contactNumber)}" class="edit-input" maxlength="11" inputmode="numeric" oninput="enforceContactField(this)" placeholder="09XXXXXXXXX"><span class="edit-field-hint" id="hint_contact"></span></div><div class="edit-field"><label>Email Address *</label><div style="display:flex;gap:6px;align-items:center"><input id="e_email" type="email" value="${esc(r.emailAddress)}" class="edit-input" style="flex:1${emailIsNA?';background:#f5f3f5':''}" ${emailIsNA?'readonly':''}><button type="button" class="btn btn-outline na-toggle-btn" style="padding:6px 10px;font-size:.72rem;white-space:nowrap;flex-shrink:0" onclick="toggleEmailNA(this)">${emailIsNA?'Undo':'N/A'}</button></div></div><div class="edit-field"><label>FB Messenger</label><input id="e_fb" type="text" value="${esc(r.fbMessenger)}" class="edit-input"></div></div><div class="edit-section-title">${icon('badge')} Government IDs</div><div class="edit-grid edit-grid-3"><div class="edit-field"><label>OSCA ID</label><input id="e_osca" type="text" value="${esc(r.osca_ID)}" class="edit-input"></div><div class="edit-field"><label>GSIS/SSS ID</label><input id="e_gsis" type="text" value="${esc(r.gsis_sss_ID)}" class="edit-input"></div><div class="edit-field"><label>TIN ID</label><input id="e_tin" type="text" value="${esc(r.tin_ID)}" class="edit-input"></div><div class="edit-field"><label>PhilHealth ID</label><input id="e_phil" type="text" value="${esc(r.philHealth_ID)}" class="edit-input"></div><div class="edit-field"><label>SC Asso. ID</label><input id="e_sc" type="text" value="${esc(r.sc_asso_ID)}" class="edit-input"></div><div class="edit-field"><label>Other Govt. ID</label><input id="e_other" type="text" value="${esc(r.other_govt_ID)}" class="edit-input"></div></div><div class="edit-section-title">${icon('work')} Other Info</div><div class="edit-grid edit-grid-3"><div class="edit-field"><label>Employment / Business</label><input id="e_employment" type="text" value="${esc(r.employment_business)}" class="edit-input"></div><div class="edit-field"><label>Has Pension</label><select id="e_pension" class="edit-input"><option value="">Select</option><option value="Yes" ${r.hasPension==='Yes'?'selected':''}>Yes</option><option value="No" ${r.hasPension==='No'?'selected':''}>No</option></select></div><div class="edit-field"><label>Can Travel</label><select id="e_travel" class="edit-input"><option value="">Select</option><option value="Yes" ${r.travelCapability==='Yes'?'selected':''}>Yes</option><option value="No" ${r.travelCapability==='No'?'selected':''}>No</option></select></div><div class="edit-field"><label>Person with Disability</label><select id="e_disability" class="edit-input"><option value="">Select</option><option value="Yes" ${r.personWithDisability==='Yes'?'selected':''}>Yes</option><option value="No" ${r.personWithDisability==='No'?'selected':''}>No</option></select></div></div>${regDateHTML}</div>`;

  const step2HTML = `<div class="edit-form"><div class="edit-section-title">${icon('favorite')} Spouse</div><div class="edit-grid edit-grid-4"><div class="edit-field"><label>Last Name</label><input id="e_spouse_last" type="text" value="${esc(r.lastnameSpouse)}" class="edit-input"></div><div class="edit-field"><label>First Name</label><input id="e_spouse_first" type="text" value="${esc(r.firstnameSpouse)}" class="edit-input"></div><div class="edit-field"><label>Middle Name</label><input id="e_spouse_middle" type="text" value="${esc(r.middlenameSpouse)}" class="edit-input"></div><div class="edit-field"><label>Suffix</label><input id="e_spouse_suffix" type="text" value="${esc(r.suffixSpouse)}" class="edit-input"></div></div><div class="edit-section-title">${icon('man')} Father</div><div class="edit-grid edit-grid-4"><div class="edit-field"><label>Last Name</label><input id="e_father_last" type="text" value="${esc(r.lastnameFather)}" class="edit-input"></div><div class="edit-field"><label>First Name</label><input id="e_father_first" type="text" value="${esc(r.firstnameFather)}" class="edit-input"></div><div class="edit-field"><label>Middle Name</label><input id="e_father_middle" type="text" value="${esc(r.middlenameFather)}" class="edit-input"></div><div class="edit-field"><label>Suffix</label><input id="e_father_suffix" type="text" value="${esc(r.suffixFather)}" class="edit-input"></div></div><div class="edit-section-title">${icon('woman')} Mother</div><div class="edit-grid edit-grid-4"><div class="edit-field"><label>Last Name</label><input id="e_mother_last" type="text" value="${esc(r.lastnameMother)}" class="edit-input"></div><div class="edit-field"><label>First Name</label><input id="e_mother_first" type="text" value="${esc(r.firstnameMother)}" class="edit-input"></div><div class="edit-field"><label>Middle Name</label><input id="e_mother_middle" type="text" value="${esc(r.middlenameMother)}" class="edit-input"></div><div class="edit-field"><label>Suffix</label><input id="e_mother_suffix" type="text" value="${esc(r.suffixMother)}" class="edit-input"></div></div><div class="edit-section-title" style="display:flex;justify-content:space-between;align-items:center"><span>${icon('child_care')} Children</span><button type="button" onclick="addEditChildRow()" class="btn btn-outline" style="padding:4px 10px;font-size:.75rem;gap:4px">${icon('add')} Add Child</button></div>${Array.from({length: r.childCount || 5}, (_,idx)=>idx+1).map(i=>`<div class="child-row-label" style="display:flex;justify-content:space-between;align-items:center"><span>Child ${i}</span>${i>5?`<button type="button" onclick="removeEditChildRow()" style="font-size:.7rem;color:#ba1a1a;background:none;border:none;cursor:pointer;font-weight:600">✕ Remove</button>`:''}</div><div class="edit-grid edit-grid-5 child-row" style="margin-bottom:10px"><div class="edit-field"><label>Full Name</label><input id="e_child${i}_name" type="text" value="${esc(r['fullnameChild'+i])}" class="edit-input"></div><div class="edit-field"><label>Occupation</label><input id="e_child${i}_occ" type="text" value="${esc(r['occupationChild'+i])}" class="edit-input"></div><div class="edit-field"><label>Income</label><input id="e_child${i}_income" type="number" step="0.01" value="${r['incomeChild'+i]||''}" class="edit-input"></div><div class="edit-field"><label>Age</label><input id="e_child${i}_age" type="number" value="${r['ageChild'+i]||''}" class="edit-input"></div><div class="edit-field"><label>Working?</label><select id="e_child${i}_working" class="edit-input"><option value="">—</option><option value="Yes" ${r['isWorkingChild'+i]==='Yes'?'selected':''}>Yes</option><option value="No" ${r['isWorkingChild'+i]==='No'?'selected':''}>No</option></select></div></div>`).join('')}<div class="edit-section-title">${icon('group')} Dependents</div>${[1,2].map(i=>`<div class="child-row-label">Dependent ${i}</div><div class="edit-grid edit-grid-5 child-row" style="margin-bottom:10px"><div class="edit-field"><label>Full Name</label><input id="e_dep${i}_name" type="text" value="${esc(r['fullnameDependent'+i])}" class="edit-input"></div><div class="edit-field"><label>Occupation</label><input id="e_dep${i}_occ" type="text" value="${esc(r['occupationDependent'+i])}" class="edit-input"></div><div class="edit-field"><label>Income</label><input id="e_dep${i}_income" type="number" step="0.01" value="${r['incomeDependent'+i]||''}" class="edit-input"></div><div class="edit-field"><label>Age</label><input id="e_dep${i}_age" type="number" value="${r['ageDependent'+i]||''}" class="edit-input"></div><div class="edit-field"><label>Working?</label><select id="e_dep${i}_working" class="edit-input"><option value="">—</option><option value="Yes" ${r['isWorkingDependent'+i]==='Yes'?'selected':''}>Yes</option><option value="No" ${r['isWorkingDependent'+i]==='No'?'selected':''}>No</option></select></div></div>`).join('')}</div>`;

  const step3HTML = `<div class="edit-form"><div class="edit-section-title">${icon('cottage')} Living Situation (Q25)</div><div class="edit-grid edit-grid-2" style="margin-bottom:14px"><div class="edit-field"><label>Living Alone?</label><select id="e_livingAlone" class="edit-input"><option value="">— Select —</option><option value="Yes" ${r.livingAlone==='Yes'?'selected':''}>Yes</option><option value="No" ${r.livingAlone==='No'?'selected':''}>No</option></select></div><div class="edit-field"><label>If No — Living With (comma-separated)</label><input id="e_livingWith" type="text" value="${esc(r.livingWith)}" class="edit-input"></div><div class="edit-field"><label>Others (specify)</label><input id="e_livingWithOthers" type="text" value="${esc(r.livingWithOthers)}" class="edit-input"></div></div><div class="edit-section-title">${icon('apartment')} Living Condition (Q26)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Conditions (comma-separated)</label><input id="e_livingCondition" type="text" value="${esc(r.livingCondition)}" class="edit-input"></div><div class="edit-field"><label>Others (specify)</label><input id="e_livingConditionOthers" type="text" value="${esc(r.livingConditionOthers)}" class="edit-input"></div></div></div>`;

  const educOpts = ['Not Attended School','Elementary Level','Elementary Graduate','High School Level','High School Graduate','Vocational','College Level','College Graduate','Post Graduate','Others'];
  const step4HTML = `<div class="edit-form"><div class="edit-section-title">${icon('school')} Education (Q27)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Highest Educational Attainment</label><select id="e_educationHighest" class="edit-input">${buildSelectOptions(educOpts,r.educationHighest,'— Select —')}</select></div><div class="edit-field"><label>Others (specify)</label><input id="e_educationHighestOthers" type="text" value="${esc(r.educationHighestOthers)}" class="edit-input"></div></div><div class="edit-section-title">${icon('build')} Skills (Q28)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Technical Skills (comma-separated)</label><input id="e_skills" type="text" value="${esc(r.skills)}" class="edit-input"></div><div class="edit-field"><label>Others (specify)</label><input id="e_skillsOthers" type="text" value="${esc(r.skillsOthers)}" class="edit-input"></div></div><div class="edit-section-title">${icon('groups')} Community (Q29–30)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Shared Skills (Q29)</label><textarea id="e_sharedSkills" class="edit-input" rows="2">${esc(r.sharedSkills)}</textarea></div><div class="edit-field"><label>Community Involvement (comma-separated)</label><input id="e_communityInvolvement" type="text" value="${esc(r.communityInvolvement)}" class="edit-input"></div><div class="edit-field"><label>Community Others</label><input id="e_communityInvolvementOthers" type="text" value="${esc(r.communityInvolvementOthers)}" class="edit-input"></div></div></div>`;

  const incomeOpts = ['60k and above','50k to 60k','40k to 50k','30k to 40k','20k to 30k','10k to 20k','5k to 10k','below 5k','None'];
  const step5HTML = `<div class="edit-form"><div class="edit-section-title">${icon('payments')} Source of Income (Q31)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Sources (comma-separated)</label><input id="e_sourceIncome" type="text" value="${esc(r.sourceIncome)}" class="edit-input"></div><div class="edit-field"><label>Others (specify)</label><input id="e_sourceIncomeOthers" type="text" value="${esc(r.sourceIncomeOthers)}" class="edit-input"></div></div><div class="edit-section-title">${icon('home_work')} Assets (Q32)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Real / Immovable Properties</label><input id="e_assetsReal" type="text" value="${esc(r.assetsReal)}" class="edit-input"></div><div class="edit-field"><label>Real Others</label><input id="e_assetsRealOthers" type="text" value="${esc(r.assetsRealOthers)}" class="edit-input"></div><div class="edit-field"><label>Personal / Movable Properties</label><input id="e_assetsPersonal" type="text" value="${esc(r.assetsPersonal)}" class="edit-input"></div><div class="edit-field"><label>Personal Others</label><input id="e_assetsPersonalOthers" type="text" value="${esc(r.assetsPersonalOthers)}" class="edit-input"></div></div><div class="edit-section-title">${icon('bar_chart')} Monthly Income &amp; Needs (Q33–34)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Monthly Income</label><select id="e_incomeMonthly" class="edit-input">${buildSelectOptions(incomeOpts,r.incomeMonthly,'— Select —')}</select></div><div class="edit-field"><label>Problems / Needs (comma-separated)</label><input id="e_problemsNeeds" type="text" value="${esc(r.problemsNeeds)}" class="edit-input"></div><div class="edit-field"><label>Livelihood Specify</label><input id="e_problemsNeedsLivelihood" type="text" value="${esc(r.problemsNeedsLivelihood)}" class="edit-input"></div><div class="edit-field"><label>Others</label><input id="e_problemsNeedsOthers" type="text" value="${esc(r.problemsNeedsOthers)}" class="edit-input"></div></div></div>`;

  const bloodTypes = ['O','O+','O-','A','A+','A-','B','B+','B-','AB','AB+','AB-','Unknown'];
  const checkupFreqs = ['Monthly','Every 3 months','Every 6 months','Annually'];
  const step6HTML = `<div class="edit-form"><div class="edit-section-title">${icon('monitor_heart')} Health (Q35)</div><div class="edit-grid edit-grid-2"><div class="edit-field"><label>Blood Type *</label><select id="e_bloodType" class="edit-input">${buildSelectOptions(bloodTypes,r.bloodType,'— Select —')}</select></div><div class="edit-field"><label>Physical Disability</label><textarea id="e_physicalDisability" class="edit-input" rows="2">${esc(r.physicalDisability)}</textarea></div><div class="edit-field"><label>Health Problems (comma-separated)</label><input id="e_healthProblems" type="text" value="${esc(r.healthProblems)}" class="edit-input"></div><div class="edit-field"><label>Health Problems Others</label><input id="e_healthProblemsOthers" type="text" value="${esc(r.healthProblemsOthers)}" class="edit-input"></div><div class="edit-field"><label>Dental Concern (comma-separated)</label><input id="e_dentalConcern" type="text" value="${esc(r.dentalConcern)}" class="edit-input"></div><div class="edit-field"><label>Visual Concern (comma-separated)</label><input id="e_visualConcern" type="text" value="${esc(r.visualConcern)}" class="edit-input"></div><div class="edit-field"><label>Aural Concern (comma-separated)</label><input id="e_auralConcern" type="text" value="${esc(r.auralConcern)}" class="edit-input"></div><div class="edit-field"><label>Maintenance Medicines</label><textarea id="e_listOfMedicines" class="edit-input" rows="2">${esc(r.listOfMedicines)}</textarea></div><div class="edit-field"><label>Scheduled Checkup</label><select id="e_scheduledCheckup" class="edit-input"><option value="">— Select —</option><option value="Yes" ${r.scheduledCheckup==='Yes'?'selected':''}>Yes</option><option value="No" ${r.scheduledCheckup==='No'?'selected':''}>No</option></select></div><div class="edit-field"><label>Checkup Frequency</label><select id="e_scheduledCheckupYes" class="edit-input">${buildSelectOptions(checkupFreqs,r.scheduledCheckupYes,'— Select —')}</select></div></div></div>`;

  const oscaPreview  = r.oscaID_type    ? `<img src="get_image.php?id=${r.id}&type=osca"  style="max-width:240px;max-height:160px;object-fit:contain;border-radius:6px;border:1px solid rgba(149,165,166,.30);background:#f5f3f5;margin-bottom:8px;display:block">` : '';
  const photoPreview = r.photoLatest_type ? `<img src="get_image.php?id=${r.id}&type=photo" style="max-width:140px;max-height:180px;object-fit:contain;border-radius:6px;border:1px solid rgba(149,165,166,.30);background:#f5f3f5;margin-bottom:8px;display:block">` : '';
  const step7HTML = `<div class="edit-form"><div class="edit-section-title">${icon('badge')} OSCA ID Photo</div><p class="edit-hint-text">Current photo shown below. Upload a new file to replace it.</p>${oscaPreview}<div class="edit-field"><label>Replace OSCA ID Photo</label><input type="file" id="e_oscaID" accept="image/jpeg,image/png,image/webp" class="edit-input" style="padding:6px"></div><div class="edit-section-title" style="margin-top:20px">${icon('photo_camera')} Latest 2×2 Photo</div><p class="edit-hint-text">Current photo shown below. Upload a new file to replace it.</p>${photoPreview}<div class="edit-field"><label>Replace 2×2 Photo</label><input type="file" id="e_photoLatest" accept="image/jpeg,image/png,image/webp" class="edit-input" style="padding:6px"></div></div>`;

  const steps = [step1HTML,step2HTML,step3HTML,step4HTML,step5HTML,step6HTML,step7HTML];
  const tabIcons = ['badge','diversity_3','cottage','school','payments','monitor_heart','photo_camera'];
  const tabNames = ['Identifying','Family','Dependency','Education','Economic','Health','ID & Photo'];
  const stepLabels = ['Step 1 — Identifying Information','Step 2 — Family Composition','Step 3 — Dependency Profile','Step 4 — Education / HR Profile','Step 5 — Economic Profile','Step 6 — Health Profile','Step 7 — ID & Photo Upload'];
  const isLast = editCurrentStep === EDIT_TOTAL_STEPS;

  document.getElementById('editModalBody').innerHTML = `
    <div class="step-tabs">${tabNames.map((name,i)=>`<button class="step-tab ${editCurrentStep===i+1?'active':''}" onclick="editGoToStep(${i+1})"><span class="step-tab-num">${i+1}</span>${icon(tabIcons[i],'step-tab-icon')}<span class="step-tab-label">${name}</span></button>`).join('')}</div>
    <div class="step-label-bar">${stepLabels[editCurrentStep-1]}</div>
    <div class="step-content" id="editStepContent">${steps[editCurrentStep-1]}</div>
    
    <div class="step-nav">
      <button class="btn btn-outline step-prev-btn" onclick="editGoToStep(${editCurrentStep-1})" ${editCurrentStep===1?'disabled':''}>${icon('arrow_back')} Previous</button>
      <div class="step-counter">
        <div class="step-dots">
          ${Array.from({length: EDIT_TOTAL_STEPS}, (_,i) => '<div class="step-dot ' + (i+1===editCurrentStep?'active':i+1<editCurrentStep?'done':'') + '"></div>').join('')}
        </div>
        <span class="step-counter-label">Step ${editCurrentStep} of ${EDIT_TOTAL_STEPS}</span>
      </div>
      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn btn-outline" onclick="saveEdit()" style="border-color:#1d3246;color:#1d3246">${icon('save')} Save</button>
        <button class="btn btn-primary step-next-btn" onclick="editGoToStep(${editCurrentStep+1})" ${isLast?'disabled':''}>Next ${icon('arrow_forward')}</button>
      </div>
    </div>`;


  document.getElementById('saveEditBtn').style.display = isLast ? 'inline-flex' : 'none';
  document.getElementById('editNextBtn').style.display = 'none';
}

function editGoToStep(step) {
  if (step < 1 || step > EDIT_TOTAL_STEPS || !_editRecord) return;
  document.activeElement?.blur();
  captureEditStep(_editRecord, editCurrentStep);
  editCurrentStep = step; renderEditModal(_editRecord);
  document.getElementById('editModalBody').scrollTop = 0;
}
function editSaveAndGoToStep(step) { editGoToStep(step); }

function addEditChildRow() {
  if (!_editRecord) return;
  captureEditStep(_editRecord, editCurrentStep);
  _editRecord.childCount = (_editRecord.childCount || 5) + 1;
  renderEditModal(_editRecord);
}
function removeEditChildRow() {
  if (!_editRecord || (_editRecord.childCount || 5) <= 5) return;
  const n = _editRecord.childCount;
  captureEditStep(_editRecord, editCurrentStep);
  delete _editRecord[`fullnameChild${n}`];
  delete _editRecord[`occupationChild${n}`];
  delete _editRecord[`incomeChild${n}`];
  delete _editRecord[`ageChild${n}`];
  delete _editRecord[`isWorkingChild${n}`];
  _editRecord.childCount = n - 1;
  renderEditModal(_editRecord);
}

function captureEditStep(r, step) {
  const val = id => { const el = document.getElementById(id); return el ? el.value : ''; };
  const isAdmin = window.OSCA?.isAdmin ?? false;
  if (step===1){r.lastnameApplicant=val('e_lastname');r.firstnameApplicant=val('e_firstname');r.middlenameApplicant=val('e_middlename');r.suffixApplicant=val('e_suffix');r.barangay=val('e_barangay');r.purok=val('e_purok');r.street=val('e_street');r.month=val('e_month');r.date=val('e_day');r.year=val('e_year');r.birthplace=val('e_birthplace');r.sex=val('e_sex');r.maritalStatus=val('e_marital');r.religion=val('e_religion');r.ethnicOrigin=val('e_ethnic');r.languageSpoken=val('e_language');r.contactNumber=val('e_contact');r.emailAddress=val('e_email');r.fbMessenger=val('e_fb');r.osca_ID=val('e_osca');r.gsis_sss_ID=val('e_gsis');r.tin_ID=val('e_tin');r.philHealth_ID=val('e_phil');r.sc_asso_ID=val('e_sc');r.other_govt_ID=val('e_other');r.employment_business=val('e_employment');r.hasPension=val('e_pension');r.travelCapability=val('e_travel');r.personWithDisability=val('e_disability');if(isAdmin){r.reg_month=val('e_reg_month');r.reg_day=val('e_reg_day');r.reg_year=val('e_reg_year');}
  }else if(step===2){r.lastnameSpouse=val('e_spouse_last');r.firstnameSpouse=val('e_spouse_first');r.middlenameSpouse=val('e_spouse_middle');r.suffixSpouse=val('e_spouse_suffix');r.lastnameFather=val('e_father_last');r.firstnameFather=val('e_father_first');r.middlenameFather=val('e_father_middle');r.suffixFather=val('e_father_suffix');r.lastnameMother=val('e_mother_last');r.firstnameMother=val('e_mother_first');r.middlenameMother=val('e_mother_middle');r.suffixMother=val('e_mother_suffix');for(let i=1;i<=(r.childCount||5);i++){r['fullnameChild'+i]=val(`e_child${i}_name`);r['occupationChild'+i]=val(`e_child${i}_occ`);r['incomeChild'+i]=val(`e_child${i}_income`);r['ageChild'+i]=val(`e_child${i}_age`);r['isWorkingChild'+i]=val(`e_child${i}_working`);}for(let i=1;i<=2;i++){r['fullnameDependent'+i]=val(`e_dep${i}_name`);r['occupationDependent'+i]=val(`e_dep${i}_occ`);r['incomeDependent'+i]=val(`e_dep${i}_income`);r['ageDependent'+i]=val(`e_dep${i}_age`);r['isWorkingDependent'+i]=val(`e_dep${i}_working`);}
  }else if(step===3){r.livingAlone=val('e_livingAlone');r.livingWith=val('e_livingWith');r.livingWithOthers=val('e_livingWithOthers');r.livingCondition=val('e_livingCondition');r.livingConditionOthers=val('e_livingConditionOthers');
  }else if(step===4){r.educationHighest=val('e_educationHighest');r.educationHighestOthers=val('e_educationHighestOthers');r.skills=val('e_skills');r.skillsOthers=val('e_skillsOthers');r.sharedSkills=val('e_sharedSkills');r.communityInvolvement=val('e_communityInvolvement');r.communityInvolvementOthers=val('e_communityInvolvementOthers');
  }else if(step===5){r.sourceIncome=val('e_sourceIncome');r.sourceIncomeOthers=val('e_sourceIncomeOthers');r.assetsReal=val('e_assetsReal');r.assetsRealOthers=val('e_assetsRealOthers');r.assetsPersonal=val('e_assetsPersonal');r.assetsPersonalOthers=val('e_assetsPersonalOthers');r.incomeMonthly=val('e_incomeMonthly');r.problemsNeeds=val('e_problemsNeeds');r.problemsNeedsLivelihood=val('e_problemsNeedsLivelihood');r.problemsNeedsOthers=val('e_problemsNeedsOthers');
  }else if(step===6){r.bloodType=val('e_bloodType');r.physicalDisability=val('e_physicalDisability');r.healthProblems=val('e_healthProblems');r.healthProblemsOthers=val('e_healthProblemsOthers');r.dentalConcern=val('e_dentalConcern');r.dentalConcernOthers=val('e_dentalConcernOthers');r.visualConcern=val('e_visualConcern');r.visualConcernOthers=val('e_visualConcernOthers');r.auralConcern=val('e_auralConcern');r.auralConcernOthers=val('e_auralConcernOthers');r.socialConcern=val('e_socialConcern');r.socialConcernOthers=val('e_socialConcernOthers');r.areaDifficulty=val('e_areaDifficulty');r.areaDifficultyOthers=val('e_areaDifficultyOthers');r.listOfMedicines=val('e_listOfMedicines');r.scheduledCheckup=val('e_scheduledCheckup');r.scheduledCheckupYes=val('e_scheduledCheckupYes');}
}

function esc(v) { if (!v) return ''; return String(v).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function enforceNameField(input) {
  const hint = document.getElementById('hint_' + input.id.replace('e_',''));
  const final = input.value.toUpperCase().replace(/[^A-ZÑ ]/g,'');
  if (input.value !== final) input.value = final;
  if (hint) { if (final.length===50) showEditHint(hint,'⚠ Maximum 50 characters reached','warn'); else if (final.length>0) showEditHint(hint,'✓ Valid','ok'); else clearEditHint(hint); }
}
function toggleMiddleNameNA(btn) {
  const input = document.getElementById('e_middlename');
  const hint  = document.getElementById('hint_middlename');
  if (!input) return;
  if (input.readOnly) {
    input.value = input.dataset.prevValue || '';
    input.readOnly = false;
    input.style.background = '';
    btn.textContent = 'N/A';
    enforceNameField(input);
  } else {
    input.dataset.prevValue = input.value;
    input.value = 'N/A';
    input.readOnly = true;
    input.style.background = '#f5f3f5';
    btn.textContent = 'Undo';
    if (hint) clearEditHint(hint);
  }
}
function toggleEmailNA(btn) {
  const input = document.getElementById('e_email');
  if (!input) return;
  if (input.readOnly) {
    input.value = input.dataset.prevValue || '';
    input.readOnly = false;
    input.style.background = '';
    btn.textContent = 'N/A';
  } else {
    input.dataset.prevValue = input.value;
    input.value = 'N/A';
    input.readOnly = true;
    input.style.background = '#f5f3f5';
    btn.textContent = 'Undo';
  }
}
function enforceContactField(input) {
  const hint = document.getElementById('hint_contact');
  let digits = input.value.replace(/\D/g,'');
  if (digits.length>=1&&digits[0]!=='0') digits='0'+digits;
  if (digits.length>=2&&digits[1]!=='9') digits='09'+digits.replace(/^0*9?/,'');
  if (digits.length>11) digits=digits.slice(0,11);
  if (input.value!==digits) input.value=digits;
  if (!hint) return;
  if (digits.length===0) clearEditHint(hint);
  else if (digits.length===11&&/^09\d{9}$/.test(digits)) showEditHint(hint,'✓ Valid Philippine mobile number','ok');
  else showEditHint(hint,`${11-digits.length} more digit(s) needed`,'warn');
}
function showEditHint(el,msg,type){if(!el)return;el.textContent=msg;el.className='edit-field-hint hint-'+type;}
function clearEditHint(el){if(!el)return;el.textContent='';el.className='edit-field-hint';}

async function saveEdit() {
  if (!editingId || !_editRecord) return;
  captureEditStep(_editRecord, editCurrentStep);
  const r = _editRecord;
  const isAdmin = window.OSCA?.isAdmin ?? false;
  if (!r.lastnameApplicant||!r.firstnameApplicant||!r.middlenameApplicant){toast('Name fields are required.','error');return;}
  if (!r.barangay||!r.purok){toast('Barangay and Purok are required.','error');return;}
  if (!r.contactNumber||!r.emailAddress){toast('Contact number and email are required.','error');return;}
  if (!r.sex||!r.maritalStatus){toast('Sex and marital status are required.','error');return;}
  if (!r.month||!r.date||!r.year){toast('Complete birthdate is required.','error');return;}
  if (!/^09\d{9}$/.test(r.contactNumber)){toast('Contact number must be 11 digits starting with 09.','error');return;}
  if (!r.bloodType){toast('Blood type is required.','error');return;}

  const btn = document.getElementById('saveEditBtn');
  btn.disabled = true; btn.innerHTML = `${icon('progress_activity','spin')} Saving…`;
  try {
    const fd = new FormData();
    fd.append('action','update_record'); fd.append('id',editingId);
    const fields = {lastnameApplicant:r.lastnameApplicant,firstnameApplicant:r.firstnameApplicant,middlenameApplicant:r.middlenameApplicant,suffixApplicant:r.suffixApplicant||'',barangay:r.barangay,purok:r.purok||'',street:r.street||'',month:r.month||'',date:r.date||'',year:r.year||'',birthplace:r.birthplace||'',sex:r.sex||'',maritalStatus:r.maritalStatus||'',religion:r.religion||'',ethnicOrigin:r.ethnicOrigin||'',languageSpoken:r.languageSpoken||'',contactNumber:r.contactNumber,emailAddress:r.emailAddress,fbMessenger:r.fbMessenger||'',osca_ID:r.osca_ID||'',gsis_sss_ID:r.gsis_sss_ID||'',tin_ID:r.tin_ID||'',philHealth_ID:r.philHealth_ID||'',sc_asso_ID:r.sc_asso_ID||'',other_govt_ID:r.other_govt_ID||'',employment_business:r.employment_business||'',hasPension:r.hasPension||'',travelCapability:r.travelCapability||'',personWithDisability:r.personWithDisability||'',reg_month:isAdmin?(r.reg_month||''):'',reg_day:isAdmin?(r.reg_day||''):'',reg_year:isAdmin?(r.reg_year||''):'',lastnameSpouse:r.lastnameSpouse||'',firstnameSpouse:r.firstnameSpouse||'',middlenameSpouse:r.middlenameSpouse||'',suffixSpouse:r.suffixSpouse||'',lastnameFather:r.lastnameFather||'',firstnameFather:r.firstnameFather||'',middlenameFather:r.middlenameFather||'',suffixFather:r.suffixFather||'',lastnameMother:r.lastnameMother||'',firstnameMother:r.firstnameMother||'',middlenameMother:r.middlenameMother||'',suffixMother:r.suffixMother||'',livingAlone:r.livingAlone||'',livingWith:r.livingWith||'',livingWithOthers:r.livingWithOthers||'',livingCondition:r.livingCondition||'',livingConditionOthers:r.livingConditionOthers||'',educationHighest:r.educationHighest||'',educationHighestOthers:r.educationHighestOthers||'',skills:r.skills||'',skillsOthers:r.skillsOthers||'',sharedSkills:r.sharedSkills||'',communityInvolvement:r.communityInvolvement||'',communityInvolvementOthers:r.communityInvolvementOthers||'',sourceIncome:r.sourceIncome||'',sourceIncomeOthers:r.sourceIncomeOthers||'',assetsReal:r.assetsReal||'',assetsRealOthers:r.assetsRealOthers||'',assetsPersonal:r.assetsPersonal||'',assetsPersonalOthers:r.assetsPersonalOthers||'',incomeMonthly:r.incomeMonthly||'',problemsNeeds:r.problemsNeeds||'',problemsNeedsLivelihood:r.problemsNeedsLivelihood||'',problemsNeedsOthers:r.problemsNeedsOthers||'',bloodType:r.bloodType||'',physicalDisability:r.physicalDisability||'',healthProblems:r.healthProblems||'',healthProblemsOthers:r.healthProblemsOthers||'',dentalConcern:r.dentalConcern||'',dentalConcernOthers:r.dentalConcernOthers||'',visualConcern:r.visualConcern||'',visualConcernOthers:r.visualConcernOthers||'',auralConcern:r.auralConcern||'',auralConcernOthers:r.auralConcernOthers||'',socialConcern:r.socialConcern||'',socialConcernOthers:r.socialConcernOthers||'',areaDifficulty:r.areaDifficulty||'',areaDifficultyOthers:r.areaDifficultyOthers||'',listOfMedicines:r.listOfMedicines||'',scheduledCheckup:r.scheduledCheckup||'',scheduledCheckupYes:r.scheduledCheckupYes||''};
    for(const [k,v] of Object.entries(fields)) fd.append(k,v);
    const childCount = r.childCount || 5;
fd.append('childCount', childCount);
for(let i=1;i<=childCount;i++){fd.append(`fullnameChild${i}`,r[`fullnameChild${i}`]||'');fd.append(`occupationChild${i}`,r[`occupationChild${i}`]||'');fd.append(`incomeChild${i}`,r[`incomeChild${i}`]||'');fd.append(`ageChild${i}`,r[`ageChild${i}`]||'');fd.append(`isWorkingChild${i}`,r[`isWorkingChild${i}`]||'');}
    for(let i=1;i<=2;i++){fd.append(`fullnameDependent${i}`,r[`fullnameDependent${i}`]||'');fd.append(`occupationDependent${i}`,r[`occupationDependent${i}`]||'');fd.append(`incomeDependent${i}`,r[`incomeDependent${i}`]||'');fd.append(`ageDependent${i}`,r[`ageDependent${i}`]||'');fd.append(`isWorkingDependent${i}`,r[`isWorkingDependent${i}`]||'');}
    const oscaFile=document.getElementById('e_oscaID'); const photoFile=document.getElementById('e_photoLatest');
    if(oscaFile&&oscaFile.files[0]) fd.append('oscaID',oscaFile.files[0]);
    if(photoFile&&photoFile.files[0]) fd.append('photoLatest',photoFile.files[0]);
    const res=await fetch('save.php',{method:'POST',body:fd});
    const data=await res.json();
    if(data.success){toast('Record updated successfully. Remember to export a new backup.','success');closeEditModal();refreshNotifState();setTimeout(()=>location.reload(),800);}
    else toast(data.message||'Update failed.','error');
  } catch(e){toast('Network error.','error');}
  finally{btn.disabled=false;btn.innerHTML=`${icon('save')} Save Changes`;}
}

function closeEditModal(){
  document.getElementById('editModal').classList.remove('open');
  document.body.style.overflow=''; editingId=null; _editRecord=null; editCurrentStep=1;
}


// ═══════════════════ DELETE — admin only ═════════════════════
let pendingDeleteId   = null;
let pendingDeleteName = '';

function confirmDelete(id, name) {
  if (!window.OSCA?.isAdmin) { toast('Only administrators can delete records.','error'); return; }
  pendingDeleteId   = id;
  pendingDeleteName = name.split(',')[0].trim().toUpperCase();
  document.getElementById('deleteName').textContent = name;
  document.getElementById('deleteConfirmInput').value = '';
  document.getElementById('deleteConfirmHint').textContent = '';
  document.getElementById('confirmDeleteBtn').disabled = true;
  openModal('deleteModal');
  setTimeout(()=>document.getElementById('deleteConfirmInput').focus(),300);
}

function checkDeleteConfirm() {
  const input=document.getElementById('deleteConfirmInput');
  const hint=document.getElementById('deleteConfirmHint');
  const btn=document.getElementById('confirmDeleteBtn');
  const cleaned=input.value.toUpperCase().replace(/[^A-ZÑ ]/g,'').slice(0,50);
  if(input.value!==cleaned) input.value=cleaned;
  const typed=cleaned.trim();
  if(typed.length===0){btn.disabled=true;hint.textContent='';input.style.borderColor='';}
  else if(typed===pendingDeleteName){btn.disabled=false;hint.textContent='✓ Name matches';hint.style.color='#27ae60';input.style.borderColor='#27ae60';}
  else{btn.disabled=true;hint.textContent='✗ Name does not match';hint.style.color='#c0392b';input.style.borderColor='#c0392b';}
}

// ── FIXED: Delete button event listener ─────────────────────
const _confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
if (_confirmDeleteBtn) {
  _confirmDeleteBtn.addEventListener('click', async function() {
    if (!pendingDeleteId) return;
    if (!window.OSCA?.isAdmin) { toast('Only administrators can delete records.', 'error'); return; }

    const deletingId = pendingDeleteId;  // capture ID before any async operations
    this.disabled    = true;
    this.textContent = 'Deleting…';

    try {
      const body = new URLSearchParams({ id: deletingId });
      const res  = await fetch('delete_record.php', { method: 'POST', body });
      const data = await res.json();

      if (data.success) {
        toast('Record deleted successfully. Remember to export a new backup.', 'success');
        refreshNotifState();
        closeDeleteModal();
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
      }
    } catch(e) {
      toast('Network error.', 'error');
    } finally {
      this.disabled    = false;
      this.textContent = 'Delete Record';
      pendingDeleteId  = null;
    }
  });
}

function updateStatCounts(){
  const totalEl=document.querySelector('.stat-total');
  if(totalEl){const cur=parseInt(totalEl.textContent.replace(/,/g,''),10);if(!isNaN(cur)&&cur>0)totalEl.textContent=(cur-1).toLocaleString();}
}
function checkEmptyState(){
  const tbody=document.querySelector('.data-table tbody');if(!tbody)return;
  if(tbody.querySelectorAll('tr.table-row').length===0){
    const tableWrap=document.querySelector('.table-wrap');const pagination=document.querySelector('.pagination');
    if(tableWrap)tableWrap.remove();if(pagination)pagination.remove();
  }
}

function updateRowNumbers(){
  const startOffset = window.OSCA?.currentOffset ?? 0;
  document.querySelectorAll('.data-table tbody .table-row').forEach((row, i) => {
    const td = row.querySelector('td:first-child');
    if (td) td.textContent = startOffset + i + 1;
  });
}


// ═══════════════════ MODAL HELPERS ═══════════════════════════
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(){document.getElementById('viewModal').classList.remove('open');document.body.style.overflow='';}
function closeDeleteModal(){const m=document.getElementById('deleteModal');if(m)m.classList.remove('open');document.body.style.overflow='';pendingDeleteId=null;}
function openLogoutModal(){openModal('logoutModal');}
function closeLogoutModal(){document.getElementById('logoutModal').classList.remove('open');document.body.style.overflow='';}

document.querySelectorAll('.modal-overlay').forEach(overlay=>{
  overlay.addEventListener('click',function(e){
    if(e.target===this){this.classList.remove('open');document.body.style.overflow='';pendingDeleteId=null;}
  });
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){closeModal();closeDeleteModal();closeEditModal();closeLogoutModal();closeSettingsModal();closeDeleteStaffModal();closeArchiveModal();closeRestoreModal();closePurgeModal();closeResetPasswordModal();closeNotifModal();closeDeleteAllNotifModal();}
});


// ═══════════════════ SETTINGS MODAL WITH TABS ════════════════
let _settingsTab = 'staff';

async function openSettingsModal(tab) {
  if (tab) _settingsTab = tab;
  openModal('settingsModal');
  await renderSettingsTab();
}

async function renderSettingsTab() {
  const body = document.getElementById('settingsModalBody');
  const isAdmin = window.OSCA?.isAdmin ?? false;

  if (_settingsTab === 'archive') {
    body.innerHTML = `<div id="archiveTabContent"><div class="modal-loading">${icon('progress_activity','spin')} Loading archive…</div></div>`;
    await loadArchiveTab();
  } else {
    if (!isAdmin) {
      body.innerHTML = `
        <div class="detail-section" style="margin-top:14px">
          <div class="detail-section-title">${icon('person')} Account</div>
          <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Display Name</div><div class="detail-value">${esc(window.OSCA?.displayName||'—')}</div></div>
            <div class="detail-item"><div class="detail-label">Role</div><div class="detail-value">Encoder</div></div>
          </div>
        </div>
        <p class="edit-hint-text" style="margin-top:8px">Only administrators can manage staff accounts.</p>`;
      return;
    }
    body.innerHTML = `<div id="staffTabContent"><div class="modal-loading">${icon('progress_activity','spin')} Loading staff…</div></div>`;
    try {
      const res  = await fetch('manage_staff.php?action=list_staff');
      const data = await res.json();
      if (!data.success) { document.getElementById('staffTabContent').innerHTML = `<p style="color:red">${esc(data.message||'Failed to load staff.')}</p>`; return; }
      renderStaffTab(data.staff);
    } catch(e) { document.getElementById('staffTabContent').innerHTML = '<p style="color:red">Network error.</p>'; }
  }
}

function switchSettingsTab(tab) {
  _settingsTab = tab;
  renderSettingsTab();
}

// ── Archive Tab ───────────────────────────────────────────────
let _archivePage   = 1;
let _archiveSearch = '';

async function loadArchiveTab(page, search) {
  if (page   !== undefined) _archivePage   = page;
  if (search !== undefined) _archiveSearch = search;

  const content = document.getElementById('archiveTabContent');
  if (content) content.innerHTML = `<div class="modal-loading" style="margin-top:12px">${icon('progress_activity','spin')} Loading…</div>`;

  try {
    const url = `get_archive.php?page=${_archivePage}&limit=10&search=${encodeURIComponent(_archiveSearch)}`;
    const res  = await fetch(url);
    const data = await res.json();
    if (!data.success) { if(content) content.innerHTML = `<p style="color:red">${esc(data.message)}</p>`; return; }
    renderArchiveTab(data);
  } catch(e) { if(content) content.innerHTML = '<p style="color:red">Network error.</p>'; }
}

function renderArchiveTab(data) {
  const content = document.getElementById('archiveTabContent');
  if (!content) return;

  const isAdmin = window.OSCA?.isAdmin ?? false;
  const { records, total, page, totalPages } = data;

  const searchBar = `
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;margin-top:14px">
      <div class="search-wrap" style="flex:1;position:relative;display:flex;align-items:center">
        <span class="material-symbols-outlined" style="position:absolute;left:10px;color:#74777d;font-size:18px;pointer-events:none">search</span>
        <input type="text" id="archiveSearchInput" value="${esc(_archiveSearch)}"
               placeholder="Search archived records…"
               oninput="debounceArchiveSearch(this.value)"
               style="width:100%;border:1px solid #95a5a6;border-radius:0.5rem;padding:7px 10px 7px 34px;font-size:.85rem;font-family:Inter,sans-serif">
      </div>
      <span style="font-size:.75rem;color:#74777d;font-family:'JetBrains Mono',monospace;white-space:nowrap">${total} archived</span>
    </div>`;

  if (records.length === 0) {
    content.innerHTML = searchBar + `
      <div style="text-align:center;padding:32px 16px;color:#74777d">
        <span class="material-symbols-outlined" style="font-size:48px;display:block;margin-bottom:8px;color:#95a5a6">inventory_2</span>
        <p style="font-weight:600;color:#43474c;margin-bottom:4px">${_archiveSearch ? 'No results found' : 'Archive is empty'}</p>
        <p style="font-size:.83rem">${_archiveSearch ? 'Try a different search term.' : 'Records moved to archive will appear here.'}</p>
      </div>`;
    return;
  }

  const rows = records.map(r => {
    const fullName = `${r.lastnameApplicant}, ${r.firstnameApplicant} ${r.middlenameApplicant||''}`.trim();
    const age = (() => {
      if (r.month && r.date && r.year) {
        const dob = new Date(`${r.month} ${r.date}, ${r.year}`);
        if (!isNaN(dob)) { const diff = Date.now() - dob.getTime(); return Math.floor(diff / 31557600000); }
      } return '—';
    })();
    const archivedDate = r.archived_at ? new Date(r.archived_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}) : '—';

    const actionButtons = `
  <div style="display:inline-flex;gap:6px;align-items:center">
    <button onclick="restoreRecord(${r.id}, '${esc(fullName)}', '${esc(r.barangay||'')}', '${age}', '${esc(r.archive_reason||'')}')"
            class="btn btn-outline" style="padding:5px 12px;font-size:.78rem;gap:4px">
      ${icon('restore')} Restore
    </button>
    ${isAdmin ? `
    <button onclick="purgeRecord(${r.id}, '${esc(fullName)}')"
            class="btn btn-danger" style="padding:5px 12px;font-size:.78rem;gap:4px"
            title="Permanently delete this record. Cannot be undone.">
      ${icon('delete_forever')} Delete
    </button>` : ''}
  </div>`;

    return `
      <tr style="border-bottom:1px solid rgba(149,165,166,.18)">
        <td style="padding:10px 12px;vertical-align:top">
          <div style="font-weight:600;font-size:.85rem;color:#1b1c1d">${esc(fullName)}</div>
          <div style="font-size:.75rem;color:#74777d;margin-top:2px">${esc(r.barangay||'—')} · Age ${age}</div>
          ${r.osca_ID ? `<div style="font-size:.72rem;font-family:'JetBrains Mono',monospace;color:#95a5a6;margin-top:1px">OSCA: ${esc(r.osca_ID)}</div>` : ''}
        </td>
        <td style="padding:10px 12px;vertical-align:top">
          <div class="archive-reason-display">${esc(r.archive_reason||'—')}</div>
          <div style="font-size:.72rem;color:#95a5a6;margin-top:4px;font-family:'JetBrains Mono',monospace">
            ${esc(r.archived_by||'—')} · ${archivedDate}
          </div>
        </td>
        <td style="padding:10px 12px;vertical-align:middle;text-align:right;white-space:nowrap">
          ${actionButtons}
        </td>
      </tr>`;
  }).join('');

  let pagination = '';
  if (totalPages > 1) {
    pagination = `<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid rgba(149,165,166,.20)">`;
    if (page > 1) pagination += `<button class="btn btn-outline" style="padding:5px 12px;font-size:.78rem" onclick="loadArchiveTab(${page-1})">← Prev</button>`;
    pagination += `<span style="font-size:.78rem;color:#74777d;font-family:'JetBrains Mono',monospace">Page ${page} of ${totalPages}</span>`;
    if (page < totalPages) pagination += `<button class="btn btn-outline" style="padding:5px 12px;font-size:.78rem" onclick="loadArchiveTab(${page+1})">Next →</button>`;
    pagination += `</div>`;
  }

  content.innerHTML = searchBar + `
    <div style="border:1px solid rgba(149,165,166,.25);border-radius:0.5rem;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:.83rem;font-family:Inter,sans-serif">
        <thead>
          <tr style="background:#f5f3f5;border-bottom:2px solid #efedef">
            <th style="padding:8px 12px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d;width:40%">Registrant</th>
            <th style="padding:8px 12px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d">Archive Reason</th>
            <th style="padding:8px 12px;background:#f5f3f5;width:${isAdmin ? '180px' : '100px'}"></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    ${pagination}`;
}
function renderArchiveTabInto(data, container) {
  const isAdmin = window.OSCA?.isAdmin ?? false;
  const { records, total, page, totalPages } = data;

  if (records.length === 0) {
    container.innerHTML = `
      <div style="text-align:center;padding:32px 16px;color:#74777d">
        <span class="material-symbols-outlined" style="font-size:48px;display:block;margin-bottom:8px;color:#95a5a6">inventory_2</span>
        <p style="font-weight:600;color:#43474c;margin-bottom:4px">${_archiveSearch ? 'No results found' : 'Archive is empty'}</p>
        <p style="font-size:.83rem">${_archiveSearch ? 'Try a different search term.' : 'Records moved to archive will appear here.'}</p>
      </div>`;
    return;
  }

  const rows = records.map(r => {
    const fullName = `${r.lastnameApplicant}, ${r.firstnameApplicant} ${r.middlenameApplicant||''}`.trim();
    const age = (() => {
      if (r.month && r.date && r.year) {
        const dob = new Date(`${r.month} ${r.date}, ${r.year}`);
        if (!isNaN(dob)) return Math.floor((Date.now() - dob.getTime()) / 31557600000);
      } return '—';
    })();
    const archivedDate = r.archived_at ? new Date(r.archived_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}) : '—';
    return `
      <tr style="border-bottom:1px solid rgba(149,165,166,.18)">
        <td style="padding:10px 12px;vertical-align:top">
          <div style="font-weight:600;font-size:.85rem;color:#1b1c1d">${esc(fullName)}</div>
          <div style="font-size:.75rem;color:#74777d;margin-top:2px">${esc(r.barangay||'—')} · Age ${age}</div>
          ${r.osca_ID ? `<div style="font-size:.72rem;font-family:'JetBrains Mono',monospace;color:#95a5a6;margin-top:1px">OSCA: ${esc(r.osca_ID)}</div>` : ''}
        </td>
        <td style="padding:10px 12px;vertical-align:top">
          <div class="archive-reason-display">${esc(r.archive_reason||'—')}</div>
          <div style="font-size:.72rem;color:#95a5a6;margin-top:4px;font-family:'JetBrains Mono',monospace">${esc(r.archived_by||'—')} · ${archivedDate}</div>
        </td>
        <td style="padding:10px 12px;vertical-align:middle;text-align:right;white-space:nowrap">
          <div style="display:inline-flex;gap:6px;align-items:center">
            <button onclick="restoreRecord(${r.id},'${esc(fullName)}','${esc(r.barangay||'')}','${age}','${esc(r.archive_reason||'')}')"
                    class="btn btn-outline" style="padding:5px 12px;font-size:.78rem;gap:4px">
              ${icon('restore')} Restore
            </button>
            ${isAdmin ? `<button onclick="purgeRecord(${r.id},'${esc(fullName)}')"
                    class="btn btn-danger" style="padding:5px 12px;font-size:.78rem;gap:4px">
              ${icon('delete_forever')} Delete
            </button>` : ''}
          </div>
        </td>
      </tr>`;
  }).join('');

  let pagination = '';
  if (totalPages > 1) {
    pagination = `<div style="display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px;padding-top:12px;border-top:1px solid rgba(149,165,166,.20)">`;
    if (page > 1) pagination += `<button class="btn btn-outline" style="padding:5px 12px;font-size:.78rem" onclick="loadArchiveTab(${page-1})">← Prev</button>`;
    pagination += `<span style="font-size:.78rem;color:#74777d;font-family:'JetBrains Mono',monospace">Page ${page} of ${totalPages}</span>`;
    if (page < totalPages) pagination += `<button class="btn btn-outline" style="padding:5px 12px;font-size:.78rem" onclick="loadArchiveTab(${page+1})">Next →</button>`;
    pagination += `</div>`;
  }

  container.innerHTML = `
    <div style="border:1px solid rgba(149,165,166,.25);border-radius:0.5rem;overflow:hidden">
      <table style="width:100%;border-collapse:collapse;font-size:.83rem;font-family:Inter,sans-serif">
        <thead><tr style="background:#f5f3f5;border-bottom:2px solid #efedef">
          <th style="padding:8px 12px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d;width:40%">Registrant</th>
          <th style="padding:8px 12px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d">Archive Reason</th>
          <th style="padding:8px 12px;width:${isAdmin?'180px':'100px'}"></th>
        </tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
    ${pagination}`;
}
let _archiveSearchTimer = null;
function debounceArchiveSearch(val) {
  clearTimeout(_archiveSearchTimer);
  _archiveSearchTimer = setTimeout(async () => {
    _archiveSearch = val;
    _archivePage   = 1;

    // Only refresh the table body, not the whole tab (preserves input focus)
    const content = document.getElementById('archiveTabContent');
    if (!content) return;

    try {
      const url  = `get_archive.php?page=1&limit=10&search=${encodeURIComponent(val)}`;
      const res  = await fetch(url);
      const data = await res.json();
      if (!data.success) return;

      // Re-render only the table + pagination, leave search bar untouched
      const tempDiv = document.createElement('div');
      renderArchiveTabInto(data, tempDiv);
      // Replace everything after the search bar
      const searchBar = content.querySelector('.search-wrap')?.closest('div[style*="margin-bottom"]');
      if (searchBar) {
        // Remove everything after the search bar row
        let next = searchBar.nextSibling;
        while (next) { const n = next.nextSibling; content.removeChild(next); next = n; }
        // Append new table content
        tempDiv.childNodes.forEach(node => content.appendChild(node.cloneNode(true)));
      } else {
        renderArchiveTab(data); // fallback
      }
    } catch(e) { /* silent fail */ }
  }, 350);
}
// ── Restore record ────────────────────────────────────────────
let _pendingRestoreId   = null;
let _pendingRestoreName = '';

function restoreRecord(id, name, barangay, age, reason) {
  _pendingRestoreId   = id;
  _pendingRestoreName = name;

  const nameEl   = document.getElementById('restoreName');
  const metaEl   = document.getElementById('restoreMeta');
  const reasonEl = document.getElementById('restoreReason');

  if (nameEl)   nameEl.textContent   = name;
  if (metaEl)   metaEl.textContent   = (barangay || '—') + ' · Age ' + (age || '—');
  if (reasonEl) reasonEl.textContent = reason || '—';

  openModal('restoreModal');
}

async function executeRestore() {
  if (!_pendingRestoreId) return;
  const btn = document.getElementById('confirmRestoreBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px">progress_activity</span> Restoring…`; }

  try {
    const body = new URLSearchParams({ action: 'restore', id: _pendingRestoreId });
    const res  = await fetch('archive_record.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      closeRestoreModal();
      toast('Record restored successfully. Remember to export a new backup.', 'success');
      refreshNotifState();
      if (window.OSCA.archivedCount > 0) window.OSCA.archivedCount--;
      await loadArchiveTab(_archivePage, _archiveSearch);
    } else {
      toast(data.message || 'Restore failed.', 'error');
    }
  } catch(e) { toast('Network error.', 'error'); }
  finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">restore</span> Restore to Active List`;
    }
    _pendingRestoreId = null;
  }
}

function closeRestoreModal() {
  const m = document.getElementById('restoreModal');
  if (m) m.classList.remove('open');
  document.body.style.overflow = '';
  _pendingRestoreId = null;
}

// ── Purge record — admin only ─────────────────────────────────
let _pendingPurgeId       = null;
let _pendingPurgeLastName = '';

function purgeRecord(id, name) {
  if (!window.OSCA?.isAdmin) {
    toast('Only administrators can permanently delete records.', 'error');
    return;
  }
  _pendingPurgeId       = id;
  _pendingPurgeLastName = name.split(',')[0].trim().toUpperCase();

  const nameEl = document.getElementById('purgeName');
  if (nameEl) nameEl.textContent = name;

  const input = document.getElementById('purgeConfirmInput');
  if (input) { input.value = ''; input.style.borderColor = ''; }

  const hint = document.getElementById('purgeConfirmHint');
  if (hint) { hint.textContent = ''; }

  const btn = document.getElementById('confirmPurgeBtn');
  if (btn) btn.disabled = true;

  openModal('purgeModal');
  setTimeout(() => { if (input) input.focus(); }, 300);
}

function checkPurgeConfirm() {
  const input = document.getElementById('purgeConfirmInput');
  const hint  = document.getElementById('purgeConfirmHint');
  const btn   = document.getElementById('confirmPurgeBtn');

  const cleaned = input.value.toUpperCase().replace(/[^A-ZÑ ]/g, '').slice(0, 50);
  if (input.value !== cleaned) input.value = cleaned;

  const typed = cleaned.trim();
  if (typed.length === 0) {
    btn.disabled = true;
    hint.textContent = '';
    input.style.borderColor = '';
  } else if (typed === _pendingPurgeLastName) {
    btn.disabled = false;
    hint.textContent = '✓ Name matches — deletion enabled';
    hint.style.color = '#27ae60';
    input.style.borderColor = '#27ae60';
  } else {
    btn.disabled = true;
    hint.textContent = '✗ Name does not match';
    hint.style.color = '#c0392b';
    input.style.borderColor = '#c0392b';
  }
}

async function executePurge() {
  if (!_pendingPurgeId) return;
  const btn = document.getElementById('confirmPurgeBtn');
  if (btn) { btn.disabled = true; btn.innerHTML = `<span class="material-symbols-outlined text-lg">progress_activity</span> Deleting…`; }

  try {
    const body = new URLSearchParams({ action: 'purge', id: _pendingPurgeId });
    const res  = await fetch('archive_record.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      closePurgeModal();
      toast('Record permanently deleted. Remember to export a new backup.', 'success');
      refreshNotifState();
      if (window.OSCA.archivedCount > 0) window.OSCA.archivedCount--;
      await loadArchiveTab(_archivePage, _archiveSearch);
    } else {
      toast(data.message || 'Delete failed.', 'error');
    }
  } catch(e) { toast('Network error.', 'error'); }
  finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<span class="material-symbols-outlined text-lg">delete_forever</span> Delete Permanently`;
    }
    _pendingPurgeId = null;
  }
}

function closePurgeModal() {
  const m = document.getElementById('purgeModal');
  if (m) m.classList.remove('open');
  document.body.style.overflow = '';
  _pendingPurgeId = null;
}


// ── Staff Tab ─────────────────────────────────────────────────
function renderStaffTab(staffList) {
  const content = document.getElementById('staffTabContent');
  if (!content) return;
  const rows = staffList.map(s=>{
    const encoderBtns = s.role==='encoder' ? `<button class="btn btn-outline" style="padding:4px 10px;font-size:.75rem" onclick="toggleStaffActive(${s.id})">${s.is_active==1?'Deactivate':'Reactivate'}</button><button class="btn btn-danger" style="padding:4px 10px;font-size:.75rem" onclick="deleteStaffAccount(${s.id},'${s.username}')">Delete</button>` : '';
    const resetBtn = `<button class="btn btn-outline" style="padding:4px 10px;font-size:.75rem" onclick="openResetPasswordModal(${s.id},'${esc(s.username)}')">Reset Password</button>`;
    return `<tr><td style="padding:9px 14px;border-bottom:1px solid rgba(149,165,166,.18);font-family:'JetBrains Mono',monospace;font-size:.78rem">${esc(s.username)}</td><td style="padding:9px 14px;border-bottom:1px solid rgba(149,165,166,.18)">${esc(s.display_name)}</td><td style="padding:9px 14px;border-bottom:1px solid rgba(149,165,166,.18)"><span style="font-size:.75rem;font-weight:700;font-family:'JetBrains Mono',monospace;color:${s.role==='admin'?'#1d3246':'#065f46'}">${s.role==='admin'?'Administrator':'Encoder'}</span></td><td style="padding:9px 14px;border-bottom:1px solid rgba(149,165,166,.18)"><span style="font-weight:600;color:${s.is_active==1?'#27ae60':'#c0392b'}">${s.is_active==1?'Active':'Inactive'}</span></td><td style="padding:9px 14px;border-bottom:1px solid rgba(149,165,166,.18);text-align:right"><div style="display:inline-flex;gap:6px">${encoderBtns}${resetBtn}</div></td></tr>`;
  }).join('');
  content.innerHTML = `
    <div class="detail-section" style="margin-top:14px">
      <div class="detail-section-title">${icon('group')} Staff Accounts</div>
      <div style="overflow-x:auto;margin-top:10px;border:1px solid rgba(149,165,166,.25);border-radius:0.5rem">
        <table style="width:100%;border-collapse:collapse;font-size:.83rem;font-family:Inter,sans-serif">
          <thead><tr>
            <th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Username</th>
            <th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Display Name</th>
            <th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Role</th>
            <th style="padding:8px 14px;text-align:left;font-family:'JetBrains Mono',monospace;font-size:.68rem;font-weight:700;text-transform:uppercase;color:#74777d;background:#f5f3f5;border-bottom:2px solid #efedef">Status</th>
            <th style="padding:8px 14px;background:#f5f3f5;border-bottom:2px solid #efedef"></th>
          </tr></thead>
          <tbody>${rows||'<tr><td colspan="5" style="padding:14px;color:#74777d">No staff accounts found.</td></tr>'}</tbody>
        </table>
      </div>
    </div>
    <div class="detail-section">
      <div class="detail-section-title">${icon('person_add')} Add New Encoder Account</div>
      <div class="edit-grid edit-grid-2" style="margin-top:10px">
        <div class="edit-field"><label>Username *</label><input id="s_new_username" type="text" class="edit-input" placeholder="e.g. jdoe" maxlength="30" oninput="enforceUsernameField(this)"><span class="edit-field-hint" id="hint_s_new_username"></span></div>
        <div class="edit-field"><label>Display Name *</label><input id="s_new_displayname" type="text" class="edit-input" placeholder="e.g. Juan Dela Cruz" maxlength="100"></div>
        <div class="edit-field">
  <label>Password *</label>
  <div class="pw-input-wrap">
    <input id="s_new_password" type="password" class="edit-input" placeholder="At least 8 characters">
    <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('s_new_password', this)" tabindex="-1" aria-label="Show password">
      <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
    </button>
  </div>
</div>
<div class="edit-field">
  <label>Confirm Password *</label>
  <div class="pw-input-wrap">
    <input id="s_new_password2" type="password" class="edit-input" placeholder="Re-type password">
    <button type="button" class="pw-toggle-btn" onclick="togglePasswordVisibility('s_new_password2', this)" tabindex="-1" aria-label="Show password">
      <span class="material-symbols-outlined" style="font-size:18px">visibility</span>
    </button>
  </div>
</div>
      </div>
      <p class="edit-hint-text" style="margin-top:6px">New accounts are always created with the <strong>Encoder</strong> role.</p>
      <button class="btn btn-primary" style="margin-top:10px" onclick="createStaffAccount()" id="createStaffBtn">
        ${icon('person_add')} Create Encoder Account
      </button>
    </div>`;
}
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const icon = btn.querySelector('.material-symbols-outlined');
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  if (icon) icon.textContent = isHidden ? 'visibility_off' : 'visibility';
  btn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
}
function enforceUsernameField(input){
  const hint=document.getElementById('hint_s_new_username');
  const cleaned=input.value.toLowerCase().replace(/[^a-z0-9_.]/g,'');
  if(input.value!==cleaned) input.value=cleaned;
  if(hint){if(cleaned.length>0&&cleaned.length<3)showEditHint(hint,'Minimum 3 characters','warn');else if(cleaned.length>=3)showEditHint(hint,'✓ Looks good','ok');else clearEditHint(hint);}
}

async function createStaffAccount(){
  const username=document.getElementById('s_new_username').value.trim();
  const displayName=document.getElementById('s_new_displayname').value.trim();
  const password=document.getElementById('s_new_password').value;
  const password2=document.getElementById('s_new_password2').value;
  if(!username||!displayName||!password){toast('All fields are required.','error');return;}
  if(username.length<3){toast('Username must be at least 3 characters.','error');return;}
  if(password.length<8){toast('Password must be at least 8 characters.','error');return;}
  if(password!==password2){toast('Passwords do not match.','error');return;}
  const btn=document.getElementById('createStaffBtn');
  btn.disabled=true; btn.innerHTML=`${icon('progress_activity','spin')} Creating…`;
  try{
    const body=new URLSearchParams({action:'create_staff',username,display_name:displayName,password});
    const res=await fetch('manage_staff.php',{method:'POST',body});
    const data=await res.json();
    if(data.success){toast('Encoder account created successfully.','success');openSettingsModal('staff');}
    else toast(data.message||'Failed to create account.','error');
  }catch(e){toast('Network error.','error');}
  finally{btn.disabled=false;btn.innerHTML=`${icon('person_add')} Create Encoder Account`;}
}

async function toggleStaffActive(id){
  try{
    const body=new URLSearchParams({action:'toggle_active',id});
    const res=await fetch('manage_staff.php',{method:'POST',body});
    const data=await res.json();
    if(data.success){toast(data.message,'success');openSettingsModal('staff');}
    else toast(data.message||'Action failed.','error');
  }catch(e){toast('Network error.','error');}
}

function closeSettingsModal(){
  document.getElementById('settingsModal').classList.remove('open');
  document.body.style.overflow='';
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
function validatePrintAgeInputs() {
  const fromEl = document.getElementById('print_age_from');
  const toEl   = document.getElementById('print_age_to');
  const errEl  = document.getElementById('printAgeError');

  let from = fromEl.value !== '' ? parseInt(fromEl.value) : null;
  let to   = toEl.value   !== '' ? parseInt(toEl.value)   : null;

  // Force minimum age of 60 for "From" (if filled in)
  if (from !== null && from < 60) {
    from = 60;
    fromEl.value = 60;
  }

  // Force minimum age of 60 for "To" as well (if filled in)
  if (to !== null && to < 60) {
    to = 60;
    toEl.value = 60;
  }

  // "To" cannot be less than "From"
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

function generatePrintReport() {
  if (!validatePrintAgeInputs()) {
    toast('Please fix the age range before generating the report.', 'error');
    return;
  }

  const barangay = document.getElementById('print_barangay').value;
  const sex       = document.getElementById('print_sex').value;
  const pwd       = document.getElementById('print_pwd').value;
  const fromEl    = document.getElementById('print_age_from');
  const toEl      = document.getElementById('print_age_to');

  // Default "From" to 60 if left blank but "To" is filled
  let from = fromEl.value !== '' ? parseInt(fromEl.value) : (toEl.value !== '' ? 60 : null);
  let to   = toEl.value   !== '' ? parseInt(toEl.value)   : null;

  let age = 'all';
  if (from !== null) age = to !== null ? `${from}-${to}` : `${from}-`;

  const params = new URLSearchParams({ filter: barangay, sex, pwd, age });
  window.open('print_report.php?' + params.toString(), '_blank');
  closePrintModal();
}

// ── Delete Staff ──────────────────────────────────────────────
let pendingDeleteStaffId=null,pendingDeleteStaffUsername='';
function deleteStaffAccount(id,username){
  if(!window.OSCA?.isAdmin){toast('Only administrators can delete staff accounts.','error');return;}
  pendingDeleteStaffId=id; pendingDeleteStaffUsername=username;
  document.getElementById('deleteStaffUsername').textContent=username;
  document.getElementById('deleteStaffConfirmInput').value='';
  document.getElementById('deleteStaffConfirmHint').textContent='';
  document.getElementById('confirmDeleteStaffBtn').disabled=true;
  openModal('deleteStaffModal');
  setTimeout(()=>document.getElementById('deleteStaffConfirmInput').focus(),300);
}
function checkDeleteStaffConfirm(){
  const input=document.getElementById('deleteStaffConfirmInput');
  const hint=document.getElementById('deleteStaffConfirmHint');
  const btn=document.getElementById('confirmDeleteStaffBtn');
  const cleaned=input.value.toLowerCase().replace(/[^a-z0-9_.]/g,'').slice(0,30);
  if(input.value!==cleaned) input.value=cleaned;
  if(cleaned.length===0){btn.disabled=true;hint.textContent='';input.style.borderColor='';}
  else if(cleaned===pendingDeleteStaffUsername){btn.disabled=false;hint.textContent='✓ Username matches';hint.style.color='#27ae60';input.style.borderColor='#27ae60';}
  else{btn.disabled=true;hint.textContent='✗ Username does not match';hint.style.color='#c0392b';input.style.borderColor='#c0392b';}
}
async function executeDeleteStaff(){
  if(!pendingDeleteStaffId) return;
  const btn=document.getElementById('confirmDeleteStaffBtn');
  btn.disabled=true; btn.textContent='Deleting…';
  try{
    const body=new URLSearchParams({action:'delete_staff',id:pendingDeleteStaffId});
    const res=await fetch('manage_staff.php',{method:'POST',body});
    const data=await res.json();
    if(data.success){toast('Staff account deleted.','success');closeDeleteStaffModal();openSettingsModal('staff');}
    else{toast(data.message||'Delete failed.','error');btn.disabled=false;btn.textContent='Delete Account';}
  }catch(e){toast('Network error.','error');btn.disabled=false;btn.textContent='Delete Account';}
  finally{pendingDeleteStaffId=null;}
}
function closeDeleteStaffModal(){
  const m=document.getElementById('deleteStaffModal');if(m)m.classList.remove('open');
  document.body.style.overflow=''; pendingDeleteStaffId=null;
}

// ── Reset Password ──────────────────────────────────────────
let pendingResetPasswordId = null;
let pendingResetPasswordUsername = '';

function openResetPasswordModal(id, username) {
  pendingResetPasswordId = id;
  pendingResetPasswordUsername = username;
  document.getElementById('resetPasswordUsername').textContent = username;
  document.getElementById('resetPasswordNew').value = '';
  document.getElementById('resetPasswordConfirm').value = '';
  document.getElementById('resetPasswordHint').textContent = '';
  openModal('resetPasswordModal');
}

function closeResetPasswordModal() {
  const m = document.getElementById('resetPasswordModal');
  if (m) m.classList.remove('open');
  document.body.style.overflow = '';
  pendingResetPasswordId = null;
}

async function executeResetPassword() {
  if (!pendingResetPasswordId) return;
  const newPw     = document.getElementById('resetPasswordNew').value;
  const confirmPw = document.getElementById('resetPasswordConfirm').value;
  const hint      = document.getElementById('resetPasswordHint');

  if (newPw.length < 8) { hint.textContent = 'Password must be at least 8 characters.'; hint.style.color = '#c0392b'; return; }
  if (newPw !== confirmPw) { hint.textContent = 'Passwords do not match.'; hint.style.color = '#c0392b'; return; }

  const btn = document.getElementById('confirmResetPasswordBtn');
  btn.disabled = true; btn.textContent = 'Resetting…';

  try {
    const body = new URLSearchParams({ action: 'reset_password', id: pendingResetPasswordId, new_password: newPw, confirm_password: confirmPw });
    const res  = await fetch('manage_staff.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      toast(data.message || 'Password reset successfully.', 'success');
      closeResetPasswordModal();
    } else {
      hint.textContent = data.message || 'Reset failed.';
      hint.style.color = '#c0392b';
    }
  } catch(e) {
    hint.textContent = 'Network error.';
    hint.style.color = '#c0392b';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Reset Password';
  }
}
// ── Settings popup menu ────────────────────────────────────────
function toggleSettingsMenu(e) {
  e.stopPropagation();
  const menu = document.getElementById('settingsPopupMenu');
  if (menu) menu.classList.toggle('open');
}

function selectSettingsMenuItem(tab) {
  const menu = document.getElementById('settingsPopupMenu');
  if (menu) menu.classList.remove('open');
  openSettingsModal(tab);
}

document.addEventListener('click', (e) => {
  const wrap = document.getElementById('settingsMenuWrap');
  const menu = document.getElementById('settingsPopupMenu');
  if (!wrap || !menu) return;
  if (!wrap.contains(e.target)) menu.classList.remove('open');
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    const menu = document.getElementById('settingsPopupMenu');
    if (menu) menu.classList.remove('open');
  }
});

// ── NCSC encoding toggle ───────────────────────────────────────
async function toggleNcsc(id, btn) {
  const wasEncoded = btn.dataset.encoded === '1';
  btn.disabled = true;

  try {
    const body = new URLSearchParams({ id });
    const res  = await fetch('toggle_ncsc.php', { method: 'POST', body });
    const data = await res.json();

    if (data.success) {
      const nowEncoded = data.ncsc_encoded === 'Yes';
      btn.dataset.encoded = nowEncoded ? '1' : '0';
      btn.className = 'ncsc-pill ' + (nowEncoded ? 'ncsc-pill-yes' : 'ncsc-pill-no');
      btn.innerHTML = `<span class="ncsc-dot"></span>NCSC`;
      toast(nowEncoded ? 'Marked as encoded to NCSC.' : 'Marked as pending.', 'success');
    } else {
      toast(data.message || 'Failed to update status.', 'error');
    }
  } catch (e) {
    toast('Network error.', 'error');
  } finally {
    btn.disabled = false;
  }
}

function printProfile(id) {
  window.open('print_profile.php?id=' + id, '_blank');
}
function printCurrentProfile() {
  if (_viewRecord && _viewRecord.id) {
    printProfile(_viewRecord.id);
  }
}

// ═══════════════════ CHANGE NOTIFICATIONS ═══════════════════
const NOTIF_STYLES = {
  create:                 { initials: 'NR', bg: '#dbeafe', color: '#2563eb' },
  complete_registration:  { initials: 'RC', bg: '#fde7d0', color: '#c2703d' },
  update:                 { initials: 'RU', bg: '#e0e7ff', color: '#4338ca' },
  archive:                { initials: 'RA', bg: '#fef3c7', color: '#92400e' },
  restore:                { initials: 'RS', bg: '#d1fae5', color: '#065f46' },
  delete:                 { initials: 'RD', bg: '#ffdad6', color: '#ba1a1a' },
  purge:                  { initials: 'PD', bg: '#fee2e2', color: '#7f1d1d' },
};

function notifStyleFor(type) {
  return NOTIF_STYLES[type] || { initials: '•', bg: '#f0f0f0', color: '#74777d' };
}

function formatNotifTime(dt) {
  if (!dt) return '';
  const d = new Date(dt.replace(' ', 'T'));
  if (isNaN(d)) return dt;
  return d.toLocaleString('en-US', { day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
}

function escNotif(v) {
  if (!v) return '';
  return String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderNotifItem(n) {
  const style = notifStyleFor(n.type);
  return `
    <div class="notif-item">
      <div class="notif-avatar" style="background:${style.bg};color:${style.color}">${style.initials}</div>
      <div class="notif-item-body">
        <div class="notif-item-title">${escNotif(n.title)}${n.message ? ' — ' + escNotif(n.message) : ''}</div>
        <div class="notif-item-meta">${formatNotifTime(n.created_at)}</div>
      </div>
    </div>`;
}

function renderNotifBadge(unreadCount) {
  const badge   = document.getElementById('notifBadge');
  const bellIco = document.getElementById('notifBellIcon');
  if (!badge) return;
  const count = Number(unreadCount) || 0;
  if (count > 0) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.classList.remove('hidden');
    bellIco.classList.add('notif-active');
  } else {
    badge.textContent = '';
    badge.classList.add('hidden');
    bellIco.classList.remove('notif-active');
  }
}

async function refreshNotifState() {
  try {
    const res  = await fetch('get_notifications.php?limit=20');
    const data = await res.json();
    if (!data.success) return;
    const list = data.list || [];
    renderNotifBadge(list.filter(isNotifUnread).length);
    const listEl = document.getElementById('notifList');
    if (listEl) {
      listEl.innerHTML = list.length
        ? list.map(renderNotifItem).join('')
        : '<div class="notif-empty">No notifications yet.</div>';
    }
    return list;
  } catch (e) { /* silent — non-critical */ }
}

function markAllNotifsRead(list) {
  if (!list || !list.length) return;
  const newest = Math.max(...list.map(n => new Date((n.created_at || '').replace(' ', 'T')).getTime() || 0));
  if (newest > getNotifLastSeen()) setNotifLastSeen(newest);
  renderNotifBadge(0);
}

async function toggleNotifPanel(e) {
  e.stopPropagation();
  const panel = document.getElementById('notifPanel');
  if (!panel) return;
  const wasOpen = panel.classList.contains('open');
  panel.classList.toggle('open');
  if (!wasOpen) {
    const list = await refreshNotifState();
    // Give a brief moment to actually see the badge/list before clearing it.
    setTimeout(() => markAllNotifsRead(list), 1200);
  }
}

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

document.addEventListener('click', (e) => {
  const wrap  = document.getElementById('notifBellWrap');
  const panel = document.getElementById('notifPanel');
  if (!wrap || !panel) return;
  if (!wrap.contains(e.target)) panel.classList.remove('open');
});

// Every current backend notification type is a "transaction" — the
// Announcements category is reserved for a future feature (e.g. an
// admin broadcast/announcement type) and will simply show 0 for now.
const NOTIF_CATEGORY_MAP = {
  create: 'transaction', complete_registration: 'transaction', update: 'transaction',
  archive: 'transaction', restore: 'transaction', delete: 'transaction', purge: 'transaction',
};
function notifCategoryFor(type) { return NOTIF_CATEGORY_MAP[type] || 'transaction'; }

let _notifAllData   = [];
let _notifCategory  = 'all';
let _notifLabel     = 'all';
let _notifDateFrom  = '';
let _notifDateTo    = '';
let _notifTimeRange = 'all';
const NOTIF_SEEN_KEY = 'osca_notif_last_seen';

function getNotifLastSeen() {
  const v = localStorage.getItem(NOTIF_SEEN_KEY);
  return v ? parseInt(v, 10) : 0;
}
function setNotifLastSeen(ts) { localStorage.setItem(NOTIF_SEEN_KEY, String(ts)); }

function isNotifUnread(n) {
  const t = new Date((n.created_at || '').replace(' ', 'T')).getTime();
  return !isNaN(t) && t > getNotifLastSeen();
}

async function openNotifModal() {
  document.getElementById('notifPanel').classList.remove('open');
  openModal('notifModal');
  _notifCategory  = 'all';
  _notifLabel     = 'all';
  _notifDateFrom  = '';
  _notifDateTo    = '';
  _notifTimeRange = 'all';
  document.getElementById('notifSearchInput').value = '';
  document.getElementById('notifDateFrom').value = '';
  document.getElementById('notifDateTo').value = '';
  document.getElementById('notifDateClearBtn').style.display = 'none';
  document.getElementById('notifTimeRangeSelect').value = 'all';
  document.getElementById('notifCustomRangeWrap').style.display = 'none';
  document.querySelectorAll('.notif-sidebar-item').forEach(el => el.classList.toggle('active', el.dataset.cat === 'all'));
  document.querySelectorAll('.notif-label-item').forEach(el => el.classList.toggle('active', el.dataset.label === 'all'));

  const list = document.getElementById('notifModalList');
  list.innerHTML = `<div class="notif-list-empty">${icon('progress_activity','spin')} Loading…</div>`;

  try {
    const res  = await fetch('get_notifications.php?limit=100');
    const data = await res.json();
    _notifAllData = (data.success && data.list) ? data.list : [];
    renderNotifCounts();
    renderNotifModalList();
    // Mark everything as "seen" once the person has actually opened the panel
    if (_notifAllData.length) {
      const newest = Math.max(..._notifAllData.map(n => new Date((n.created_at||'').replace(' ','T')).getTime() || 0));
      setNotifLastSeen(newest);
    }
  } catch (e) {
    list.innerHTML = '<p style="color:red;padding:20px">Failed to load notifications.</p>';
  }
}

function setNotifCategory(cat) {
  _notifCategory = cat;
  document.querySelectorAll('.notif-sidebar-item').forEach(el => el.classList.toggle('active', el.dataset.cat === cat));
  renderNotifModalList();
}

function setNotifLabel(label) {
  _notifLabel = label;
  document.querySelectorAll('.notif-label-item').forEach(el => el.classList.toggle('active', el.dataset.label === label));
  renderNotifModalList();
}

function applyNotifDateFilter() {
  _notifDateFrom = document.getElementById('notifDateFrom').value;
  _notifDateTo   = document.getElementById('notifDateTo').value;
  const clearBtn = document.getElementById('notifDateClearBtn');
  if (clearBtn) clearBtn.style.display = (_notifDateFrom || _notifDateTo) ? 'block' : 'none';
  renderNotifModalList();
}

function clearNotifDateFilter() {
  _notifDateFrom  = '';
  _notifDateTo    = '';
  _notifTimeRange = 'all';
  document.getElementById('notifDateFrom').value = '';
  document.getElementById('notifDateTo').value = '';
  document.getElementById('notifDateClearBtn').style.display = 'none';
  document.getElementById('notifTimeRangeSelect').value = 'all';
  document.getElementById('notifCustomRangeWrap').style.display = 'none';
  renderNotifModalList();
}

function setNotifTimeRange(value) {
  _notifTimeRange = value;
  const customWrap = document.getElementById('notifCustomRangeWrap');
  const clearBtn   = document.getElementById('notifDateClearBtn');

  if (value === 'custom') {
    if (customWrap) customWrap.style.display = 'flex';
    applyNotifDateFilter(); // let the two date inputs drive filtering
    return;
  }

  if (customWrap) customWrap.style.display = 'none';
  document.getElementById('notifDateFrom').value = '';
  document.getElementById('notifDateTo').value = '';

  const today = new Date();
  const fmt = d => d.toISOString().slice(0, 10);

  switch (value) {
    case 'today':
      _notifDateFrom = _notifDateTo = fmt(today);
      break;
    case 'week': {
      const day = today.getDay(); // 0=Sun..6=Sat
      const diffToMonday = (day === 0 ? 6 : day - 1);
      const monday = new Date(today);
      monday.setDate(today.getDate() - diffToMonday);
      _notifDateFrom = fmt(monday);
      _notifDateTo   = fmt(today);
      break;
    }
    case 'month': {
      const first = new Date(today.getFullYear(), today.getMonth(), 1);
      _notifDateFrom = fmt(first);
      _notifDateTo   = fmt(today);
      break;
    }
    case 'all':
    default:
      _notifDateFrom = '';
      _notifDateTo   = '';
      break;
  }

  if (clearBtn) clearBtn.style.display = (_notifDateFrom || _notifDateTo) ? 'block' : 'none';
  renderNotifModalList();
}

function renderNotifCounts() {
  const counts = { all: _notifAllData.length, transaction: 0 };
  _notifAllData.forEach(n => { counts[notifCategoryFor(n.type)]++; });
  document.getElementById('notifCountAll').textContent         = counts.all;
  document.getElementById('notifCountTransaction').textContent = counts.transaction;
}

function filterNotifModal() { renderNotifModalList(); }

function renderNotifModalList() {
  const list   = document.getElementById('notifModalList');
  const search = (document.getElementById('notifSearchInput').value || '').trim().toLowerCase();
  const lastSeen = getNotifLastSeen();

  let items = _notifAllData.filter(n => {
    if (_notifCategory !== 'all' && notifCategoryFor(n.type) !== _notifCategory) return false;
    if (_notifLabel === 'unread') {
      const t = new Date((n.created_at||'').replace(' ','T')).getTime();
      if (!(t > lastSeen)) return false;
    }
    if (_notifDateFrom || _notifDateTo) {
      const t = new Date((n.created_at||'').replace(' ','T'));
      if (isNaN(t)) return false;
      if (_notifDateFrom && t < new Date(_notifDateFrom + 'T00:00:00')) return false;
      if (_notifDateTo   && t > new Date(_notifDateTo   + 'T23:59:59')) return false;
    }
    if (search) {
      const hay = `${n.title||''} ${n.message||''}`.toLowerCase();
      if (!hay.includes(search)) return false;
    }
    return true;
  });

  if (!items.length) {
    list.innerHTML = '<div class="notif-list-empty">No notifications found.</div>';
    return;
  }

  list.innerHTML = items.map(n => {
    const style  = notifStyleFor(n.type);
    const unread = isNotifUnread(n);
    return `
      <div class="notif-row ${unread ? 'unread' : ''}" data-notif-id="${n.id}">
        ${unread ? '<span class="notif-row-unread-dot"></span>' : '<span style="width:8px"></span>'}
        <div class="notif-row-avatar" style="background:${style.bg};color:${style.color}">${style.initials}</div>
        <div class="notif-row-body">
          <span class="notif-row-title">${escNotif(n.title)}</span>
          <span class="notif-row-sub">${escNotif(n.message || '')}</span>
        </div>
        <span class="notif-row-time">${formatNotifTime(n.created_at)}</span>
        <button class="notif-row-delete-btn" onclick="deleteNotification(${n.id}, event)" title="Delete notification" aria-label="Delete notification">
          <span class="material-symbols-outlined" style="font-size:16px">delete</span>
        </button>
      </div>`;
  }).join('');
}

async function deleteNotification(id, evt) {
  if (evt) evt.stopPropagation();
  const row = document.querySelector(`.notif-row[data-notif-id="${id}"]`);
  if (row) { row.style.opacity = '0.4'; row.style.pointerEvents = 'none'; }

  try {
    const body = new URLSearchParams({ action: 'delete_notification', id });
    const res  = await fetch('get_notifications.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      _notifAllData = _notifAllData.filter(n => n.id !== id);
      renderNotifCounts();
      renderNotifModalList();
      renderNotifBadge(_notifAllData.filter(isNotifUnread).length);
    } else {
      toast(data.message || 'Failed to delete notification.', 'error');
      if (row) { row.style.opacity = ''; row.style.pointerEvents = ''; }
    }
  } catch (e) {
    toast('Network error.', 'error');
    if (row) { row.style.opacity = ''; row.style.pointerEvents = ''; }
  }
}

function deleteAllNotifications() {
  openModal('deleteAllNotifModal');
}

function closeDeleteAllNotifModal() {
  const m = document.getElementById('deleteAllNotifModal');
  if (m) m.classList.remove('open');
  document.body.style.overflow = '';
}

async function executeDeleteAllNotifications() {
  const btn = document.getElementById('confirmDeleteAllNotifBtn');
  btn.disabled = true;
  btn.innerHTML = `${icon('progress_activity','spin')} Deleting…`;

  try {
    const body = new URLSearchParams({ action: 'delete_all_notifications' });
    const res  = await fetch('get_notifications.php', { method: 'POST', body });
    const data = await res.json();
    if (data.success) {
      _notifAllData = [];
      renderNotifCounts();
      renderNotifModalList();
      renderNotifBadge(0);
      closeDeleteAllNotifModal();
      toast('All notifications deleted.', 'success');
    } else {
      toast(data.message || 'Failed to delete notifications.', 'error');
    }
  } catch (e) {
    toast('Network error.', 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `${icon('delete_sweep')} Delete All`;
  }
}

function closeNotifModal() {
  document.getElementById('notifModal').classList.remove('open');
  document.body.style.overflow = '';
}

document.addEventListener('DOMContentLoaded', () => {
  refreshNotifState();
  setInterval(refreshNotifState, 30000);
});

// Refresh immediately after a successful backup export clears the queue.
const _origSubmitExportBackup = window.submitExportBackup;
if (typeof _origSubmitExportBackup === 'function') {
  window.submitExportBackup = async function () {
    await _origSubmitExportBackup.apply(this, arguments);
    refreshNotifState();
  };
}