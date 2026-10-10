<?php
header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true); // unused for now, kept for parity
http_response_code(200);
echo json_encode([
    'status' => true,
    'code' => 100,
    'message' => 'Mock request successful',
]);