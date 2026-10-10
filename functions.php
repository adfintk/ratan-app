<?php
require_once __DIR__ . '/db.php';

const BATCH_CREATED = 'CREATED';
const BATCH_RUNNING = 'RUNNING';
const BATCH_STOPPED = 'STOPPED';
const BATCH_COMPLETED = 'COMPLETED';

const ITEM_PENDING = 'PENDING';
const ITEM_PROCESSING = 'PROCESSING';
const ITEM_SUCCESS = 'SUCCESS';
const ITEM_FAILED = 'FAILED';

function clean_values(string $raw): array {
    $seen = [];
    $result = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $v = trim($line);
        if ($v === '' || isset($seen[$v])) continue;
        $seen[$v] = true;
        $result[] = $v;
    }
    return $result;
}

function create_batch(int $intervalSeconds, array $values): int {
    $pdo = get_pdo();
    $now = date('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO batches (status, interval_seconds, next_run_at, created_at, updated_at)
             VALUES (?, ?, NULL, ?, ?)"
        );
        $stmt->execute([BATCH_CREATED, $intervalSeconds, $now, $now]);
        $batchId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            "INSERT INTO items (batch_id, seq, value, status) VALUES (?, ?, ?, ?)"
        );
        foreach ($values as $i => $v) {
            $itemStmt->execute([$batchId, $i, $v, ITEM_PENDING]);
        }
        $pdo->commit();
        return $batchId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function get_batch(int $batchId): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM batches WHERE id = ?");
    $stmt->execute([$batchId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_batch_status(int $batchId, string $status): void {
    $stmt = get_pdo()->prepare("UPDATE batches SET status = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$status, date('Y-m-d H:i:s'), $batchId]);
}

function set_batch_next_run_at(int $batchId, ?string $datetime): void {
    $stmt = get_pdo()->prepare("UPDATE batches SET next_run_at = ?, updated_at = ? WHERE id = ?");
    $stmt->execute([$datetime, date('Y-m-d H:i:s'), $batchId]);
}

function get_items_by_batch(int $batchId): array {
    $stmt = get_pdo()->prepare("SELECT * FROM items WHERE batch_id = ? ORDER BY seq ASC");
    $stmt->execute([$batchId]);
    return $stmt->fetchAll();
}

function get_next_pending_item(int $batchId): ?array {
    $stmt = get_pdo()->prepare(
        "SELECT * FROM items WHERE batch_id = ? AND status = ? ORDER BY seq ASC LIMIT 1"
    );
    $stmt->execute([$batchId, ITEM_PENDING]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_processing_item(int $batchId): ?array {
    $stmt = get_pdo()->prepare(
        "SELECT * FROM items WHERE batch_id = ? AND status = ? ORDER BY seq ASC LIMIT 1"
    );
    $stmt->execute([$batchId, ITEM_PROCESSING]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function mark_item_processing(int $itemId): void {
    $stmt = get_pdo()->prepare("UPDATE items SET status = ? WHERE id = ?");
    $stmt->execute([ITEM_PROCESSING, $itemId]);
}

function mark_item_result(int $itemId, string $status, ?int $httpStatus, ?string $response, ?string $error): void {
    $stmt = get_pdo()->prepare(
        "UPDATE items SET status = ?, processed_at = ?, http_status = ?, response = ?, error = ? WHERE id = ?"
    );
    $stmt->execute([$status, date('Y-m-d H:i:s'), $httpStatus, $response, $error, $itemId]);
}

function count_items_by_status(int $batchId): array {
    $counts = [ITEM_PENDING => 0, ITEM_PROCESSING => 0, ITEM_SUCCESS => 0, ITEM_FAILED => 0];
    $stmt = get_pdo()->prepare("SELECT status, COUNT(*) AS c FROM items WHERE batch_id = ? GROUP BY status");
    $stmt->execute([$batchId]);
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['status']] = (int)$row['c'];
    }
    return $counts;
}

function get_last_processed_item(int $batchId): ?array {
    $stmt = get_pdo()->prepare(
        "SELECT * FROM items WHERE batch_id = ? AND status IN (?, ?) ORDER BY seq DESC LIMIT 1"
    );
    $stmt->execute([$batchId, ITEM_SUCCESS, ITEM_FAILED]);
    $row = $stmt->fetch();
    return $row ?: null;
}

// Recovery: normally an item resolves to SUCCESS/FAILED within the same
// PHP request that marked it PROCESSING. A row stuck in PROCESSING means a
// prior request died mid-flight (host timeout, etc). Only called from
// start_batch.php, matching when a user explicitly asks to resume.
function reset_stuck_items(): void {
    $stmt = get_pdo()->prepare("UPDATE items SET status = ? WHERE status = ?");
    $stmt->execute([ITEM_PENDING, ITEM_PROCESSING]);
}