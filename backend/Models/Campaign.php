<?php

namespace App\Models;

class Campaign
{
    public function __construct(private \PDO $db)
    {
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM campaigns WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'name LIKE :search';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM campaigns {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT * FROM campaigns {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO campaigns (name, description, email_template_id, landing_page_id, status, scheduled_at, created_by, created_at, updated_at)
             VALUES (:name, :description, :email_template_id, :landing_page_id, :status, :scheduled_at, :created_by, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':email_template_id' => $data['email_template_id'],
            ':landing_page_id' => $data['landing_page_id'] ?? null,
            ':status' => $data['status'] ?? 'draft',
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':created_by' => $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
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

        $fields[] = 'updated_at = NOW()';
        $fieldStr = implode(', ', $fields);

        $stmt = $this->db->prepare("UPDATE campaigns SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM campaigns WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE campaigns SET status = :status, updated_at = NOW() WHERE id = :id');
        return $stmt->execute([':id' => $id, ':status' => $status]);
    }

    public function getRecipients(int $campaignId, int $page = 1, int $perPage = 20): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = :campaign_id');
        $countStmt->execute([':campaign_id' => $campaignId]);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT cr.*, r.email, r.first_name, r.last_name, r.department
             FROM campaign_recipients cr
             INNER JOIN recipients r ON r.id = cr.recipient_id
             WHERE cr.campaign_id = :campaign_id
             ORDER BY r.last_name ASC, r.first_name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':campaign_id', $campaignId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function addRecipients(int $campaignId, array $recipientIds): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO campaign_recipients (campaign_id, recipient_id, unique_token, mail_status, created_at)
             VALUES (:campaign_id, :recipient_id, :unique_token, :mail_status, NOW())'
        );

        foreach ($recipientIds as $recipientId) {
            $stmt->execute([
                ':campaign_id' => $campaignId,
                ':recipient_id' => $recipientId,
                ':unique_token' => generate_uuid(),
                ':mail_status' => 'pending',
            ]);
        }
    }

    public function getStats(int $campaignId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN mail_status IN ("sent", "delivered", "opened", "clicked") THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked,
                SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submitted
             FROM campaign_recipients
             WHERE campaign_id = :campaign_id'
        );
        $stmt->execute([':campaign_id' => $campaignId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'opened' => (int) ($row['opened'] ?? 0),
            'clicked' => (int) ($row['clicked'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
        ];
    }
}
