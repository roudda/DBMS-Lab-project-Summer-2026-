<?php
// controllers/patientController.php
// Reference CRUD implementation for the Patient table (Member 1's module).
// Copy this pattern for Address, Emergency_Contact, and Medical_Record.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listPatients(array $query): void
{
    $pdo = getDb();
    try {
        if (!empty($query['search'])) {
            $stmt = $pdo->prepare(
                'SELECT * FROM Patient WHERE name LIKE ? OR phone LIKE ? ORDER BY patient_id DESC'
            );
            $like = '%' . $query['search'] . '%';
            $stmt->execute([$like, $like]);
        } else {
            $stmt = $pdo->query('SELECT * FROM Patient ORDER BY patient_id DESC');
        }
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch patients.']);
    }
}

function getPatient(string $id): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->prepare('SELECT * FROM Patient WHERE patient_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            jsonResponse(404, ['message' => 'Patient not found.']);
        }
        jsonResponse(200, $row);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch patient.']);
    }
}

function createPatient(array $body): void
{
    $name = $body['name'] ?? null;
    $dob = $body['dob'] ?? null;
    $gender = $body['gender'] ?? null;
    $phone = $body['phone'] ?? null;
    $email = $body['email'] ?? null;
    $blood_group = $body['blood_group'] ?? null;

    if (!$name || !$dob || !$gender) {
        jsonResponse(400, ['message' => 'name, dob and gender are required.']);
    }

    $pdo = getDb();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO Patient (name, dob, gender, phone, email, blood_group)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $dob, $gender, $phone, $email, $blood_group]);
        jsonResponse(201, ['patient_id' => (int) $pdo->lastInsertId(), 'message' => 'Patient registered.']);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            jsonResponse(409, ['message' => 'A patient with that phone number already exists.']);
        }
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not register patient.']);
    }
}

function updatePatient(string $id, array $body): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->prepare(
            'UPDATE Patient SET name = ?, dob = ?, gender = ?, phone = ?, email = ?, blood_group = ?
             WHERE patient_id = ?'
        );
        $stmt->execute([
            $body['name'] ?? null,
            $body['dob'] ?? null,
            $body['gender'] ?? null,
            $body['phone'] ?? null,
            $body['email'] ?? null,
            $body['blood_group'] ?? null,
            $id,
        ]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(404, ['message' => 'Patient not found.']);
        }
        jsonResponse(200, ['message' => 'Patient updated.']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not update patient.']);
    }
}

function deletePatient(string $id): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->prepare('DELETE FROM Patient WHERE patient_id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(404, ['message' => 'Patient not found.']);
        }
        jsonResponse(200, ['message' => 'Patient deleted.']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not delete patient.']);
    }
}
