-- ============================================================
-- MediCare+ Hospital Management System
-- Database: medicare_plus
-- Normalized to 3NF | MySQL 8.0+
-- ============================================================

DROP DATABASE IF EXISTS medicare_plus;
CREATE DATABASE medicare_plus;
USE medicare_plus;

-- ============================================================
-- MEMBER 2 MODULE: DOCTOR & DEPARTMENT
-- (created first since other modules reference department/doctor)
-- ============================================================

CREATE TABLE Department (
    department_id   INT AUTO_INCREMENT PRIMARY KEY,
    dept_name       VARCHAR(100) NOT NULL UNIQUE,
    location        VARCHAR(100),
    phone           VARCHAR(20)
);

CREATE TABLE Doctor (
    doctor_id       INT AUTO_INCREMENT PRIMARY KEY,
    department_id   INT NOT NULL,
    name            VARCHAR(100) NOT NULL,
    specialization  VARCHAR(100),
    phone           VARCHAR(20),
    email           VARCHAR(100) UNIQUE,
    qualification   VARCHAR(100),
    joined_date     DATE,
    FOREIGN KEY (department_id) REFERENCES Department(department_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE Staff (
    staff_id        INT AUTO_INCREMENT PRIMARY KEY,
    department_id   INT,
    name            VARCHAR(100) NOT NULL,
    role            ENUM('Admin','Receptionist','Pharmacist','Cashier','Nurse','Other') NOT NULL,
    phone           VARCHAR(20),
    email           VARCHAR(100) UNIQUE,
    joined_date     DATE,
    FOREIGN KEY (department_id) REFERENCES Department(department_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Schedule (
    schedule_id     INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id       INT NOT NULL,
    day_of_week     ENUM('Mon','Tue','Wed','Thu','Fri','Sat','Sun') NOT NULL,
    start_time      TIME NOT NULL,
    end_time        TIME NOT NULL,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_schedule_time CHECK (end_time > start_time)
);

-- ============================================================
-- MEMBER 1 MODULE: PATIENT MANAGEMENT
-- ============================================================

CREATE TABLE Patient (
    patient_id      INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    dob             DATE NOT NULL,
    gender          ENUM('Male','Female','Other') NOT NULL,
    phone           VARCHAR(20) UNIQUE,
    email           VARCHAR(100),
    blood_group     VARCHAR(5),
    registered_on   DATE DEFAULT (CURRENT_DATE)
);

CREATE TABLE Address (
    address_id      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    street          VARCHAR(150),
    city            VARCHAR(50),
    state           VARCHAR(50),
    zip_code        VARCHAR(10),
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Emergency_Contact (
    contact_id      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    name            VARCHAR(100) NOT NULL,
    relationship    VARCHAR(50),
    phone           VARCHAR(20) NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Medical_Record (
    record_id           INT AUTO_INCREMENT PRIMARY KEY,
    patient_id          INT NOT NULL,
    allergies           TEXT,
    chronic_conditions  TEXT,
    notes               TEXT,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- MEMBER 3 MODULE: APPOINTMENT & TREATMENT
-- ============================================================

CREATE TABLE Room (
    room_id         INT AUTO_INCREMENT PRIMARY KEY,
    room_number     VARCHAR(10) NOT NULL UNIQUE,
    room_type       ENUM('General','Private','ICU','Operation Theatre') NOT NULL,
    floor           INT,
    status          ENUM('Available','Occupied','Maintenance') DEFAULT 'Available'
);

CREATE TABLE Appointment (
    appointment_id  INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    doctor_id       INT NOT NULL,
    appt_date       DATE NOT NULL,
    appt_time       TIME NOT NULL,
    status          ENUM('Scheduled','Completed','Cancelled','No-Show') DEFAULT 'Scheduled',
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    -- prevents the exact same doctor from being double-booked at the same slot
    CONSTRAINT uq_doctor_slot UNIQUE (doctor_id, appt_date, appt_time)
);

CREATE TABLE Admission (
    admission_id     INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    room_id          INT NOT NULL,
    doctor_id        INT NOT NULL,
    admission_date   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    discharge_date   DATETIME,
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (room_id) REFERENCES Room(room_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE Treatment (
    treatment_id     INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id   INT NOT NULL,
    diagnosis        TEXT NOT NULL,
    treatment_notes  TEXT,
    treatment_date   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES Appointment(appointment_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- MEMBER 4 MODULE: BILLING & PHARMACY
-- ============================================================

CREATE TABLE Medicine (
    medicine_id     INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    manufacturer    VARCHAR(100),
    unit_price      DECIMAL(10,2) NOT NULL CHECK (unit_price >= 0),
    category        VARCHAR(50)
);

CREATE TABLE Prescription (
    prescription_id  INT AUTO_INCREMENT PRIMARY KEY,
    treatment_id     INT NOT NULL,
    medicine_id      INT NOT NULL,
    dosage           VARCHAR(50),
    duration_days    INT,
    quantity         INT NOT NULL CHECK (quantity > 0),
    FOREIGN KEY (treatment_id) REFERENCES Treatment(treatment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES Medicine(medicine_id)
        ON DELETE RESTRICT ON UPDATE CASCADE
);

CREATE TABLE Inventory (
    inventory_id      INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id       INT NOT NULL UNIQUE,
    quantity_in_stock INT NOT NULL DEFAULT 0 CHECK (quantity_in_stock >= 0),
    reorder_level     INT DEFAULT 20,
    expiry_date       DATE,
    FOREIGN KEY (medicine_id) REFERENCES Medicine(medicine_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE Bill (
    bill_id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    appointment_id  INT,
    admission_id    INT,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0 CHECK (total_amount >= 0),
    bill_date       DATETIME DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('Unpaid','Partially Paid','Paid') DEFAULT 'Unpaid',
    FOREIGN KEY (patient_id) REFERENCES Patient(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES Appointment(appointment_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (admission_id) REFERENCES Admission(admission_id)
        ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE TABLE Payment (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    bill_id         INT NOT NULL,
    amount_paid     DECIMAL(10,2) NOT NULL CHECK (amount_paid > 0),
    payment_date    DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method  ENUM('Cash','Card','UPI','Insurance') NOT NULL,
    FOREIGN KEY (bill_id) REFERENCES Bill(bill_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- SHARED: USER LOGIN (role-based access, links to Staff or Doctor)
-- ============================================================

CREATE TABLE User_Login (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('Admin','Doctor','Receptionist','Pharmacist','Cashier','Nurse') NOT NULL,
    staff_id        INT UNIQUE,
    doctor_id       INT UNIQUE,
    last_login      DATETIME,
    FOREIGN KEY (staff_id) REFERENCES Staff(staff_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES Doctor(doctor_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- a login must belong to exactly one of Staff or Doctor, not both/neither
    CONSTRAINT chk_login_owner CHECK (
        (staff_id IS NOT NULL AND doctor_id IS NULL) OR
        (staff_id IS NULL AND doctor_id IS NOT NULL)
    )
);

-- ============================================================
-- INDEXES for common lookups/joins
-- ============================================================
CREATE INDEX idx_appointment_patient ON Appointment(patient_id);
CREATE INDEX idx_appointment_doctor  ON Appointment(doctor_id);
CREATE INDEX idx_bill_patient        ON Bill(patient_id);
CREATE INDEX idx_prescription_med    ON Prescription(medicine_id);
CREATE INDEX idx_admission_patient   ON Admission(patient_id);

-- ============================================================
-- END OF SCHEMA
-- ============================================================
