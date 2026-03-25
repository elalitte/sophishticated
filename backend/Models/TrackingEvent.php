<?php

namespace App\Models;

class TrackingEvent
{
    public function __construct(private \PDO $db)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tracking_events (campaign_recipient_id, event_type, ip_address, user_agent, extra_data, created_at)
             VALUES (:campaign_recipient_id, :event_type, :ip_address, :user_agent, :extra_data, NOW())'
        );
        $stmt->execute([
            ':campaign_recipient_id' => $data['campaign_recipient_id'],
            ':event_type' => $data['event_type'],
            ':ip_address' => $data['ip_address'] ?? null,
            ':user_agent' => $data['user_agent'] ?? null,
            ':extra_data' => $data['extra_data'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findByCampaignRecipient(int $campaignRecipientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tracking_events
             WHERE campaign_recipient_id = :campaign_recipient_id
             ORDER BY created_at ASC'
        );
        $stmt->execute([':campaign_recipient_id' => $campaignRecipientId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findByCampaign(int $campaignId, array $filters = []): array
    {
        $where = ['cr.campaign_id = :campaign_id'];
        $params = [':campaign_id' => $campaignId];

        if (!empty($filters['event_type'])) {
            $where[] = 'te.event_type = :event_type';
            $params[':event_type'] = $filters['event_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'te.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'te.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $this->db->prepare(
            "SELECT te.*, cr.recipient_id, cr.unique_token
             FROM tracking_events te
             INNER JOIN campaign_recipients cr ON cr.id = te.campaign_recipient_id
             {$whereClause}
             ORDER BY te.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTimeline(int $campaignId, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT te.*, r.email, r.first_name, r.last_name, cr.unique_token
             FROM tracking_events te
             INNER JOIN campaign_recipients cr ON cr.id = te.campaign_recipient_id
             INNER JOIN recipients r ON r.id = cr.recipient_id
             WHERE cr.campaign_id = :campaign_id
             ORDER BY te.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':campaign_id', $campaignId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
