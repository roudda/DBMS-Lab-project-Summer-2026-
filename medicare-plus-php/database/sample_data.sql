-- ============================================================
-- MediCare+ — Sample/Demo Data
-- Populates all 18 tables with realistic rows for demos and
-- screenshots. Safe to run after the schema (and optionally
-- after seed.php) — every INSERT looks up IDs by a unique name/
-- code rather than assuming specific auto-increment numbers, so
-- it won't collide with whatever's already in the database.
--
-- Run this via phpMyAdmin: select the medicare_plus database ->
-- Import -> choose this file -> Go. Or from a terminal:
--   mysql -u root -p medicare_plus < database/sample_data.sql
-- ============================================================

USE medicare_plus;

-- ============================================================
-- 1. DEPARTMENT
-- ============================================================
INSERT INTO Department (dept_name, location, phone) VALUES
  ('Cardiology',   'Block B, 2nd Floor', '02-9991235'),
  ('Orthopedics',  'Block B, 3rd Floor', '02-9991236'),
  ('Pediatrics',   'Block C, 1st Floor', '02-9991237')
ON DUPLICATE KEY UPDATE dept_name = dept_name;

-- ============================================================
-- 2. DOCTOR
-- ============================================================
INSERT INTO Doctor (department_id, name, specialization, phone, email, qualification, joined_date)
SELECT department_id, 'Dr. Farhan Kabir', 'Cardiologist', '01700000010', 'farhan.kabir@medicareplus.test', 'MBBS, MD (Cardiology)', '2021-03-10'
FROM Department WHERE dept_name = 'Cardiology'
UNION ALL
SELECT department_id, 'Dr. Nusrat Jahan', 'Orthopedic Surgeon', '01700000011', 'nusrat.jahan@medicareplus.test', 'MBBS, MS (Ortho)', '2020-07-22'
FROM Department WHERE dept_name = 'Orthopedics'
UNION ALL
SELECT department_id, 'Dr. Imran Chowdhury', 'Pediatrician', '01700000012', 'imran.chowdhury@medicareplus.test', 'MBBS, DCH', '2022-01-15'
FROM Department WHERE dept_name = 'Pediatrics';

-- ============================================================
-- 3. STAFF (extra, beyond the demo accounts seed.php already made)
-- ============================================================
INSERT INTO Staff (department_id, name, role, phone, email, joined_date)
SELECT department_id, 'Rina Akter', 'Nurse', '01700000020', 'rina.akter@medicareplus.test', '2021-09-01'
FROM Department WHERE dept_name = 'Cardiology'
UNION ALL
SELECT department_id, 'Sabbir Hossain', 'Nurse', '01700000021', 'sabbir.hossain@medicareplus.test', '2022-04-18'
FROM Department WHERE dept_name = 'Orthopedics';

-- ============================================================
-- 4. SCHEDULE
-- ============================================================
INSERT INTO Schedule (doctor_id, day_of_week, start_time, end_time)
SELECT doctor_id, 'Mon', '09:00:00', '13:00:00' FROM Doctor WHERE email = 'farhan.kabir@medicareplus.test'
UNION ALL
SELECT doctor_id, 'Wed', '09:00:00', '13:00:00' FROM Doctor WHERE email = 'farhan.kabir@medicareplus.test'
UNION ALL
SELECT doctor_id, 'Tue', '14:00:00', '18:00:00' FROM Doctor WHERE email = 'nusrat.jahan@medicareplus.test'
UNION ALL
SELECT doctor_id, 'Thu', '14:00:00', '18:00:00' FROM Doctor WHERE email = 'nusrat.jahan@medicareplus.test'
UNION ALL
SELECT doctor_id, 'Fri', '10:00:00', '14:00:00' FROM Doctor WHERE email = 'imran.chowdhury@medicareplus.test';

-- ============================================================
-- 5. PATIENT
-- ============================================================
INSERT INTO Patient (name, dob, gender, phone, email, blood_group) VALUES
  ('Tanvir Ahmed',    '1988-02-14', 'Male',   '01811111111', 'tanvir.ahmed@example.com',    'B+'),
  ('Mahia Islam',     '1995-07-30', 'Female', '01811111112', 'mahia.islam@example.com',     'A+'),
  ('Kamrul Hasan',    '1975-11-05', 'Male',   '01811111113', 'kamrul.hasan@example.com',    'O-'),
  ('Shirin Sultana',  '2001-01-20', 'Female', '01811111114', 'shirin.sultana@example.com',  'AB+'),
  ('Rakibul Islam',   '2016-09-12', 'Male',   '01811111115', 'rakibul.islam.guardian@example.com', 'O+')
ON DUPLICATE KEY UPDATE name = name;

-- ============================================================
-- 6. ADDRESS
-- ============================================================
INSERT INTO Address (patient_id, street, city, state, zip_code)
SELECT patient_id, '12 Gulshan Avenue', 'Dhaka', 'Dhaka Division', '1212' FROM Patient WHERE phone = '01811111111'
UNION ALL
SELECT patient_id, '45 Dhanmondi Road 7', 'Dhaka', 'Dhaka Division', '1209' FROM Patient WHERE phone = '01811111112'
UNION ALL
SELECT patient_id, '8 Agrabad Commercial Area', 'Chattogram', 'Chattogram Division', '4100' FROM Patient WHERE phone = '01811111113'
UNION ALL
SELECT patient_id, '21 Uposhohor', 'Sylhet', 'Sylhet Division', '3100' FROM Patient WHERE phone = '01811111114'
UNION ALL
SELECT patient_id, '3 Shaheb Bazar', 'Rajshahi', 'Rajshahi Division', '6000' FROM Patient WHERE phone = '01811111115';

-- ============================================================
-- 7. EMERGENCY_CONTACT
-- ============================================================
INSERT INTO Emergency_Contact (patient_id, name, relationship, phone)
SELECT patient_id, 'Sumaiya Ahmed', 'Spouse', '01822222221' FROM Patient WHERE phone = '01811111111'
UNION ALL
SELECT patient_id, 'Rafiqul Islam', 'Father', '01822222222' FROM Patient WHERE phone = '01811111112'
UNION ALL
SELECT patient_id, 'Farida Hasan', 'Spouse', '01822222223' FROM Patient WHERE phone = '01811111113'
UNION ALL
SELECT patient_id, 'Abdus Sultan', 'Father', '01822222224' FROM Patient WHERE phone = '01811111114'
UNION ALL
SELECT patient_id, 'Nasrin Islam', 'Mother', '01822222225' FROM Patient WHERE phone = '01811111115';

-- ============================================================
-- 8. MEDICAL_RECORD
-- ============================================================
INSERT INTO Medical_Record (patient_id, allergies, chronic_conditions, notes)
SELECT patient_id, 'Penicillin', 'Hypertension', 'Monitor blood pressure at every visit.' FROM Patient WHERE phone = '01811111111'
UNION ALL
SELECT patient_id, 'None known', 'None', 'No significant history.' FROM Patient WHERE phone = '01811111112'
UNION ALL
SELECT patient_id, 'Sulfa drugs', 'Type 2 Diabetes', 'On regular oral medication.' FROM Patient WHERE phone = '01811111113'
UNION ALL
SELECT patient_id, 'None known', 'Asthma (mild)', 'Carries inhaler.' FROM Patient WHERE phone = '01811111114'
UNION ALL
SELECT patient_id, 'None known', 'None', 'Pediatric patient, routine checkups only.' FROM Patient WHERE phone = '01811111115';

-- ============================================================
-- 9. ROOM
-- ============================================================
INSERT INTO Room (room_number, room_type, floor, status) VALUES
  ('101', 'General', 1, 'Available'),
  ('102', 'General', 1, 'Available'),
  ('201', 'Private', 2, 'Occupied'),
  ('301', 'ICU', 3, 'Available'),
  ('OT-1', 'Operation Theatre', 1, 'Available')
ON DUPLICATE KEY UPDATE room_number = room_number;

-- ============================================================
-- 10. APPOINTMENT
-- ============================================================
INSERT INTO Appointment (patient_id, doctor_id, appt_date, appt_time, status)
SELECT p.patient_id, d.doctor_id, '2026-10-05', '09:30:00', 'Scheduled'
FROM Patient p, Doctor d WHERE p.phone = '01811111111' AND d.email = 'farhan.kabir@medicareplus.test'
UNION ALL
SELECT p.patient_id, d.doctor_id, '2026-10-06', '14:30:00', 'Scheduled'
FROM Patient p, Doctor d WHERE p.phone = '01811111113' AND d.email = 'nusrat.jahan@medicareplus.test'
UNION ALL
SELECT p.patient_id, d.doctor_id, '2026-10-07', '10:15:00', 'Completed'
FROM Patient p, Doctor d WHERE p.phone = '01811111115' AND d.email = 'imran.chowdhury@medicareplus.test'
UNION ALL
SELECT p.patient_id, d.doctor_id, '2026-09-28', '09:45:00', 'Completed'
FROM Patient p, Doctor d WHERE p.phone = '01811111112' AND d.email = 'farhan.kabir@medicareplus.test'
UNION ALL
SELECT p.patient_id, d.doctor_id, '2026-09-30', '11:00:00', 'Cancelled'
FROM Patient p, Doctor d WHERE p.phone = '01811111114' AND d.email = 'nusrat.jahan@medicareplus.test';

-- ============================================================
-- 11. ADMISSION
-- ============================================================
INSERT INTO Admission (patient_id, room_id, doctor_id, admission_date, discharge_date)
SELECT p.patient_id, r.room_id, d.doctor_id, '2026-09-15 08:00:00', '2026-09-18 11:00:00'
FROM Patient p, Room r, Doctor d
WHERE p.phone = '01811111113' AND r.room_number = '201' AND d.email = 'nusrat.jahan@medicareplus.test'
UNION ALL
SELECT p.patient_id, r.room_id, d.doctor_id, '2026-09-20 10:00:00', NULL
FROM Patient p, Room r, Doctor d
WHERE p.phone = '01811111111' AND r.room_number = '301' AND d.email = 'farhan.kabir@medicareplus.test';

-- ============================================================
-- 12. TREATMENT (only for Completed appointments)
-- ============================================================
INSERT INTO Treatment (appointment_id, diagnosis, treatment_notes, treatment_date)
SELECT a.appointment_id, 'Seasonal flu', 'Prescribed rest and fluids, follow-up if symptoms persist beyond 5 days.', '2026-10-07 10:30:00'
FROM Appointment a JOIN Patient p ON a.patient_id = p.patient_id
WHERE p.phone = '01811111115' AND a.appt_date = '2026-10-07'
UNION ALL
SELECT a.appointment_id, 'Mild hypertension', 'Advised lifestyle changes and low-sodium diet; prescribed medication.', '2026-09-28 10:00:00'
FROM Appointment a JOIN Patient p ON a.patient_id = p.patient_id
WHERE p.phone = '01811111112' AND a.appt_date = '2026-09-28';

-- ============================================================
-- 13. MEDICINE
-- ============================================================
INSERT INTO Medicine (name, manufacturer, unit_price, category) VALUES
  ('Paracetamol 500mg',   'Square Pharmaceuticals', 1.50,  'Analgesic'),
  ('Amoxicillin 250mg',   'Beximco Pharma',         3.20,  'Antibiotic'),
  ('Losartan 50mg',       'Incepta Pharmaceuticals',4.00,  'Antihypertensive'),
  ('Cetirizine 10mg',     'ACI Limited',            2.10,  'Antihistamine'),
  ('Metformin 500mg',     'Square Pharmaceuticals', 2.80,  'Antidiabetic')
ON DUPLICATE KEY UPDATE name = name;

-- ============================================================
-- 14. INVENTORY
-- ============================================================
INSERT INTO Inventory (medicine_id, quantity_in_stock, reorder_level, expiry_date)
SELECT medicine_id, 500, 100, '2027-06-30' FROM Medicine WHERE name = 'Paracetamol 500mg'
UNION ALL
SELECT medicine_id, 15,  50,  '2027-03-31' FROM Medicine WHERE name = 'Amoxicillin 250mg'
UNION ALL
SELECT medicine_id, 200, 40,  '2027-08-15' FROM Medicine WHERE name = 'Losartan 50mg'
UNION ALL
SELECT medicine_id, 8,   30,  '2026-12-31' FROM Medicine WHERE name = 'Cetirizine 10mg'
UNION ALL
SELECT medicine_id, 120, 40,  '2027-05-20' FROM Medicine WHERE name = 'Metformin 500mg'
ON DUPLICATE KEY UPDATE quantity_in_stock = VALUES(quantity_in_stock);
-- Note: Amoxicillin and Cetirizine are seeded below their reorder_level
-- on purpose, so /api/inventory/low-stock has something to show.

-- ============================================================
-- 15. PRESCRIPTION (linked to the two Treatment rows above)
-- ============================================================
INSERT INTO Prescription (treatment_id, medicine_id, dosage, duration_days, quantity)
SELECT t.treatment_id, m.medicine_id, '1 tablet every 6 hours', 5, 20
FROM Treatment t JOIN Appointment a ON t.appointment_id = a.appointment_id
JOIN Patient p ON a.patient_id = p.patient_id, Medicine m
WHERE p.phone = '01811111115' AND m.name = 'Paracetamol 500mg'
UNION ALL
SELECT t.treatment_id, m.medicine_id, '1 tablet daily', 30, 30
FROM Treatment t JOIN Appointment a ON t.appointment_id = a.appointment_id
JOIN Patient p ON a.patient_id = p.patient_id, Medicine m
WHERE p.phone = '01811111112' AND m.name = 'Losartan 50mg';

-- ============================================================
-- 16. BILL
-- ============================================================
INSERT INTO Bill (patient_id, appointment_id, admission_id, total_amount, status)
SELECT p.patient_id, a.appointment_id, NULL, 650.00, 'Paid'
FROM Patient p JOIN Appointment a ON a.patient_id = p.patient_id
WHERE p.phone = '01811111115' AND a.appt_date = '2026-10-07'
UNION ALL
SELECT p.patient_id, a.appointment_id, NULL, 900.00, 'Partially Paid'
FROM Patient p JOIN Appointment a ON a.patient_id = p.patient_id
WHERE p.phone = '01811111112' AND a.appt_date = '2026-09-28'
UNION ALL
SELECT p.patient_id, NULL, ad.admission_id, 25000.00, 'Unpaid'
FROM Patient p JOIN Admission ad ON ad.patient_id = p.patient_id
WHERE p.phone = '01811111113';

-- ============================================================
-- 17. PAYMENT
-- ============================================================
INSERT INTO Payment (bill_id, amount_paid, payment_method)
SELECT b.bill_id, 650.00, 'Card'
FROM Bill b JOIN Patient p ON b.patient_id = p.patient_id
WHERE p.phone = '01811111115' AND b.total_amount = 650.00
UNION ALL
SELECT b.bill_id, 500.00, 'Cash'
FROM Bill b JOIN Patient p ON b.patient_id = p.patient_id
WHERE p.phone = '01811111112' AND b.total_amount = 900.00;

-- ============================================================
-- 18. USER_LOGIN (extra doctor logins for the doctors added above)
-- Password for all of these is also "demo1234" — same bcrypt hash
-- that backend/seed/seed.php uses, so they work with either backend.
-- ============================================================
INSERT INTO User_Login (username, password_hash, role, doctor_id)
SELECT 'dr.farhan', '$2y$10$aP/jkqfiwqcDWwMnAC1KEe2ONhym.wTUsHsoUFuDYCd2G.QfqIqCu', 'Doctor', doctor_id
FROM Doctor WHERE email = 'farhan.kabir@medicareplus.test'
UNION ALL
SELECT 'dr.nusrat', '$2y$10$aP/jkqfiwqcDWwMnAC1KEe2ONhym.wTUsHsoUFuDYCd2G.QfqIqCu', 'Doctor', doctor_id
FROM Doctor WHERE email = 'nusrat.jahan@medicareplus.test'
ON DUPLICATE KEY UPDATE username = username;

-- ============================================================
-- Done. Quick sanity check — row counts per table:
-- ============================================================
SELECT 'Department' AS table_name, COUNT(*) AS row_count FROM Department
UNION ALL SELECT 'Doctor', COUNT(*) FROM Doctor
UNION ALL SELECT 'Staff', COUNT(*) FROM Staff
UNION ALL SELECT 'Schedule', COUNT(*) FROM Schedule
UNION ALL SELECT 'Patient', COUNT(*) FROM Patient
UNION ALL SELECT 'Address', COUNT(*) FROM Address
UNION ALL SELECT 'Emergency_Contact', COUNT(*) FROM Emergency_Contact
UNION ALL SELECT 'Medical_Record', COUNT(*) FROM Medical_Record
UNION ALL SELECT 'Room', COUNT(*) FROM Room
UNION ALL SELECT 'Appointment', COUNT(*) FROM Appointment
UNION ALL SELECT 'Admission', COUNT(*) FROM Admission
UNION ALL SELECT 'Treatment', COUNT(*) FROM Treatment
UNION ALL SELECT 'Medicine', COUNT(*) FROM Medicine
UNION ALL SELECT 'Inventory', COUNT(*) FROM Inventory
UNION ALL SELECT 'Prescription', COUNT(*) FROM Prescription
UNION ALL SELECT 'Bill', COUNT(*) FROM Bill
UNION ALL SELECT 'Payment', COUNT(*) FROM Payment
UNION ALL SELECT 'User_Login', COUNT(*) FROM User_Login;
