<?php
/**
 * Global helper functions used across controllers and middlewares.
 */

/**
 * Send a JSON response and terminate.
 */
function json_response(mixed $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Redirect the browser and terminate.
 */
function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

/**
 * Return the current CSRF token from the session.
 */
function csrf_token(): string
{
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Sanitize a string for safe HTML output.
 */
function sanitize(string $input): string
{
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a cryptographically-safe UUID v4.
 */
function generate_uuid(): string
{
    $bytes = random_bytes(16);

    // Set version to 0100 (UUID v4)
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    // Set variant to 10xx
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

    return sprintf(
        '%s-%s-%s-%s-%s',
        bin2hex(substr($bytes, 0, 4)),
        bin2hex(substr($bytes, 4, 2)),
        bin2hex(substr($bytes, 6, 2)),
        bin2hex(substr($bytes, 8, 2)),
        bin2hex(substr($bytes, 10, 6))
    );
}

/**
 * Read and decode the raw JSON body of the current request.
 */
function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Insert a row into the audit_logs table.
 */
function audit_log(
    string  $action,
    ?string $targetType = null,
    ?int    $targetId   = null,
    ?array  $details    = null
): void {
    $user = current_user();
    $userId = $user['id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO audit_logs (user_id, action, target_type, target_id, details, ip_address)
         VALUES (:user_id, :action, :target_type, :target_id, :details, :ip_address)'
    );
    $stmt->execute([
        ':user_id'     => $userId,
        ':action'      => $action,
        ':target_type' => $targetType,
        ':target_id'   => $targetId,
        ':details'     => $details !== null ? json_encode($details) : null,
        ':ip_address'  => $ip,
    ]);
}

/**
 * Return the currently authenticated user array, or null.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Ensure the current user has one of the given roles.
 * Sends a 403 JSON response and exits if the check fails.
 */
function require_role(string ...$roles): void
{
    $user = current_user();
    if ($user === null || !in_array($user['role'] ?? '', $roles, true)) {
        json_response(['error' => 'Forbidden – insufficient role'], 403);
    }
}
