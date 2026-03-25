<?php

namespace App\Models;

class AuditLog
{
    public function __construct(private \PDO $db)
    {
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, created_at)
             VALUES (:user_id, :action, :entity_type, :entity_id, :old_values, :new_values, :ip_address, NOW())'
        );
        $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':action' => $data['action'],
            ':entity_type' => $data['entity_type'] ?? null,
            ':entity_id' => $data['entity_id'] ?? null,
            ':old_values' => $data['old_values'] ?? null,
            ':new_values' => $data['new_values'] ?? null,
            ':ip_address' => $data['ip_address'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'al.action = :action';
            $params[':action'] = $filters['action'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = :user_id';
            $params[':user_id'] = $filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = :entity_type';
            $params[':entity_type'] = $filters['entity_type'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'al.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'al.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs al {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT al.*, u.username
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
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
}
