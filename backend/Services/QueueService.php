<?php

namespace App\Services;

class QueueService
{
    public function __construct(private \PDO $db)
    {
    }

    /**
     * Dispatch a job onto the queue.
     *
     * @param  string      $jobClass    Fully-qualified class name of the job
     * @param  array       $payload     Data to pass to the job
     * @param  string|null $availableAt ISO 8601 datetime when the job becomes available (null = now)
     * @param  int         $priority    1 (highest) to 10 (lowest), default 5
     * @return int         The ID of the newly created job
     */
    public function dispatch(
        string  $jobClass,
        array   $payload,
        ?string $availableAt = null,
        int     $priority    = 5
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO job_queue (job_class, payload, status, priority, available_at, created_at)
             VALUES (:job_class, :payload, :status, :priority, :available_at, NOW())'
        );

        $stmt->execute([
            ':job_class'    => $jobClass,
            ':payload'      => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':status'       => 'pending',
            ':priority'     => $priority,
            ':available_at' => $availableAt ?? date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Dispatch a job with a delay in seconds.
     *
     * @param  string $jobClass     Fully-qualified class name of the job
     * @param  array  $payload      Data to pass to the job
     * @param  int    $delaySeconds Number of seconds to wait before the job is available
     * @param  int    $priority     1 (highest) to 10 (lowest), default 5
     * @return int    The ID of the newly created job
     */
    public function dispatchWithDelay(
        string $jobClass,
        array  $payload,
        int    $delaySeconds,
        int    $priority = 5
    ): int {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        return $this->dispatch($jobClass, $payload, $availableAt, $priority);
    }
}
