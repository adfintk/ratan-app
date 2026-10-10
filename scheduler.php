<?php
require_once __DIR__ . '/functions.php';

function send_and_record(string $value): array {
    $url = rtrim(APP_BASE_URL, '/') . '/api/mock_send.php';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['value' => $value]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [ITEM_FAILED, null, null, $err];
    }
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $status = ($httpStatus >= 200 && $httpStatus < 400) ? ITEM_SUCCESS : ITEM_FAILED;
    return [$status, $httpStatus, $body, null];
}

/**
 * Advances a RUNNING batch by exactly one item, if (and only if) it's
 * actually due. Safe to call from many places at once (cron, status polls)
 * because GET_LOCK() ensures only one caller can be mid-advance for a given
 * batch_id at any moment -- this is what prevents duplicate/simultaneous
 * sends.
 */
function try_advance_batch(int $batchId): array {
    $pdo = get_pdo();
    $lockName = "api_scheduler_batch_{$batchId}";

    $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 0) AS l");
    $lockStmt->execute([$lockName]);
    if ((int)$lockStmt->fetch()['l'] !== 1) {
        return ['advanced' => false, 'reason' => 'locked'];
    }

    try {
        $batch = get_batch($batchId);
        if ($batch === null || $batch['status'] !== BATCH_RUNNING) {
            return ['advanced' => false, 'reason' => 'not_running'];
        }
        if ($batch['next_run_at'] !== null && strtotime($batch['next_run_at']) > time()) {
            return ['advanced' => false, 'reason' => 'not_due'];
        }

        $item = get_next_pending_item($batchId);
        if ($item === null) {
            set_batch_status($batchId, BATCH_COMPLETED);
            set_batch_next_run_at($batchId, null);
            return ['advanced' => false, 'reason' => 'completed'];
        }

        mark_item_processing($item['id']);
        [$status, $httpStatus, $responseText, $error] = send_and_record($item['value']);
        mark_item_result($item['id'], $status, $httpStatus, $responseText, $error);

        $remaining = count_items_by_status($batchId)[ITEM_PENDING];
        if ($remaining === 0) {
            set_batch_status($batchId, BATCH_COMPLETED);
            set_batch_next_run_at($batchId, null);
            return ['advanced' => true, 'reason' => 'completed'];
        }

        $nextRun = date('Y-m-d H:i:s', time() + (int)$batch['interval_seconds']);
        set_batch_next_run_at($batchId, $nextRun);
        return ['advanced' => true, 'reason' => 'processed'];
    } finally {
        $releaseStmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
        $releaseStmt->execute([$lockName]);
    }
}

function build_status_payload(int $batchId): array {
    $batch = get_batch($batchId);
    $counts = count_items_by_status($batchId);
    $total = array_sum($counts);
    $processed = $counts[ITEM_SUCCESS] + $counts[ITEM_FAILED];
    $remaining = $counts[ITEM_PENDING] + $counts[ITEM_PROCESSING];

    $currentValue = null;
    $lastHttpStatus = null;
    $lastResponse = null;
    $lastError = null;

    $last = get_last_processed_item($batchId);
    if ($last !== null) {
        $currentValue = $last['value'];
        $lastHttpStatus = $last['http_status'] !== null ? (int)$last['http_status'] : null;
        $lastResponse = $last['response'];
        $lastError = $last['error'];
    }
    $processingItem = get_processing_item($batchId);
    if ($processingItem !== null) {
        $currentValue = $processingItem['value']; // prefer showing what's in flight
    }

    $countdown = null;
    if ($batch['status'] === BATCH_RUNNING && $batch['next_run_at'] !== null) {
        $countdown = max(0, strtotime($batch['next_run_at']) - time());
    }

    return [
        'id' => (int)$batch['id'],
        'status' => $batch['status'],
        'interval_seconds' => (int)$batch['interval_seconds'],
        'created_at' => $batch['created_at'],
        'updated_at' => $batch['updated_at'],
        'total_count' => $total,
        'processed_count' => $processed,
        'remaining_count' => $remaining,
        'pending_count' => $counts[ITEM_PENDING],
        'processing_count' => $counts[ITEM_PROCESSING],
        'success_count' => $counts[ITEM_SUCCESS],
        'failed_count' => $counts[ITEM_FAILED],
        'current_value' => $currentValue,
        'last_http_status' => $lastHttpStatus,
        'last_response' => $lastResponse,
        'last_error' => $lastError,
        'countdown_seconds' => $countdown,
        'items' => get_items_by_batch($batchId),
    ];
}