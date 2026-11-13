<?php
// controllers/appointmentController.php
// Reference implementation for Member 3's module. Note that double-booking
// prevention is NOT re-implemented here in application code — it relies on
// the uq_doctor_slot UNIQUE constraint on Appointment(doctor_id, appt_date,
// appt_time) in the schema. This route just catches the resulting
// duplicate-entry error (MySQL error 1062) and turns it into a friendly
// message.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listAppointments(array $query): void
{
    $pdo = getDb();
    $conditions = [];
    $params = [];

    if (!empty($query['doctor_id'])) {
        $conditions[] = 'a.doctor_id = ?';
        $params[] = $query['doctor_id'];
    }
    if (!empty($query['patient_id'])) {
        $conditions[] = 'a.patient_id = ?';
        $params[] = $query['patient_id'];
    }
    if (!empty($query['date'])) {
        $conditions[] = 'a.appt_date = ?';
        $params[] = $query['date'];
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    try {
        $stmt = $pdo->prepare(
            "SELECT a.appointment_id, a.appt_date, a.appt_time, a.status,
                    p.name AS patient_name, d.name AS doctor_name
             FROM Appointment a
             JOIN Patient p ON a.patient_id = p.patient_id
             JOIN Doctor d ON a.doctor_id = d.doctor_id
             $where
             ORDER BY a.appt_date, a.appt_time"
        );
        $stmt->execute($params);
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch appointments.']);
    }
}

function createAppointment(array $body): void
{
    $patient_id = $body['patient_id'] ?? null;
    $doctor_id = $body['doctor_id'] ?? null;
    $appt_date = $body['appt_date'] ?? null;
    $appt_time = $body['appt_time'] ?? null;

    if (!$patient_id || !$doctor_id || !$appt_date || !$appt_time) {
        jsonResponse(400, ['message' => 'patient_id, doctor_id, appt_date and appt_time are required.']);
    }

    $pdo = getDb();
    try {
        if ($appt_date < date('Y-m-d')) {
            jsonResponse(400, ['message' => 'Appointments cannot be booked in the past.']);
        }
        // If a doctor has a configured schedule, enforce it. Doctors without
        // schedule rows remain bookable so the demo/sample database keeps working.
        $dayMap = ['Sunday'=>'Sun','Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri','Saturday'=>'Sat'];
        $day = $dayMap[date('l', strtotime($appt_date))];
        $s = $pdo->prepare('SELECT COUNT(*) FROM Schedule WHERE doctor_id=?');
        $s->execute([$doctor_id]);
        if ((int)$s->fetchColumn() > 0) {
            $s = $pdo->prepare('SELECT COUNT(*) FROM Schedule WHERE doctor_id=? AND day_of_week=? AND start_time<=? AND end_time>?');
            $s->execute([$doctor_id, $day, $appt_time, $appt_time]);
            if ((int)$s->fetchColumn() === 0) {
                jsonResponse(409, ['message' => "The selected time is outside this doctor\'s working schedule."]);
            }
        }
        $stmt = $pdo->prepare(
            'INSERT INTO Appointment (patient_id, doctor_id, appt_date, appt_time)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$patient_id, $doctor_id, $appt_date, $appt_time]);
        jsonResponse(201, ['appointment_id' => (int) $pdo->lastInsertId(), 'message' => 'Appointment booked.']);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? null) === 1062) {
            jsonResponse(409, [
                'message' => 'That doctor already has an appointment at this exact date and time. Pick another slot.',
            ]);
        }
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not book appointment.']);
    }
}

function updateAppointmentStatus(string $id, array $body): void
{
    $status = $body['status'] ?? null;
    $allowed = ['Scheduled', 'Completed', 'Cancelled', 'No-Show'];

    if (!in_array($status, $allowed, true)) {
        jsonResponse(400, ['message' => 'status must be one of: ' . implode(', ', $allowed)]);
    }

    $pdo = getDb();
    try {
        $stmt = $pdo->prepare('UPDATE Appointment SET status = ? WHERE appointment_id = ?');
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) {
            jsonResponse(404, ['message' => 'Appointment not found.']);
        }
        jsonResponse(200, ['message' => 'Appointment status updated.']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not update appointment.']);
    }
}
