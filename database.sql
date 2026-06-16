-- Create main applicants table
CREATE TABLE IF NOT EXISTS applicants (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- STEP 1: IDENTIFYING INFORMATION
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

    -- STEP 2: FAMILY COMPOSITION
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

    -- Record info
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);