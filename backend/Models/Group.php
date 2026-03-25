<?php

namespace App\Models;

class Group
{
    public function __construct(private \PDO $db)
    {
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR description LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['type'])) {
            $where[] = 'type = :type';
            $params[':type'] = $filters['type'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM `groups` {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT * FROM `groups` {$whereClause} ORDER BY name ASC LIMIT :limit OFFSET :offset"
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
            'INSERT INTO `groups` (name, description, type, created_at, updated_at)
             VALUES (:name, :description, :type, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':type' => $data['type'] ?? 'manual',
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

        $stmt = $this->db->prepare("UPDATE `groups` SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM `groups` WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function getMembers(int $groupId, int $page = 1, int $perPage = 20): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM group_recipient WHERE group_id = :group_id');
        $countStmt->execute([':group_id' => $groupId]);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;

        $stmt = $this->db->prepare(
            'SELECT r.* FROM recipients r
             INNER JOIN group_recipient gm ON r.id = gm.recipient_id
             WHERE gm.group_id = :group_id
             ORDER BY r.last_name ASC, r.first_name ASC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':group_id', $groupId, \PDO::PARAM_INT);
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

    public function getMemberCount(int $groupId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM group_recipient WHERE group_id = :group_id');
        $stmt->execute([':group_id' => $groupId]);
        return (int) $stmt->fetchColumn();
    }

    public function addMembers(int $groupId, array $recipientIds): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO group_recipient (group_id, recipient_id, added_at) VALUES (:group_id, :recipient_id, NOW())'
        );

        foreach ($recipientIds as $recipientId) {
            $stmt->execute([
                ':group_id' => $groupId,
                ':recipient_id' => $recipientId,
            ]);
        }
    }

    public function removeMembers(int $groupId, array $recipientIds): void
    {
        if (empty($recipientIds)) {
            return;
        }

        $placeholders = [];
        $params = [':group_id' => $groupId];

        foreach ($recipientIds as $i => $recipientId) {
            $key = ":recipient_id_{$i}";
            $placeholders[] = $key;
            $params[$key] = $recipientId;
        }

        $in = implode(', ', $placeholders);
        $stmt = $this->db->prepare(
            "DELETE FROM group_recipient WHERE group_id = :group_id AND recipient_id IN ({$in})"
        );
        $stmt->execute($params);
    }
}
