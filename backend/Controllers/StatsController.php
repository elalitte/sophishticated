<?php

namespace App\Controllers;

use App\Services\StatsService;

class StatsController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/stats/global
     * Global statistics with optional date range.
     */
    public function global(): void
    {
        $dateFrom = $_GET['date_from'] ?? null;
        $dateTo   = $_GET['date_to'] ?? null;

        $statsService = new StatsService($this->db);
        $stats = $statsService->getGlobalStats($dateFrom, $dateTo);

        json_response($stats);
    }

    /**
     * GET /api/stats/campaign/{id}
     * Statistics for a specific campaign.
     */
    public function campaign($id): void
    {
        $id = (int) $id;
        $statsService = new StatsService($this->db);
        $stats = $statsService->getCampaignStats($id);

        if (!$stats) {
            json_response(['error' => 'Campaign not found'], 404);
        }

        json_response($stats);
    }

    /**
     * GET /api/stats/group/{id}
     * Statistics for a specific group.
     */
    public function group($id): void
    {
        $id = (int) $id;
        $statsService = new StatsService($this->db);
        $stats = $statsService->getGroupStats($id);

        if (!$stats) {
            json_response(['error' => 'Group not found'], 404);
        }

        json_response($stats);
    }

    /**
     * GET /api/stats/recipient/{id}
     * Statistics for a specific recipient.
     */
    public function recipient($id): void
    {
        $id = (int) $id;
        $statsService = new StatsService($this->db);
        $stats = $statsService->getRecipientStats($id);

        if (!$stats) {
            json_response(['error' => 'Recipient not found'], 404);
        }

        json_response($stats);
    }

    /**
     * GET /api/stats/export/{type}/{id?}
     * Export statistics as CSV.
     */
    public function export(string $type, $id = null): void
    {
        $id = $id !== null ? (int) $id : null;
        $allowedTypes = ['global', 'campaign', 'group', 'recipient'];
        if (!in_array($type, $allowedTypes, true)) {
            json_response(['error' => 'Invalid export type'], 422);
        }

        if ($type !== 'global' && $id === null) {
            json_response(['error' => 'ID is required for this export type'], 422);
        }

        $statsService = new StatsService($this->db);
        $csvContent = $statsService->exportCsv($type, $id);

        $filename = "export_{$type}";
        if ($id !== null) {
            $filename .= "_{$id}";
        }
        $filename .= '_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        echo $csvContent;
        exit;
    }

    /**
     * GET /api/stats/dashboard/export
     * Alias for global CSV export.
     */
    public function exportGlobal(): void
    {
        $this->export('global');
    }
}
