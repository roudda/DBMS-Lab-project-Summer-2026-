<?php
// Central JWT secret accessor. Keeps login and protected API requests on the
// same secret even when XAMPP is used without a backend/.env file.
require_once __DIR__ . '/env.php';

function getJwtSecret(): string
{
    static $secret = null;
    if ($secret !== null) return $secret;

    loadEnv(__DIR__ . '/../.env');
    $secret = trim((string)(getenv('JWT_SECRET') ?: ''));

    // Coursework/XAMPP fallback. If you create backend/.env, its JWT_SECRET
    // automatically takes precedence.
    if ($secret === '' || $secret === 'change_this_to_a_long_random_string') {
        $secret = 'medicare-plus-coursework-jwt-secret-2026-change-me';
    }
    return $secret;
}
