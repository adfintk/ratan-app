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

reset_stuck_items();
$batch = get_batch($batchId);

if ($batch['status'] !== BATCH_RUNNING) {
    $nextItem = get_next_pending_item($batchId);
    if ($nextItem === null) {
        if ($batch['status'] !== BATCH_COMPLETED) {
            set_batch_status($batchId, BATCH_COMPLETED);
        }
    } else {
        set_batch_status($batchId, BATCH_RUNNING);
        set_batch_next_run_at($batchId, date('Y-m-d H:i:s')); // due immediately
        try_advance_batch($batchId); // fire the first item right away
    }
}

echo json_encode(build_status_payload($batchId));