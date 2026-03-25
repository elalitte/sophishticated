<?php

namespace App\Controllers;

use App\Services\QueueService;

class CampaignController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/campaigns
     * List campaigns with optional status filter.
     */
    public function index(): void
    {
        $where  = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = 'c.status = :status';
            $params[':status'] = $_GET['status'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM campaigns c {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT c.*,
                    c.started_at AS launched_at,
                    et.name AS template_name,
                    COUNT(cr.id) AS total_recipients,
                    COUNT(cr.id) AS target_count,
                    SUM(CASE WHEN cr.mail_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                    SUM(cr.opened) AS opened_count,
                    SUM(cr.clicked) AS clicked_count,
                    SUM(cr.submitted_credentials) AS submitted_count,
                    CASE WHEN COUNT(cr.id) > 0 THEN ROUND(SUM(cr.opened) / COUNT(cr.id) * 100, 1) ELSE 0 END AS open_rate,
                    CASE WHEN COUNT(cr.id) > 0 THEN ROUND(SUM(cr.clicked) / COUNT(cr.id) * 100, 1) ELSE 0 END AS click_rate,
                    CASE WHEN COUNT(cr.id) > 0 THEN ROUND(SUM(cr.submitted_credentials) / COUNT(cr.id) * 100, 1) ELSE 0 END AS submit_rate
             FROM campaigns c
             LEFT JOIN campaign_recipients cr ON cr.campaign_id = c.id
             LEFT JOIN email_templates et ON et.id = c.email_template_id
             {$whereClause}
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();

        $campaigns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Enrich with template names for multi-template campaigns
        foreach ($campaigns as &$c) {
            $tStmt = $this->db->prepare(
                "SELECT et.id, et.name FROM campaign_templates ct
                 JOIN email_templates et ON et.id = ct.email_template_id
                 WHERE ct.campaign_id = :id ORDER BY et.name"
            );
            $tStmt->execute([':id' => $c['id']]);
            $tpls = $tStmt->fetchAll(\PDO::FETCH_ASSOC);
            $c['template_names'] = array_column($tpls, 'name');
            $c['template_ids'] = array_map('intval', array_column($tpls, 'id'));
            if (count($tpls) > 1) {
                $c['template_name'] = count($tpls) . ' templates';
            }
        }
        unset($c);

        json_response([
            'data'     => $campaigns,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * POST /api/campaigns
     * Create a new campaign (supports single or multiple templates).
     */
    public function store(): void
    {
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            json_response(['error' => 'Campaign name is required'], 422);
            return;
        }

        // Support both single template_id and array of template_ids
        $templateIds = [];
        if (!empty($input['email_template_ids']) && is_array($input['email_template_ids'])) {
            $templateIds = array_map('intval', $input['email_template_ids']);
        } elseif (!empty($input['email_template_id'])) {
            $templateIds = [(int) $input['email_template_id']];
        }

        if (empty($templateIds)) {
            json_response(['error' => 'At least one email template is required'], 422);
            return;
        }

        // Verify all templates exist
        $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
        $stmt = $this->db->prepare("SELECT id FROM email_templates WHERE id IN ({$placeholders})");
        $stmt->execute($templateIds);
        $foundIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (count($foundIds) !== count($templateIds)) {
            json_response(['error' => 'One or more email templates not found'], 404);
            return;
        }

        $user = current_user();

        $this->db->beginTransaction();
        try {
            // Use first template as primary (backward compat)
            $primaryTemplateId = $templateIds[0];

            $stmt = $this->db->prepare(
                "INSERT INTO campaigns
                    (name, description, email_template_id, phishing_domain,
                     send_mode, stagger_minutes, scheduled_at, status, created_by, created_at, updated_at)
                 VALUES
                    (:name, :description, :email_template_id, :phishing_domain,
                     :send_mode, :stagger_minutes, :scheduled_at, 'draft', :created_by, NOW(), NOW())"
            );
            $stmt->execute([
                ':name'              => $name,
                ':description'       => $input['description'] ?? null,
                ':email_template_id' => $primaryTemplateId,
                ':phishing_domain'   => $input['phishing_domain'] ?? null,
                ':send_mode'         => $input['send_mode'] ?? 'immediate',
                ':stagger_minutes'   => !empty($input['stagger_minutes']) ? (int) $input['stagger_minutes'] : null,
                ':scheduled_at'      => $input['scheduled_at'] ?? null,
                ':created_by'        => $user['id'] ?? null,
            ]);

            $campaignId = (int) $this->db->lastInsertId();

            // Insert campaign_templates junction
            $ctStmt = $this->db->prepare(
                "INSERT INTO campaign_templates (campaign_id, email_template_id) VALUES (:cid, :tid)"
            );
            foreach ($templateIds as $tid) {
                $ctStmt->execute([':cid' => $campaignId, ':tid' => $tid]);
            }

            // Collect recipient IDs from direct list and/or groups
            $recipientIds = [];

            if (!empty($input['recipient_ids']) && is_array($input['recipient_ids'])) {
                $recipientIds = array_map('intval', $input['recipient_ids']);
            }

            if (!empty($input['group_ids']) && is_array($input['group_ids'])) {
                $placeholders = implode(',', array_fill(0, count($input['group_ids']), '?'));
                $gStmt = $this->db->prepare(
                    "SELECT DISTINCT recipient_id FROM group_recipient WHERE group_id IN ({$placeholders})"
                );
                $gStmt->execute(array_map('intval', $input['group_ids']));
                $groupRecipients = $gStmt->fetchAll(\PDO::FETCH_COLUMN);
                $recipientIds = array_unique(array_merge($recipientIds, $groupRecipients));
            }

            // Smart template assignment
            if (!empty($recipientIds)) {
                $assignments = $this->assignTemplatesToRecipients($recipientIds, $templateIds);

                $insertStmt = $this->db->prepare(
                    "INSERT INTO campaign_recipients (campaign_id, recipient_id, email_template_id, unique_token, mail_status)
                     VALUES (:campaign_id, :recipient_id, :template_id, :token, 'pending')"
                );
                foreach ($assignments as $rid => $tid) {
                    $insertStmt->execute([
                        ':campaign_id'  => $campaignId,
                        ':recipient_id' => (int) $rid,
                        ':template_id'  => (int) $tid,
                        ':token'        => generate_uuid(),
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            json_response(['error' => 'Failed to create campaign: ' . $e->getMessage()], 500);
        }

        audit_log('create_campaign', 'campaign', $campaignId, ['name' => $name]);

        // Auto-launch if requested
        if (!empty($input['launch'])) {
            $this->db->prepare("UPDATE campaigns SET status = 'running', started_at = NOW() WHERE id = :id")
                ->execute([':id' => $campaignId]);

            $queueService = new \App\Services\QueueService($this->db);
            $queueService->dispatch('DeliverCampaignJob', ['campaign_id' => $campaignId], null, 1);

            audit_log('launch_campaign', 'campaign', $campaignId);
        }

        $campaign = $this->findById($campaignId);
        json_response($campaign, 201);
    }

    /**
     * Assign templates to recipients, avoiding previously-seen templates.
     *
     * For each recipient:
     *   1. Get list of template IDs they've already received in past campaigns
     *   2. Filter available templates to only unseen ones
     *   3. If all templates have been seen, allow all of them
     *   4. Pick randomly from available pool
     *
     * @return array<int, int>  recipient_id => email_template_id
     */
    private function assignTemplatesToRecipients(array $recipientIds, array $templateIds): array
    {
        // Get template history for all recipients in one query
        $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT recipient_id, email_template_id
             FROM campaign_recipients
             WHERE recipient_id IN ({$placeholders})
               AND email_template_id IS NOT NULL"
        );
        $stmt->execute($recipientIds);

        $history = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $history[(int) $row['recipient_id']][] = (int) $row['email_template_id'];
        }

        $assignments = [];
        foreach ($recipientIds as $rid) {
            $rid = (int) $rid;
            $seenTemplates = $history[$rid] ?? [];

            // Filter to unseen templates from the available pool
            $available = array_diff($templateIds, $seenTemplates);

            // If all have been seen, reset — allow all templates
            if (empty($available)) {
                $available = $templateIds;
            }

            // Pick randomly
            $available = array_values($available);
            $assignments[$rid] = $available[array_rand($available)];
        }

        return $assignments;
    }

    /**
     * GET /api/campaigns/{id}
     * Campaign details with template info and stats.
     */
    public function show($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        // Fetch all associated templates
        $tStmt = $this->db->prepare(
            "SELECT et.* FROM campaign_templates ct
             JOIN email_templates et ON et.id = ct.email_template_id
             WHERE ct.campaign_id = :id ORDER BY et.name"
        );
        $tStmt->execute([':id' => $id]);
        $templates = $tStmt->fetchAll(\PDO::FETCH_ASSOC);

        $campaign['email_templates'] = $templates;
        $campaign['template_ids'] = array_map('intval', array_column($templates, 'id'));
        $campaign['template_names'] = array_column($templates, 'name');

        // Backward compat: single template
        if ($campaign['email_template_id']) {
            $stmt = $this->db->prepare('SELECT * FROM email_templates WHERE id = :id');
            $stmt->execute([':id' => $campaign['email_template_id']]);
            $campaign['email_template'] = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        // Fetch stats (same field names as index() for frontend consistency)
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS target_count,
                SUM(CASE WHEN mail_status = 'delivered' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(opened) AS opened_count,
                SUM(clicked) AS clicked_count,
                SUM(submitted_credentials) AS submitted_count,
                CASE WHEN COUNT(*) > 0 THEN ROUND(SUM(opened) / COUNT(*) * 100, 1) ELSE 0 END AS open_rate,
                CASE WHEN COUNT(*) > 0 THEN ROUND(SUM(clicked) / COUNT(*) * 100, 1) ELSE 0 END AS click_rate,
                CASE WHEN COUNT(*) > 0 THEN ROUND(SUM(submitted_credentials) / COUNT(*) * 100, 1) ELSE 0 END AS submit_rate
             FROM campaign_recipients
             WHERE campaign_id = :id"
        );
        $stmt->execute([':id' => $id]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Merge stats as flat fields on the campaign object (matches index() format)
        foreach ($stats as $key => $value) {
            $campaign[$key] = $value;
        }

        json_response($campaign);
    }

    /**
     * PUT /api/campaigns/{id}
     * Update a draft campaign.
     */
    public function update($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if ($campaign['status'] !== 'draft') {
            json_response(['error' => 'Only draft campaigns can be edited'], 422);
        }

        $input = get_json_input();

        // Handle template_ids update
        if (!empty($input['email_template_ids']) && is_array($input['email_template_ids'])) {
            $templateIds = array_map('intval', $input['email_template_ids']);
            // Update primary template
            $input['email_template_id'] = $templateIds[0];

            // Rebuild campaign_templates junction
            $this->db->prepare("DELETE FROM campaign_templates WHERE campaign_id = :id")
                ->execute([':id' => $id]);
            $ctStmt = $this->db->prepare(
                "INSERT INTO campaign_templates (campaign_id, email_template_id) VALUES (:cid, :tid)"
            );
            foreach ($templateIds as $tid) {
                $ctStmt->execute([':cid' => $id, ':tid' => $tid]);
            }

            // Re-assign templates to existing recipients
            $rStmt = $this->db->prepare(
                "SELECT DISTINCT recipient_id FROM campaign_recipients WHERE campaign_id = :id"
            );
            $rStmt->execute([':id' => $id]);
            $recipientIds = $rStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (!empty($recipientIds)) {
                $assignments = $this->assignTemplatesToRecipients(
                    array_map('intval', $recipientIds),
                    $templateIds
                );
                $uStmt = $this->db->prepare(
                    "UPDATE campaign_recipients SET email_template_id = :tid
                     WHERE campaign_id = :cid AND recipient_id = :rid"
                );
                foreach ($assignments as $rid => $tid) {
                    $uStmt->execute([':tid' => $tid, ':cid' => $id, ':rid' => $rid]);
                }
            }
        }

        $allowedFields = [
            'name', 'description', 'email_template_id', 'phishing_domain',
            'landing_page_id', 'send_mode', 'stagger_minutes', 'scheduled_at',
        ];

        $fields = [];
        $params = [':id' => $id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $input[$field];
            }
        }

        if (!empty($fields)) {
            $fields[] = 'updated_at = NOW()';
            $fieldStr = implode(', ', $fields);
            $stmt = $this->db->prepare("UPDATE campaigns SET {$fieldStr} WHERE id = :id");
            $stmt->execute($params);
        }

        audit_log('update_campaign', 'campaign', $id);

        $updated = $this->findById($id);
        json_response($updated);
    }

    /**
     * DELETE /api/campaigns/{id}
     * Delete only if draft or cancelled.
     */
    public function destroy($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign['status'], ['draft', 'cancelled'], true)) {
            json_response(['error' => 'Only draft or cancelled campaigns can be deleted'], 422);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('DELETE FROM campaign_recipients WHERE campaign_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->db->prepare('DELETE FROM campaign_templates WHERE campaign_id = :id');
            $stmt->execute([':id' => $id]);

            $stmt = $this->db->prepare('DELETE FROM campaigns WHERE id = :id');
            $stmt->execute([':id' => $id]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            json_response(['error' => 'Failed to delete campaign'], 500);
        }

        audit_log('delete_campaign', 'campaign', $id, ['name' => $campaign['name']]);

        json_response(['message' => 'Campaign deleted successfully']);
    }

    /**
     * POST /api/campaigns/{id}/launch
     * Launch a draft or scheduled campaign.
     */
    public function launch($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if (!in_array($campaign['status'], ['draft', 'scheduled'], true)) {
            json_response(['error' => 'Campaign must be in draft or scheduled status to launch'], 422);
        }

        $stmt = $this->db->prepare(
            "UPDATE campaigns SET status = 'running', started_at = NOW(), updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        // Dispatch delivery job
        $queueService = new QueueService($this->db);
        $queueService->dispatch('DeliverCampaignJob', ['campaign_id' => $id]);

        audit_log('launch_campaign', 'campaign', $id);

        json_response(['message' => 'Campaign launched successfully']);
    }

    /**
     * POST /api/campaigns/{id}/pause
     */
    public function pause($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if ($campaign['status'] !== 'running') {
            json_response(['error' => 'Only running campaigns can be paused'], 422);
        }

        $stmt = $this->db->prepare(
            "UPDATE campaigns SET status = 'paused', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        audit_log('pause_campaign', 'campaign', $id);
        json_response(['message' => 'Campaign paused']);
    }

    /**
     * POST /api/campaigns/{id}/resume
     */
    public function resume($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if ($campaign['status'] !== 'paused') {
            json_response(['error' => 'Only paused campaigns can be resumed'], 422);
        }

        $stmt = $this->db->prepare(
            "UPDATE campaigns SET status = 'running', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        $queueService = new QueueService($this->db);
        $queueService->dispatch('DeliverCampaignJob', ['campaign_id' => $id, 'resume' => true]);

        audit_log('resume_campaign', 'campaign', $id);
        json_response(['message' => 'Campaign resumed']);
    }

    /**
     * POST /api/campaigns/{id}/cancel
     */
    public function cancel($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        if (in_array($campaign['status'], ['completed', 'cancelled'], true)) {
            json_response(['error' => 'Campaign is already completed or cancelled'], 422);
        }

        $stmt = $this->db->prepare(
            "UPDATE campaigns SET status = 'cancelled', updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);

        audit_log('cancel_campaign', 'campaign', $id);
        json_response(['message' => 'Campaign cancelled']);
    }

    /**
     * POST /api/campaigns/{id}/duplicate
     */
    public function duplicate($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
            return;
        }

        $user = current_user();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO campaigns
                    (name, description, email_template_id, phishing_domain,
                     send_mode, stagger_minutes, status, created_by, created_at, updated_at)
                 VALUES
                    (:name, :description, :email_template_id, :phishing_domain,
                     :send_mode, :stagger_minutes, 'draft', :created_by, NOW(), NOW())"
            );
            $stmt->execute([
                ':name'              => $campaign['name'] . ' (copie)',
                ':description'       => $campaign['description'],
                ':email_template_id' => $campaign['email_template_id'],
                ':phishing_domain'   => $campaign['phishing_domain'] ?? null,
                ':send_mode'         => $campaign['send_mode'],
                ':stagger_minutes'   => $campaign['stagger_minutes'] ?? null,
                ':created_by'        => $user['id'] ?? null,
            ]);

            $newId = (int) $this->db->lastInsertId();

            // Copy campaign_templates junction
            $this->db->prepare(
                "INSERT INTO campaign_templates (campaign_id, email_template_id)
                 SELECT :new_id, email_template_id FROM campaign_templates WHERE campaign_id = :old_id"
            )->execute([':new_id' => $newId, ':old_id' => $id]);

            // Get template IDs for smart assignment
            $tStmt = $this->db->prepare(
                "SELECT email_template_id FROM campaign_templates WHERE campaign_id = :id"
            );
            $tStmt->execute([':id' => $newId]);
            $templateIds = array_map('intval', $tStmt->fetchAll(\PDO::FETCH_COLUMN));

            // Copy recipients with smart template re-assignment
            $recipientStmt = $this->db->prepare(
                "SELECT recipient_id FROM campaign_recipients WHERE campaign_id = :id"
            );
            $recipientStmt->execute([':id' => $id]);
            $recipientIds = array_map('intval', $recipientStmt->fetchAll(\PDO::FETCH_COLUMN));

            if (!empty($recipientIds) && !empty($templateIds)) {
                $assignments = $this->assignTemplatesToRecipients($recipientIds, $templateIds);

                $insertStmt = $this->db->prepare(
                    "INSERT INTO campaign_recipients (campaign_id, recipient_id, email_template_id, unique_token, mail_status)
                     VALUES (:campaign_id, :recipient_id, :template_id, :token, 'pending')"
                );
                foreach ($assignments as $rid => $tid) {
                    $insertStmt->execute([
                        ':campaign_id'  => $newId,
                        ':recipient_id' => (int) $rid,
                        ':template_id'  => (int) $tid,
                        ':token'        => generate_uuid(),
                    ]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            json_response(['error' => 'Failed to duplicate campaign: ' . $e->getMessage()], 500);
        }

        audit_log('duplicate_campaign', 'campaign', $newId, ['source_id' => $id]);

        $newCampaign = $this->findById($newId);
        json_response($newCampaign, 201);
    }

    /**
     * GET /api/campaigns/{id}/recipients
     * Paginated recipients list with tracking status.
     */
    public function recipients($id): void
    {
        $id = (int) $id;
        $campaign = $this->findById($id);
        if (!$campaign) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = :id'
        );
        $countStmt->execute([':id' => $id]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT cr.*, r.email, r.first_name, r.last_name, r.department,
                    et.name AS template_name
             FROM campaign_recipients cr
             INNER JOIN recipients r ON r.id = cr.recipient_id
             LEFT JOIN email_templates et ON et.id = cr.email_template_id
             WHERE cr.campaign_id = :id
             ORDER BY r.last_name ASC, r.first_name ASC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        json_response([
            'data'     => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Helper: find campaign by ID.
     */
    private function findById($id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM campaigns WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
