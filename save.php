<?php
session_start();
require_once 'db.php';

header('Content-Type: application/json');

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
    $gsis       = trim($_POST['gsis_sss_ID'] ?? '');
    $tin        = trim($_POST['tin_ID'] ?? '');
    $philhealth = trim($_POST['philHealth_ID'] ?? '');
    $sc_asso    = trim($_POST['sc_asso_ID'] ?? '');
    $other_govt = trim($_POST['other_govt_ID'] ?? '');
    $employment = trim($_POST['employment_business'] ?? '');
    $pension    = $_POST['hasPension'] ?? null;
    $travel     = $_POST['travelCapability'] ?? null;
    // Address (now collected in Step 1)
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

    try {
        if (!empty($_SESSION['applicant_id'])) {
            $stmt = $pdo->prepare("UPDATE applicants SET
                lastnameApplicant=?, firstnameApplicant=?, middlenameApplicant=?, suffixApplicant=?,
                sex=?, month=?, date=?, year=?, birthplace=?, maritalStatus=?, religion=?,
                contactNumber=?, emailAddress=?, fbMessenger=?, ethnicOrigin=?, languageSpoken=?,
                osca_ID=?, gsis_sss_ID=?, tin_ID=?, philHealth_ID=?, sc_asso_ID=?,
                other_govt_ID=?, employment_business=?, hasPension=?, travelCapability=?,
                barangay=?, purok=?, street=?
                WHERE id=?");
            $stmt->execute([
                $lastname, $firstname, $middlename, $suffix,
                $sex, $month, $date, $year, $birthplace, $marital, $religion,
                $contact, $email, $fb, $ethnic, $language,
                $osca, $gsis, $tin, $philhealth, $sc_asso,
                $other_govt, $employment, $pension, $travel,
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
                barangay, purok, street, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'incomplete', " . ($custom_date ? '?' : 'NOW()') . ")");
            $exec_params = [
                $lastname, $firstname, $middlename, $suffix,
                $sex, $month, $date, $year, $birthplace, $marital, $religion,
                $contact, $email, $fb, $ethnic, $language,
                $osca, $gsis, $tin, $philhealth, $sc_asso,
                $other_govt, $employment, $pension, $travel,
                $barangay, $purok, $street
            ];
            if ($custom_date) $exec_params[] = $custom_date;
            $stmt->execute($exec_params);
            $id = $pdo->lastInsertId();
            $_SESSION['applicant_id'] = $id;
        }

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Step 1 saved successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'save_step2') {
    if (empty($_SESSION['applicant_id'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start again.']);
        exit;
    }

    $id = $_SESSION['applicant_id'];

    // Spouse
    $spouseLast   = trim($_POST['lastnameSpouse'] ?? '');
    $spouseFirst  = trim($_POST['firstnameSpouse'] ?? '');
    $spouseMiddle = trim($_POST['middlenameSpouse'] ?? '');
    $spouseSuffix = trim($_POST['suffixSpouse'] ?? '');

    // Father
    $fatherLast   = trim($_POST['lastnameFather'] ?? '');
    $fatherFirst  = trim($_POST['firstnameFather'] ?? '');
    $fatherMiddle = trim($_POST['middlenameFather'] ?? '');
    $fatherSuffix = trim($_POST['suffixFather'] ?? '');

    // Mother
    $motherLast   = trim($_POST['lastnameMother'] ?? '');
    $motherFirst  = trim($_POST['firstnameMother'] ?? '');
    $motherMiddle = trim($_POST['middlenameMother'] ?? '');
    $motherSuffix = trim($_POST['suffixMother'] ?? '');

    // Children
    $children = [];
    for ($i = 1; $i <= 5; $i++) {
        $children[$i] = [
            'fullname'   => trim($_POST["fullnameChild$i"] ?? ''),
            'occupation' => trim($_POST["occupationChild$i"] ?? ''),
            'income'     => $_POST["incomeChild$i"] ?? null,
            'age'        => $_POST["ageChild$i"] ?? null,
            'isWorking'  => $_POST["isWorkingChild$i"] ?? null,
        ];
    }

    // Dependents
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
            fullnameDependent2=?, occupationDependent2=?, incomeDependent2=?, ageDependent2=?, isWorkingDependent2=?,
            status='complete'
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

        unset($_SESSION['applicant_id']);
        echo json_encode(['success' => true, 'message' => 'Registration completed successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} elseif ($action === 'update_record') {
    // Requires admin session
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
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
    $gsis       = trim($_POST['gsis_sss_ID'] ?? '');
    $tin        = trim($_POST['tin_ID'] ?? '');
    $philhealth = trim($_POST['philHealth_ID'] ?? '');
    $sc_asso    = trim($_POST['sc_asso_ID'] ?? '');
    $other_govt = trim($_POST['other_govt_ID'] ?? '');
    $employment = trim($_POST['employment_business'] ?? '');
    $pension    = $_POST['hasPension'] ?? null;
    $travel     = $_POST['travelCapability'] ?? null;

    // Optional: override registration date (created_at)
    $reg_month      = trim($_POST['reg_month'] ?? '');
    $reg_day        = trim($_POST['reg_day'] ?? '');
    $reg_year       = trim($_POST['reg_year'] ?? '');
    $custom_created = null;
    if ($reg_month && $reg_day && $reg_year) {
        $parsed = DateTime::createFromFormat('F j Y', "$reg_month $reg_day $reg_year");
        if ($parsed && $parsed <= new DateTime()) {
            $custom_created = $parsed->format('Y-m-d H:i:s');
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

    $children = [];
    for ($i = 1; $i <= 5; $i++) {
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
        echo json_encode(['success' => true, 'message' => 'Record updated successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>