-- Create main applicants table
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- ─────────────────────────────────────────
    -- STEP 1: IDENTIFYING INFORMATION
    -- ─────────────────────────────────────────

    -- Name
    lastnameApplicant VARCHAR(100) NOT NULL,
    firstnameApplicant VARCHAR(100) NOT NULL,
    middlenameApplicant VARCHAR(100) NOT NULL,
    suffixApplicant ENUM('N/A','JR','SR','I','II','III','IV','V','VI') DEFAULT NULL,

    -- Address
    barangay VARCHAR(100) NULL,
    purok VARCHAR(100) NULL,
    street VARCHAR(100) NULL,

    -- Birthdate
    month ENUM('January','February','March','April','May','June','July','August','September','October','November','December') NULL,
    date ENUM('1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20','21','22','23','24','25','26','27','28','29','30','31') NULL,
    year INT(4) NULL,
    birthplace VARCHAR(255) NULL,

    -- Additional Info
    maritalStatus ENUM('Single','Married','Widowed','Separated') NULL,
    religion ENUM('Catholic','Islam','Iglesia ni Cristo','Evangelicals','Protestants','Seventh-day Adventist','Bible Baptist','Church','Aglipayan','UCCP','Jehovah''s Witnesses','Others') NULL,
    sex ENUM('Male','Female') NULL,
    contactNumber VARCHAR(20) NULL,
    emailAddress VARCHAR(255) NULL,
    fbMessenger VARCHAR(255) NULL,
    ethnicOrigin VARCHAR(100) NULL,
    languageSpoken VARCHAR(255) NULL,
    osca_ID VARCHAR(50) NULL,
    gsis_sss_ID VARCHAR(50) NULL,
    tin_ID VARCHAR(50) NULL,
    philHealth_ID VARCHAR(50) NULL,
    sc_asso_ID VARCHAR(50) NULL,
    other_govt_ID VARCHAR(100) NULL,
    employment_business VARCHAR(255) NULL,
    hasPension ENUM('Yes','No') NULL,
    travelCapability ENUM('Yes','No') NULL,
    personWithDisability ENUM('Yes','No') NULL,

    -- ─────────────────────────────────────────
    -- STEP 2: FAMILY COMPOSITION
    -- ─────────────────────────────────────────

    -- Spouse
    lastnameSpouse VARCHAR(100) NULL,
    firstnameSpouse VARCHAR(100) NULL,
    middlenameSpouse VARCHAR(100) NULL,
    suffixSpouse VARCHAR(20) NULL,

    -- Mother
    lastnameMother VARCHAR(100) NULL,
    firstnameMother VARCHAR(100) NULL,
    middlenameMother VARCHAR(100) NULL,
    suffixMother VARCHAR(20) NULL,

    -- Father
    lastnameFather VARCHAR(100) NULL,
    firstnameFather VARCHAR(100) NULL,
    middlenameFather VARCHAR(100) NULL,
    suffixFather VARCHAR(20) NULL,

    -- Children (max 5)
    fullnameChild1 VARCHAR(255) NULL,
    occupationChild1 VARCHAR(255) NULL,
    incomeChild1 DECIMAL(10,2) NULL,
    ageChild1 INT(3) NULL,
    isWorkingChild1 ENUM('Yes','No') NULL,

    fullnameChild2 VARCHAR(255) NULL,
    occupationChild2 VARCHAR(255) NULL,
    incomeChild2 DECIMAL(10,2) NULL,
    ageChild2 INT(3) NULL,
    isWorkingChild2 ENUM('Yes','No') NULL,

    fullnameChild3 VARCHAR(255) NULL,
    occupationChild3 VARCHAR(255) NULL,
    incomeChild3 DECIMAL(10,2) NULL,
    ageChild3 INT(3) NULL,
    isWorkingChild3 ENUM('Yes','No') NULL,

    fullnameChild4 VARCHAR(255) NULL,
    occupationChild4 VARCHAR(255) NULL,
    incomeChild4 DECIMAL(10,2) NULL,
    ageChild4 INT(3) NULL,
    isWorkingChild4 ENUM('Yes','No') NULL,

    fullnameChild5 VARCHAR(255) NULL,
    occupationChild5 VARCHAR(255) NULL,
    incomeChild5 DECIMAL(10,2) NULL,
    ageChild5 INT(3) NULL,
    isWorkingChild5 ENUM('Yes','No') NULL,

    -- Dependents (max 2)
    fullnameDependent1 VARCHAR(255) NULL,
    occupationDependent1 VARCHAR(255) NULL,
    incomeDependent1 DECIMAL(10,2) NULL,
    ageDependent1 INT(3) NULL,
    isWorkingDependent1 ENUM('Yes','No') NULL,

    fullnameDependent2 VARCHAR(255) NULL,
    occupationDependent2 VARCHAR(255) NULL,
    incomeDependent2 DECIMAL(10,2) NULL,
    ageDependent2 INT(3) NULL,
    isWorkingDependent2 ENUM('Yes','No') NULL,

    -- ─────────────────────────────────────────
    -- STEP 3: LIVING SITUATION
    -- ─────────────────────────────────────────

    -- Q25: Living Alone or Living With (radio)
    livingAlone ENUM('Yes','No') NULL,

    -- Q25: Who they live with (comma-separated multi-select)
    -- e.g. "Spouse,Children,Relatives"
    livingWith VARCHAR(255) NULL,

    -- Q25: Others specify (free text if "Others" checked)
    livingWithOthers VARCHAR(255) NULL,

    -- Q26: Living Condition (comma-separated multi-select)
    -- e.g. "No privacy,Informal Settler"
    livingCondition VARCHAR(255) NULL,

    -- Q26: Others specify (free text if "Others" checked)
    livingConditionOthers VARCHAR(255) NULL,

    -- ─────────────────────────────────────────
    -- STEP 4: EDUCATION / HR PROFILE
    -- ─────────────────────────────────────────

    -- Q27: Highest Educational Attainment (radio)
    -- Options: Not Attended School | Elementary Level | Elementary Graduate |
    --          High School Level | High School Graduate | Vocational |
    --          College Level | College Graduate | Post Graduate | Others
    educationHighest ENUM(
        'Not Attended School',
        'Elementary Level',
        'Elementary Graduate',
        'High School Level',
        'High School Graduate',
        'Vocational',
        'College Level',
        'College Graduate',
        'Post Graduate',
        'Others'
    ) NULL,

    -- Q27: Others specify (free text if "Others" selected)
    educationHighestOthers VARCHAR(255) NULL,

    -- Q28: Specialization / Technical Skills (comma-separated multi-select)
    -- e.g. "Medical,Teaching,Cooking"
    skills VARCHAR(500) NULL,

    -- Q28: Others specify (free text if "Others" checked)
    skillsOthers VARCHAR(255) NULL,

    -- Q29: Shared Skills (longtext, comma-separated free entry)
    sharedSkills TEXT NULL,

    -- Q30: Involvement in Community Activities (comma-separated multi-select)
    -- e.g. "Medical,Religious,Sponsorship"
    communityInvolvement VARCHAR(500) NULL,

    -- Q30: Others specify (free text if "Others" checked)
    communityInvolvementOthers VARCHAR(255) NULL,

    -- ─────────────────────────────────────────
    -- STEP 5: ECONOMIC PROFILE
    -- ─────────────────────────────────────────

    -- Q31: Source of Income and Assistance (comma-separated multi-select)
    -- e.g. "Own Pension,Savings,Fishing"
    sourceIncome VARCHAR(500) NULL,

    -- Q31: Others specify
    sourceIncomeOthers VARCHAR(255) NULL,

    -- Q32a: Assets – Real and Immovable Properties (comma-separated multi-select)
    -- e.g. "House,Lot / Farmland"
    assetsReal VARCHAR(500) NULL,

    -- Q32a: Others specify
    assetsRealOthers VARCHAR(255) NULL,

    -- Q32b: Assets – Personal and Movable Properties (comma-separated multi-select)
    -- e.g. "Automobile,Laptops,Mobile phones"
    assetsPersonal VARCHAR(500) NULL,

    -- Q32b: Others specify
    assetsPersonalOthers VARCHAR(255) NULL,

    -- Q33: Monthly Income (single selection / radio)
    -- Options: 60k and above | 50k to 60k | 40k to 50k | 30k to 40k |
    --          20k to 30k | 10k to 20k | 5k to 10k | below 5k | None
    incomeMonthly ENUM(
        '60k and above',
        '50k to 60k',
        '40k to 50k',
        '30k to 40k',
        '20k to 30k',
        '10k to 20k',
        '5k to 10k',
        'below 5k',
        'None'
    ) NULL,

    -- Q34: Problems / Needs Commonly Encountered (comma-separated multi-select)
    -- e.g. "Lack of income / resources,Skills / capability training"
    problemsNeeds VARCHAR(500) NULL,

    -- Q34: Livelihood Opportunities specify (free text)
    problemsNeedsLivelihood VARCHAR(255) NULL,

    -- Q34: Others specify (free text)
    problemsNeedsOthers VARCHAR(255) NULL,

    -- ─────────────────────────────────────────
    -- STEP 6: HEALTH PROFILE
    -- ─────────────────────────────────────────

    -- Q35a: Blood Type (single selection)
    bloodType ENUM('O','O+','O-','A','A+','A-','B','B+','B-','AB','AB+','AB-','Unknown') NULL,

    -- Q35a: Physical Disability (free text)
    physicalDisability TEXT NULL,

    -- Q35a: Health Problems (comma-separated multi-select)
    healthProblems TEXT NULL,

    -- Q35a: Health Problems Others specify
    healthProblemsOthers TEXT NULL,

    -- Q35b: Dental Concern (comma-separated checkbox)
    dentalConcern TEXT NULL,

    -- Q35b: Dental Concern Others specify
    dentalConcernOthers TEXT NULL,

    -- Q35c: Visual Concern (comma-separated multi-select)
    visualConcern TEXT NULL,

    -- Q35c: Visual Concern Others specify
    visualConcernOthers TEXT NULL,

    -- Q35d: Aural / Hearing Concern (comma-separated checkbox)
    auralConcern TEXT NULL,

    -- Q35d: Aural Concern Others specify
    auralConcernOthers TEXT NULL,

    -- Q35e: Social / Emotional Concern (comma-separated multi-select)
    socialConcern TEXT NULL,

    -- Q35e: Social Concern Others specify
    socialConcernOthers TEXT NULL,

    -- Q35f: Area of Difficulty (comma-separated multi-select)
    areaDifficulty TEXT NULL,

    -- Q35f: Area of Difficulty Others specify
    areaDifficultyOthers TEXT NULL,

    -- Q36: List of Medicines for Maintenance (long text, comma-separated)
    listOfMedicines TEXT NULL,

    -- Q37: Scheduled Checkup (Yes/No)
    scheduledCheckup ENUM('Yes','No') NULL,

    -- Q37a: Scheduled Checkup Frequency (shown only if scheduledCheckup = Yes)
    scheduledCheckupYes ENUM('Monthly','Every 3 months','Every 6 months','Annually') NULL,

    -- ─────────────────────────────────────────
    -- STEP 7: ID / PHOTO UPLOAD
    -- ─────────────────────────────────────────

    -- Q38: OSCA ID photo (max 50mb)
    oscaID MEDIUMBLOB NULL,

    -- Q38: OSCA ID file mime type (for serving the image)
    oscaID_type VARCHAR(50) NULL,

    -- Q39: Latest 2x2 photo capture (max 50mb)
    photoLatest MEDIUMBLOB NULL,

    -- Q39: Photo mime type (for serving the image)
    photoLatest_type VARCHAR(50) NULL,

    -- ─────────────────────────────────────────
    -- RECORD INFO
    -- ─────────────────────────────────────────
    -- ─────────────────────────────────────────
    -- STATUS
    -- ─────────────────────────────────────────
    status ENUM('incomplete','complete') DEFAULT 'incomplete',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- ─────────────────────────────────────────────────────────────────────────────
-- IF TABLE ALREADY EXISTS — run these ALTER statements instead
-- Copy only the blocks for columns you haven't added yet
-- ─────────────────────────────────────────────────────────────────────────────

-- STEP 1 columns (added later)
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS personWithDisability     ENUM('Yes','No')  NULL AFTER travelCapability;

-- STEP 3 columns
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS livingAlone              ENUM('Yes','No')  NULL AFTER isWorkingDependent2,
    ADD COLUMN IF NOT EXISTS livingWith               VARCHAR(255)      NULL AFTER livingAlone,
    ADD COLUMN IF NOT EXISTS livingWithOthers         VARCHAR(255)      NULL AFTER livingWith,
    ADD COLUMN IF NOT EXISTS livingCondition          VARCHAR(255)      NULL AFTER livingWithOthers,
    ADD COLUMN IF NOT EXISTS livingConditionOthers    VARCHAR(255)      NULL AFTER livingCondition;

-- STEP 4 columns
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS educationHighest         ENUM('Not Attended School','Elementary Level','Elementary Graduate','High School Level','High School Graduate','Vocational','College Level','College Graduate','Post Graduate','Others') NULL AFTER livingConditionOthers,
    ADD COLUMN IF NOT EXISTS educationHighestOthers   VARCHAR(255)      NULL AFTER educationHighest,
    ADD COLUMN IF NOT EXISTS skills                   VARCHAR(500)      NULL AFTER educationHighestOthers,
    ADD COLUMN IF NOT EXISTS skillsOthers             VARCHAR(255)      NULL AFTER skills,
    ADD COLUMN IF NOT EXISTS sharedSkills             TEXT              NULL AFTER skillsOthers,
    ADD COLUMN IF NOT EXISTS communityInvolvement     VARCHAR(500)      NULL AFTER sharedSkills,
    ADD COLUMN IF NOT EXISTS communityInvolvementOthers VARCHAR(255)    NULL AFTER communityInvolvement;

-- STEP 5 columns
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS sourceIncome              VARCHAR(500)      NULL AFTER communityInvolvementOthers,
    ADD COLUMN IF NOT EXISTS sourceIncomeOthers        VARCHAR(255)      NULL AFTER sourceIncome,
    ADD COLUMN IF NOT EXISTS assetsReal                VARCHAR(500)      NULL AFTER sourceIncomeOthers,
    ADD COLUMN IF NOT EXISTS assetsRealOthers          VARCHAR(255)      NULL AFTER assetsReal,
    ADD COLUMN IF NOT EXISTS assetsPersonal            VARCHAR(500)      NULL AFTER assetsRealOthers,
    ADD COLUMN IF NOT EXISTS assetsPersonalOthers      VARCHAR(255)      NULL AFTER assetsPersonal,
    ADD COLUMN IF NOT EXISTS incomeMonthly             ENUM('60k and above','50k to 60k','40k to 50k','30k to 40k','20k to 30k','10k to 20k','5k to 10k','below 5k','None') NULL AFTER assetsPersonalOthers,
    ADD COLUMN IF NOT EXISTS problemsNeeds             VARCHAR(500)      NULL AFTER incomeMonthly,
    ADD COLUMN IF NOT EXISTS problemsNeedsLivelihood   VARCHAR(255)      NULL AFTER problemsNeeds,
    ADD COLUMN IF NOT EXISTS problemsNeedsOthers       VARCHAR(255)      NULL AFTER problemsNeedsLivelihood;

-- STEP 6 columns
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS bloodType                 ENUM('O','O+','O-','A','A+','A-','B','B+','B-','AB','AB+','AB-','Unknown') NULL AFTER problemsNeedsOthers,
    ADD COLUMN IF NOT EXISTS physicalDisability        TEXT              NULL AFTER bloodType,
    ADD COLUMN IF NOT EXISTS healthProblems            TEXT              NULL AFTER physicalDisability,
    ADD COLUMN IF NOT EXISTS healthProblemsOthers      TEXT              NULL AFTER healthProblems,
    ADD COLUMN IF NOT EXISTS dentalConcern             TEXT              NULL AFTER healthProblemsOthers,
    ADD COLUMN IF NOT EXISTS dentalConcernOthers       TEXT              NULL AFTER dentalConcern,
    ADD COLUMN IF NOT EXISTS visualConcern             TEXT              NULL AFTER dentalConcernOthers,
    ADD COLUMN IF NOT EXISTS visualConcernOthers       TEXT              NULL AFTER visualConcern,
    ADD COLUMN IF NOT EXISTS auralConcern              TEXT              NULL AFTER visualConcernOthers,
    ADD COLUMN IF NOT EXISTS auralConcernOthers        TEXT              NULL AFTER auralConcern,
    ADD COLUMN IF NOT EXISTS socialConcern             TEXT              NULL AFTER auralConcernOthers,
    ADD COLUMN IF NOT EXISTS socialConcernOthers       TEXT              NULL AFTER socialConcern,
    ADD COLUMN IF NOT EXISTS areaDifficulty            TEXT              NULL AFTER socialConcernOthers,
    ADD COLUMN IF NOT EXISTS areaDifficultyOthers      TEXT              NULL AFTER areaDifficulty,
    ADD COLUMN IF NOT EXISTS listOfMedicines           TEXT              NULL AFTER areaDifficultyOthers,
    ADD COLUMN IF NOT EXISTS scheduledCheckup          ENUM('Yes','No')  NULL AFTER listOfMedicines,
    ADD COLUMN IF NOT EXISTS scheduledCheckupYes       ENUM('Monthly','Every 3 months','Every 6 months','Annually') NULL AFTER scheduledCheckup;

-- STEP 7 columns
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS oscaID                    MEDIUMBLOB        NULL AFTER scheduledCheckupYes,
    ADD COLUMN IF NOT EXISTS oscaID_type               VARCHAR(50)       NULL AFTER oscaID,
    ADD COLUMN IF NOT EXISTS photoLatest               MEDIUMBLOB        NULL AFTER oscaID_type,
    ADD COLUMN IF NOT EXISTS photoLatest_type          VARCHAR(50)       NULL AFTER photoLatest;
-- STATUS column (if table already exists)
ALTER TABLE applicants
    ADD COLUMN IF NOT EXISTS status ENUM('incomplete','complete') DEFAULT 'incomplete' AFTER isWorkingDependent2;