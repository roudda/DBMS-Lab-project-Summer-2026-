<?php
// seed/seed.php
// Creates one demo login per role so the login page can be demoed
// end-to-end before real staff accounts exist. Run with: php seed/seed.php
require_once __DIR__ . '/../config/db.php';

const DEMO_PASSWORD = 'demo1234';

function seed(): void
{
    $pdo = getDb();
    $pdo->beginTransaction();

    try {
        // password_hash() with PASSWORD_BCRYPT produces a standard bcrypt
        // hash — the same format bcryptjs produced on the Node side, and
        // verifiable with password_verify() either way.
        $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_BCRYPT, ['cost' => 10]);

        // 1. A department for everything to hang off of
        $stmt = $pdo->prepare('INSERT INTO Department (dept_name, location, phone) VALUES (?, ?, ?)');
        $stmt->execute(['General Medicine', 'Block A, 1st Floor', '02-9991234']);
        $departmentId = (int) $pdo->lastInsertId();

        // 2. One demo doctor
        $stmt = $pdo->prepare(
            'INSERT INTO Doctor (department_id, name, specialization, phone, email, qualification, joined_date)
             VALUES (?, ?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->execute([
            $departmentId,
            'Dr. Ayesha Rahman',
            'General Physician',
            '01700000001',
            'ayesha.demo@medicareplus.test',
            'MBBS, FCPS',
        ]);
        $doctorId = (int) $pdo->lastInsertId();

        // 3. One demo staff member per non-doctor role
        $staffRoles = ['Admin', 'Receptionist', 'Pharmacist', 'Cashier'];
        $staffIds = [];
        $stmt = $pdo->prepare(
            'INSERT INTO Staff (department_id, name, role, phone, email, joined_date)
             VALUES (?, ?, ?, ?, ?, CURDATE())'
        );
        foreach ($staffRoles as $role) {
            $stmt->execute([
                $departmentId,
                "Demo $role",
                $role,
                '01700000000',
                strtolower($role) . '.demo@medicareplus.test',
            ]);
            $staffIds[$role] = (int) $pdo->lastInsertId();
        }

        // 4. User_Login rows — username pattern matches the login page's demo note
        $logins = [
            ['username' => 'demo.admin', 'role' => 'Admin', 'staff_id' => $staffIds['Admin'], 'doctor_id' => null],
            ['username' => 'demo.doctor', 'role' => 'Doctor', 'staff_id' => null, 'doctor_id' => $doctorId],
            ['username' => 'demo.receptionist', 'role' => 'Receptionist', 'staff_id' => $staffIds['Receptionist'], 'doctor_id' => null],
            ['username' => 'demo.pharmacist', 'role' => 'Pharmacist', 'staff_id' => $staffIds['Pharmacist'], 'doctor_id' => null],
            ['username' => 'demo.cashier', 'role' => 'Cashier', 'staff_id' => $staffIds['Cashier'], 'doctor_id' => null],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO User_Login (username, password_hash, role, staff_id, doctor_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($logins as $l) {
            $stmt->execute([$l['username'], $passwordHash, $l['role'], $l['staff_id'], $l['doctor_id']]);
        }

        $pdo->commit();

        echo "Seed complete. Demo logins (password for all: \"demo1234\"):\n";
        foreach ($logins as $l) {
            echo "  - {$l['username']}  [{$l['role']}]\n";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        echo 'Seeding failed: ' . $e->getMessage() . "\n";
    }
}

seed();
