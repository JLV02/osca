<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
require_once 'db.php';

$currentDisplayName = $_SESSION['display_name'] ?? ($_SESSION['admin_username'] ?? 'Staff');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid record ID.');
}

$stmt = $pdo->prepare("
    SELECT
        id, ncsc_encoded,
        lastnameApplicant, firstnameApplicant, middlenameApplicant, suffixApplicant,
        barangay, purok, street,
        month, date, year, birthplace,
        maritalStatus, religion, sex,
        contactNumber, emailAddress, fbMessenger, ethnicOrigin, languageSpoken,
        osca_ID, gsis_sss_ID, tin_ID, philHealth_ID, sc_asso_ID, other_govt_ID,
        employment_business, hasPension, travelCapability, personWithDisability,

        lastnameSpouse, firstnameSpouse, middlenameSpouse, suffixSpouse,
        lastnameFather, firstnameFather, middlenameFather, suffixFather,
        lastnameMother, firstnameMother, middlenameMother, suffixMother,

        fullnameChild1, occupationChild1, incomeChild1, ageChild1, isWorkingChild1,
        fullnameChild2, occupationChild2, incomeChild2, ageChild2, isWorkingChild2,
        fullnameChild3, occupationChild3, incomeChild3, ageChild3, isWorkingChild3,
        fullnameChild4, occupationChild4, incomeChild4, ageChild4, isWorkingChild4,
        fullnameChild5, occupationChild5, incomeChild5, ageChild5, isWorkingChild5,

        fullnameDependent1, occupationDependent1, incomeDependent1, ageDependent1, isWorkingDependent1,
        fullnameDependent2, occupationDependent2, incomeDependent2, ageDependent2, isWorkingDependent2,

        livingAlone, livingWith, livingWithOthers, livingCondition, livingConditionOthers,

        educationHighest, educationHighestOthers,
        skills, skillsOthers, sharedSkills,
        communityInvolvement, communityInvolvementOthers,

        sourceIncome, sourceIncomeOthers,
        assetsReal, assetsRealOthers,
        assetsPersonal, assetsPersonalOthers,
        incomeMonthly,
        problemsNeeds, problemsNeedsLivelihood, problemsNeedsOthers,

        bloodType, physicalDisability,
        healthProblems, healthProblemsOthers,
        dentalConcern, dentalConcernOthers,
        visualConcern, visualConcernOthers,
        auralConcern, auralConcernOthers,
        socialConcern, socialConcernOthers,
        areaDifficulty, areaDifficultyOthers,
        listOfMedicines, scheduledCheckup, scheduledCheckupYes,

        (oscaID IS NOT NULL AND LENGTH(oscaID) > 0) AS has_osca_photo,
        (photoLatest IS NOT NULL AND LENGTH(photoLatest) > 0) AS has_latest_photo,
        oscaID_type, photoLatest_type,

        created_at, updated_at
    FROM applicants
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$r) {
    die('Record not found.');
}

// ── Overflow children (6+) ──────────────────────────────────
$stmt2 = $pdo->prepare("SELECT child_index, fullname, occupation, income, age, isWorking
                         FROM applicant_children_extra
                         WHERE applicant_id = ? ORDER BY child_index");
$stmt2->execute([$id]);
$extras = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$maxIndex = 5;
foreach ($extras as $ex) {
    $i = (int)$ex['child_index'];
    $maxIndex = max($maxIndex, $i);
    $r["fullnameChild$i"]   = $ex['fullname'];
    $r["occupationChild$i"] = $ex['occupation'];
    $r["incomeChild$i"]     = $ex['income'];
    $r["ageChild$i"]        = $ex['age'];
    $r["isWorkingChild$i"]  = $ex['isWorking'];
}
$childCount = $maxIndex;

function na($v) { return ($v !== null && $v !== '') ? htmlspecialchars($v) : '—'; }
function csv($v) { return ($v !== null && $v !== '') ? htmlspecialchars(str_replace(',', ', ', $v)) : '—'; }
function calcAge($month, $date, $year) {
    if (!$month || !$date || !$year) return '—';
    $dob = DateTime::createFromFormat('F j Y', "$month $date $year");
    return $dob ? $dob->diff(new DateTime())->y : '—';
}
function fullPersonName($r, $prefix) {
    $l = $r["lastname$prefix"] ?? ''; $f = $r["firstname$prefix"] ?? '';
    $m = $r["middlename$prefix"] ?? ''; $s = $r["suffix$prefix"] ?? '';
    $name = trim("$f $m $l $s");
    return $name !== '' ? $name : '—';
}
function childRows($r, $count, $prefix) {
    $rows = '';
    $max = $prefix === 'Child' ? $count : 2;
    for ($i = 1; $i <= $max; $i++) {
        $name = $r["fullname$prefix$i"] ?? '';
        if (!$name) continue;
        $rows .= '<tr>'
            .'<td>'.htmlspecialchars($name).'</td>'
            .'<td>'.na($r["occupation$prefix$i"] ?? null).'</td>'
            .'<td>'.na($r["income$prefix$i"] ?? null).'</td>'
            .'<td>'.na($r["age$prefix$i"] ?? null).'</td>'
            .'<td>'.na($r["isWorking$prefix$i"] ?? null).'</td>'
            .'</tr>';
    }
    return $rows ?: '<tr><td colspan="5" style="text-align:center;color:#999">None listed</td></tr>';
}

$suffix = (!empty($r['suffixApplicant']) && $r['suffixApplicant'] !== 'N/A') ? ' '.$r['suffixApplicant'] : '';
$middle = (!empty($r['middlenameApplicant']) && $r['middlenameApplicant'] !== 'N/A') ? ' '.$r['middlenameApplicant'] : '';
$fullName = $r['lastnameApplicant'].', '.$r['firstnameApplicant'].$middle.$suffix;

// ── Helper to output one numbered item as its own unbreakable block ──
function item($num, $title, $rowsHtml) {
    echo '<div class="item"><div class="item-num">'.$num.'. '.htmlspecialchars($title).'</div>';
    echo '<table class="info">'.$rowsHtml.'</table></div>';
}
function row($label, $value) {
    return '<tr><td class="label">'.htmlspecialchars($label).'</td><td>'.$value.'</td></tr>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OSCA Full Profile — <?= htmlspecialchars($fullName) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; color:#1b1c1d; margin: 20px; font-size: 0.8rem; }
  .print-header { text-align:center; margin-bottom: 14px; border-bottom: 2px solid #1d3246; padding-bottom: 10px; }
  .print-header img.logo { height: 50px; margin-bottom: 4px; }
  .print-header h1 { font-size: 1.05rem; margin: 4px 0 2px; }
  .print-header p  { font-size: 0.76rem; margin: 2px 0; color:#43474c; }

  .profile-wrap { display:flex; gap: 18px; margin-top: 10px; margin-bottom: 8px; align-items:flex-start; }
  .photo-box {
    width: 110px; height: 135px; border: 1px solid #999; flex-shrink:0;
    display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f2f2f2;
  }
  .photo-box img { width:100%; height:100%; object-fit: cover; }
  .photo-box .no-photo { font-size:0.65rem; color:#999; text-align:center; padding:6px; }
  .id-photo-box { width: 150px; height: 100px; border:1px solid #999; display:flex; align-items:center; justify-content:center; overflow:hidden; background:#f2f2f2; }
  .id-photo-box img { max-width:100%; max-height:100%; object-fit:contain; }

  .headline h2 { margin:0 0 4px; font-size:1.05rem; }
  .headline .osca-id { font-size:0.78rem; color:#43474c; line-height:1.5; }

  /* ── Pagination fix: each STEP TITLE stays glued to the item after it,
         but the step as a WHOLE is allowed to break across pages.
         Each individual ITEM (numbered block) is kept intact. ── */
  .step-title {
    background:#1d3246; color:#fff; font-size:0.78rem; font-weight:bold; text-transform:uppercase;
    letter-spacing:.04em; padding:5px 10px; margin-top:14px;
    break-after: avoid; break-inside: avoid;
    page-break-after: avoid; page-break-inside: avoid;
  }
  .item {
    border:1px solid #ccc; border-top:none; padding:6px 10px;
    break-inside: avoid; page-break-inside: avoid;
  }
  .item-num { font-weight:bold; color:#1d3246; font-size:0.76rem; margin-bottom:3px; }

  table.info { width:100%; border-collapse: collapse; font-size: 0.76rem; }
  table.info td { padding: 2px 6px; vertical-align: top; }
  table.info td.label { width: 185px; font-weight:bold; color:#43474c; white-space:nowrap; }

  table.subtable { width:100%; border-collapse:collapse; font-size:0.74rem; margin-top:3px; }
  table.subtable th, table.subtable td { border:1px solid #ccc; padding:3px 6px; text-align:left; }
  table.subtable th { background:#e9edf1; font-size:0.66rem; text-transform:uppercase; }

  .meta-row { display:flex; justify-content:space-between; font-size:0.72rem; color:#43474c; margin: 8px 0; }
  .no-print { margin-bottom: 16px; }
  .no-print button {
    background:#1d3246; color:#fff; border:none; padding:9px 18px;
    border-radius:6px; font-size:0.85rem; cursor:pointer;
  }
  .footer-note { margin-top: 14px; font-size:0.7rem; color:#74777d; text-align:right; }

  @media print {
    .no-print { display:none; }
    body { margin: 10mm; }
  }
</style>
</head>
<body>

  <div class="no-print">
    <button onclick="window.print()">🖨 Print Full Profile</button>
  </div>

  <div class="print-header">
    <img class="logo" src="HimCity_Logo_nobg.png" alt="Logo">
    <h1>Office of Senior Citizens Affairs — Full Registrant Profile</h1>
    <p>Himamaylan City</p>
  </div>

  <div class="meta-row">
    <span><strong>OSCA ID:</strong> <?= na($r['osca_ID']) ?> &nbsp; | &nbsp; <strong>NCSC Status:</strong> <?= $r['ncsc_encoded'] === 'Yes' ? 'Encoded' : 'Pending' ?></span>
    <span><strong>Generated:</strong> <?= date('F j, Y g:i A') ?> by <?= htmlspecialchars($currentDisplayName) ?></span>
  </div>

  <div class="profile-wrap">
    <div class="photo-box">
      <?php if ($r['has_latest_photo']): ?>
        <img src="get_image.php?id=<?= $r['id'] ?>&type=photo" alt="2x2 Photo">
      <?php else: ?>
        <div class="no-photo">No 2x2 photo on file</div>
      <?php endif; ?>
    </div>
    <div class="headline">
      <h2><?= htmlspecialchars($fullName) ?></h2>
      <div class="osca-id">
        <?= na($r['sex']) ?> · Age <?= calcAge($r['month'],$r['date'],$r['year']) ?> ·
        Brgy. <?= na($r['barangay']) ?><br>
        Registered: <?= $r['created_at'] ? date('F j, Y', strtotime($r['created_at'])) : '—' ?>
      </div>
    </div>
  </div>

  <!-- ═══════════════ STEP 1 — IDENTIFYING INFORMATION ═══════════════ -->
  <div class="step-title">Step 1 of 7 — Identifying Information</div>
  <?php
  item(1, 'Full Name',
      row('Last Name', na($r['lastnameApplicant'])) .
      row('First Name', na($r['firstnameApplicant'])) .
      row('Middle Name', na($r['middlenameApplicant'])) .
      row('Extension', na($r['suffixApplicant']))
  );
  item(2, 'Current Address',
      row('Barangay', na($r['barangay'])) .
      row('Purok / Zone / Sitio', na($r['purok'])) .
      row('Street / House No.', na($r['street']))
  );
  item(3, 'Date of Birth',
      row('Birthdate', na(trim(($r['month']??'').' '.($r['date']??'').', '.($r['year']??'')))) .
      row('Computed Age', calcAge($r['month'],$r['date'],$r['year']))
  );
  item(4, 'Personal Details',
      row('Birthplace', na($r['birthplace'])) .
      row('Marital Status', na($r['maritalStatus'])) .
      row('Sex', na($r['sex'])) .
      row('Religion', na($r['religion']))
  );
  item(5, 'Contact Information',
      row('Contact Number', na($r['contactNumber'])) .
      row('Email Address', na($r['emailAddress'])) .
      row('FB Messenger', na($r['fbMessenger'])) .
      row('Ethnic Origin', na($r['ethnicOrigin'])) .
      row('Language Spoken', na($r['languageSpoken']))
  );
  item(6, 'Government IDs',
      row('OSCA ID No.', na($r['osca_ID'])) .
      row('GSIS / SSS No.', na($r['gsis_sss_ID'])) .
      row('TIN No.', na($r['tin_ID'])) .
      row('PhilHealth ID', na($r['philHealth_ID'])) .
      row('Senior Citizens Assoc. ID', na($r['sc_asso_ID'])) .
      row('Other Govt. ID', na($r['other_govt_ID']))
  );
  item(7, 'Other Information',
      row('Employment / Business', na($r['employment_business'])) .
      row('Receiving Pension?', na($r['hasPension'])) .
      row('Can Travel?', na($r['travelCapability'])) .
      row('Person with Disability?', na($r['personWithDisability']))
  );
  ?>

  <!-- ═══════════════ STEP 2 — FAMILY COMPOSITION ═══════════════ -->
  <div class="step-title">Step 2 of 7 — Family Composition</div>
  <?php
  item(8, 'Spouse Information', row('Full Name', fullPersonName($r,'Spouse')));
  item(9, "Father's Name", row('Full Name', fullPersonName($r,'Father')));
  item(10, "Mother's Name", row('Full Name', fullPersonName($r,'Mother')));
  ?>
  <div class="item">
    <div class="item-num">11. Children</div>
    <table class="subtable">
      <thead><tr><th>Name</th><th>Occupation</th><th>Income</th><th>Age</th><th>Working?</th></tr></thead>
      <tbody><?= childRows($r, $childCount, 'Child') ?></tbody>
    </table>
  </div>
  <div class="item">
    <div class="item-num">12. Dependents (up to 2)</div>
    <table class="subtable">
      <thead><tr><th>Name</th><th>Occupation</th><th>Income</th><th>Age</th><th>Working?</th></tr></thead>
      <tbody><?= childRows($r, 2, 'Dependent') ?></tbody>
    </table>
  </div>

  <!-- ═══════════════ STEP 3 — LIVING SITUATION ═══════════════ -->
  <div class="step-title">Step 3 of 7 — Living Situation</div>
  <?php
  item(13, 'Living Situation', row('Living Alone?', na($r['livingAlone'])));
  item(14, 'Living With',
      row('Living With', csv($r['livingWith'])) .
      row('Others (specify)', na($r['livingWithOthers']))
  );
  item(15, 'Living Condition',
      row('Living Condition', csv($r['livingCondition'])) .
      row('Others (specify)', na($r['livingConditionOthers']))
  );
  ?>

  <!-- ═══════════════ STEP 4 — EDUCATION / HR PROFILE ═══════════════ -->
  <div class="step-title">Step 4 of 7 — Education / HR Profile</div>
  <?php
  item(16, 'Highest Educational Attainment',
      row('Attainment', na($r['educationHighest'])) .
      row('Others (specify)', na($r['educationHighestOthers']))
  );
  item(17, 'Specialization / Technical Skills',
      row('Skills', csv($r['skills'])) .
      row('Others (specify)', na($r['skillsOthers']))
  );
  item(18, 'Shared Skills', row('Shared Skills', csv($r['sharedSkills'])));
  item(19, 'Involvement in Community Activities',
      row('Involvement', csv($r['communityInvolvement'])) .
      row('Others (specify)', na($r['communityInvolvementOthers']))
  );
  ?>

  <!-- ═══════════════ STEP 5 — ECONOMIC PROFILE ═══════════════ -->
  <div class="step-title">Step 5 of 7 — Economic Profile</div>
  <?php
  item(20, 'Source of Income and Assistance',
      row('Sources', csv($r['sourceIncome'])) .
      row('Others (specify)', na($r['sourceIncomeOthers']))
  );
  item(21, 'Assets — Real and Immovable Properties',
      row('Properties', csv($r['assetsReal'])) .
      row('Others (specify)', na($r['assetsRealOthers']))
  );
  item(22, 'Assets — Personal and Movable Properties',
      row('Properties', csv($r['assetsPersonal'])) .
      row('Others (specify)', na($r['assetsPersonalOthers']))
  );
  item(23, 'Average Monthly Income', row('Monthly Income', na($r['incomeMonthly'])));
  item(24, 'Problems / Needs Commonly Encountered',
      row('Problems / Needs', csv($r['problemsNeeds'])) .
      row('Livelihood (specify)', na($r['problemsNeedsLivelihood'])) .
      row('Others (specify)', na($r['problemsNeedsOthers']))
  );
  ?>

  <!-- ═══════════════ STEP 6 — HEALTH PROFILE ═══════════════ -->
  <div class="step-title">Step 6 of 7 — Health Profile</div>
  <?php
  item(25, 'Blood Type', row('Blood Type', na($r['bloodType'])));
  item(26, 'Physical Disability', row('Description', na($r['physicalDisability'])));
  item(27, 'Health Problems',
      row('Health Problems', csv($r['healthProblems'])) .
      row('Others (specify)', na($r['healthProblemsOthers']))
  );
  item(28, 'Dental Concern',
      row('Dental Concern', csv($r['dentalConcern'])) .
      row('Others (specify)', na($r['dentalConcernOthers']))
  );
  item(29, 'Visual Concern',
      row('Visual Concern', csv($r['visualConcern'])) .
      row('Others (specify)', na($r['visualConcernOthers']))
  );
  item(30, 'Aural / Hearing Concern',
      row('Aural Concern', csv($r['auralConcern'])) .
      row('Others (specify)', na($r['auralConcernOthers']))
  );
  item(31, 'Social / Emotional Concern',
      row('Social Concern', csv($r['socialConcern'])) .
      row('Others (specify)', na($r['socialConcernOthers']))
  );
  item(32, 'Area of Difficulty',
      row('Area of Difficulty', csv($r['areaDifficulty'])) .
      row('Others (specify)', na($r['areaDifficultyOthers']))
  );
  item(33, 'List of Medicines for Maintenance', row('Medicines', csv($r['listOfMedicines'])));
  item(34, 'Scheduled Checkup',
      row('Scheduled Checkup', na($r['scheduledCheckup'])) .
      row('Frequency', na($r['scheduledCheckupYes']))
  );
  ?>

  <!-- ═══════════════ STEP 7 — ID & PHOTO UPLOAD ═══════════════ -->
  <div class="step-title">Step 7 of 7 — ID &amp; Photo Upload</div>
  <div class="item">
    <div class="item-num">35. OSCA ID Photo</div>
    <?php if ($r['has_osca_photo']): ?>
      <div class="id-photo-box"><img src="get_image.php?id=<?= $r['id'] ?>&type=osca" alt="OSCA ID Photo"></div>
    <?php else: ?>
      <p style="font-size:.76rem;color:#999">No OSCA ID photo on file</p>
    <?php endif; ?>
  </div>
  <div class="item">
    <div class="item-num">36. Latest 2×2 Photo</div>
    <?php if ($r['has_latest_photo']): ?>
      <div class="photo-box" style="width:100px;height:120px"><img src="get_image.php?id=<?= $r['id'] ?>&type=photo" alt="2x2 Photo"></div>
    <?php else: ?>
      <p style="font-size:.76rem;color:#999">No 2x2 photo on file</p>
    <?php endif; ?>
  </div>
  <div class="item">
    <div class="item-num">Record Info</div>
    <table class="info">
      <?= row('Registered On', $r['created_at'] ? date('F j, Y g:i A', strtotime($r['created_at'])) : '—') ?>
      <?= row('Last Updated', $r['updated_at'] ? date('F j, Y g:i A', strtotime($r['updated_at'])) : '—') ?>
    </table>
  </div>

  <p class="footer-note">End of Profile — <?= htmlspecialchars($fullName) ?></p>

</body>
</html>