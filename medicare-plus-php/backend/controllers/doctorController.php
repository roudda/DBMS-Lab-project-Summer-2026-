<?php
// controllers/doctorController.php
// Minimal read endpoint from Member 2's module — enough to populate a
// doctor picker on the Appointment booking form. Full Doctor & Department
// CRUD (Doctor, Department, Schedule) follows the same pattern as
// patientController.php when that module gets built out.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listDoctors(): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->query(
            'SELECT d.doctor_id, d.name, d.specialization, dep.dept_name
             FROM Doctor d
             JOIN Department dep ON d.department_id = dep.department_id
             ORDER BY d.name'
        );
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch doctors.']);
    }
}
