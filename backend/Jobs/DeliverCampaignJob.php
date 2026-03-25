<?php

require_once __DIR__ . '/../Services/QueueService.php';

use App\Services\QueueService;

class DeliverCampaignJob
{
    /**
     * Start delivering a campaign by dispatching individual mail jobs.
     *
     * - Sets campaign status to 'running'
     * - Queues a DeliverSingleMailJob for each pending recipient
     * - Applies staggered delays when send_mode='staggered'
     */
    public function handle(array $payload, \PDO $db): void
    {
        $campaignId = (int) ($payload['campaign_id'] ?? 0);

        if ($campaignId <= 0) {
            throw new \RuntimeException('DeliverCampaignJob: missing or invalid campaign_id');
        }

        // ── Update campaign status to running ───────────────────────────
        $stmt = $db->prepare(
            "UPDATE campaigns SET status = 'running', started_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $campaignId]);

        // ── Load campaign details ───────────────────────────────────────
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $campaignId]);
        $campaign = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$campaign) {
            throw new \RuntimeException("DeliverCampaignJob: campaign #{$campaignId} not found");
        }

        $sendMode        = $campaign['send_mode'] ?? 'immediate';
        // stagger_minutes stores the interval in seconds (UI label says "secondes")
        $staggerSeconds  = (int) ($campaign['stagger_minutes'] ?? 60);

        // ── Get all pending campaign recipients ─────────────────────────
        $stmt = $db->prepare(
            "SELECT id FROM campaign_recipients
             WHERE campaign_id = :campaign_id AND mail_status = 'pending'
             ORDER BY id ASC"
        );
        $stmt->execute([':campaign_id' => $campaignId]);
        $recipients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Dispatch individual mail jobs ───────────────────────────────
        $queueService = new QueueService($db);

        foreach ($recipients as $index => $cr) {
            if ($sendMode === 'staggered') {
                $delay = $index * $staggerSeconds;
            } else {
                // immediate: 1 second between each to avoid burst
                $delay = $index * 1;
            }

            $queueService->dispatchWithDelay(
                'DeliverSingleMailJob',
                ['campaign_recipient_id' => (int) $cr['id']],
                $delay,
                3 // higher priority than default
            );
        }
    }
}
