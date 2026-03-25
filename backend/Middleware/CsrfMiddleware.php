<?php
/**
 * CsrfMiddleware – Validates X-CSRF-TOKEN header against the session token.
 *
 * GET requests are considered safe and are automatically skipped.
 */
class CsrfMiddleware
{
    public static function handle(): void
    {
        // Safe methods do not require CSRF validation
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return;
        }

        $headerToken  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || !hash_equals($sessionToken, $headerToken)) {
            json_response(['error' => 'Forbidden – invalid CSRF token'], 403);
        }
    }
}
