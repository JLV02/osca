<?php
session_start();
require_once 'db.php';
require_once 'notifications_helper.php';
require_once 'audit_log_helper.php';

header('Content-Type: application/json');
// ─────────────────────────────────────────────────────────────────────────────
// Role helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns true when a staff member (any role) is authenticated.
 */
function is_staff(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Returns true only for the administrator role.
 */
function is_admin(): bool {
    return is_staff() && ($_SESSION['admin_role'] ?? '') === 'admin';
}
function osca_id_taken(PDO $pdo, string $osca_id, int $excludeId = 0): bool {
    if ($osca_id === '') return false;
    $sql = "SELECT id FROM applicants WHERE osca_ID = ?";
    $params = [$osca_id];
    if ($excludeId > 0) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

// Require an authenticated staff session for every action in this file.
if (!is_staff()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'save_step1') {
    $lastname   = trim($_POST['lastnameApplicant'] ?? '');
    $firstname  = trim($_POST['firstnameApplicant'] ?? '');
    $middlename = trim($_POST['middlenameApplicant'] ?? '');
    $suffix     = $_POST['suffixApplicant'] ?? null;
    $sex        = $_POST['sex'] ?? null;
    $month      = $_POST['month'] ?? null;
    $date       = $_POST['date'] ?? null;
    $year       = $_POST['year'] ?? null;
    $birthplace = trim($_POST['birthplace'] ?? '');
    $marital    = $_POST['maritalStatus'] ?? null;
    $religion   = $_POST['religion'] ?? null;
    $contact    = trim($_POST['contactNumber'] ?? '');
    $email      = trim($_POST['emailAddress'] ?? '');
    $fb         = trim($_POST['fbMessenger'] ?? '');
    $ethnic     = trim($_POST['ethnicOrigin'] ?? '');
    $language   = trim($_POST['languageSpoken'] ?? '');
    $osca       = trim($_POST['osca_ID'] ?? '');
    if ($osca !== '') {
        $excludeId = !empty($_SESSION['applicant_id']) ? (int)$_SESSION['applicant_id'] : 0;
        if (osca_id_taken($pdo, $osca, $excludeId)) {
            echo json_encode(['success' => false, 'message' => 'OSCA ID "'.htmlspecialchars($osca).'" is already assigned to another registrant. Please enter a unique OSCA ID.']);
            exit;
        }
    }
    $gsis       = trim($_POST['gsis_sss_ID'] ?? '');
    $tin        = trim($_POST['tin_ID'] ?? '');
    $philhealth = trim($_POST['philHealth_ID'] ?? '');
    $sc_asso    = trim($_POST['sc_asso_ID'] ?? '');
    $other_govt = trim($_POST['other_govt_ID'] ?? '');
    $employment = trim($_POST['employment_business'] ?? '');
    $pension    = $_POST['hasPension'] ?? null;
    $travel     = $_POST['travelCapability'] ?? null;
    $disability = $_POST['personWithDisability'] ?? null;
    $barangay   = trim($_POST['barangay'] ?? '');
    $purok      = trim($_POST['purok'] ?? '');
    $street     = trim($_POST['street'] ?? '');

    // Custom registration date (optional — defaults to NOW())
    $reg_month  = trim($_POST['reg_month'] ?? '');
    $reg_day    = trim($_POST['reg_day'] ?? '');
    $reg_year   = trim($_POST['reg_year'] ?? '');
    $custom_date = null;
    if ($reg_month && $reg_day && $reg_year) {
        $parsed = DateTime::createFromFormat('F j Y', "$reg_month $reg_day $reg_year");
        if ($parsed && $parsed <= new DateTime()) {
            $custom_date = $parsed->format('Y-m-d H:i:s');
        }
    }

    if (empty($lastname) || empty($firstname)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required name fields.']);
        exit;
    }
    if (empty($barangay)) {
        echo json_encode(['success' => false, 'message' => 'Barangay is required.']);
        exit;
    }

   $isNewRecord = empty($_SESSION['applicant_id']);
    try {
        if (!empty($_SESSION['applicant_id'])) {
            $stmt = $pdo->prepare("UPDATE applicants SET
                lastnameApplicant=?, firstnameApplicant=?, middlenameApplicant=?, suffixApplicant=?,
                sex=?, month=?, date=?, year=?, birthplace=?, maritalStatus=?, religion=?,
                contactNumber=?, emailAddress=?, fbMessenger=?, ethnicOrigin=?, languageSpoken=?,
                osca_ID=?, gsis_sss_ID=?, tin_ID=?, philHealth_ID=?, sc_asso_ID=?,
                other_govt_ID=?, employment_business=?, hasPension=?, travelCapability=?,
                personWithDisability=?,
                barangay=?, purok=?, street=?
                WHERE id=?");
            $stmt->execute([
                $lastname, $firstname, $middlename, $suffix,
                $sex, $month, $date, $year, $birthplace, $marital, $religion,
                $contact, $email, $fb, $ethnic, $language,
                $osca, $gsis, $tin, $philhealth, $sc_asso,
                $other_govt, $employment, $pension, $travel,
                $disability,
                $barangay, $purok, $street,
                $_SESSION['applicant_id']
            ]);
            $id = $_SESSION['applicant_id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO applicants
                (lastnameApplicant, firstnameApplicant, middlenameApplicant, suffixApplicant,
                sex, month, date, year, birthplace, maritalStatus, religion,
                contactNumber, emailAddress, fbMessenger, ethnicOrigin, languageSpoken,
                osca_ID, gsis_sss_ID, tin_ID, philHealth_ID, sc_asso_ID,
                other_govt_ID, employment_business, hasPension, travelCapability,
                personWithDisability,
                barangay, purok, street, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'incomplete', " . ($custom_date ? '?' : 'NOW()') . ")");
            $exec_params = [
                $lastname, $firstname, $middlename, $suffix,
                $sex, $month, $date, $year, $birthplace, $marital, $religion,
                $contact, $email, $fb, $ethnic, $language,
                $osca, $gsis, $tin, $philhealth, $sc_asso,
                $other_govt, $employment, $pension, $travel,
                $disability,
                $barangay, $purok, $street
            ];
            if ($custom_date) $exec_params[] = $custom_date;
            $stmt->execute($exec_params);
            $id = $pdo->lastInsertId();
            $_SESSION['applicant_id'] = $id;
        }

        $fullName = trim("$firstname $lastname");
        osca_bump_change($pdo, $isNewRecord ? 'create' : 'update', $isNewRecord ? 'New Registration' : 'Record Updated', $fullName);
        osca_log_audit($pdo, $isNewRecord ? 'create_registration' : 'update_registration', 'applicant', (int)$id, $fullName);
        echo json_encode(['success' => true, 'message' => 'Step 1 saved successfully!']);
   } catch (Throwable $e) {
        if ($e instanceof PDOException && $e->getCode() === '23000' && stripos($e->getMessage(), 'osca_id') !== false) {
            echo json_encode(['success' => false, 'message' => 'That OSCA ID is already in use by another registrant. Please enter a unique OSCA ID.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

} elseif ($action === 'save_step2') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $id = $_SESSION['applicant_id'];

    $spouseLast   = trim($_POST['lastnameSpouse'] ?? '');
    $spouseFirst  = trim($_POST['firstnameSpouse'] ?? '');
    $spouseMiddle = trim($_POST['middlenameSpouse'] ?? '');
    $spouseSuffix = trim($_POST['suffixSpouse'] ?? '');

    $fatherLast   = trim($_POST['lastnameFather'] ?? '');
    $fatherFirst  = trim($_POST['firstnameFather'] ?? '');
    $fatherMiddle = trim($_POST['middlenameFather'] ?? '');
    $fatherSuffix = trim($_POST['suffixFather'] ?? '');

    $motherLast   = trim($_POST['lastnameMother'] ?? '');
    $motherFirst  = trim($_POST['firstnameMother'] ?? '');
    $motherMiddle = trim($_POST['middlenameMother'] ?? '');
    $motherSuffix = trim($_POST['suffixMother'] ?? '');

    $childCount = max(5, (int)($_POST['childCount'] ?? 5));

$children = [];
for ($i = 1; $i <= $childCount; $i++) {
    $children[$i] = [
        'fullname'   => trim($_POST["fullnameChild$i"] ?? ''),
        'occupation' => trim($_POST["occupationChild$i"] ?? ''),
        'income'     => $_POST["incomeChild$i"] ?? null,
        'age'        => $_POST["ageChild$i"] ?? null,
        'isWorking'  => $_POST["isWorkingChild$i"] ?? null,
    ];
}

    $dependents = [];
    for ($i = 1; $i <= 2; $i++) {
        $dependents[$i] = [
            'fullname'   => trim($_POST["fullnameDependent$i"] ?? ''),
            'occupation' => trim($_POST["occupationDependent$i"] ?? ''),
            'income'     => $_POST["incomeDependent$i"] ?? null,
            'age'        => $_POST["ageDependent$i"] ?? null,
            'isWorking'  => $_POST["isWorkingDependent$i"] ?? null,
        ];
    }

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            lastnameSpouse=?, firstnameSpouse=?, middlenameSpouse=?, suffixSpouse=?,
            lastnameFather=?, firstnameFather=?, middlenameFather=?, suffixFather=?,
            lastnameMother=?, firstnameMother=?, middlenameMother=?, suffixMother=?,
            fullnameChild1=?, occupationChild1=?, incomeChild1=?, ageChild1=?, isWorkingChild1=?,
            fullnameChild2=?, occupationChild2=?, incomeChild2=?, ageChild2=?, isWorkingChild2=?,
            fullnameChild3=?, occupationChild3=?, incomeChild3=?, ageChild3=?, isWorkingChild3=?,
            fullnameChild4=?, occupationChild4=?, incomeChild4=?, ageChild4=?, isWorkingChild4=?,
            fullnameChild5=?, occupationChild5=?, incomeChild5=?, ageChild5=?, isWorkingChild5=?,
            fullnameDependent1=?, occupationDependent1=?, incomeDependent1=?, ageDependent1=?, isWorkingDependent1=?,
            fullnameDependent2=?, occupationDependent2=?, incomeDependent2=?, ageDependent2=?, isWorkingDependent2=?
            WHERE id=?");

        $stmt->execute([
            $spouseLast, $spouseFirst, $spouseMiddle, $spouseSuffix,
            $fatherLast, $fatherFirst, $fatherMiddle, $fatherSuffix,
            $motherLast, $motherFirst, $motherMiddle, $motherSuffix,
            $children[1]['fullname'], $children[1]['occupation'], $children[1]['income'] ?: null, $children[1]['age'] ?: null, $children[1]['isWorking'],
            $children[2]['fullname'], $children[2]['occupation'], $children[2]['income'] ?: null, $children[2]['age'] ?: null, $children[2]['isWorking'],
            $children[3]['fullname'], $children[3]['occupation'], $children[3]['income'] ?: null, $children[3]['age'] ?: null, $children[3]['isWorking'],
            $children[4]['fullname'], $children[4]['occupation'], $children[4]['income'] ?: null, $children[4]['age'] ?: null, $children[4]['isWorking'],
            $children[5]['fullname'], $children[5]['occupation'], $children[5]['income'] ?: null, $children[5]['age'] ?: null, $children[5]['isWorking'],
            $dependents[1]['fullname'], $dependents[1]['occupation'], $dependents[1]['income'] ?: null, $dependents[1]['age'] ?: null, $dependents[1]['isWorking'],
            $dependents[2]['fullname'], $dependents[2]['occupation'], $dependents[2]['income'] ?: null, $dependents[2]['age'] ?: null, $dependents[2]['isWorking'],
            $id
        ]);

        echo json_encode(['success' => true, 'message' => 'Step 2 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'save_step3') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }
    $id = $_SESSION['applicant_id'];

    $livingAlone           = $_POST['livingAlone'] ?? null;
    $livingWith            = trim($_POST['livingWith'] ?? '');
    $livingWithOthers      = trim($_POST['livingWithOthers'] ?? '');
    $livingCondition       = trim($_POST['livingCondition'] ?? '');
    $livingConditionOthers = trim($_POST['livingConditionOthers'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            livingAlone=?, livingWith=?, livingWithOthers=?,
            livingCondition=?, livingConditionOthers=?
            WHERE id=?");
        $stmt->execute([
            $livingAlone ?: null, $livingWith ?: null, $livingWithOthers ?: null,
            $livingCondition ?: null, $livingConditionOthers ?: null,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Step 3 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'save_step4') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }
    $id = $_SESSION['applicant_id'];

    $educationHighest           = $_POST['educationHighest'] ?? null;
    $educationHighestOthers     = trim($_POST['educationHighestOthers'] ?? '');
    $skills                     = trim($_POST['skills'] ?? '');
    $skillsOthers               = trim($_POST['skillsOthers'] ?? '');
    $sharedSkills               = trim($_POST['sharedSkills'] ?? '');
    $communityInvolvement       = trim($_POST['communityInvolvement'] ?? '');
    $communityInvolvementOthers = trim($_POST['communityInvolvementOthers'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            educationHighest=?, educationHighestOthers=?,
            skills=?, skillsOthers=?, sharedSkills=?,
            communityInvolvement=?, communityInvolvementOthers=?
            WHERE id=?");
        $stmt->execute([
            $educationHighest ?: null, $educationHighestOthers ?: null,
            $skills ?: null, $skillsOthers ?: null, $sharedSkills ?: null,
            $communityInvolvement ?: null, $communityInvolvementOthers ?: null,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Step 4 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'save_step5') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }
    $id = $_SESSION['applicant_id'];

    $sourceIncome            = trim($_POST['sourceIncome'] ?? '');
    $sourceIncomeOthers      = trim($_POST['sourceIncomeOthers'] ?? '');
    $assetsReal              = trim($_POST['assetsReal'] ?? '');
    $assetsRealOthers        = trim($_POST['assetsRealOthers'] ?? '');
    $assetsPersonal          = trim($_POST['assetsPersonal'] ?? '');
    $assetsPersonalOthers    = trim($_POST['assetsPersonalOthers'] ?? '');
    $incomeMonthly           = $_POST['incomeMonthly'] ?? null;
    $problemsNeeds           = trim($_POST['problemsNeeds'] ?? '');
    $problemsNeedsLivelihood = trim($_POST['problemsNeedsLivelihood'] ?? '');
    $problemsNeedsOthers     = trim($_POST['problemsNeedsOthers'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            sourceIncome=?, sourceIncomeOthers=?,
            assetsReal=?, assetsRealOthers=?,
            assetsPersonal=?, assetsPersonalOthers=?,
            incomeMonthly=?,
            problemsNeeds=?, problemsNeedsLivelihood=?, problemsNeedsOthers=?
            WHERE id=?");
        $stmt->execute([
            $sourceIncome ?: null, $sourceIncomeOthers ?: null,
            $assetsReal ?: null, $assetsRealOthers ?: null,
            $assetsPersonal ?: null, $assetsPersonalOthers ?: null,
            $incomeMonthly ?: null,
            $problemsNeeds ?: null, $problemsNeedsLivelihood ?: null, $problemsNeedsOthers ?: null,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Step 5 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'save_step6') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }
    $id = $_SESSION['applicant_id'];

    $bloodType            = $_POST['bloodType'] ?? null;
    $physicalDisability   = trim($_POST['physicalDisability'] ?? '');
    $healthProblems       = trim($_POST['healthProblems'] ?? '');
    $healthProblemsOthers = trim($_POST['healthProblemsOthers'] ?? '');
    $dentalConcern        = trim($_POST['dentalConcern'] ?? '');
    $dentalConcernOthers  = trim($_POST['dentalConcernOthers'] ?? '');
    $visualConcern        = trim($_POST['visualConcern'] ?? '');
    $visualConcernOthers  = trim($_POST['visualConcernOthers'] ?? '');
    $auralConcern         = trim($_POST['auralConcern'] ?? '');
    $auralConcernOthers   = trim($_POST['auralConcernOthers'] ?? '');
    $socialConcern        = trim($_POST['socialConcern'] ?? '');
    $socialConcernOthers  = trim($_POST['socialConcernOthers'] ?? '');
    $areaDifficulty       = trim($_POST['areaDifficulty'] ?? '');
    $areaDifficultyOthers = trim($_POST['areaDifficultyOthers'] ?? '');
    $listOfMedicines      = trim($_POST['listOfMedicines'] ?? '');
    $scheduledCheckup     = $_POST['scheduledCheckup'] ?? null;
    $scheduledCheckupYes  = $_POST['scheduledCheckupYes'] ?? null;

    if (empty($bloodType)) {
        echo json_encode(['success' => false, 'message' => 'Blood type is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            bloodType=?, physicalDisability=?,
            healthProblems=?, healthProblemsOthers=?,
            dentalConcern=?, dentalConcernOthers=?,
            visualConcern=?, visualConcernOthers=?,
            auralConcern=?, auralConcernOthers=?,
            socialConcern=?, socialConcernOthers=?,
            areaDifficulty=?, areaDifficultyOthers=?,
            listOfMedicines=?, scheduledCheckup=?, scheduledCheckupYes=?
            WHERE id=?");
        $stmt->execute([
            $bloodType, $physicalDisability ?: null,
            $healthProblems ?: null, $healthProblemsOthers ?: null,
            $dentalConcern ?: null, $dentalConcernOthers ?: null,
            $visualConcern ?: null, $visualConcernOthers ?: null,
            $auralConcern ?: null, $auralConcernOthers ?: null,
            $socialConcern ?: null, $socialConcernOthers ?: null,
            $areaDifficulty ?: null, $areaDifficultyOthers ?: null,
            $listOfMedicines ?: null, $scheduledCheckup ?: null, $scheduledCheckupYes ?: null,
            $id
        ]);
        echo json_encode(['success' => true, 'message' => 'Step 6 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'submit_registration') {
    // Final step — Step 7: ID photo + 2x2 photo upload, marks registration complete
    // Both admin and encoder can submit registrations
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }
    $id = $_SESSION['applicant_id'];

    if (empty($_FILES['oscaID']['tmp_name']) || empty($_FILES['photoLatest']['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => 'Both the OSCA ID photo and the 2x2 photo are required.']);
        exit;
    }

    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['oscaID']['size'] > $maxSize || $_FILES['photoLatest']['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'Each file must be 5MB or smaller.']);
        exit;
    }

    $oscaIDData      = file_get_contents($_FILES['oscaID']['tmp_name']);
    $oscaIDType      = $_FILES['oscaID']['type'] ?: 'application/octet-stream';
    $photoLatestData = file_get_contents($_FILES['photoLatest']['tmp_name']);
    $photoLatestType = $_FILES['photoLatest']['type'] ?: 'application/octet-stream';

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            oscaID=?, oscaID_type=?,
            photoLatest=?, photoLatest_type=?,
            status='complete'
            WHERE id=?");
        $stmt->bindParam(1, $oscaIDData, PDO::PARAM_LOB);
        $stmt->bindParam(2, $oscaIDType);
        $stmt->bindParam(3, $photoLatestData, PDO::PARAM_LOB);
        $stmt->bindParam(4, $photoLatestType);
        $stmt->bindParam(5, $id);
        $stmt->execute();

        $nameStmt = $pdo->prepare("SELECT lastnameApplicant, firstnameApplicant FROM applicants WHERE id = ?");
        $nameStmt->execute([$id]);
        $nameRow  = $nameStmt->fetch(PDO::FETCH_ASSOC);
        $fullName = $nameRow ? trim($nameRow['firstnameApplicant'].' '.$nameRow['lastnameApplicant']) : null;

        unset($_SESSION['applicant_id']);
        osca_bump_change($pdo, 'complete_registration', 'Registration Completed', $fullName);
        osca_log_audit($pdo, 'complete_registration', 'applicant', (int)$id, $fullName);
        echo json_encode(['success' => true, 'message' => 'Registration completed successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'update_record') {
    // Both admin and encoder can edit records
    if (!is_staff()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
        exit;
    }

    $lastname   = trim($_POST['lastnameApplicant'] ?? '');
    $firstname  = trim($_POST['firstnameApplicant'] ?? '');
    $middlename = trim($_POST['middlenameApplicant'] ?? '');
    $suffix     = trim($_POST['suffixApplicant'] ?? '') ?: null;
    $barangay   = trim($_POST['barangay'] ?? '');
    $purok      = trim($_POST['purok'] ?? '');
    $street     = trim($_POST['street'] ?? '');
    $month      = $_POST['month'] ?? null;
    $day        = $_POST['date'] ?? null;
    $year       = $_POST['year'] ?? null;
    $birthplace = trim($_POST['birthplace'] ?? '');
    $sex        = $_POST['sex'] ?? null;
    $marital    = $_POST['maritalStatus'] ?? null;
    $contact    = trim($_POST['contactNumber'] ?? '');
    $email      = trim($_POST['emailAddress'] ?? '');
    $fb         = trim($_POST['fbMessenger'] ?? '');
    $osca       = trim($_POST['osca_ID'] ?? '');
    if ($osca !== '') {
        if (osca_id_taken($pdo, $osca, $id)) {
            echo json_encode(['success' => false, 'message' => 'OSCA ID "'.htmlspecialchars($osca).'" is already assigned to another registrant. Please enter a unique OSCA ID.']);
            exit;
        }
    }
    $gsis       = trim($_POST['gsis_sss_ID'] ?? '');
    $tin        = trim($_POST['tin_ID'] ?? '');
    $philhealth = trim($_POST['philHealth_ID'] ?? '');
    $sc_asso    = trim($_POST['sc_asso_ID'] ?? '');
    $other_govt = trim($_POST['other_govt_ID'] ?? '');
    $employment = trim($_POST['employment_business'] ?? '');
    $pension    = $_POST['hasPension'] ?? null;
    $travel     = $_POST['travelCapability'] ?? null;
    $disability = $_POST['personWithDisability'] ?? null;

    // Override registration date — admin only
    $custom_created = null;
    if (is_admin()) {
        $reg_month = trim($_POST['reg_month'] ?? '');
        $reg_day   = trim($_POST['reg_day'] ?? '');
        $reg_year  = trim($_POST['reg_year'] ?? '');
        if ($reg_month && $reg_day && $reg_year) {
            $parsed = DateTime::createFromFormat('F j Y', "$reg_month $reg_day $reg_year");
            if ($parsed && $parsed <= new DateTime()) {
                $custom_created = $parsed->format('Y-m-d H:i:s');
            }
        }
    }

    if (empty($lastname) || empty($firstname)) {
        echo json_encode(['success' => false, 'message' => 'Name fields are required.']);
        exit;
    }
    if (empty($barangay)) {
        echo json_encode(['success' => false, 'message' => 'Barangay is required.']);
        exit;
    }
    if (empty($contact) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Contact number and email are required.']);
        exit;
    }

    $spouseLast   = trim($_POST['lastnameSpouse'] ?? '');
    $spouseFirst  = trim($_POST['firstnameSpouse'] ?? '');
    $spouseMiddle = trim($_POST['middlenameSpouse'] ?? '');
    $spouseSuffix = trim($_POST['suffixSpouse'] ?? '');

    $fatherLast   = trim($_POST['lastnameFather'] ?? '');
    $fatherFirst  = trim($_POST['firstnameFather'] ?? '');
    $fatherMiddle = trim($_POST['middlenameFather'] ?? '');
    $fatherSuffix = trim($_POST['suffixFather'] ?? '');

    $motherLast   = trim($_POST['lastnameMother'] ?? '');
    $motherFirst  = trim($_POST['firstnameMother'] ?? '');
    $motherMiddle = trim($_POST['middlenameMother'] ?? '');
    $motherSuffix = trim($_POST['suffixMother'] ?? '');

    $childCount = max(5, (int)($_POST['childCount'] ?? 5));

    $children = [];
    for ($i = 1; $i <= $childCount; $i++) {
        $children[$i] = [
            'fullname'   => trim($_POST["fullnameChild$i"] ?? ''),
            'occupation' => trim($_POST["occupationChild$i"] ?? ''),
            'income'     => $_POST["incomeChild$i"] ?? null,
            'age'        => $_POST["ageChild$i"] ?? null,
            'isWorking'  => $_POST["isWorkingChild$i"] ?? null,
        ];
    }
    $dependents = [];
    for ($i = 1; $i <= 2; $i++) {
        $dependents[$i] = [
            'fullname'   => trim($_POST["fullnameDependent$i"] ?? ''),
            'occupation' => trim($_POST["occupationDependent$i"] ?? ''),
            'income'     => $_POST["incomeDependent$i"] ?? null,
            'age'        => $_POST["ageDependent$i"] ?? null,
            'isWorking'  => $_POST["isWorkingDependent$i"] ?? null,
        ];
    }

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET
            lastnameApplicant=?, firstnameApplicant=?, middlenameApplicant=?, suffixApplicant=?,
            barangay=?, purok=?, street=?,
            month=?, date=?, year=?, birthplace=?,
            sex=?, maritalStatus=?,
            contactNumber=?, emailAddress=?, fbMessenger=?,
            osca_ID=?, gsis_sss_ID=?, tin_ID=?, philHealth_ID=?, sc_asso_ID=?, other_govt_ID=?,
            employment_business=?, hasPension=?, travelCapability=?,
            personWithDisability=?,
            lastnameSpouse=?, firstnameSpouse=?, middlenameSpouse=?, suffixSpouse=?,
            lastnameFather=?, firstnameFather=?, middlenameFather=?, suffixFather=?,
            lastnameMother=?, firstnameMother=?, middlenameMother=?, suffixMother=?,
            fullnameChild1=?, occupationChild1=?, incomeChild1=?, ageChild1=?, isWorkingChild1=?,
            fullnameChild2=?, occupationChild2=?, incomeChild2=?, ageChild2=?, isWorkingChild2=?,
            fullnameChild3=?, occupationChild3=?, incomeChild3=?, ageChild3=?, isWorkingChild3=?,
            fullnameChild4=?, occupationChild4=?, incomeChild4=?, ageChild4=?, isWorkingChild4=?,
            fullnameChild5=?, occupationChild5=?, incomeChild5=?, ageChild5=?, isWorkingChild5=?,
            fullnameDependent1=?, occupationDependent1=?, incomeDependent1=?, ageDependent1=?, isWorkingDependent1=?,
            fullnameDependent2=?, occupationDependent2=?, incomeDependent2=?, ageDependent2=?, isWorkingDependent2=?"
            . ($custom_created ? ", created_at=?" : "") .
            " WHERE id=?");

        $exec_params = [
            $lastname, $firstname, $middlename, $suffix,
            $barangay, $purok ?: null, $street ?: null,
            $month ?: null, $day ?: null, $year ?: null, $birthplace ?: null,
            $sex ?: null, $marital ?: null,
            $contact, $email, $fb ?: null,
            $osca ?: null, $gsis ?: null, $tin ?: null, $philhealth ?: null, $sc_asso ?: null, $other_govt ?: null,
            $employment ?: null, $pension ?: null, $travel ?: null,
            $disability ?: null,
            $spouseLast ?: null, $spouseFirst ?: null, $spouseMiddle ?: null, $spouseSuffix ?: null,
            $fatherLast ?: null, $fatherFirst ?: null, $fatherMiddle ?: null, $fatherSuffix ?: null,
            $motherLast ?: null, $motherFirst ?: null, $motherMiddle ?: null, $motherSuffix ?: null,
            $children[1]['fullname'] ?: null, $children[1]['occupation'] ?: null, $children[1]['income'] ?: null, $children[1]['age'] ?: null, $children[1]['isWorking'] ?: null,
            $children[2]['fullname'] ?: null, $children[2]['occupation'] ?: null, $children[2]['income'] ?: null, $children[2]['age'] ?: null, $children[2]['isWorking'] ?: null,
            $children[3]['fullname'] ?: null, $children[3]['occupation'] ?: null, $children[3]['income'] ?: null, $children[3]['age'] ?: null, $children[3]['isWorking'] ?: null,
            $children[4]['fullname'] ?: null, $children[4]['occupation'] ?: null, $children[4]['income'] ?: null, $children[4]['age'] ?: null, $children[4]['isWorking'] ?: null,
            $children[5]['fullname'] ?: null, $children[5]['occupation'] ?: null, $children[5]['income'] ?: null, $children[5]['age'] ?: null, $children[5]['isWorking'] ?: null,
            $dependents[1]['fullname'] ?: null, $dependents[1]['occupation'] ?: null, $dependents[1]['income'] ?: null, $dependents[1]['age'] ?: null, $dependents[1]['isWorking'] ?: null,
            $dependents[2]['fullname'] ?: null, $dependents[2]['occupation'] ?: null, $dependents[2]['income'] ?: null, $dependents[2]['age'] ?: null, $dependents[2]['isWorking'] ?: null,
        ];
        if ($custom_created) $exec_params[] = $custom_created;
        $exec_params[] = $id;
        $stmt->execute($exec_params);
    // ── Sync overflow children (6+) ─────────────────────────────
$pdo->prepare("DELETE FROM applicant_children_extra WHERE applicant_id = ?")->execute([$id]);
if ($childCount > 5) {
    $ins = $pdo->prepare("INSERT INTO applicant_children_extra
        (applicant_id, child_index, fullname, occupation, income, age, isWorking)
        VALUES (?,?,?,?,?,?,?)");
    for ($i = 6; $i <= $childCount; $i++) {
        $c = $children[$i];
        if (empty($c['fullname']) && empty($c['occupation']) && empty($c['income']) && empty($c['age']) && empty($c['isWorking'])) {
            continue; // skip a fully blank extra row
        }
        $ins->execute([$id, $i, $c['fullname'] ?: null, $c['occupation'] ?: null, $c['income'] ?: null, $c['age'] ?: null, $c['isWorking'] ?: null]);
    }
}

        $fullName = trim("$firstname $lastname");
        osca_bump_change($pdo, 'update', 'Record Updated', $fullName);
        osca_log_audit($pdo, 'update_record', 'applicant', (int)$id, $fullName);
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Step 1 saved successfully!']);
    } catch (Throwable $e) {
        if ($e instanceof PDOException && $e->getCode() === '23000' && stripos($e->getMessage(), 'osca_id') !== false) {
            echo json_encode(['success' => false, 'message' => 'That OSCA ID is already in use by another registrant. Please enter a unique OSCA ID.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

} elseif ($action === 'archive_record') {
    // Soft-archive: both admin and encoder can move records to the archive
    if (!is_staff()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE applicants SET status='archived' WHERE id=?");
        $stmt->execute([$id]);
        $nStmt = $pdo->prepare("SELECT lastnameApplicant, firstnameApplicant FROM applicants WHERE id = ?");
        $nStmt->execute([$id]);
        $nRow = $nStmt->fetch(PDO::FETCH_ASSOC);
        $archName = $nRow ? trim($nRow['firstnameApplicant'].' '.$nRow['lastnameApplicant']) : null;
        osca_bump_change($pdo, 'archive', 'Record Archived', $archName);
        osca_log_audit($pdo, 'archive_record', 'applicant', $id, $archName);
        echo json_encode(['success' => true, 'message' => 'Record archived successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'restore_record') {
    // Restore from archive: both roles
    if (!is_staff()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
        exit;
    }

    try {
       $stmt = $pdo->prepare("UPDATE applicants SET status='complete' WHERE id=?");
        $stmt->execute([$id]);
        $nStmt = $pdo->prepare("SELECT lastnameApplicant, firstnameApplicant FROM applicants WHERE id = ?");
        $nStmt->execute([$id]);
        $nRow = $nStmt->fetch(PDO::FETCH_ASSOC);
        $restName = $nRow ? trim($nRow['firstnameApplicant'].' '.$nRow['lastnameApplicant']) : null;
        osca_bump_change($pdo, 'restore', 'Record Restored', $restName);
        osca_log_audit($pdo, 'restore_record', 'applicant', $id, $restName);
        echo json_encode(['success' => true, 'message' => 'Record restored successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>