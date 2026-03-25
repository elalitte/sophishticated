<?php

namespace App\Controllers;

use App\Models\User;

class AdminController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /api/admin/users
     * List all users.
     */
    public function index(): void
    {
        $userModel = new User($this->db);

        $filters = [];
        if (!empty($_GET['role'])) {
            $filters['role'] = $_GET['role'];
        }
        if (isset($_GET['is_active'])) {
            $filters['is_active'] = (int) $_GET['is_active'];
        }
        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));

        $result = $userModel->findAll($filters, $page, $perPage);

        // Strip password hashes from output
        $result['data'] = array_map(function (array $user) {
            unset($user['password']);
            return $user;
        }, $result['data']);

        json_response($result);
    }

    /**
     * POST /api/admin/users
     * Create a new user.
     */
    public function store(): void
    {
        $input = get_json_input();

        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role     = $input['role'] ?? 'user';

        if ($username === '') {
            json_response(['error' => 'Username is required'], 422);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'A valid email is required'], 422);
        }
        if (strlen($password) < 8) {
            json_response(['error' => 'Password must be at least 8 characters'], 422);
        }

        $userModel = new User($this->db);

        // Check uniqueness
        if ($userModel->findByUsername($username)) {
            json_response(['error' => 'Username already exists'], 409);
        }
        if ($userModel->findByEmail($email)) {
            json_response(['error' => 'Email already exists'], 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $id = $userModel->create([
            'username'      => $username,
            'email'         => $email,
            'password' => $passwordHash,
            'role'          => $role,
            'is_active'     => $input['is_active'] ?? 1,
        ]);

        audit_log('create_user', 'user', $id, ['username' => $username, 'role' => $role]);

        $user = $userModel->findById($id);
        unset($user['password']);

        json_response($user, 201);
    }

    /**
     * PUT /api/admin/users/{id}
     * Update a user's role or status.
     */
    public function update($id): void
    {
        $id = (int) $id;
        $userModel = new User($this->db);
        $user = $userModel->findById($id);

        if (!$user) {
            json_response(['error' => 'User not found'], 404);
        }

        $input = get_json_input();

        $allowedFields = ['role', 'is_active', 'username', 'email', 'must_change_password'];
        $data = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }

        // If a new password is provided, hash it
        if (!empty($input['password'])) {
            $data['password'] = password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }

        if (empty($data)) {
            json_response(['error' => 'No valid fields to update'], 422);
        }

        $userModel->update($id, $data);

        audit_log('update_user', 'user', $id, array_keys($data));

        $updated = $userModel->findById($id);
        unset($updated['password']);

        json_response($updated);
    }

    /**
     * DELETE /api/admin/users/{id}
     * Delete a user (cannot delete self).
     */
    public function destroy($id): void
    {
        $id = (int) $id;
        $currentUser = current_user();
        if ($currentUser && (int) $currentUser['id'] === $id) {
            json_response(['error' => 'You cannot delete your own account'], 422);
        }

        $userModel = new User($this->db);
        $user = $userModel->findById($id);

        if (!$user) {
            json_response(['error' => 'User not found'], 404);
        }

        $userModel->delete($id);

        audit_log('delete_user', 'user', $id, ['username' => $user['username']]);

        json_response(['message' => 'User deleted successfully']);
    }

    /**
     * GET /api/admin/audit-log
     * Paginated audit logs with filters.
     */
    public function auditLog(): void
    {
        $where  = [];
        $params = [];

        if (!empty($_GET['action'])) {
            $where[] = 'action = :action';
            $params[':action'] = $_GET['action'];
        }

        if (!empty($_GET['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params[':user_id'] = (int) $_GET['user_id'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset  = ($page - 1) * $perPage;

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT al.*, u.username
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             {$whereClause}
             ORDER BY al.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }
        $stmt->execute();

        json_response([
            'data'     => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * POST /api/admin/users/{id}/reset-password
     * Reset a user's password.
     */
    public function resetPassword($id): void
    {
        $id = (int) $id;
        $userModel = new User($this->db);
        $user = $userModel->findById($id);

        if (!$user) {
            json_response(['error' => 'User not found'], 404);
        }

        $input = get_json_input();
        $newPassword = $input['password'] ?? '';

        if (strlen($newPassword) < 8) {
            $newPassword = bin2hex(random_bytes(6)); // Generate random 12-char password
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        $userModel->update($id, [
            'password' => $hash,
            'must_change_password' => 1,
        ]);

        audit_log('reset_password', 'user', $id);

        json_response([
            'message' => 'Password reset successfully',
            'temporary_password' => $newPassword,
        ]);
    }
}
