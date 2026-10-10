<?php
require_once __DIR__ . '/../includes/scheduler.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$valuesRaw = $input['values'] ?? '';
$interval = isset($input['interval_seconds']) ? (int)$input['interval_seconds'] : 0;

if (!in_array($interval, ALLOWED_INTERVALS, true)) {
    http_response_code(422);
    echo json_encode(['detail' => 'interval_seconds must be one of: ' . implode(', ', ALLOWED_INTERVALS)]);
    exit;
}

$values = clean_values($valuesRaw);
if (empty($values)) {
    http_response_code(400);
    echo json_encode(['detail' => 'No non-empty test values were provided']);
    exit;
}

$batchId = create_batch($interval, $values);
http_response_code(201);
echo json_encode(build_status_payload($batchId));