<?php
// controllers/authController.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/jwt_config.php';
require_once __DIR__ . '/../helpers/http.php';

/**
 * POST /api/auth/login
 * Body: { username, password, role }
 *
 * User_Login.role tells us whether to join Doctor or Staff to fetch a
 * display name. This mirrors the CHECK constraint in the schema: a login
 * row belongs to exactly one of staff_id or doctor_id.
 */
function login(array $body): void
{
    $username = $body['username'] ?? null;
    $password = $body['password'] ?? null;
    $role = $body['role'] ?? null;

    if (!$username || !$password || !$role) {
        jsonResponse(400, ['message' => 'username, password and role are all required.']);
    }

    $pdo = getDb();

    try {
        $stmt = $pdo->prepare(
            'SELECT ul.user_id, ul.username, ul.password_hash, ul.role,
                    ul.staff_id, ul.doctor_id,
                    COALESCE(s.name, d.name) AS display_name
             FROM User_Login ul
             LEFT JOIN Staff  s ON ul.staff_id  = s.staff_id
             LEFT JOIN Doctor d ON ul.doctor_id = d.doctor_id
             WHERE ul.username = ? AND ul.role = ?
             LIMIT 1'
        );
        $stmt->execute([$username, $role]);
        $user = $stmt->fetch();

        // Same message for "no such user" and "wrong password" below —
        // never reveal which one it was, that's an account-enumeration leak.
        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(401, ['message' => 'Invalid username, password, or role.']);
        }

        $expiresIn = parseDuration(getenv('JWT_EXPIRES_IN') ?: '8h');
        $token = jwt_encode(
            [
                'user_id' => (int) $user['user_id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'staff_id' => $user['staff_id'] !== null ? (int) $user['staff_id'] : null,
                'doctor_id' => $user['doctor_id'] !== null ? (int) $user['doctor_id'] : null,
            ],
            getJwtSecret(),
            $expiresIn
        );

        // Best-effort — don't fail the login if this write has a problem.
        try {
            $pdo->prepare('UPDATE User_Login SET last_login = NOW() WHERE user_id = ?')
                ->execute([$user['user_id']]);
        } catch (Exception $e) {
            error_log('Failed to update last_login: ' . $e->getMessage());
        }

        jsonResponse(200, [
            'token' => $token,
            'user' => [
                'user_id' => (int) $user['user_id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'name' => $user['display_name'],
                'staff_id' => $user['staff_id'] !== null ? (int) $user['staff_id'] : null,
                'doctor_id' => $user['doctor_id'] !== null ? (int) $user['doctor_id'] : null,
            ],
        ]);
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(500, ['message' => 'Something went wrong on our end. Try again shortly.']);
    }
}

/**
 * GET /api/auth/me
 * Requires requireAuth() to have already verified the JWT and produced $authUser.
 */
function me(array $authUser): void
{
    jsonResponse(200, ['user' => $authUser]);
}
