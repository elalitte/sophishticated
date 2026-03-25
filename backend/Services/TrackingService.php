<?php

namespace App\Services;

class TrackingService
{
    public function __construct(private \PDO $db)
    {
    }

    // ─── Public tracking methods ────────────────────────────────────

    /**
     * Record that a recipient opened the email (tracking pixel loaded).
     */
    public function recordOpen(string $token, ?string $ip, ?string $userAgent): void
    {
        $cr = $this->findByToken($token);
        if ($cr === false) {
            return;
        }

        $isFirstOpen = (int) $cr['opened'] === 0;

        $stmt = $this->db->prepare(
            'UPDATE campaign_recipients
             SET opened      = 1,
                 opened_at   = COALESCE(opened_at, NOW()),
                 open_count  = open_count + 1,
                 ip_address  = COALESCE(ip_address, :ip),
                 user_agent  = COALESCE(user_agent, :ua)
             WHERE id = :id'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':ua' => $userAgent,
            ':id' => $cr['id'],
        ]);

        $this->insertEvent($cr['id'], 'opened', null, $ip, $userAgent);

        WebSocketBroadcaster::broadcast('campaign.' . $cr['campaign_id'], 'email.opened', [
            'campaign_recipient_id' => $cr['id'],
            'campaign_id'           => $cr['campaign_id'],
            'recipient_id'          => $cr['recipient_id'],
            'first_open'            => $isFirstOpen,
            'open_count'            => (int) $cr['open_count'] + 1,
            'timestamp'             => date('c'),
        ]);
        WebSocketBroadcaster::broadcast('campaigns', 'stats.updated', [
            'campaign_id' => $cr['campaign_id'],
            'event'       => 'email.opened',
        ]);
    }

    /**
     * Record that a recipient clicked the phishing link.
     */
    public function recordClick(string $token, ?string $ip, ?string $userAgent): void
    {
        $cr = $this->findByToken($token);
        if ($cr === false) {
            return;
        }

        $isFirstClick = (int) $cr['clicked'] === 0;

        // Also mark as opened if not already
        $stmt = $this->db->prepare(
            'UPDATE campaign_recipients
             SET opened      = 1,
                 opened_at   = COALESCE(opened_at, NOW()),
                 clicked     = 1,
                 clicked_at  = COALESCE(clicked_at, NOW()),
                 click_count = click_count + 1,
                 ip_address  = COALESCE(ip_address, :ip),
                 user_agent  = COALESCE(user_agent, :ua)
             WHERE id = :id'
        );
        $stmt->execute([
            ':ip' => $ip,
            ':ua' => $userAgent,
            ':id' => $cr['id'],
        ]);

        $this->insertEvent($cr['id'], 'clicked', null, $ip, $userAgent);

        WebSocketBroadcaster::broadcast('campaign.' . $cr['campaign_id'], 'link.clicked', [
            'campaign_recipient_id' => $cr['id'],
            'campaign_id'           => $cr['campaign_id'],
            'recipient_id'          => $cr['recipient_id'],
            'first_click'           => $isFirstClick,
            'click_count'           => (int) $cr['click_count'] + 1,
            'timestamp'             => date('c'),
        ]);
        WebSocketBroadcaster::broadcast('campaigns', 'stats.updated', [
            'campaign_id' => $cr['campaign_id'],
            'event'       => 'link.clicked',
        ]);
    }

    /**
     * Record that a recipient visited the landing page.
     */
    public function recordPageVisit(string $token, ?string $ip, ?string $userAgent): void
    {
        $cr = $this->findByToken($token);
        if ($cr === false) {
            return;
        }

        $this->insertEvent($cr['id'], 'page_visited', null, $ip, $userAgent);

        WebSocketBroadcaster::broadcast('campaign.' . $cr['campaign_id'], 'page.visited', [
            'campaign_recipient_id' => $cr['id'],
            'campaign_id'           => $cr['campaign_id'],
            'recipient_id'          => $cr['recipient_id'],
            'timestamp'             => date('c'),
        ]);
        WebSocketBroadcaster::broadcast('campaigns', 'stats.updated', [
            'campaign_id' => $cr['campaign_id'],
            'event'       => 'page.visited',
        ]);
    }

    /**
     * Record that a recipient submitted credentials on the landing page.
     *
     * SECURITY: credentials are NEVER stored in clear text.
     * Only field names, filled status, lengths, and SHA-256 hashes are persisted.
     */
    public function recordSubmission(string $token, array $formData, ?string $ip, ?string $userAgent): void
    {
        $cr = $this->findByToken($token);
        if ($cr === false) {
            return;
        }

        $isFirstSubmission = (int) $cr['submitted_credentials'] === 0;

        // Sanitise form data – never store raw credentials
        $sanitisedFields = [];
        foreach ($formData as $fieldName => $fieldValue) {
            $entry = [
                'name'   => $fieldName,
                'filled' => !empty($fieldValue),
                'length' => is_string($fieldValue) ? mb_strlen($fieldValue) : 0,
            ];

            // Sensitive fields get a SHA-256 hash for deduplication/analysis only
            $sensitiveNames = ['password', 'passwd', 'pass', 'mot_de_passe', 'mdp', 'secret', 'pin', 'code'];
            if (in_array(strtolower($fieldName), $sensitiveNames, true) && is_string($fieldValue)) {
                $entry['hash_sha256'] = hash('sha256', $fieldValue);
            }

            $sanitisedFields[] = $entry;
        }

        $submittedData = json_encode(['fields' => $sanitisedFields], JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare(
            'UPDATE campaign_recipients
             SET opened                = 1,
                 opened_at             = COALESCE(opened_at, NOW()),
                 clicked               = 1,
                 clicked_at            = COALESCE(clicked_at, NOW()),
                 submitted_credentials = 1,
                 submitted_at          = COALESCE(submitted_at, NOW()),
                 submission_count      = submission_count + 1,
                 submitted_data        = :data,
                 ip_address            = COALESCE(ip_address, :ip),
                 user_agent            = COALESCE(user_agent, :ua)
             WHERE id = :id'
        );
        $stmt->execute([
            ':data' => $submittedData,
            ':ip'   => $ip,
            ':ua'   => $userAgent,
            ':id'   => $cr['id'],
        ]);

        $this->insertEvent($cr['id'], 'submitted', ['field_count' => count($sanitisedFields)], $ip, $userAgent);

        WebSocketBroadcaster::broadcast('campaign.' . $cr['campaign_id'], 'credentials.submitted', [
            'campaign_recipient_id' => $cr['id'],
            'campaign_id'           => $cr['campaign_id'],
            'recipient_id'          => $cr['recipient_id'],
            'first_submission'      => $isFirstSubmission,
            'submission_count'      => (int) $cr['submission_count'] + 1,
            'field_count'           => count($sanitisedFields),
            'timestamp'             => date('c'),
        ]);
        WebSocketBroadcaster::broadcast('campaigns', 'stats.updated', [
            'campaign_id' => $cr['campaign_id'],
            'event'       => 'credentials.submitted',
        ]);
    }

    // ─── Private helpers ────────────────────────────────────────────

    /**
     * Find a campaign_recipient row by its unique tracking token.
     */
    private function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM campaign_recipients WHERE unique_token = :token'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Insert a row into the tracking_events table.
     */
    private function insertEvent(
        int     $campaignRecipientId,
        string  $eventType,
        ?array  $eventData,
        ?string $ip,
        ?string $userAgent
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO tracking_events (campaign_recipient_id, event_type, event_data, ip_address, user_agent)
             VALUES (:cr_id, :type, :data, :ip, :ua)'
        );
        $stmt->execute([
            ':cr_id' => $campaignRecipientId,
            ':type'  => $eventType,
            ':data'  => $eventData !== null ? json_encode($eventData) : null,
            ':ip'    => $ip,
            ':ua'    => $userAgent,
        ]);
    }
}
