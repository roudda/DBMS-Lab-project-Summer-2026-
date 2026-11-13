<?php
// helpers/jwt.php
// A small, dependency-free HS256 JWT implementation. We only need
// sign + verify with one algorithm, so a full library (like
// firebase/php-jwt) would be overkill for this project — this is
// ~40 lines and does exactly what jsonwebtoken did on the Node side.

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Parses simple duration strings like "8h", "30m", "7d" into seconds.
 * Falls back to treating the value as a raw integer of seconds.
 * Mirrors what the "8h"-style JWT_EXPIRES_IN value meant on the Node side.
 */
function parseDuration(string $value): int
{
    if (preg_match('/^(\d+)\s*([smhd])$/i', trim($value), $m)) {
        $n = (int) $m[1];
        $unit = strtolower($m[2]);
        $multipliers = ['s' => 1, 'm' => 60, 'h' => 3600, 'd' => 86400];
        return $n * $multipliers[$unit];
    }
    return (int) $value ?: 3600 * 8; // default: 8 hours
}

/**
 * Signs a payload and returns a JWT string.
 */
function jwt_encode(array $payload, string $secret, int $expiresInSeconds): string
{
    $header = ['typ' => 'JWT', 'alg' => 'HS256'];
    $payload['iat'] = time();
    $payload['exp'] = time() + $expiresInSeconds;

    $segments = [
        base64UrlEncode(json_encode($header)),
        base64UrlEncode(json_encode($payload)),
    ];

    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = base64UrlEncode($signature);

    return implode('.', $segments);
}

/**
 * Verifies signature + expiry and returns the decoded payload.
 * Throws an Exception on any failure — callers turn that into a 401.
 */
function jwt_decode(string $jwt, string $secret): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new Exception('Malformed token.');
    }

    [$headerB64, $payloadB64, $sigB64] = $parts;

    $signingInput = $headerB64 . '.' . $payloadB64;
    $expectedSig = base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));

    if (!hash_equals($expectedSig, $sigB64)) {
        throw new Exception('Invalid signature.');
    }

    $payload = json_decode(base64UrlDecode($payloadB64), true);
    if (!is_array($payload)) {
        throw new Exception('Invalid payload.');
    }

    if (isset($payload['exp']) && time() > $payload['exp']) {
        throw new Exception('Token expired.');
    }

    return $payload;
}
