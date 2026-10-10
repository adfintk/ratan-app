<?php
require_once __DIR__ . '/../includes/scheduler.php';
header('Content-Type: application/json');

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$batch = get_batch($batchId);
if ($batch === null) {
    http_response_code(404);
    echo json_encode(['detail' => 'batch not found']);
    exit;
}

// Nudge progress if due -- this is what makes the dashboard feel responsive
// while it's open, on top of cron guaranteeing progress when it's not.
try_advance_batch($batchId);

echo json_encode(build_status_payload($batchId));