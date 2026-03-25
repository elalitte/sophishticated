<?php

/**
 * Queue Worker – CLI process that continuously processes jobs from the job_queue table.
 *
 * Usage:  php worker/worker.php
 *
 * Supports graceful shutdown via SIGTERM.
 * Uses SELECT ... FOR UPDATE SKIP LOCKED for safe concurrent workers.
 */

// ── Bootstrap ───────────────────────────────────────────────────────────
require_once __DIR__ . '/../backend/bootstrap.php';

$db = getDB();

// ── Graceful shutdown ───────────────────────────────────────────────────
$shouldRun = true;

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$shouldRun) {
        echo '[' . date('Y-m-d H:i:s') . "] SIGTERM received – shutting down gracefully...\n";
        $shouldRun = false;
    });

    pcntl_signal(SIGINT, function () use (&$shouldRun) {
        echo '[' . date('Y-m-d H:i:s') . "] SIGINT received – shutting down gracefully...\n";
        $shouldRun = false;
    });
}

echo '[' . date('Y-m-d H:i:s') . "] Worker started. Waiting for jobs...\n";

// ── Job class mapping ───────────────────────────────────────────────────
$jobClassMap = [
    'SyncRecipientsJob'    => __DIR__ . '/../backend/Jobs/SyncRecipientsJob.php',
    'DeliverCampaignJob'   => __DIR__ . '/../backend/Jobs/DeliverCampaignJob.php',
    'DeliverSingleMailJob' => __DIR__ . '/../backend/Jobs/DeliverSingleMailJob.php',
    'CheckReadStatusJob'   => __DIR__ . '/../backend/Jobs/CheckReadStatusJob.php',
];

// ── Main loop ───────────────────────────────────────────────────────────
while ($shouldRun) {
    try {
        // Start transaction for row-level locking
        $db->beginTransaction();

        // Fetch the next available job
        $stmt = $db->prepare(
            "SELECT * FROM job_queue
             WHERE status = 'pending' AND available_at <= NOW()
             ORDER BY priority ASC, created_at ASC
             LIMIT 1
             FOR UPDATE SKIP LOCKED"
        );
        $stmt->execute();
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            $db->commit();
            sleep(2);

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            continue;
        }

        // Mark as processing
        $stmt = $db->prepare(
            "UPDATE job_queue
             SET status = 'processing', started_at = NOW(), attempts = attempts + 1
             WHERE id = :id"
        );
        $stmt->execute([':id' => $job['id']]);

        $db->commit();

        // ── Resolve and instantiate the job class ───────────────────
        $jobClass = $job['job_class'];
        $payload  = json_decode($job['payload'] ?? '{}', true) ?: [];
        $attempts = (int) $job['attempts'] + 1; // +1 because we just incremented

        echo '[' . date('Y-m-d H:i:s') . "] Processing job #{$job['id']} ({$jobClass}), attempt #{$attempts}...\n";

        if (!isset($jobClassMap[$jobClass])) {
            throw new \RuntimeException("Unknown job class: {$jobClass}");
        }

        require_once $jobClassMap[$jobClass];

        if (!class_exists($jobClass)) {
            throw new \RuntimeException("Class {$jobClass} not found after requiring file");
        }

        $instance = new $jobClass();

        // ── Execute the job ─────────────────────────────────────────
        try {
            $instance->handle($payload, $db);

            // Success
            $stmt = $db->prepare(
                "UPDATE job_queue SET status = 'completed', completed_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => $job['id']]);

            echo '[' . date('Y-m-d H:i:s') . "] Job #{$job['id']} ({$jobClass}) completed successfully.\n";
        } catch (\Throwable $e) {
            $maxAttempts = (int) ($job['max_attempts'] ?? 3);

            if ($attempts < $maxAttempts) {
                // Retry with exponential backoff: attempts * 30 seconds
                $backoffSeconds = $attempts * 30;
                $availableAt    = date('Y-m-d H:i:s', time() + $backoffSeconds);

                $stmt = $db->prepare(
                    "UPDATE job_queue
                     SET status = 'pending', available_at = :available_at, error_message = :error
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':available_at' => $availableAt,
                    ':error'        => mb_substr($e->getMessage(), 0, 5000),
                    ':id'           => $job['id'],
                ]);

                echo '[' . date('Y-m-d H:i:s') . "] Job #{$job['id']} ({$jobClass}) failed (attempt {$attempts}/{$maxAttempts}). "
                    . "Retrying in {$backoffSeconds}s. Error: {$e->getMessage()}\n";
            } else {
                // Permanently failed
                $stmt = $db->prepare(
                    "UPDATE job_queue
                     SET status = 'failed', error_message = :error
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':error' => mb_substr($e->getMessage(), 0, 5000),
                    ':id'    => $job['id'],
                ]);

                echo '[' . date('Y-m-d H:i:s') . "] Job #{$job['id']} ({$jobClass}) permanently failed after {$attempts} attempts. "
                    . "Error: {$e->getMessage()}\n";
            }
        }
    } catch (\Throwable $e) {
        // Catch errors in the outer loop (e.g. DB connection issues)
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo '[' . date('Y-m-d H:i:s') . "] Worker error: {$e->getMessage()}\n";
        sleep(5);
    }

    // Dispatch pending signals
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}

echo '[' . date('Y-m-d H:i:s') . "] Worker stopped.\n";
