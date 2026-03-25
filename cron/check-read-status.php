<?php

// Cron script – Dispatches a CheckReadStatusJob into the job queue.
// Designed to run every 5 minutes via crontab.

require_once __DIR__ . '/../backend/bootstrap.php';

$db = getDB();

// Avoid duplicate: don't dispatch if a CheckReadStatusJob is already pending/processing
$stmt = $db->query(
    "SELECT COUNT(*) FROM job_queue
     WHERE job_class = 'CheckReadStatusJob' AND status IN ('pending', 'processing')"
);

if ((int) $stmt->fetchColumn() > 0) {
    exit; // A job is already queued or running
}

$queueService = new \App\Services\QueueService($db);
$queueService->dispatch('CheckReadStatusJob', [], null, 8); // Low priority (8)

echo '[' . date('Y-m-d H:i:s') . "] CheckReadStatusJob dispatched.\n";
