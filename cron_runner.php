<?php
// cron_runner.php — invoked by cron every minute. Since cron can't fire
// every 15/30s, this loops internally, checking every ~1s for up to
// CRON_TIME_BUDGET_SECONDS, so it stays close to on-time regardless.
// MUST be run via CLI (php cron_runner.php), not fetched over HTTP.

require_once __DIR__ . '/includes/scheduler.php';

// Prevent overlapping runs if one invocation ever takes longer than expected.
$lockFile = __DIR__ . '/cron_runner.lock';
$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Another cron_runner.php is already running. Exiting.\n");
    exit(0);
}

$startedAt = time();
while (time() - $startedAt < CRON_TIME_BUDGET_SECONDS) {
    $pdo = get_pdo();
    $runningBatchIds = $pdo->query("SELECT id FROM batches WHERE status = 'RUNNING'")
        ->fetchAll(PDO::FETCH_COLUMN);

    if (empty($runningBatchIds)) {
        sleep(3);
        continue;
    }

    foreach ($runningBatchIds as $batchId) {
        try_advance_batch((int)$batchId);
    }

    sleep(1);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);