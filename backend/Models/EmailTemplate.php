<?php

namespace App\Models;

class EmailTemplate
{
    public function __construct(private \PDO $db)
    {
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['difficulty'])) {
            $where[] = 'difficulty = :difficulty';
            $params[':difficulty'] = $filters['difficulty'];
        }

        if (!empty($filters['tags'])) {
            $where[] = 'tags LIKE :tags';
            $params[':tags'] = '%' . $filters['tags'] . '%';
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR subject LIKE :search2)';
            $params[':search'] = '%' . $filters['search'] . '%';
            $params[':search2'] = '%' . $filters['search'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM email_templates {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit'] = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT * FROM email_templates {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
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
        $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO email_templates (name, subject, body_html, body_text, sender_name, sender_email, difficulty, tags, is_active, created_at, updated_at)
             VALUES (:name, :subject, :body_html, :body_text, :sender_name, :sender_email, :difficulty, :tags, :is_active, NOW(), NOW())'
        );
        $stmt->execute([
            ':name' => $data['name'],
            ':subject' => $data['subject'],
            ':body_html' => $data['body_html'],
            ':body_text' => $data['body_text'] ?? null,
            ':sender_name' => $data['sender_name'] ?? null,
            ':sender_email' => $data['sender_email'] ?? null,
            ':difficulty' => $data['difficulty'] ?? 'medium',
            ':tags' => $data['tags'] ?? null,
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

        $stmt = $this->db->prepare("UPDATE email_templates SET {$fieldStr} WHERE id = :id");
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM email_templates WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}
