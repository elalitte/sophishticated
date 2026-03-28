<?php
/**
 * Bootstrap – Application initialization
 *
 * Loaded once by the front controller (public/index.php).
 * Sets up autoloading, environment, error reporting, session, and core includes.
 */

// ── Composer autoload ──────────────────────────────────────────────
require_once __DIR__ . '/../vendor/autoload.php';

// ── Environment variables (.env at project root) ───────────────────
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── Error reporting ────────────────────────────────────────────────
if (isset($_ENV['APP_DEBUG']) && filter_var($_ENV['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ── Session ────────────────────────────────────────────────────────
$sessionLifetime = isset($_ENV['SESSION_LIFETIME']) ? (int) $_ENV['SESSION_LIFETIME'] : 1440;

session_start([
    'cookie_httponly'  => true,
    'cookie_samesite'  => 'Strict',
    'gc_maxlifetime'   => $sessionLifetime,
]);

// Generate CSRF token if one does not exist yet
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Core includes ──────────────────────────────────────────────────
require_once __DIR__ . '/Config/database.php';
require_once __DIR__ . '/helpers.php';
