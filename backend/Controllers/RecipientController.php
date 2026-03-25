<?php

namespace App\Controllers;

use App\Models\Recipient;
use App\Models\Group;
use App\Services\QueueService;

class RecipientController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/recipients
     * Paginated list with search/filter support.
     */
    public function index(): void
    {
        $filters = [];

        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }
        if (!empty($_GET['group_id'])) {
            $filters['group_id'] = (int) $_GET['group_id'];
        }
        // Frontend sends "groups" as comma-separated IDs
        if (!empty($_GET['groups'])) {
            $filters['group_ids'] = array_map('intval', explode(',', $_GET['groups']));
        }
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int) $_GET['is_active'];
        }
        // Frontend sends "active_only=1"
        if (!empty($_GET['active_only'])) {
            $filters['is_active'] = 1;
        }
        if (!empty($_GET['department'])) {
            $filters['department'] = $_GET['department'];
        }

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

        $recipientModel = new Recipient($this->db);
        $result = $recipientModel->findAll($filters, $page, $perPage);

        json_response($result);
    }

    /**
     * GET /api/recipients/{id}
     * Single recipient with their groups.
     */
    public function show($id): void
    {
        $id = (int) $id;
        $recipientModel = new Recipient($this->db);
        $recipient = $recipientModel->findById($id);

        if (!$recipient) {
            json_response(['error' => 'Recipient not found'], 404);
        }

        $recipient['groups'] = $recipientModel->getGroups($id);

        json_response($recipient);
    }

    /**
     * PUT /api/recipients/{id}
     * Update recipient fields.
     */
    public function update($id): void
    {
        $id = (int) $id;
        $recipientModel = new Recipient($this->db);
        $recipient = $recipientModel->findById($id);

        if (!$recipient) {
            json_response(['error' => 'Recipient not found'], 404);
        }

        $input = get_json_input();

        $allowedFields = ['email', 'first_name', 'last_name', 'department', 'job_title', 'is_active'];
        $data = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        // Cast is_active to integer (JS sends true/false)
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($data)) {
            json_response(['error' => 'No valid fields to update'], 422);
        }

        $recipientModel->update($id, $data);

        audit_log('update_recipient', 'recipient', $id, $data);

        $updated = $recipientModel->findById($id);
        json_response($updated);
    }

    /**
     * POST /api/recipients/sync
     * Dispatch a sync job to import recipients from Microsoft Graph.
     */
    public function sync(): void
    {
        $queueService = new QueueService($this->db);
        $queueService->dispatch('SyncRecipientsJob', []);

        audit_log('sync_recipients', 'recipient');

        json_response(['message' => 'Recipient sync job has been queued'], 202);
    }

    /**
     * GET /api/recipients/{id}/history
     * Campaign participation history for a recipient.
     */
    public function history($id): void
    {
        $id = (int) $id;
        $recipientModel = new Recipient($this->db);
        $recipient = $recipientModel->findById($id);

        if (!$recipient) {
            json_response(['error' => 'Recipient not found'], 404);
        }

        $history = $recipientModel->getCampaignHistory($id);

        json_response([
            'recipient_id' => $id,
            'history'      => $history,
        ]);
    }

    /**
     * GET /api/recipients/departments
     * List distinct departments.
     */
    public function departments(): void
    {
        $stmt = $this->db->query("SELECT DISTINCT department FROM recipients WHERE department IS NOT NULL AND department != '' ORDER BY department");
        $departments = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        json_response($departments);
    }

    /**
     * POST /api/recipients/{id}/groups/{groupId}
     * Add recipient to a group.
     */
    public function addToGroup($id, $groupId): void
    {
        $id = (int) $id;
        $groupId = (int) $groupId;
        $groupModel = new Group($this->db);
        $groupModel->addMembers($groupId, [$id]);
        json_response(['message' => 'Recipient added to group']);
    }

    /**
     * DELETE /api/recipients/{id}/groups/{groupId}
     * Remove recipient from a group.
     */
    public function removeFromGroup($id, $groupId): void
    {
        $id = (int) $id;
        $groupId = (int) $groupId;
        $groupModel = new Group($this->db);
        $groupModel->removeMembers($groupId, [$id]);
        json_response(['message' => 'Recipient removed from group']);
    }
}
