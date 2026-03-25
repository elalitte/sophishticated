<?php

namespace App\Controllers;

use App\Services\JsonTemplateStorage;

class LandingPageController
{
    private \PDO $db;
    private JsonTemplateStorage $storage;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
        $this->storage = new JsonTemplateStorage();
    }

    /**
     * GET /api/landing-pages
     */
    public function index(): void
    {
        $filters = [];
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int) $_GET['is_active'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        $pages = $this->storage->findAllLandingPages($filters);
        json_response(['data' => $pages]);
    }

    /**
     * POST /api/landing-pages
     */
    public function store(): void
    {
        $input = get_json_input();

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            json_response(['error' => 'Landing page name is required'], 422);
        }

        $page = $this->storage->createLandingPage($input);
        $this->storage->syncToDb($this->db);

        audit_log('create_landing_page', 'landing_page', $page['id'], ['name' => $name]);

        json_response($page, 201);
    }

    /**
     * GET /api/landing-pages/{id}
     */
    public function show($id): void
    {
        $id = (int) $id;
        $page = $this->storage->findLandingPageById($id);
        if (!$page) {
            json_response(['error' => 'Landing page not found'], 404);
        }

        json_response($page);
    }

    /**
     * PUT /api/landing-pages/{id}
     */
    public function update($id): void
    {
        $id = (int) $id;
        $page = $this->storage->findLandingPageById($id);
        if (!$page) {
            json_response(['error' => 'Landing page not found'], 404);
        }

        $input = get_json_input();

        if (empty($input)) {
            json_response(['error' => 'No valid fields to update'], 422);
        }

        $updated = $this->storage->updateLandingPage($id, $input);
        $this->storage->syncToDb($this->db);

        audit_log('update_landing_page', 'landing_page', $id);

        json_response($updated);
    }

    /**
     * DELETE /api/landing-pages/{id}
     */
    public function destroy($id): void
    {
        $id = (int) $id;
        $page = $this->storage->findLandingPageById($id);
        if (!$page) {
            json_response(['error' => 'Landing page not found'], 404);
        }

        $this->storage->deleteLandingPage($id);
        $this->storage->syncToDb($this->db);

        audit_log('delete_landing_page', 'landing_page', $id, ['name' => $page['name']]);

        json_response(['message' => 'Landing page deleted successfully']);
    }

    /**
     * POST /api/landing-pages/{id}/duplicate
     */
    public function duplicate($id): void
    {
        $id = (int) $id;
        $page = $this->storage->findLandingPageById($id);
        if (!$page) {
            json_response(['error' => 'Landing page not found'], 404);
        }

        $newData = $page;
        $newData['name'] = $page['name'] . ' (copie)';
        unset($newData['id'], $newData['ref'], $newData['capture_credentials']);

        $newPage = $this->storage->createLandingPage($newData);
        $this->storage->syncToDb($this->db);

        audit_log('duplicate_landing_page', 'landing_page', $newPage['id'], ['source_id' => $id]);
        json_response($newPage, 201);
    }

    /**
     * POST /api/landing-pages/{id}/toggle-active
     */
    public function toggleActive($id): void
    {
        $id = (int) $id;
        $page = $this->storage->findLandingPageById($id);
        if (!$page) {
            json_response(['error' => 'Landing page not found'], 404);
        }

        $newStatus = $page['is_active'] ? 0 : 1;
        $updated = $this->storage->updateLandingPage($id, ['is_active' => $newStatus]);
        $this->storage->syncToDb($this->db);

        json_response($updated);
    }
}
