<?php

namespace App\Controllers;

class DashboardController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/dashboard
     * Return summary data for the dashboard.
     */
    public function index(): void
    {
        // Active campaigns count
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM campaigns WHERE status IN ('running', 'scheduled')"
        );
        $activeCampaigns = (int) $stmt->fetchColumn();

        // Completed campaigns this month
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM campaigns
             WHERE status = 'completed'
               AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        );
        $stmt->execute();
        $completedThisMonth = (int) $stmt->fetchColumn();

        // Global vigilance rate (recipients who never clicked / total targeted)
        $stmt = $this->db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN clicked_at IS NULL AND submitted_at IS NULL THEN 1 ELSE 0 END) AS vigilant
             FROM campaign_recipients"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int) ($row['total'] ?? 0);
        $vigilant = (int) ($row['vigilant'] ?? 0);
        $vigilanceRate = $total > 0 ? round(($vigilant / $total) * 100, 1) : 0;

        // Running campaigns with live stats
        $stmt = $this->db->query(
            "SELECT c.id, c.name, c.status, c.started_at,
                    COUNT(cr.id) AS total,
                    SUM(CASE WHEN cr.mail_status = 'delivered' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN cr.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                    SUM(CASE WHEN cr.clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked,
                    SUM(CASE WHEN cr.submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submitted
             FROM campaigns c
             LEFT JOIN campaign_recipients cr ON cr.campaign_id = c.id
             WHERE c.status IN ('running', 'scheduled')
             GROUP BY c.id
             ORDER BY c.started_at DESC"
        );
        $runningCampaigns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Recent completed campaigns with vigilance rate (last 10)
        $stmt = $this->db->query(
            "SELECT c.id, c.name, c.status, c.scheduled_at, c.started_at, c.completed_at,
                    COUNT(cr.id) AS total_recipients,
                    SUM(CASE WHEN cr.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened,
                    SUM(CASE WHEN cr.clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked,
                    SUM(CASE WHEN cr.submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submitted,
                    CASE WHEN COUNT(cr.id) > 0
                        THEN ROUND(SUM(CASE WHEN cr.clicked_at IS NULL AND cr.submitted_at IS NULL THEN 1 ELSE 0 END) / COUNT(cr.id) * 100, 1)
                        ELSE 0 END AS vigilance_rate
             FROM campaigns c
             LEFT JOIN campaign_recipients cr ON cr.campaign_id = c.id
             WHERE c.status = 'completed'
             GROUP BY c.id
             ORDER BY c.completed_at DESC
             LIMIT 10"
        );
        $recentCampaigns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Last sync date
        $stmt = $this->db->query(
            "SELECT MAX(updated_at) FROM recipients"
        );
        $lastSync = $stmt->fetchColumn() ?: null;

        // Active recipients count
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM recipients WHERE is_active = 1"
        );
        $activeRecipients = (int) $stmt->fetchColumn();

        json_response([
            'active_campaigns'       => $activeCampaigns,
            'running_campaigns_count' => $activeCampaigns,
            'completed_this_month'   => $completedThisMonth,
            'vigilance_rate'         => $vigilanceRate,
            'running_campaigns'      => $runningCampaigns,
            'recent_campaigns'       => $recentCampaigns,
            'last_sync'              => $lastSync,
            'active_recipients'      => $activeRecipients,
        ]);
    }
}
