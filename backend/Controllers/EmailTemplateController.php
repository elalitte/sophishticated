<?php

namespace App\Controllers;

use App\Services\JsonTemplateStorage;

class EmailTemplateController
{
    private \PDO $db;
    private JsonTemplateStorage $storage;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->storage = new JsonTemplateStorage();
    }

    /**
     * GET /api/email-templates
     */
    public function index(): void
    {
        $filters = [];
        if (!empty($_GET['difficulty'])) {
            $filters['difficulty'] = $_GET['difficulty'];
        }
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int) $_GET['is_active'];
        }
        if (!empty($_GET['tags'])) {
            $filters['tags'] = $_GET['tags'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        $templates = $this->storage->findAllTemplates($filters);
        json_response(['data' => $templates]);
    }

    /**
     * POST /api/email-templates
     */
    public function store(): void
    {
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            json_response(['error' => 'Template name is required'], 422);
        }

        $subject = trim($input['subject'] ?? '');
        if ($subject === '') {
            json_response(['error' => 'Subject is required'], 422);
        }

        $template = $this->storage->createTemplate($input);
        $this->storage->syncToDb($this->db);

        audit_log('create_email_template', 'email_template', $template['id'], ['name' => $name]);

        json_response($template, 201);
    }

    /**
     * GET /api/email-templates/{id}
     */
    public function show($id): void
    {
        $id = (int) $id;
        $template = $this->storage->findTemplateById($id);

        if (!$template) {
            json_response(['error' => 'Email template not found'], 404);
        }

        json_response($template);
    }

    /**
     * PUT /api/email-templates/{id}
     */
    public function update($id): void
    {
        $id = (int) $id;
        $template = $this->storage->findTemplateById($id);
        if (!$template) {
            json_response(['error' => 'Email template not found'], 404);
        }

        $input = get_json_input();

        if (empty($input)) {
            json_response(['error' => 'No valid fields to update'], 422);
        }

        $updated = $this->storage->updateTemplate($id, $input);
        $this->storage->syncToDb($this->db);

        audit_log('update_email_template', 'email_template', $id);

        json_response($updated);
    }

    /**
     * DELETE /api/email-templates/{id}
     */
    public function destroy($id): void
    {
        $id = (int) $id;
        $template = $this->storage->findTemplateById($id);
        if (!$template) {
            json_response(['error' => 'Email template not found'], 404);
        }

        $this->storage->deleteTemplate($id);
        $this->storage->syncToDb($this->db);

        audit_log('delete_email_template', 'email_template', $id, ['name' => $template['name']]);

        json_response(['message' => 'Email template deleted successfully']);
    }

    /**
     * POST /api/templates/{id}/duplicate
     */
    public function duplicate($id): void
    {
        $id = (int) $id;
        $template = $this->storage->findTemplateById($id);
        if (!$template) {
            json_response(['error' => 'Template not found'], 404);
        }

        $newData = $template;
        $newData['name'] = $template['name'] . ' (copie)';
        unset($newData['id'], $newData['difficulty']);

        $newTemplate = $this->storage->createTemplate($newData);
        $this->storage->syncToDb($this->db);

        audit_log('duplicate_template', 'email_template', $newTemplate['id'], ['source_id' => $id]);
        json_response($newTemplate, 201);
    }

    /**
     * POST /api/templates/{id}/toggle-active
     */
    public function toggleActive($id): void
    {
        $id = (int) $id;
        $template = $this->storage->findTemplateById($id);
        if (!$template) {
            json_response(['error' => 'Template not found'], 404);
        }

        $newStatus = $template['is_active'] ? 0 : 1;
        $updated = $this->storage->updateTemplate($id, ['is_active' => $newStatus]);
        $this->storage->syncToDb($this->db);

        json_response($updated);
    }
}
