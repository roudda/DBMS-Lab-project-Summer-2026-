<?php
// middleware/auth.php
// Reads "Authorization: Bearer <token>", verifies it, and returns the
// decoded payload. Any route that needs a logged-in user calls this
// first — same role as authMiddleware.js's requireAuth.

require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/jwt_config.php';
require_once __DIR__ . '/../helpers/http.php';

function requireAuth(): array
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = '';
    foreach ($headers as $name => $value) {
        if (strcasecmp((string)$name, 'Authorization') === 0) {
            $authHeader = (string)$value;
            break;
        }
    }
    if ($authHeader === '') {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['Authorization']
            ?? '';
    }

    [$scheme, $token] = array_pad(preg_split('/\s+/', trim($authHeader), 2), 2, '');

    if ($scheme !== 'Bearer' || !$token) {
        jsonResponse(401, ['message' => 'Missing or malformed Authorization header.']);
    }

    try {
        return jwt_decode($token, getJwtSecret());
    } catch (Exception $e) {
        jsonResponse(401, ['message' => 'Session expired or invalid. Please sign in again.']);
    }
}

/**
 * Usage: requireRole($user, ['Admin']), or requireRole($user, ['Admin', 'Receptionist'])
 * Call after requireAuth(), passing the user it returned.
 */
function requireRole(array $user, array $allowedRoles): void
{
    if (!isset($user['role']) || !in_array($user['role'], $allowedRoles, true)) {
        jsonResponse(403, ['message' => 'You do not have permission to do that.']);
    }
}
