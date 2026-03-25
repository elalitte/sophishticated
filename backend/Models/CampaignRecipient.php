<?php

namespace App\Models;

class CampaignRecipient
{
    public function __construct(private \PDO $db)
    {
    }

    public function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM campaign_recipients WHERE unique_token = :token');
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM campaign_recipients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        $fieldStr = implode(', ', $fields);

        $stmt = $this->db->prepare("UPDATE campaign_recipients SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function getByCampaign(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT cr.*, r.email, r.first_name, r.last_name
             FROM campaign_recipients cr
             INNER JOIN recipients r ON r.id = cr.recipient_id
             WHERE cr.campaign_id = :campaign_id
             ORDER BY r.last_name ASC, r.first_name ASC'
        );
        $stmt->execute([':campaign_id' => $campaignId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countByStatus(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT mail_status, COUNT(*) AS count
             FROM campaign_recipients
             WHERE campaign_id = :campaign_id
             GROUP BY mail_status'
        );
        $stmt->execute([':campaign_id' => $campaignId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['mail_status']] = (int) $row['count'];
        }

        return $counts;
    }
}
