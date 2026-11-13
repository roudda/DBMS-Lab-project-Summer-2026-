<?php
// helpers/http.php
// Small helper so every controller sends JSON the same way the Express
// version did with res.status(...).json(...).

function jsonResponse(int $status, $data): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
