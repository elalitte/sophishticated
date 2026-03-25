<?php

namespace App\Controllers;

use App\Models\User;

class AuthController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * POST /api/auth/login
     * Authenticate user with username + password.
     */
    public function login(): void
    {
        $input = get_json_input();

        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if ($username === '' || $password === '') {
            json_response(['error' => 'Username and password are required'], 422);
        }

        $userModel = new User($this->db);
        $user = $userModel->findByUsername($username);

        if (!$user || !password_verify($password, $user['password'] ?? '')) {
            json_response(['error' => 'Invalid credentials'], 401);
        }

        if (empty($user['is_active'])) {
            json_response(['error' => 'Account is disabled'], 403);
        }

        // Update last login timestamp
        $userModel->updateLastLogin((int) $user['id']);

        // Build session user (exclude sensitive fields)
        $sessionUser = [
            'id'                   => (int) $user['id'],
            'username'             => $user['username'],
            'email'                => $user['email'],
            'role'                 => $user['role'],
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
        ];

        $_SESSION['user'] = $sessionUser;

        // Regenerate CSRF token on login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        audit_log('login', 'user', (int) $user['id']);

        json_response([
            'user'       => $sessionUser,
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * POST /api/auth/logout
     * Destroy the current session.
     */
    public function logout(): void
    {
        audit_log('logout', 'user', $_SESSION['user']['id'] ?? null);

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        json_response(['message' => 'Logged out successfully']);
    }

    /**
     * GET /api/auth/me
     * Return the current authenticated user.
     */
    public function me(): void
    {
        $user = current_user();

        if (!$user) {
            json_response(['error' => 'Not authenticated'], 401);
        }

        json_response([
            'user'       => $user,
            'csrf_token' => csrf_token(),
        ]);
    }

    /**
     * POST /api/auth/change-password
     * Change the current user's password.
     */
    public function changePassword(): void
    {
        $user = current_user();
        if (!$user) {
            json_response(['error' => 'Not authenticated'], 401);
        }

        $input = get_json_input();

        $oldPassword = $input['old_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';

        if ($oldPassword === '' || $newPassword === '') {
            json_response(['error' => 'Old password and new password are required'], 422);
        }

        // Validate new password strength: min 8 chars, 1 uppercase, 1 digit
        if (strlen($newPassword) < 8) {
            json_response(['error' => 'New password must be at least 8 characters'], 422);
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            json_response(['error' => 'New password must contain at least one uppercase letter'], 422);
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            json_response(['error' => 'New password must contain at least one digit'], 422);
        }

        // Verify old password against the database
        $userModel = new User($this->db);
        $dbUser = $userModel->findById((int) $user['id']);

        if (!$dbUser || !password_verify($oldPassword, $dbUser['password'] ?? '')) {
            json_response(['error' => 'Current password is incorrect'], 401);
        }

        // Hash and update
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $userModel->update((int) $user['id'], [
            'password'             => $newHash,
            'must_change_password' => 0,
        ]);

        // Update session
        $_SESSION['user']['must_change_password'] = 0;

        audit_log('change_password', 'user', (int) $user['id']);

        json_response(['message' => 'Password changed successfully']);
    }
}
