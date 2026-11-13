<?php
// config/db.php
// Central PDO connection. Every controller calls getDb() instead of
// opening its own connection — PDO's persistent-connection option keeps
// this cheap, similar in spirit to the mysql2 connection pool on the
// Node side.

require_once __DIR__ . '/../helpers/env.php';
loadEnv(__DIR__ . '/../.env');

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'medicare_plus';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: '';

        $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements (not client-side emulation) so
                // bound values are never interpolated into the SQL string.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Could not connect to MySQL: ' . $e->getMessage());
            error_log('Check that MySQL is running and backend/.env has the correct DB_* values.');
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Database connection failed.']);
            exit;
        }
    }

    return $pdo;
}
