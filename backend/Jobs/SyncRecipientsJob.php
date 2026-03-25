<?php

require_once __DIR__ . '/../Services/MicrosoftGraphService.php';
require_once __DIR__ . '/../Services/WebSocketBroadcaster.php';

use App\Services\MicrosoftGraphService;
use App\Services\WebSocketBroadcaster;

class SyncRecipientsJob
{
    /**
     * Sync recipients from Microsoft Graph into the local database.
     *
     * - Creates / updates recipients from Graph users
     * - Marks recipients no longer in Graph as synced_from_graph=0
     * - Auto-creates 'team' groups for each unique department
     * - Associates recipients with their department group
     */
    public function handle(array $payload, \PDO $db): void
    {
        $graphService = new MicrosoftGraphService();
        $graphUsers   = $graphService->getAllUsers();

        $syncedGraphIds = [];
        $syncedEmails   = [];
        $syncedCount    = 0;

        // ── Upsert each Graph user ──────────────────────────────────────
        foreach ($graphUsers as $user) {
            $graphId     = $user['id']          ?? null;
            $email       = $user['mail']        ?? null;
            $displayName = $user['displayName'] ?? null;
            $firstName   = $user['givenName']   ?? null;
            $lastName    = $user['surname']     ?? null;
            $jobTitle    = $user['jobTitle']    ?? null;
            $department  = $user['department']  ?? null;

            if (empty($email)) {
                continue;
            }

            // Try to find existing recipient by graph_id or email
            $existing = null;

            if ($graphId) {
                $stmt = $db->prepare('SELECT * FROM recipients WHERE graph_id = :graph_id LIMIT 1');
                $stmt->execute([':graph_id' => $graphId]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            if (!$existing) {
                $stmt = $db->prepare('SELECT * FROM recipients WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $existing = $stmt->fetch(\PDO::FETCH_ASSOC);
            }

            if ($existing) {
                // Update existing recipient
                $stmt = $db->prepare(
                    'UPDATE recipients
                     SET display_name    = :display_name,
                         first_name      = :first_name,
                         last_name       = :last_name,
                         job_title       = :job_title,
                         department      = :department,
                         graph_id        = :graph_id,
                         synced_from_graph = 1,
                         last_synced_at  = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':display_name' => $displayName,
                    ':first_name'   => $firstName,
                    ':last_name'    => $lastName,
                    ':job_title'    => $jobTitle,
                    ':department'   => $department,
                    ':graph_id'     => $graphId,
                    ':id'           => $existing['id'],
                ]);

                $recipientId = (int) $existing['id'];
            } else {
                // Create new recipient
                $stmt = $db->prepare(
                    'INSERT INTO recipients (email, display_name, first_name, last_name, job_title, department, graph_id, synced_from_graph, last_synced_at)
                     VALUES (:email, :display_name, :first_name, :last_name, :job_title, :department, :graph_id, 1, NOW())'
                );
                $stmt->execute([
                    ':email'        => $email,
                    ':display_name' => $displayName,
                    ':first_name'   => $firstName,
                    ':last_name'    => $lastName,
                    ':job_title'    => $jobTitle,
                    ':department'   => $department,
                    ':graph_id'     => $graphId,
                ]);

                $recipientId = (int) $db->lastInsertId();
            }

            if ($graphId) {
                $syncedGraphIds[] = $graphId;
            }
            $syncedEmails[] = $email;
            $syncedCount++;

            // ── Associate recipient with department group ────────────
            if (!empty($department)) {
                $groupId = $this->ensureDepartmentGroup($db, $department);
                $this->associateRecipientGroup($db, $recipientId, $groupId);
            }
        }

        // ── Mark recipients not found in Graph ──────────────────────────
        // Set synced_from_graph=0 for recipients that were previously synced
        // but are no longer present in Graph (do NOT deactivate them)
        if (!empty($syncedGraphIds)) {
            $placeholders = implode(',', array_fill(0, count($syncedGraphIds), '?'));
            $stmt = $db->prepare(
                "UPDATE recipients
                 SET synced_from_graph = 0
                 WHERE synced_from_graph = 1
                   AND graph_id IS NOT NULL
                   AND graph_id NOT IN ({$placeholders})"
            );
            $stmt->execute($syncedGraphIds);
        }

        // ── Broadcast completion event ──────────────────────────────────
        WebSocketBroadcaster::broadcast('sync', 'sync.completed', [
            'count'     => $syncedCount,
            'timestamp' => date('c'),
        ]);
    }

    /**
     * Ensure a group of type 'team' exists for the given department name.
     * Returns the group ID.
     */
    private function ensureDepartmentGroup(\PDO $db, string $department): int
    {
        $stmt = $db->prepare(
            "SELECT id FROM `groups` WHERE name = :name AND type = 'team' LIMIT 1"
        );
        $stmt->execute([':name' => $department]);
        $group = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($group) {
            return (int) $group['id'];
        }

        $stmt = $db->prepare(
            "INSERT INTO `groups` (name, type, description)
             VALUES (:name, 'team', :description)"
        );
        $stmt->execute([
            ':name'        => $department,
            ':description' => "Auto-created from Microsoft Graph department: {$department}",
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Associate a recipient with a group (ignore if already associated).
     */
    private function associateRecipientGroup(\PDO $db, int $recipientId, int $groupId): void
    {
        $stmt = $db->prepare(
            'INSERT IGNORE INTO group_recipient (recipient_id, group_id)
             VALUES (:recipient_id, :group_id)'
        );
        $stmt->execute([
            ':recipient_id' => $recipientId,
            ':group_id'     => $groupId,
        ]);
    }
}
