<?php

namespace App\Controllers;

class GroupController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/groups
     * List all groups with member counts.
     */
    public function index(): void
    {
        $stmt = $this->db->query(
            "SELECT g.*, COUNT(gm.recipient_id) AS member_count
             FROM `groups` g
             LEFT JOIN group_recipient gm ON gm.group_id = g.id
             GROUP BY g.id
             ORDER BY g.name ASC"
        );

        json_response(['data' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /**
     * POST /api/groups
     * Create a new group.
     */
    public function store(): void
    {
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            json_response(['error' => 'Group name is required'], 422);
            return;
        }

        $description = $input['description'] ?? null;
        $type        = $input['type'] ?? 'custom';
        $allowedTypes = ['team', 'category', 'custom'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'custom';
        }
        $color       = $input['color'] ?? null;

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO `groups` (name, description, type, color, created_at, updated_at)
                 VALUES (:name, :description, :type, :color, NOW(), NOW())"
            );
            $stmt->execute([
                ':name'        => $name,
                ':description' => $description,
                ':type'        => $type,
                ':color'       => $color,
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                json_response(['error' => 'Un groupe avec ce nom existe déjà'], 409);
                return;
            }
            throw $e;
        }

        $id = (int) $this->db->lastInsertId();

        audit_log('create_group', 'group', $id, ['name' => $name]);

        $group = $this->findGroupById($id);
        json_response($group, 201);
    }

    /**
     * PUT /api/groups/{id}
     * Update a group.
     */
    public function update($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }

        $input = get_json_input();

        $allowedFields = ['name', 'description', 'type', 'color'];
        $fields = [];
        $params = [':id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        if (empty($fields)) {
            json_response(['error' => 'No valid fields to update'], 422);
        }

        $fields[] = 'updated_at = NOW()';
        $fieldStr = implode(', ', $fields);

        $stmt = $this->db->prepare("UPDATE `groups` SET {$fieldStr} WHERE id = :id");
        $stmt->execute($params);

        audit_log('update_group', 'group', $id);

        $updated = $this->findGroupById($id);
        json_response($updated);
    }

    /**
     * DELETE /api/groups/{id}
     * Delete a group.
     */
    public function destroy($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }

        // Remove members first
        $stmt = $this->db->prepare('DELETE FROM group_recipient WHERE group_id = :id');
        $stmt->execute([':id' => $id]);

        $stmt = $this->db->prepare('DELETE FROM `groups` WHERE id = :id');
        $stmt->execute([':id' => $id]);

        audit_log('delete_group', 'group', $id, ['name' => $group['name']]);

        json_response(['message' => 'Group deleted successfully']);
    }

    /**
     * POST /api/groups/{id}/members
     * Add recipients to a group.
     */
    public function addMembers($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }

        $input = get_json_input();
        // Accept both recipient_ids (array) and recipient_id (single)
        $recipientIds = $input['recipient_ids'] ?? [];
        if (empty($recipientIds) && isset($input['recipient_id'])) {
            $recipientIds = [(int) $input['recipient_id']];
        }

        if (empty($recipientIds) || !is_array($recipientIds)) {
            json_response(['error' => 'recipient_ids array or recipient_id is required'], 422);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO group_recipient (group_id, recipient_id) VALUES (:group_id, :recipient_id)'
        );

        $added = 0;
        foreach ($recipientIds as $recipientId) {
            $stmt->execute([
                ':group_id'     => $id,
                ':recipient_id' => (int) $recipientId,
            ]);
            $added += $stmt->rowCount();
        }

        audit_log('add_group_recipient', 'group', $id, ['count' => $added]);

        json_response(['message' => "{$added} member(s) added", 'added' => $added]);
    }

    /**
     * DELETE /api/groups/{id}/members
     * Remove recipients from a group.
     */
    public function removeMembers($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }

        $input = get_json_input();
        $recipientIds = $input['recipient_ids'] ?? [];

        if (empty($recipientIds) || !is_array($recipientIds)) {
            json_response(['error' => 'recipient_ids array is required'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
        $params = array_merge([$id], array_map('intval', $recipientIds));

        $stmt = $this->db->prepare(
            "DELETE FROM group_recipient WHERE group_id = ? AND recipient_id IN ({$placeholders})"
        );
        $stmt->execute($params);

        $removed = $stmt->rowCount();

        audit_log('remove_group_recipient', 'group', $id, ['count' => $removed]);

        json_response(['message' => "{$removed} member(s) removed", 'removed' => $removed]);
    }

    /**
     * GET /api/groups/{id}
     */
    public function show($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM group_recipient WHERE group_id = :id');
        $stmt->execute([':id' => $id]);
        $group['member_count'] = (int) $stmt->fetchColumn();
        json_response($group);
    }

    /**
     * GET /api/groups/{id}/members
     */
    public function members($id): void
    {
        $id = (int) $id;
        $group = $this->findGroupById($id);
        if (!$group) {
            json_response(['error' => 'Group not found'], 404);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM group_recipient WHERE group_id = :id');
        $countStmt->execute([':id' => $id]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT r.*, CONCAT(COALESCE(r.first_name,''), ' ', COALESCE(r.last_name,'')) AS full_name
             FROM recipients r
             JOIN group_recipient gr ON gr.recipient_id = r.id
             WHERE gr.group_id = :id
             ORDER BY r.last_name ASC, r.first_name ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        json_response([
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * DELETE /api/groups/{id}/members/{memberId}
     */
    public function removeSingleMember($id, $memberId): void
    {
        $id = (int) $id;
        $memberId = (int) $memberId;
        $stmt = $this->db->prepare('DELETE FROM group_recipient WHERE group_id = :gid AND recipient_id = :rid');
        $stmt->execute([':gid' => $id, ':rid' => $memberId]);
        json_response(['message' => 'Member removed']);
    }

    /**
     * Helper: find a group by ID.
     */
    private function findGroupById($id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM `groups` WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
