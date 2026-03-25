<?php

require_once __DIR__ . '/../Services/MicrosoftGraphService.php';
require_once __DIR__ . '/../Services/TrackingService.php';
require_once __DIR__ . '/../Services/WebSocketBroadcaster.php';

use App\Services\MicrosoftGraphService;
use App\Services\TrackingService;

/**
 * Polls Microsoft Graph API to detect email opens via the isRead flag.
 *
 * This complements pixel/CSS/font tracking for clients that block all
 * external content. Processes campaign_recipients that have been delivered
 * but not yet marked as opened and have a stored Graph message ID.
 */
class CheckReadStatusJob
{
    public function handle(array $payload, \PDO $db): void
    {
        $graphService    = new MicrosoftGraphService();
        $trackingService = new TrackingService($db);

        // Fetch all delivered-but-unopened recipients that have a Graph message ID
        $stmt = $db->query(
            "SELECT cr.id, cr.unique_token, cr.graph_message_id, r.email
             FROM campaign_recipients cr
             JOIN recipients r ON r.id = cr.recipient_id
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE cr.mail_status = 'delivered'
               AND cr.opened = 0
               AND cr.graph_message_id IS NOT NULL
               AND c.status IN ('running', 'completed', 'paused')
             ORDER BY cr.delivered_at ASC
             LIMIT 200"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return;
        }

        $openedCount = 0;

        foreach ($rows as $row) {
            try {
                $isRead = $graphService->isMessageRead($row['email'], $row['graph_message_id']);

                if ($isRead === true) {
                    $trackingService->recordOpen($row['unique_token'], 'graph-api', 'Microsoft Graph isRead');
                    $openedCount++;
                }
            } catch (\Throwable $e) {
                // Log and continue with next recipient – don't let one failure block the batch
                error_log("CheckReadStatusJob: error checking message {$row['graph_message_id']} for {$row['email']}: {$e->getMessage()}");
            }

            // Small delay to respect Graph API rate limits (≈2 requests/sec)
            usleep(500000);
        }

        if ($openedCount > 0) {
            error_log("CheckReadStatusJob: detected {$openedCount} email open(s) via Graph API");
        }
    }
}
