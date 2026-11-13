<?php
// controllers/billController.php
// Member 4's other table pair (Bill + Payment) — added to support the
// Cashier dashboard. Same pattern as inventoryController.php.
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listBills(array $query): void
{
    $pdo = getDb();
    $conditions = [];
    $params = [];

    if (!empty($query['status'])) {
        $conditions[] = 'b.status = ?';
        $params[] = $query['status'];
    }
    if (!empty($query['patient_id'])) {
        $conditions[] = 'b.patient_id = ?';
        $params[] = $query['patient_id'];
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    try {
        $stmt = $pdo->prepare(
            "SELECT b.bill_id, b.total_amount, b.bill_date, b.status,
                    p.patient_id, p.name AS patient_name,
                    b.appointment_id, b.admission_id,
                    COALESCE((SELECT SUM(amount_paid) FROM Payment WHERE bill_id = b.bill_id), 0) AS amount_paid
             FROM Bill b
             JOIN Patient p ON b.patient_id = p.patient_id
             $where
             ORDER BY b.bill_date DESC"
        );
        $stmt->execute($params);
        jsonResponse(200, $stmt->fetchAll());
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch bills.']);
    }
}

function getBill(string $id): void
{
    $pdo = getDb();
    try {
        $stmt = $pdo->prepare(
            'SELECT b.*, p.name AS patient_name
             FROM Bill b JOIN Patient p ON b.patient_id = p.patient_id
             WHERE b.bill_id = ?'
        );
        $stmt->execute([$id]);
        $bill = $stmt->fetch();
        if (!$bill) {
            jsonResponse(404, ['message' => 'Bill not found.']);
        }

        $stmt = $pdo->prepare('SELECT * FROM Payment WHERE bill_id = ? ORDER BY payment_date DESC');
        $stmt->execute([$id]);
        $bill['payments'] = $stmt->fetchAll();

        jsonResponse(200, $bill);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not fetch bill.']);
    }
}

function createBill(array $body): void
{
    $patient_id = $body['patient_id'] ?? null;
    $appointment_id = $body['appointment_id'] ?? null;
    $admission_id = $body['admission_id'] ?? null;
    $total_amount = $body['total_amount'] ?? null;

    if (!$patient_id || $total_amount === null) {
        jsonResponse(400, ['message' => 'patient_id and total_amount are required.']);
    }

    $pdo = getDb();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO Bill (patient_id, appointment_id, admission_id, total_amount)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$patient_id, $appointment_id ?: null, $admission_id ?: null, $total_amount]);
        jsonResponse(201, ['bill_id' => (int) $pdo->lastInsertId(), 'message' => 'Bill created.']);
    } catch (Exception $e) {
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not create bill.']);
    }
}

/**
 * Records a payment against a bill, then recomputes the bill's status
 * from the total paid so far (Unpaid / Partially Paid / Paid) — this is
 * done in application code since it depends on comparing a SUM() against
 * Bill.total_amount, which a plain CHECK constraint can't express.
 */
function recordPayment(string $billId, array $body): void
{
    $amount = $body['amount_paid'] ?? null;
    $method = $body['payment_method'] ?? null;
    $allowedMethods = ['Cash', 'Card', 'UPI', 'Insurance'];

    if (!$amount || $amount <= 0) {
        jsonResponse(400, ['message' => 'amount_paid must be a positive number.']);
    }
    if (!in_array($method, $allowedMethods, true)) {
        jsonResponse(400, ['message' => 'payment_method must be one of: ' . implode(', ', $allowedMethods)]);
    }

    $pdo = getDb();
    try {
        $stmt = $pdo->prepare('SELECT total_amount FROM Bill WHERE bill_id = ?');
        $stmt->execute([$billId]);
        $bill = $stmt->fetch();
        if (!$bill) {
            jsonResponse(404, ['message' => 'Bill not found.']);
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare('INSERT INTO Payment (bill_id, amount_paid, payment_method) VALUES (?, ?, ?)');
        $stmt->execute([$billId, $amount, $method]);

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_paid), 0) AS paid FROM Payment WHERE bill_id = ?');
        $stmt->execute([$billId]);
        $totalPaid = (float) $stmt->fetch()['paid'];

        $status = 'Partially Paid';
        if ($totalPaid <= 0) {
            $status = 'Unpaid';
        } elseif ($totalPaid >= (float) $bill['total_amount']) {
            $status = 'Paid';
        }

        $pdo->prepare('UPDATE Bill SET status = ? WHERE bill_id = ?')->execute([$status, $billId]);

        $pdo->commit();

        jsonResponse(201, [
            'message' => 'Payment recorded.',
            'total_paid' => $totalPaid,
            'bill_status' => $status,
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not record payment.']);
    }
}
