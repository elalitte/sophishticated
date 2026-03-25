<?php

require_once __DIR__ . '/../Services/MicrosoftGraphService.php';
require_once __DIR__ . '/../Services/CampaignService.php';
require_once __DIR__ . '/../Services/WebSocketBroadcaster.php';

use App\Services\MicrosoftGraphService;
use App\Services\CampaignService;
use App\Services\WebSocketBroadcaster;

class DeliverSingleMailJob
{
    /**
     * Deliver a single phishing email to one campaign recipient.
     *
     * - Personalises the email template
     * - Creates the mail in the recipient's inbox via Graph API
     * - Records tracking events and broadcasts WebSocket updates
     * - Checks if the entire campaign is complete after each delivery
     */
    public function handle(array $payload, \PDO $db): void
    {
        $crId = (int) ($payload['campaign_recipient_id'] ?? 0);

        if ($crId <= 0) {
            throw new \RuntimeException('DeliverSingleMailJob: missing or invalid campaign_recipient_id');
        }

        // ── Load campaign_recipient ─────────────────────────────────────
        $stmt = $db->prepare('SELECT * FROM campaign_recipients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $crId]);
        $cr = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cr) {
            throw new \RuntimeException("DeliverSingleMailJob: campaign_recipient #{$crId} not found");
        }

        // ── Load recipient ──────────────────────────────────────────────
        $stmt = $db->prepare('SELECT * FROM recipients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $cr['recipient_id']]);
        $recipient = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$recipient) {
            throw new \RuntimeException("DeliverSingleMailJob: recipient #{$cr['recipient_id']} not found");
        }

        // ── Load campaign ───────────────────────────────────────────────
        $stmt = $db->prepare('SELECT * FROM campaigns WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $cr['campaign_id']]);
        $campaign = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$campaign) {
            throw new \RuntimeException("DeliverSingleMailJob: campaign #{$cr['campaign_id']} not found");
        }

        // ── Load email template (per-recipient or campaign-level fallback) ──
        $templateId = $cr['email_template_id'] ?? $campaign['email_template_id'];
        $stmt = $db->prepare('SELECT * FROM email_templates WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $templateId]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$template) {
            throw new \RuntimeException("DeliverSingleMailJob: email_template #{$templateId} not found");
        }

        // ── Personalise template ────────────────────────────────────────
        $campaignService  = new CampaignService($db);
        $personalizedHtml = $campaignService->personalizeTemplate(
            $template['html_body'],
            $recipient,
            $cr['unique_token'],
            $campaign['phishing_domain'] ?? null
        );

        // Personalise subject (recipient variables + date variables)
        $personalizedSubject = $campaignService->personalizeSubject(
            $template['subject'],
            $recipient
        );

        // ── Send mail via Graph API ─────────────────────────────────────
        $graphService = new MicrosoftGraphService();

        try {
            $success = $graphService->createMailInInbox(
                $recipient['email'],
                $personalizedSubject,
                $personalizedHtml,
                $template['sender_name'] ?? 'Notification',
                $template['sender_email'] ?? 'noreply@example.com'
            );

            if ($success) {
                // ── Success: update status and store Graph message ID ────
                $graphMessageId = is_string($success) ? $success : null;
                $stmt = $db->prepare(
                    "UPDATE campaign_recipients
                     SET mail_status = 'delivered', delivered_at = NOW(), graph_message_id = :msg_id
                     WHERE id = :id"
                );
                $stmt->execute([':id' => $crId, ':msg_id' => $graphMessageId]);

                // Insert tracking event
                $stmt = $db->prepare(
                    "INSERT INTO tracking_events (campaign_recipient_id, event_type, created_at)
                     VALUES (:cr_id, 'delivered', NOW())"
                );
                $stmt->execute([':cr_id' => $crId]);

                // Broadcast delivery event
                WebSocketBroadcaster::broadcast('campaign.' . $cr['campaign_id'], 'email.delivered', [
                    'campaign_recipient_id' => $crId,
                    'campaign_id'           => (int) $cr['campaign_id'],
                    'recipient_id'          => (int) $cr['recipient_id'],
                    'recipient_email'       => $recipient['email'],
                    'timestamp'             => date('c'),
                ]);
            } else {
                // ── Graph returned false (non-retryable failure) ────────
                $this->markFailed($db, $crId, 'Graph API returned false');
            }
        } catch (\Throwable $e) {
            $this->markFailed($db, $crId, $e->getMessage());
            throw $e; // Re-throw so the worker can retry if attempts remain
        }

        // ── Check if entire campaign is complete ────────────────────────
        $this->checkCampaignCompletion($db, (int) $cr['campaign_id']);
    }

    /**
     * Mark a campaign_recipient as failed.
     */
    private function markFailed(\PDO $db, int $crId, string $reason): void
    {
        $stmt = $db->prepare(
            "UPDATE campaign_recipients SET mail_status = 'failed' WHERE id = :id"
        );
        $stmt->execute([':id' => $crId]);

        error_log("DeliverSingleMailJob: delivery failed for campaign_recipient #{$crId} – {$reason}");
    }

    /**
     * If no more 'pending' recipients exist for this campaign, mark it as completed.
     */
    private function checkCampaignCompletion(\PDO $db, int $campaignId): void
    {
        $stmt = $db->prepare(
            "SELECT COUNT(*) AS pending_count
             FROM campaign_recipients
             WHERE campaign_id = :campaign_id AND mail_status = 'pending'"
        );
        $stmt->execute([':campaign_id' => $campaignId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ((int) $row['pending_count'] === 0) {
            $stmt = $db->prepare(
                "UPDATE campaigns SET status = 'completed', completed_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => $campaignId]);

            WebSocketBroadcaster::broadcast('campaign.' . $campaignId, 'campaign.completed', [
                'campaign_id' => $campaignId,
                'timestamp'   => date('c'),
            ]);
        }
    }
}
