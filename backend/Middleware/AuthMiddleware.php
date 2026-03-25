<?php
/**
 * AuthMiddleware – Ensures the user is authenticated (session-based).
 */
class AuthMiddleware
{
    public static function handle(): void
    {
        if (empty($_SESSION['user'])) {
            json_response(['error' => 'Unauthorized – authentication required'], 401);
        }
    }
}
