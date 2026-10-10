<?php
require_once __DIR__ . '/../includes/scheduler.php';
header('Content-Type: application/json');

$batchId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$batch = get_batch($batchId);
if ($batch === null) {
    http_response_code(404);
    echo json_encode(['detail' => 'batch not found']);
    exit;
}

if ($batch['status'] === BATCH_RUNNING) {
    set_batch_status($batchId, BATCH_STOPPED);
}

echo json_encode(build_status_payload($batchId));