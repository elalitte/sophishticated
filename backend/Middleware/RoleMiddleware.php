<?php
/**
 * RoleMiddleware – Verifies the authenticated user has an allowed role.
 */
class RoleMiddleware
{
    public static function handle(string ...$roles): void
    {
        $user = $_SESSION['user'] ?? null;

        if ($user === null || !in_array($user['role'] ?? '', $roles, true)) {
            json_response(['error' => 'Forbidden – insufficient role'], 403);
        }
    }
}
