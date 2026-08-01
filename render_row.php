<?php
function renderApplicantRow(array $r, bool $isAdmin, int $rowNumber = 1): string {
    $suffix = (!empty($r['suffixApplicant']) && $r['suffixApplicant'] !== 'N/A') ? $r['suffixApplicant'] : '';
    $middle = (!empty($r['middlenameApplicant']) && $r['middlenameApplicant'] !== 'N/A') ? ' '.$r['middlenameApplicant'] : '';
    $fullName = $r['lastnameApplicant'].', '.$r['firstnameApplicant'].$middle;
    if ($suffix) $fullName .= ' '.$suffix;
    $jsName = addslashes($r['lastnameApplicant'].', '.$r['firstnameApplicant']);
    $age = '—';
    if ($r['month'] && $r['date'] && $r['year']) {
        $dob = DateTime::createFromFormat('F j Y', $r['month'].' '.$r['date'].' '.$r['year']);
        if ($dob) $age = $dob->diff(new DateTime())->y;
    }
    $isToday = date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d');

    ob_start();
    ?>
    <tr class="table-row hover:bg-surface-container-low transition-colors <?= $isToday ? 'bg-primary/[0.03]' : '' ?>"
        data-id="<?= $r['id'] ?>" style="border-bottom:1px solid rgba(149,165,166,.15)">
      <td class="text-on-surface-variant text-xs"><?= $rowNumber ?></td>
      <td class="col-name">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full bg-primary-container flex items-center justify-center flex-shrink-0">
            <span class="text-on-primary-container text-xs font-bold font-mono"><?= strtoupper(substr($r['lastnameApplicant'],0,1)) ?></span>
          </div>
          <span class="name-text font-semibold text-on-surface text-sm" title="<?= htmlspecialchars($fullName) ?>"><?= htmlspecialchars($fullName) ?></span>
        </div>
      </td>
      <td>
        <?php $ncscEncoded = ($r['ncsc_encoded'] ?? 'No') === 'Yes'; ?>
        <button onclick="toggleNcsc(<?= $r['id'] ?>, this)"
                data-encoded="<?= $ncscEncoded ? '1' : '0' ?>"
                title="Click to toggle NCSC encoding status"
                class="ncsc-pill <?= $ncscEncoded ? 'ncsc-pill-yes' : 'ncsc-pill-no' ?>">
          <span class="ncsc-dot"></span>NCSC
        </button>
      </td>
      <td class="text-on-surface-variant"><?= htmlspecialchars($r['sex']??'—') ?></td>
      <td class="text-on-surface-variant"><?= $r['month']&&$r['date']&&$r['year'] ? htmlspecialchars("{$r['month']} {$r['date']}, {$r['year']}") : '—' ?></td>
      <td class="text-on-surface-variant"><?= $age ?></td>
      <td class="text-on-surface-variant"><?= ($r['personWithDisability'] ?? '') === 'Yes' ? 'Yes' : 'No' ?></td>
      <td class="font-mono text-xs text-on-surface-variant"><?= htmlspecialchars($r['osca_ID']??'—') ?></td>
      <td class="text-on-surface-variant col-barangay" title="<?= htmlspecialchars($r['barangay']??'—') ?>"><?= htmlspecialchars($r['barangay']??'—') ?></td>
      <td class="<?= $isToday ? 'font-bold text-primary' : 'text-on-surface-variant' ?>">
        <?= $isToday ? 'Today' : date('M j, Y', strtotime($r['created_at'])) ?>
      </td>
      <td>
        <div class="flex items-center justify-center gap-1">
          <button onclick="viewRecord(<?= $r['id'] ?>)" title="View"
                  class="w-8 h-8 flex items-center justify-center text-primary hover:bg-primary/10 rounded-lg transition-colors">
            <span class="material-symbols-outlined text-xl">visibility</span>
          </button>
          <button onclick="editRecord(<?= $r['id'] ?>)" title="Edit"
                  class="w-8 h-8 flex items-center justify-center text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-lg transition-colors">
            <span class="material-symbols-outlined text-xl">edit_square</span>
          </button>
          <button onclick="confirmArchive(<?= $r['id'] ?>, '<?= $jsName ?>')" title="Archive record"
                  class="w-8 h-8 flex items-center justify-center text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
            <span class="material-symbols-outlined text-xl">inventory_2</span>
          </button>
          <?php if ($isAdmin): ?>
          <button onclick="confirmDelete(<?= $r['id'] ?>, '<?= $jsName ?>')" title="Delete permanently"
                  class="w-8 h-8 flex items-center justify-center text-red-600 hover:bg-red-50 rounded-lg transition-colors">
            <span class="material-symbols-outlined text-xl">delete_forever</span>
          </button>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
    return ob_get_clean();
}

function pageUrl($p, $filter, $search, $limit, $sexFilter, $ageFilter, $pwdFilter) {
    return '?page='.urlencode($p)
        .'&filter='.urlencode($filter)
        .'&search='.urlencode($search)
        .'&limit='.urlencode($limit)
        .'&sex='.urlencode($sexFilter)
        .'&age='.urlencode($ageFilter)
        .'&pwd='.urlencode($pwdFilter);
}

function renderPaginationFooter($page, $limit, $offset, $filteredTotal, $totalPages, $filter, $search, $sexFilter, $ageFilter, $pwdFilter) {
    ob_start();
    ?>
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

      <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
        <a href="<?= pageUrl($page-1,$filter,$search,$limit,$sexFilter,$ageFilter,$pwdFilter) ?>"
           class="px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-md transition-colors font-semibold">Previous</a>
        <?php else: ?>
        <span class="px-3 py-1.5 text-outline/40 font-semibold cursor-not-allowed select-none">Previous</span>
        <?php endif; ?>

        <?php if ($limit > 0 && $totalPages > 1): ?>
        <div class="flex items-center gap-1 mx-1">
          <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
          <a href="<?= pageUrl($p,$filter,$search,$limit,$sexFilter,$ageFilter,$pwdFilter) ?>"
             class="w-8 h-8 flex items-center justify-center rounded-md text-xs font-bold transition-colors
                    <?= $p===$page ? 'bg-primary text-white' : 'text-on-surface hover:bg-[#efedef]' ?>">
            <?= $p ?>
          </a>
          <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php if ($limit > 0 && $page < $totalPages): ?>
        <a href="<?= pageUrl($page+1,$filter,$search,$limit,$sexFilter,$ageFilter,$pwdFilter) ?>"
           class="px-3 py-1.5 text-on-surface-variant hover:text-primary hover:bg-surface-container-high rounded-md transition-colors font-semibold">Next</a>
        <?php else: ?>
        <span class="px-3 py-1.5 text-outline/40 font-semibold cursor-not-allowed select-none">Next</span>
        <?php endif; ?>
      </div>
    <?php
    return ob_get_clean();
}