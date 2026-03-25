<?php

namespace App\Services;

class StatsService
{
    public function __construct(private \PDO $db)
    {
    }

    // ═══════════════════════════════════════════════════════════════
    // Global statistics
    // ═══════════════════════════════════════════════════════════════

    /**
     * Aggregate statistics across all campaigns, optionally filtered by date range.
     */
    public function getGlobalStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFilter = $this->buildCampaignDateFilter($dateFrom, $dateTo);
        $params     = $dateFilter['params'];
        $where      = $dateFilter['where'];

        // ── Campaign count ──────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM campaigns WHERE status NOT IN ('draft','cancelled') {$where}"
        );
        $stmt->execute($params);
        $campaignCount = (int) $stmt->fetchColumn();

        // ── Recipient totals & average rates ────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(cr.id)                       AS total_recipients,
                SUM(cr.opened)                     AS total_opened,
                SUM(cr.clicked)                    AS total_clicked,
                SUM(cr.submitted_credentials)      AS total_submitted
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE c.status NOT IN ('draft','cancelled') {$where}"
        );
        $stmt->execute($params);
        $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

        $totalRecipients = (int) ($totals['total_recipients'] ?? 0);
        $pct = fn(int $n) => $totalRecipients > 0 ? round($n / $totalRecipients * 100, 2) : 0;

        // ── Evolution per campaign ──────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                c.id, c.name, c.started_at,
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM campaigns c
             JOIN campaign_recipients cr ON cr.campaign_id = c.id
             WHERE c.status NOT IN ('draft','cancelled') {$where}
             GROUP BY c.id
             ORDER BY c.started_at ASC"
        );
        $stmt->execute($params);
        $evolution = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($evolution as &$row) {
            $n = (int) $row['recipients'];
            $row['open_rate']   = $n > 0 ? round((int) $row['opened']   / $n * 100, 2) : 0;
            $row['click_rate']  = $n > 0 ? round((int) $row['clicked']  / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['submitted']/ $n * 100, 2) : 0;
        }
        unset($row);

        // ── Comparison between groups ───────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                g.id, g.name, g.color,
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM `groups` g
             JOIN group_recipient gr ON gr.group_id = g.id
             JOIN campaign_recipients cr ON cr.recipient_id = gr.recipient_id
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE c.status NOT IN ('draft','cancelled') {$where}
             GROUP BY g.id
             ORDER BY g.name"
        );
        $stmt->execute($params);
        $groupComparison = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($groupComparison as &$row) {
            $n = (int) $row['recipients'];
            $row['open_rate']   = $n > 0 ? round((int) $row['opened']   / $n * 100, 2) : 0;
            $row['click_rate']  = $n > 0 ? round((int) $row['clicked']  / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['submitted']/ $n * 100, 2) : 0;
        }
        unset($row);

        // ── Top 5 templates ─────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                et.id, et.name, et.subject, et.difficulty_level,
                COUNT(cr.id)                       AS recipients,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM email_templates et
             JOIN campaigns c ON c.email_template_id = et.id
             JOIN campaign_recipients cr ON cr.campaign_id = c.id
             WHERE c.status NOT IN ('draft','cancelled') {$where}
             GROUP BY et.id
             ORDER BY SUM(cr.clicked) / COUNT(cr.id) DESC
             LIMIT 5"
        );
        $stmt->execute($params);
        $topTemplates = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($topTemplates as &$row) {
            $n = (int) $row['recipients'];
            $row['click_rate']  = $n > 0 ? round((int) $row['clicked']  / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['submitted']/ $n * 100, 2) : 0;
        }
        unset($row);

        // ── Top 10 vulnerable employees ─────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                r.id, r.email, r.display_name, r.first_name, r.last_name, r.department,
                COUNT(cr.id)                       AS campaigns_targeted,
                SUM(cr.clicked)                    AS total_clicks,
                SUM(cr.submitted_credentials)      AS total_submissions
             FROM recipients r
             JOIN campaign_recipients cr ON cr.recipient_id = r.id
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE c.status NOT IN ('draft','cancelled') {$where}
             GROUP BY r.id
             HAVING total_clicks > 0 OR total_submissions > 0
             ORDER BY total_submissions DESC, total_clicks DESC
             LIMIT 10"
        );
        $stmt->execute($params);
        $topVulnerable = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($topVulnerable as &$row) {
            $n = (int) $row['campaigns_targeted'];
            $row['click_rate']  = $n > 0 ? round((int) $row['total_clicks']      / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['total_submissions'] / $n * 100, 2) : 0;
        }
        unset($row);

        return [
            'campaign_count'      => $campaignCount,
            'total_recipients'    => $totalRecipients,
            'average_open_rate'   => $pct((int) ($totals['total_opened']    ?? 0)),
            'average_click_rate'  => $pct((int) ($totals['total_clicked']   ?? 0)),
            'average_submit_rate' => $pct((int) ($totals['total_submitted'] ?? 0)),
            'evolution'           => $evolution,
            'group_comparison'    => $groupComparison,
            'top_templates'       => $topTemplates,
            'top_vulnerable'      => $topVulnerable,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Campaign-level statistics
    // ═══════════════════════════════════════════════════════════════

    public function getCampaignStats(int $campaignId): array
    {
        // ── Funnel ──────────────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*)                           AS delivered,
                SUM(opened)                        AS opened,
                SUM(clicked)                       AS clicked,
                SUM(submitted_credentials)         AS submitted
             FROM campaign_recipients
             WHERE campaign_id = :id AND mail_status = 'delivered'"
        );
        $stmt->execute([':id' => $campaignId]);
        $funnel = $stmt->fetch(\PDO::FETCH_ASSOC);

        $delivered = (int) ($funnel['delivered'] ?? 0);
        $funnelData = [
            'delivered' => $delivered,
            'opened'    => (int) ($funnel['opened']    ?? 0),
            'clicked'   => (int) ($funnel['clicked']   ?? 0),
            'submitted' => (int) ($funnel['submitted'] ?? 0),
        ];
        $funnelData['open_rate']   = $delivered > 0 ? round($funnelData['opened']    / $delivered * 100, 2) : 0;
        $funnelData['click_rate']  = $delivered > 0 ? round($funnelData['clicked']   / $delivered * 100, 2) : 0;
        $funnelData['submit_rate'] = $delivered > 0 ? round($funnelData['submitted'] / $delivered * 100, 2) : 0;

        // ── Breakdown by group ──────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                g.id, g.name, g.color,
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM `groups` g
             JOIN group_recipient gr ON gr.group_id = g.id
             JOIN campaign_recipients cr ON cr.recipient_id = gr.recipient_id AND cr.campaign_id = :id
             GROUP BY g.id
             ORDER BY g.name"
        );
        $stmt->execute([':id' => $campaignId]);
        $byGroup = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($byGroup as &$row) {
            $n = (int) $row['recipients'];
            $row['open_rate']   = $n > 0 ? round((int) $row['opened']   / $n * 100, 2) : 0;
            $row['click_rate']  = $n > 0 ? round((int) $row['clicked']  / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['submitted']/ $n * 100, 2) : 0;
        }
        unset($row);

        // ── Average time between events ─────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                AVG(TIMESTAMPDIFF(SECOND, cr.delivered_at, cr.opened_at))  AS avg_deliver_to_open,
                AVG(TIMESTAMPDIFF(SECOND, cr.opened_at, cr.clicked_at))   AS avg_open_to_click,
                AVG(TIMESTAMPDIFF(SECOND, cr.clicked_at, cr.submitted_at))AS avg_click_to_submit
             FROM campaign_recipients cr
             WHERE cr.campaign_id = :id"
        );
        $stmt->execute([':id' => $campaignId]);
        $avgTimes = $stmt->fetch(\PDO::FETCH_ASSOC);

        // ── Detailed recipient list ─────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                cr.id AS campaign_recipient_id,
                r.id AS recipient_id, r.email, r.display_name, r.first_name, r.last_name, r.department,
                cr.mail_status, cr.delivered_at,
                cr.opened, cr.opened_at, cr.open_count,
                cr.clicked, cr.clicked_at, cr.click_count,
                cr.submitted_credentials, cr.submitted_at, cr.submission_count,
                cr.ip_address, cr.user_agent
             FROM campaign_recipients cr
             JOIN recipients r ON r.id = cr.recipient_id
             WHERE cr.campaign_id = :id
             ORDER BY r.last_name, r.first_name"
        );
        $stmt->execute([':id' => $campaignId]);
        $recipients = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'funnel'        => $funnelData,
            'by_group'      => $byGroup,
            'avg_times'     => [
                'deliver_to_open_seconds'  => $avgTimes['avg_deliver_to_open']  !== null ? round((float) $avgTimes['avg_deliver_to_open'])  : null,
                'open_to_click_seconds'    => $avgTimes['avg_open_to_click']    !== null ? round((float) $avgTimes['avg_open_to_click'])    : null,
                'click_to_submit_seconds'  => $avgTimes['avg_click_to_submit']  !== null ? round((float) $avgTimes['avg_click_to_submit'])  : null,
            ],
            'recipients'    => $recipients,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Group-level statistics
    // ═══════════════════════════════════════════════════════════════

    public function getGroupStats(int $groupId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFilter = $this->buildCampaignDateFilter($dateFrom, $dateTo);
        $params     = array_merge($dateFilter['params'], [':gid' => $groupId]);
        $where      = $dateFilter['where'];

        // ── Evolution over time (per campaign) ──────────────────
        $stmt = $this->db->prepare(
            "SELECT
                c.id AS campaign_id, c.name AS campaign_name, c.started_at,
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM campaigns c
             JOIN campaign_recipients cr ON cr.campaign_id = c.id
             JOIN group_recipient gr ON gr.recipient_id = cr.recipient_id AND gr.group_id = :gid
             WHERE c.status NOT IN ('draft','cancelled') {$where}
             GROUP BY c.id
             ORDER BY c.started_at ASC"
        );
        $stmt->execute($params);
        $evolution = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($evolution as &$row) {
            $n = (int) $row['recipients'];
            $row['open_rate']   = $n > 0 ? round((int) $row['opened']   / $n * 100, 2) : 0;
            $row['click_rate']  = $n > 0 ? round((int) $row['clicked']  / $n * 100, 2) : 0;
            $row['submit_rate'] = $n > 0 ? round((int) $row['submitted']/ $n * 100, 2) : 0;
        }
        unset($row);

        // ── Group average vs company average ────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM campaign_recipients cr
             JOIN group_recipient gr ON gr.recipient_id = cr.recipient_id AND gr.group_id = :gid
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE c.status NOT IN ('draft','cancelled') {$where}"
        );
        $stmt->execute($params);
        $groupTotals = $stmt->fetch(\PDO::FETCH_ASSOC);

        $gn = (int) ($groupTotals['recipients'] ?? 0);

        // Company-wide averages (reuse dateFilter params without :gid)
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(cr.id)                       AS recipients,
                SUM(cr.opened)                     AS opened,
                SUM(cr.clicked)                    AS clicked,
                SUM(cr.submitted_credentials)      AS submitted
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE c.status NOT IN ('draft','cancelled') {$where}"
        );
        $stmt->execute($dateFilter['params']);
        $companyTotals = $stmt->fetch(\PDO::FETCH_ASSOC);
        $cn = (int) ($companyTotals['recipients'] ?? 0);

        $comparison = [
            'group' => [
                'open_rate'   => $gn > 0 ? round((int) $groupTotals['opened']   / $gn * 100, 2) : 0,
                'click_rate'  => $gn > 0 ? round((int) $groupTotals['clicked']  / $gn * 100, 2) : 0,
                'submit_rate' => $gn > 0 ? round((int) $groupTotals['submitted']/ $gn * 100, 2) : 0,
            ],
            'company' => [
                'open_rate'   => $cn > 0 ? round((int) $companyTotals['opened']   / $cn * 100, 2) : 0,
                'click_rate'  => $cn > 0 ? round((int) $companyTotals['clicked']  / $cn * 100, 2) : 0,
                'submit_rate' => $cn > 0 ? round((int) $companyTotals['submitted']/ $cn * 100, 2) : 0,
            ],
        ];

        // ── Member list with individual scores ──────────────────
        $stmt = $this->db->prepare(
            "SELECT
                r.id, r.email, r.display_name, r.first_name, r.last_name, r.department,
                COUNT(cr.id)                       AS campaigns_targeted,
                SUM(cr.opened)                     AS total_opens,
                SUM(cr.clicked)                    AS total_clicks,
                SUM(cr.submitted_credentials)      AS total_submissions
             FROM recipients r
             JOIN group_recipient gr ON gr.recipient_id = r.id AND gr.group_id = :gid
             LEFT JOIN campaign_recipients cr ON cr.recipient_id = r.id
             LEFT JOIN campaigns c ON c.id = cr.campaign_id AND c.status NOT IN ('draft','cancelled')
             {$where}
             GROUP BY r.id
             ORDER BY r.last_name, r.first_name"
        );
        // Need to add :gid to dateFilter params for this query
        $memberParams = array_merge($dateFilter['params'], [':gid' => $groupId]);
        $stmt->execute($memberParams);
        $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($members as &$member) {
            $n = (int) $member['campaigns_targeted'];
            $member['click_rate']  = $n > 0 ? round((int) $member['total_clicks']      / $n * 100, 2) : 0;
            $member['submit_rate'] = $n > 0 ? round((int) $member['total_submissions'] / $n * 100, 2) : 0;
        }
        unset($member);

        return [
            'evolution'   => $evolution,
            'comparison'  => $comparison,
            'members'     => $members,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // Recipient-level statistics
    // ═══════════════════════════════════════════════════════════════

    public function getRecipientStats(int $recipientId): array
    {
        // ── Campaign history ────────────────────────────────────
        $stmt = $this->db->prepare(
            "SELECT
                c.id AS campaign_id, c.name AS campaign_name, c.started_at,
                et.name AS template_name, et.difficulty_level,
                cr.mail_status, cr.delivered_at,
                cr.opened, cr.opened_at,
                cr.clicked, cr.clicked_at,
                cr.submitted_credentials, cr.submitted_at
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             JOIN email_templates et ON et.id = c.email_template_id
             WHERE cr.recipient_id = :rid AND c.status NOT IN ('draft','cancelled')
             ORDER BY c.started_at DESC"
        );
        $stmt->execute([':rid' => $recipientId]);
        $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // ── Vigilance score ─────────────────────────────────────
        // Percentage of campaigns where the recipient did NOT click or submit
        $totalCampaigns = count($history);
        $safeCampaigns  = 0;
        foreach ($history as $row) {
            if ((int) $row['clicked'] === 0 && (int) $row['submitted_credentials'] === 0) {
                $safeCampaigns++;
            }
        }
        $vigilanceScore = $totalCampaigns > 0
            ? round($safeCampaigns / $totalCampaigns * 100, 2)
            : 100;

        // ── Behavior evolution ──────────────────────────────────
        // Same data as history but formatted for charting (chronological)
        $behaviorEvolution = [];
        foreach (array_reverse($history) as $row) {
            $behaviorEvolution[] = [
                'campaign_id'   => $row['campaign_id'],
                'campaign_name' => $row['campaign_name'],
                'started_at'    => $row['started_at'],
                'difficulty'    => (int) $row['difficulty_level'],
                'opened'        => (bool) $row['opened'],
                'clicked'       => (bool) $row['clicked'],
                'submitted'     => (bool) $row['submitted_credentials'],
            ];
        }

        return [
            'campaigns_targeted' => $totalCampaigns,
            'vigilance_score'    => $vigilanceScore,
            'history'            => $history,
            'behavior_evolution' => $behaviorEvolution,
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // CSV export
    // ═══════════════════════════════════════════════════════════════

    /**
     * Generate CSV content for the given export type.
     *
     * @param  string   $type 'global', 'campaign', or 'group'
     * @param  int|null $id   Campaign or group ID (required for campaign/group types)
     * @return string   CSV content
     */
    public function exportCsv(string $type, ?int $id = null): string
    {
        $output = fopen('php://temp', 'r+');

        switch ($type) {
            case 'global':
                $this->exportGlobalCsv($output);
                break;

            case 'campaign':
                if ($id === null) {
                    throw new \InvalidArgumentException('Campaign ID is required for campaign export');
                }
                $this->exportCampaignCsv($output, $id);
                break;

            case 'group':
                if ($id === null) {
                    throw new \InvalidArgumentException('Group ID is required for group export');
                }
                $this->exportGroupCsv($output, $id);
                break;

            default:
                throw new \InvalidArgumentException("Unknown export type: {$type}");
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Add UTF-8 BOM for Excel compatibility
        return "\xEF\xBB\xBF" . $csv;
    }

    // ─── Private helpers ────────────────────────────────────────────

    private function exportGlobalCsv($fp): void
    {
        fputcsv($fp, [
            'Campagne', 'Date', 'Destinataires', 'Ouvertures', 'Taux ouverture',
            'Clics', 'Taux clic', 'Soumissions', 'Taux soumission',
        ], ';');

        $stmt = $this->db->prepare(
            "SELECT
                c.name, c.started_at,
                COUNT(cr.id) AS recipients,
                SUM(cr.opened) AS opened,
                SUM(cr.clicked) AS clicked,
                SUM(cr.submitted_credentials) AS submitted
             FROM campaigns c
             JOIN campaign_recipients cr ON cr.campaign_id = c.id
             WHERE c.status NOT IN ('draft','cancelled')
             GROUP BY c.id
             ORDER BY c.started_at DESC"
        );
        $stmt->execute();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $n = (int) $row['recipients'];
            fputcsv($fp, [
                $row['name'],
                $row['started_at'],
                $n,
                (int) $row['opened'],
                $n > 0 ? round((int) $row['opened']   / $n * 100, 1) . '%' : '0%',
                (int) $row['clicked'],
                $n > 0 ? round((int) $row['clicked']  / $n * 100, 1) . '%' : '0%',
                (int) $row['submitted'],
                $n > 0 ? round((int) $row['submitted']/ $n * 100, 1) . '%' : '0%',
            ], ';');
        }
    }

    private function exportCampaignCsv($fp, int $campaignId): void
    {
        fputcsv($fp, [
            'Email', 'Nom', 'Prenom', 'Service', 'Statut livraison',
            'Livre le', 'Ouvert', 'Ouvert le', 'Clique', 'Clique le',
            'Soumis', 'Soumis le', 'Adresse IP',
        ], ';');

        $stmt = $this->db->prepare(
            "SELECT
                r.email, r.last_name, r.first_name, r.department,
                cr.mail_status, cr.delivered_at,
                cr.opened, cr.opened_at,
                cr.clicked, cr.clicked_at,
                cr.submitted_credentials, cr.submitted_at,
                cr.ip_address
             FROM campaign_recipients cr
             JOIN recipients r ON r.id = cr.recipient_id
             WHERE cr.campaign_id = :id
             ORDER BY r.last_name, r.first_name"
        );
        $stmt->execute([':id' => $campaignId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($fp, [
                $row['email'],
                $row['last_name'],
                $row['first_name'],
                $row['department'],
                $row['mail_status'],
                $row['delivered_at'],
                $row['opened'] ? 'Oui' : 'Non',
                $row['opened_at'],
                $row['clicked'] ? 'Oui' : 'Non',
                $row['clicked_at'],
                $row['submitted_credentials'] ? 'Oui' : 'Non',
                $row['submitted_at'],
                $row['ip_address'],
            ], ';');
        }
    }

    private function exportGroupCsv($fp, int $groupId): void
    {
        fputcsv($fp, [
            'Email', 'Nom', 'Prenom', 'Service',
            'Campagnes ciblees', 'Ouvertures', 'Clics', 'Soumissions',
            'Taux clic', 'Taux soumission', 'Score vigilance',
        ], ';');

        $stmt = $this->db->prepare(
            "SELECT
                r.email, r.last_name, r.first_name, r.department,
                COUNT(cr.id) AS campaigns_targeted,
                SUM(cr.opened) AS total_opens,
                SUM(cr.clicked) AS total_clicks,
                SUM(cr.submitted_credentials) AS total_submissions
             FROM recipients r
             JOIN group_recipient gr ON gr.recipient_id = r.id AND gr.group_id = :gid
             LEFT JOIN campaign_recipients cr ON cr.recipient_id = r.id
             LEFT JOIN campaigns c ON c.id = cr.campaign_id AND c.status NOT IN ('draft','cancelled')
             GROUP BY r.id
             ORDER BY r.last_name, r.first_name"
        );
        $stmt->execute([':gid' => $groupId]);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $n = (int) $row['campaigns_targeted'];
            $clicks = (int) $row['total_clicks'];
            $submissions = (int) $row['total_submissions'];
            $safe = $n > 0 ? $n - max($clicks, $submissions) : 0;

            fputcsv($fp, [
                $row['email'],
                $row['last_name'],
                $row['first_name'],
                $row['department'],
                $n,
                (int) $row['total_opens'],
                $clicks,
                $submissions,
                $n > 0 ? round($clicks      / $n * 100, 1) . '%' : '0%',
                $n > 0 ? round($submissions / $n * 100, 1) . '%' : '0%',
                $n > 0 ? round($safe         / $n * 100, 1) . '%' : '100%',
            ], ';');
        }
    }

    /**
     * Build a WHERE clause fragment and params array for filtering campaigns by date range.
     */
    private function buildCampaignDateFilter(?string $dateFrom, ?string $dateTo): array
    {
        $where  = '';
        $params = [];

        if ($dateFrom !== null) {
            $where .= ' AND c.started_at >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $where .= ' AND c.started_at <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        return ['where' => $where, 'params' => $params];
    }
}
