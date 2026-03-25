<?php

namespace App\Models;

class LandingPage
{
    public function __construct(private \PDO $db)
    {
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM landing_pages WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR url LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = $filters['is_active'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM landing_pages {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT * FROM landing_pages {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
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

    public function findActive(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM landing_pages WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO landing_pages (name, html_content, url, capture_credentials, capture_input, is_active, created_at, updated_at)
             VALUES (:name, :html_content, :url, :capture_credentials, :capture_input, :is_active, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':html_content' => $data['html_content'],
            ':url' => $data['url'] ?? null,
            ':capture_credentials' => $data['capture_credentials'] ?? 0,
            ':capture_input' => $data['capture_input'] ?? 0,
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

        $stmt = $this->db->prepare("UPDATE landing_pages SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM landing_pages WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
