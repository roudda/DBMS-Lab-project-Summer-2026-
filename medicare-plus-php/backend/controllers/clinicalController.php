<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/http.php';

function listTreatments(array $query): void {
    $pdo = getDb();
    $sql = 'SELECT t.treatment_id,t.appointment_id,t.diagnosis,t.treatment_notes,t.treatment_date,
                   p.patient_id,p.name AS patient_name,d.doctor_id,d.name AS doctor_name
            FROM Treatment t
            JOIN Appointment a ON t.appointment_id=a.appointment_id
            JOIN Patient p ON a.patient_id=p.patient_id
            JOIN Doctor d ON a.doctor_id=d.doctor_id';
    $where=[]; $params=[];
    if (!empty($query['appointment_id'])) { $where[]='t.appointment_id=?'; $params[]=$query['appointment_id']; }
    if (!empty($query['patient_id'])) { $where[]='a.patient_id=?'; $params[]=$query['patient_id']; }
    if (!empty($query['doctor_id'])) { $where[]='a.doctor_id=?'; $params[]=$query['doctor_id']; }
    if ($where) $sql .= ' WHERE '.implode(' AND ',$where);
    $sql .= ' ORDER BY t.treatment_date DESC';
    try { $s=$pdo->prepare($sql); $s->execute($params); jsonResponse(200,$s->fetchAll()); }
    catch(Exception $e){ error_log($e->getMessage()); jsonResponse(500,['message'=>'Could not fetch treatments.']); }
}

function createTreatment(array $body): void {
    $appointmentId=$body['appointment_id']??null; $diagnosis=trim($body['diagnosis']??''); $notes=trim($body['treatment_notes']??'');
    if(!$appointmentId||$diagnosis==='') jsonResponse(400,['message'=>'appointment_id and diagnosis are required.']);
    $pdo=getDb();
    try {
        $s=$pdo->prepare('SELECT status FROM Appointment WHERE appointment_id=?'); $s->execute([$appointmentId]); $a=$s->fetch();
        if(!$a) jsonResponse(404,['message'=>'Appointment not found.']);
        if($a['status']==='Cancelled') jsonResponse(409,['message'=>'Cancelled appointments cannot receive treatment.']);
        $s=$pdo->prepare('INSERT INTO Treatment (appointment_id,diagnosis,treatment_notes) VALUES (?,?,?)');
        $s->execute([$appointmentId,$diagnosis,$notes?:null]);
        jsonResponse(201,['treatment_id'=>(int)$pdo->lastInsertId(),'message'=>'Treatment record created.']);
    } catch(Exception $e){ error_log($e->getMessage()); jsonResponse(500,['message'=>'Could not create treatment.']); }
}

function listPrescriptions(array $query): void {
    $pdo=getDb(); $sql='SELECT pr.prescription_id,pr.treatment_id,pr.medicine_id,m.name AS medicine_name,
                         pr.dosage,pr.duration_days,pr.quantity,t.treatment_date,p.patient_id,p.name AS patient_name
                  FROM Prescription pr JOIN Medicine m ON pr.medicine_id=m.medicine_id
                  JOIN Treatment t ON pr.treatment_id=t.treatment_id
                  JOIN Appointment a ON t.appointment_id=a.appointment_id
                  JOIN Patient p ON a.patient_id=p.patient_id';
    $where=[];$params=[];
    if(!empty($query['patient_id'])){$where[]='p.patient_id=?';$params[]=$query['patient_id'];}
    if(!empty($query['treatment_id'])){$where[]='pr.treatment_id=?';$params[]=$query['treatment_id'];}
    if($where)$sql.=' WHERE '.implode(' AND ',$where); $sql.=' ORDER BY pr.prescription_id DESC';
    try{$s=$pdo->prepare($sql);$s->execute($params);jsonResponse(200,$s->fetchAll());}
    catch(Exception $e){error_log($e->getMessage());jsonResponse(500,['message'=>'Could not fetch prescriptions.']);}
}

function createPrescription(array $body): void {
    $tid = (int)($body['treatment_id'] ?? 0);

    // New format: one treatment can contain multiple medicines.
    // Legacy single-medicine payloads are still accepted for compatibility.
    $items = $body['medicines'] ?? null;
    if (!is_array($items)) {
        $items = [[
            'medicine_id' => $body['medicine_id'] ?? null,
            'dosage' => $body['dosage'] ?? null,
            'duration_days' => $body['duration_days'] ?? null,
            'quantity' => $body['quantity'] ?? null,
        ]];
    }

    if ($tid < 1 || count($items) < 1) {
        jsonResponse(400, ['message' => 'A valid treatment and at least one medicine are required.']);
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $mid = (int)($item['medicine_id'] ?? 0);
        $qty = (int)($item['quantity'] ?? 0);
        $duration = $item['duration_days'] ?? null;
        if ($duration !== null && $duration !== '') {
            $duration = (int)$duration;
            if ($duration < 1) $duration = null;
        } else {
            $duration = null;
        }
        $dosage = trim((string)($item['dosage'] ?? ''));

        if ($mid < 1 || $qty < 1) {
            jsonResponse(400, ['message' => 'Every medicine must have a valid medicine and positive quantity.']);
        }
        if (isset($seen[$mid])) {
            jsonResponse(400, ['message' => 'A medicine can only appear once in the same prescription. Use one row and set the total quantity.']);
        }
        $seen[$mid] = true;
        $normalized[] = [
            'medicine_id' => $mid,
            'dosage' => $dosage !== '' ? $dosage : null,
            'duration_days' => $duration,
            'quantity' => $qty,
        ];
    }

    $pdo = getDb();
    try {
        $pdo->beginTransaction();

        // Confirm the treatment exists before touching inventory.
        $s = $pdo->prepare('SELECT treatment_id FROM Treatment WHERE treatment_id=? FOR UPDATE');
        $s->execute([$tid]);
        if (!$s->fetch()) {
            $pdo->rollBack();
            jsonResponse(404, ['message' => 'Treatment not found. Create/save the treatment before prescribing medicine.']);
        }

        // Lock/check every medicine first. Nothing is inserted or deducted
        // until every requested medicine has sufficient stock.
        $stockStmt = $pdo->prepare('SELECT quantity_in_stock FROM Inventory WHERE medicine_id=? FOR UPDATE');
        $stock = [];
        foreach ($normalized as $item) {
            $stockStmt->execute([$item['medicine_id']]);
            $inv = $stockStmt->fetch();
            if (!$inv) {
                $pdo->rollBack();
                jsonResponse(404, ['message' => 'Medicine ID ' . $item['medicine_id'] . ' is not in inventory.']);
            }
            $available = (int)$inv['quantity_in_stock'];
            if ($available < $item['quantity']) {
                $pdo->rollBack();
                jsonResponse(409, ['message' => 'Insufficient stock for medicine ID ' . $item['medicine_id'] . '. Available: ' . $available . ', requested: ' . $item['quantity'] . '.']);
            }
            $stock[$item['medicine_id']] = $available;
        }

        $insert = $pdo->prepare('INSERT INTO Prescription (treatment_id,medicine_id,dosage,duration_days,quantity) VALUES (?,?,?,?,?)');
        $update = $pdo->prepare('UPDATE Inventory SET quantity_in_stock=quantity_in_stock-? WHERE medicine_id=?');
        $ids = [];

        foreach ($normalized as $item) {
            $insert->execute([$tid, $item['medicine_id'], $item['dosage'], $item['duration_days'], $item['quantity']]);
            $ids[] = (int)$pdo->lastInsertId();
            $update->execute([$item['quantity'], $item['medicine_id']]);
        }

        $pdo->commit();
        jsonResponse(201, [
            'prescription_ids' => $ids,
            'count' => count($ids),
            'message' => count($ids) === 1
                ? 'Prescription created and stock updated.'
                : count($ids) . ' medicines prescribed and stock updated.'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        jsonResponse(500, ['message' => 'Could not create prescription. No inventory changes were kept.']);
    }
}

function patientMedicalRecord(int $id): void {
    $pdo=getDb();
    try{$s=$pdo->prepare('SELECT record_id,patient_id,allergies,chronic_conditions,notes,created_at FROM Medical_Record WHERE patient_id=? ORDER BY created_at DESC');$s->execute([$id]);jsonResponse(200,$s->fetchAll());}
    catch(Exception $e){error_log($e->getMessage());jsonResponse(500,['message'=>'Could not fetch medical records.']);}
}
function addMedicalRecord(int $id,array $body): void {
    $pdo=getDb();
    try{$s=$pdo->prepare('SELECT patient_id FROM Patient WHERE patient_id=?');$s->execute([$id]);if(!$s->fetch())jsonResponse(404,['message'=>'Patient not found.']);
        $s=$pdo->prepare('INSERT INTO Medical_Record(patient_id,allergies,chronic_conditions,notes) VALUES(?,?,?,?)');
        $s->execute([$id,$body['allergies']??null,$body['chronic_conditions']??null,$body['notes']??null]);jsonResponse(201,['record_id'=>(int)$pdo->lastInsertId(),'message'=>'Medical record added.']);
    }catch(Exception $e){error_log($e->getMessage());jsonResponse(500,['message'=>'Could not add medical record.']);}
}
