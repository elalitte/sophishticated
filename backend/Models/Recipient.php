<?php

namespace App\Models;

class Recipient
{
    public function __construct(private \PDO $db)
    {
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM recipients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM recipients WHERE email = :email');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByGraphId(string $graphId): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM recipients WHERE graph_id = :graph_id');
        $stmt->execute([':graph_id' => $graphId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(r.first_name LIKE :search OR r.last_name LIKE :search2 OR r.email LIKE :search3 OR r.department LIKE :search4)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
            $params[':search3'] = '%' . $filters['search'] . '%';
            $params[':search4'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'r.is_active = :is_active';
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['group_id'])) {
            $where[] = 'r.id IN (SELECT recipient_id FROM group_recipient WHERE group_id = :group_id)';
            $params[':group_id'] = $filters['group_id'];
        }

        if (!empty($filters['group_ids']) && is_array($filters['group_ids'])) {
            $placeholders = [];
            foreach ($filters['group_ids'] as $i => $gid) {
                $key = ':gid' . $i;
                $placeholders[] = $key;
                $params[$key] = $gid;
            }
            $in = implode(',', $placeholders);
            $where[] = "r.id IN (SELECT recipient_id FROM group_recipient WHERE group_id IN ({$in}))";
        }

        if (!empty($filters['department'])) {
            $where[] = 'r.department = :department';
            $params[':department'] = $filters['department'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM recipients r {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT r.*, TRIM(CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,''))) AS full_name FROM recipients r {$whereClause} ORDER BY r.last_name ASC, r.first_name ASC LIMIT :limit OFFSET :offset"
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
            'INSERT INTO recipients (graph_id, email, first_name, last_name, department, job_title, is_active, created_at, updated_at)
             VALUES (:graph_id, :email, :first_name, :last_name, :department, :job_title, :is_active, NOW(), NOW())'
        );
        $stmt->execute([
            ':graph_id' => $data['graph_id'] ?? null,
            ':email' => $data['email'],
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':department' => $data['department'] ?? null,
            ':job_title' => $data['job_title'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
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

        $stmt = $this->db->prepare("UPDATE recipients SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM recipients WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getGroups(int $recipientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.* FROM `groups` g
             INNER JOIN group_recipient gm ON g.id = gm.group_id
             WHERE gm.recipient_id = :recipient_id
             ORDER BY g.name ASC'
        );
        $stmt->execute([':recipient_id' => $recipientId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCampaignHistory(int $recipientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id AS campaign_id, c.name AS campaign_name, c.status AS campaign_status,
                    cr.mail_status, cr.opened_at, cr.clicked_at, cr.submitted_at,
                    c.scheduled_at, c.started_at
             FROM campaign_recipients cr
             INNER JOIN campaigns c ON c.id = cr.campaign_id
             WHERE cr.recipient_id = :recipient_id
             ORDER BY c.created_at DESC'
        );
        $stmt->execute([':recipient_id' => $recipientId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function bulkUpsertFromGraph(array $users): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recipients (graph_id, email, first_name, last_name, department, job_title, is_active, created_at, updated_at)
             VALUES (:graph_id, :email, :first_name, :last_name, :department, :job_title, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                department = VALUES(department),
                job_title = VALUES(job_title),
                updated_at = NOW()'
        );

        $count = 0;
        foreach ($users as $user) {
            $stmt->execute([
                ':graph_id' => $user['graph_id'],
                ':email' => $user['email'],
                ':first_name' => $user['first_name'] ?? '',
                ':last_name' => $user['last_name'] ?? '',
                ':department' => $user['department'] ?? null,
                ':job_title' => $user['job_title'] ?? null,
            ]);
            $count++;
        }

        return $count;
    }
}
