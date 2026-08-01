<?php
/**
 * get_record.php
 * Returns a single applicant record as JSON.
 * Called by: viewRecord(id) and editRecord(id) in dashboard.js
 *
 * Requires: admin session (set by login.php → $_SESSION['admin_logged_in'])
 * Usage: GET get_record.php?id=<int>
 */
session_start();
require_once 'db.php';

header('Content-Type: application/json');

// ── Auth guard ────────────────────────────────────────────────
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

// ── Validate ID ───────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

// ── Fetch record ──────────────────────────────────────────────
try {
    /*
     * Select every column EXCEPT the two MEDIUMBLOB photo columns.
     * Those are served by a separate endpoint (serve_photo.php) to
     * avoid bloating the JSON payload.  The dashboard modals never
     * need to display the raw blob inline.
     */
    $stmt = $pdo->prepare("
        SELECT
            id,
            ncsc_encoded,
            -- ── Name ─────────────────────────────────────────
            lastnameApplicant, firstnameApplicant,
            middlenameApplicant, suffixApplicant,

            -- ── Address ──────────────────────────────────────
            barangay, purok, street,

            -- ── Birthdate / Personal ─────────────────────────
            month, date, year, birthplace,
            maritalStatus, religion, sex,
            contactNumber, emailAddress, fbMessenger,
            ethnicOrigin, languageSpoken,

            -- ── Government IDs ───────────────────────────────
            osca_ID, gsis_sss_ID, tin_ID,
            philHealth_ID, sc_asso_ID, other_govt_ID,

            -- ── Other Info ───────────────────────────────────
            employment_business, hasPension, travelCapability,
            personWithDisability,

            -- ── Spouse ───────────────────────────────────────
            lastnameSpouse, firstnameSpouse,
            middlenameSpouse, suffixSpouse,

            -- ── Father ───────────────────────────────────────
            lastnameFather, firstnameFather,
            middlenameFather, suffixFather,

            -- ── Mother ───────────────────────────────────────
            lastnameMother, firstnameMother,
            middlenameMother, suffixMother,

            -- ── Children (max 5) ─────────────────────────────
            fullnameChild1, occupationChild1, incomeChild1, ageChild1, isWorkingChild1,
            fullnameChild2, occupationChild2, incomeChild2, ageChild2, isWorkingChild2,
            fullnameChild3, occupationChild3, incomeChild3, ageChild3, isWorkingChild3,
            fullnameChild4, occupationChild4, incomeChild4, ageChild4, isWorkingChild4,
            fullnameChild5, occupationChild5, incomeChild5, ageChild5, isWorkingChild5,

            -- ── Dependents (max 2) ───────────────────────────
            fullnameDependent1, occupationDependent1, incomeDependent1, ageDependent1, isWorkingDependent1,
            fullnameDependent2, occupationDependent2, incomeDependent2, ageDependent2, isWorkingDependent2,

            -- ── Living Situation (Step 3) ─────────────────────
            livingAlone, livingWith, livingWithOthers,
            livingCondition, livingConditionOthers,

            -- ── Education / HR Profile (Step 4) ──────────────
            educationHighest, educationHighestOthers,
            skills, skillsOthers, sharedSkills,
            communityInvolvement, communityInvolvementOthers,

            -- ── Economic Profile (Step 5) ─────────────────────
            sourceIncome, sourceIncomeOthers,
            assetsReal, assetsRealOthers,
            assetsPersonal, assetsPersonalOthers,
            incomeMonthly,
            problemsNeeds, problemsNeedsLivelihood, problemsNeedsOthers,

            -- ── Health Profile (Step 6) ───────────────────────
            bloodType, physicalDisability,
            healthProblems, healthProblemsOthers,
            dentalConcern, dentalConcernOthers,
            visualConcern, visualConcernOthers,
            auralConcern, auralConcernOthers,
            socialConcern, socialConcernOthers,
            areaDifficulty, areaDifficultyOthers,
            listOfMedicines,
            scheduledCheckup, scheduledCheckupYes,

            -- ── Photos — return existence flags only ──────────
            (oscaID    IS NOT NULL AND LENGTH(oscaID)    > 0) AS has_osca_photo,
            (photoLatest IS NOT NULL AND LENGTH(photoLatest) > 0) AS has_latest_photo,
            oscaID_type, photoLatest_type,

            -- ── Record meta ──────────────────────────────────
            created_at, updated_at

        FROM applicants
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    // ── Pull overflow children (6+) and merge into the record ──────
$stmt2 = $pdo->prepare("SELECT child_index, fullname, occupation, income, age, isWorking
                         FROM applicant_children_extra
                         WHERE applicant_id = ? ORDER BY child_index");
$stmt2->execute([$id]);
$extras = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$maxIndex = 5;
foreach ($extras as $ex) {
    $i = (int)$ex['child_index'];
    $maxIndex = max($maxIndex, $i);
    $record["fullnameChild$i"]   = $ex['fullname'];
    $record["occupationChild$i"] = $ex['occupation'];
    $record["incomeChild$i"]     = $ex['income'] !== null ? (float)$ex['income'] : null;
    $record["ageChild$i"]        = $ex['age'] !== null ? (int)$ex['age'] : null;
    $record["isWorkingChild$i"]  = $ex['isWorking'];
}
$record['childCount'] = $maxIndex; // tells dashboard.js how many child rows to render

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    // ── Cast numeric-ish columns so JS gets real numbers ──────
    foreach (['id', 'year',
              'ageChild1','ageChild2','ageChild3','ageChild4','ageChild5',
              'ageDependent1','ageDependent2'] as $col) {
        if (isset($record[$col]) && $record[$col] !== null) {
            $record[$col] = (int)$record[$col];
        }
    }
    foreach (['incomeChild1','incomeChild2','incomeChild3','incomeChild4','incomeChild5',
              'incomeDependent1','incomeDependent2'] as $col) {
        if (isset($record[$col]) && $record[$col] !== null) {
            $record[$col] = (float)$record[$col];
        }
    }
    $record['has_osca_photo']   = (bool)$record['has_osca_photo'];
    $record['has_latest_photo'] = (bool)$record['has_latest_photo'];

    echo json_encode(['success' => true, 'record' => $record]);

} catch (PDOException $e) {
    // Never expose raw DB error messages in production
    error_log('[get_record] PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}