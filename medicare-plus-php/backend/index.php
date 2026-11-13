<?php
// index.php
// Single front controller for the whole API. Express spread routing
// across server.js + routes/*.js; a plain PHP dev setup doesn't have
// Express's router, so this file plays that role: it matches
// method + path, runs requireAuth()/requireRole() where the original
// routes did, then calls the matching controller function.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/helpers/http.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/middleware/auth.php';
require_once __DIR__ . '/controllers/authController.php';
require_once __DIR__ . '/controllers/patientController.php';
require_once __DIR__ . '/controllers/appointmentController.php';
require_once __DIR__ . '/controllers/inventoryController.php';
require_once __DIR__ . '/controllers/billController.php';
require_once __DIR__ . '/controllers/doctorController.php';
require_once __DIR__ . '/controllers/clinicalController.php';
require_once __DIR__ . '/controllers/admissionController.php';
require_once __DIR__ . '/controllers/reportController.php';

// During development, allow the frontend (any origin/port) to call this
// API — same intent as the Node side's app.use(cors()).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Make routing work whether this runs via `php -S ... index.php` at the
// server root, or under Apache/XAMPP inside a subfolder like
// /medicare-plus-php/backend/. We strip whatever folder this script
// actually lives in from the request path, so route patterns below can
// always just say '/api/...' regardless of where the project sits.
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
if ($path === '' || $path === false) {
    $path = '/';
}

// Parse the JSON body once, the way express.json() did.
$rawBody = file_get_contents('php://input');
$body = [];
if ($rawBody !== false && $rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
$query = $_GET;

/**
 * Matches an Express-style ":id" path pattern against the real request
 * path. Fills $params with any captured segments on success.
 */
function routeMatches(string $pattern, string $path, array &$params): bool
{
    $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';
    if (preg_match($regex, $path, $m)) {
        $params = array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        return true;
    }
    return false;
}

$routes = [
    ['GET', '/api/health', function () {
        jsonResponse(200, ['status' => 'ok', 'service' => 'medicare-plus-backend-php']);
    }],

    // ---- extended clinical / hospital operations ----
    ['GET', '/api/dashboard/stats', function () {
        $user = requireAuth(); requireRole($user, ['Admin']); dashboardStats();
    }],
    ['GET', '/api/patients/:id/medical-records', function (array $params) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor','Receptionist','Nurse']); patientMedicalRecord((int)$params['id']);
    }],
    ['POST', '/api/patients/:id/medical-records', function (array $params) use ($body) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor','Nurse']); addMedicalRecord((int)$params['id'], $body);
    }],
    ['GET', '/api/treatments', function () use ($query) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor','Nurse']); listTreatments($query);
    }],
    ['POST', '/api/treatments', function () use ($body) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor']); createTreatment($body);
    }],
    ['GET', '/api/prescriptions', function () use ($query) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor','Pharmacist','Nurse']); listPrescriptions($query);
    }],
    ['POST', '/api/prescriptions', function () use ($body) {
        $user = requireAuth(); requireRole($user, ['Admin','Doctor']); createPrescription($body);
    }],
    ['GET', '/api/rooms', function () { $user=requireAuth(); requireRole($user,['Admin','Receptionist','Nurse']); listRooms(); }],
    ['GET', '/api/admissions', function () { $user=requireAuth(); requireRole($user,['Admin','Receptionist','Nurse']); listAdmissions(); }],
    ['POST', '/api/admissions', function () use ($body) { $user=requireAuth(); requireRole($user,['Admin','Receptionist']); createAdmission($body); }],
    ['PATCH', '/api/admissions/:id/discharge', function (array $params) { $user=requireAuth(); requireRole($user,['Admin','Receptionist','Nurse']); dischargeAdmission((int)$params['id']); }],

    // ---- auth ----
    ['POST', '/api/auth/login', function () use ($body) {
        login($body);
    }],
    ['GET', '/api/auth/me', function () {
        $user = requireAuth();
        me($user);
    }],

    // ---- patients ---- (router.use(requireAuth) in Express -> requireAuth() first in every handler here)
    ['GET', '/api/patients', function () use ($query) {
        requireAuth();
        listPatients($query);
    }],
    ['GET', '/api/patients/:id', function (array $params) {
        requireAuth();
        getPatient($params['id']);
    }],
    ['POST', '/api/patients', function () use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Receptionist']);
        createPatient($body);
    }],
    ['PUT', '/api/patients/:id', function (array $params) use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Receptionist']);
        updatePatient($params['id'], $body);
    }],
    ['DELETE', '/api/patients/:id', function (array $params) {
        $user = requireAuth();
        requireRole($user, ['Admin']);
        deletePatient($params['id']);
    }],

    // ---- appointments ----
    ['GET', '/api/appointments', function () use ($query) {
        requireAuth();
        listAppointments($query);
    }],
    ['POST', '/api/appointments', function () use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Receptionist']);
        createAppointment($body);
    }],
    ['PATCH', '/api/appointments/:id/status', function (array $params) use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Receptionist', 'Doctor']);
        updateAppointmentStatus($params['id'], $body);
    }],

    // ---- inventory ---- (list "/low-stock" before "/" only matters for readability;
    // both patterns are full matches so there's no ambiguity either way)
    ['GET', '/api/medicines', function () { $user=requireAuth(); requireRole($user,['Admin','Doctor','Pharmacist']); listMedicines(); }],
    ['GET', '/api/inventory/low-stock', function () {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Pharmacist']);
        lowStock();
    }],
    ['GET', '/api/inventory', function () {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Pharmacist']);
        listInventory();
    }],

    // ---- billing ----
    ['GET', '/api/bills', function () use ($query) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Cashier']);
        listBills($query);
    }],
    ['GET', '/api/bills/:id', function (array $params) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Cashier']);
        getBill($params['id']);
    }],
    ['POST', '/api/bills', function () use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Cashier', 'Receptionist']);
        createBill($body);
    }],
    ['POST', '/api/bills/:id/payments', function (array $params) use ($body) {
        $user = requireAuth();
        requireRole($user, ['Admin', 'Cashier']);
        recordPayment($params['id'], $body);
    }],

    // ---- doctors (read-only lookup, e.g. for the appointment-booking dropdown) ----
    ['GET', '/api/doctors', function () {
        requireAuth();
        listDoctors();
    }],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }
    $params = [];
    if (routeMatches($pattern, $path, $params)) {
        try {
            $handler($params);
        } catch (Throwable $e) {
            // Central error handler — same role as the Express app's
            // final app.use((err, req, res, next) => ...) handler.
            error_log('Unhandled error: ' . $e->getMessage());
            jsonResponse(500, ['message' => 'Unexpected server error.']);
        }
        exit;
    }
}

// Fallback 404 — same message shape as the Node version.
jsonResponse(404, ['message' => "No route for $method $path"]);
