<?php
// router.php
// Enables running the application with PHP's built-in development server:
// php -S localhost:5000 router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Redirect root to frontend login page
if ($uri === '/' || $uri === '') {
    header('Location: /frontend/login.html');
    exit;
}

// Serve existing static files directly
$filePath = __DIR__ . $uri;
if (is_file($filePath)) {
    return false; // Tells PHP CLI built-in server to serve the static file
}

// Route API requests to backend/index.php
if (str_starts_with($uri, '/backend/api') || str_starts_with($uri, '/api')) {
    $_SERVER['SCRIPT_NAME'] = '/backend/index.php';
    require __DIR__ . '/backend/index.php';
    exit;
}

// Fallback: file not found
http_response_code(404);
echo "404 Not Found";
