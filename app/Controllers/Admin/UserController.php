<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use App\Services\AuthService;

final class UserController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private AuthService $auth,
        private AuditService $audit
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('q', '');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, 160) : '';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '';
        $roles = $this->staffRoles();
        $role = in_array($request->query('role'), array_column($roles, 'code'), true) ? $request->query('role') : '';
        $sql = 'SELECT u.id, u.name, u.email, u.is_active, GROUP_CONCAT(r.code ORDER BY r.code) AS role_codes FROM users u
                LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id
                WHERE u.deleted_at IS NULL AND EXISTS (SELECT 1 FROM user_roles sr INNER JOIN roles rr ON rr.id = sr.role_id WHERE sr.user_id = u.id AND rr.code <> \'customer\')';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $params = ['%' . $q . '%', '%' . $q . '%'];
        }
        if ($status !== '') {
            $sql .= ' AND u.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        if ($role !== '') {
            $sql .= ' AND EXISTS (SELECT 1 FROM user_roles fr INNER JOIN roles filter_role ON filter_role.id = fr.role_id WHERE fr.user_id = u.id AND filter_role.code = ?)';
            $params[] = $role;
        }
        $sql .= ' GROUP BY u.id, u.name, u.email, u.is_active ORDER BY u.name, u.id';

        return $this->view('admin/users/index', [
            'title' => __('admin.users'),
            'robots' => 'noindex',
            'active' => 'users',
            'filters' => ['q' => $q, 'status' => $status, 'role' => $role],
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
            'roles' => $roles,
            'canManagePrivileged' => $this->canManagePrivileged(),
            'currentUserId' => $this->auth->id(),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->view('admin/users/form', [
            'title' => __('admin.new_user'),
            'robots' => 'noindex',
            'active' => 'users',
            'roles' => $this->staffRoles(),
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'password' => 'required|min:8|max:200',
            'role' => 'required|max:40',
        ]);
        $role = $this->validatedRole((string) $data['role']);
        // Preserve the exact password chosen by the administrator.
        $data['password'] = $request->input('password');
        if (mb_strlen($data['password']) > 200 || str_contains($data['password'], "\0")) {
            throw new ValidationException(['password' => 'Use a password between 8 and 200 characters.']);
        }
        $email = mb_strtolower(trim((string) $data['email']));
        $safeInput = array_diff_key($data, ['password' => true]);
        if ($this->db->fetchColumn('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new ValidationException(['email' => __('auth.email_taken')], $safeInput);
        }
        try {
            $this->db->transaction(function () use ($data, $email, $role): void {
                $id = $this->db->insert(
                    'INSERT INTO users (name, email, password_hash, email_verified_at, must_change_password) VALUES (?, ?, ?, NOW(), 1)',
                    [$data['name'], $email, \App\Helpers\Password::hash($data['password'])]
                );
                $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $role['id']]);
                $this->audit->log($this->auth->id(), 'staff_created', 'user', $id, ['role' => $role['code']]);
            });
        } catch (\PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                throw new ValidationException(['email' => __('auth.email_taken')], $safeInput);
            }
            throw $exception;
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/users');
    }

    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['is_active' => 'nullable|in:0,1', 'role' => 'nullable|max:40']);
        $active = (int) ($data['is_active'] ?? 0);
        $role = !empty($data['role']) ? $this->validatedRole((string) $data['role']) : null;
        $this->db->transaction(function () use ($id, $active, $role): void {
            // Serialize changes to the active-super-admin invariant before locking accounts.
            $this->db->fetch('SELECT id FROM roles WHERE code = ? FOR UPDATE', ['super_admin']);
            $user = $this->db->fetch('SELECT id, is_active FROM users WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [(int) $id]);
            if ($user === null) {
                throw new HttpException(404, 'Staff account not found.');
            }
            $roles = array_column($this->db->fetchAll('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [(int) $id]), 'code');
            if (array_filter($roles, static fn (string $code): bool => $code !== 'customer') === []) {
                throw new HttpException(403, 'Manage customer accounts from Customers.');
            }
            if (in_array('super_admin', $roles, true) && !$this->canManagePrivileged()) {
                throw new HttpException(403, __('admin.forbidden_role'));
            }
            if ((int) $id === $this->auth->id() && $active === 0) {
                throw new ValidationException(['is_active' => 'You cannot deactivate your own account.']);
            }
            $nextRoles = $role === null ? $roles : [$role['code']];
            if ((int) $user['is_active'] === 1 && in_array('super_admin', $roles, true)
                && ($active === 0 || !in_array('super_admin', $nextRoles, true))) {
                $other = $this->db->fetch('SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.code = ? AND u.is_active = 1 AND u.deleted_at IS NULL AND u.id <> ? LIMIT 1 FOR UPDATE', ['super_admin', (int) $id]);
                if ($other === null) {
                    throw new ValidationException(['role' => 'Keep at least one active super administrator.']);
                }
            }
            $this->db->run('UPDATE users SET is_active = ? WHERE id = ?', [$active, (int) $id]);
            if ($role !== null) {
                $this->db->run('DELETE FROM user_roles WHERE user_id = ?', [(int) $id]);
                $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [(int) $id, $role['id']]);
            }
            $this->audit->log($this->auth->id(), 'staff_updated', 'user', (int) $id, [
                'previous_active' => (int) $user['is_active'], 'active' => $active,
                'previous_roles' => $roles, 'roles' => $nextRoles,
            ]);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/users');
    }

    public function passwordForm(Request $request, string $id): Response
    {
        $user = $this->managedStaff((int) $id);
        if ((int) $id === $this->auth->id()) {
            return $this->redirect('/admin/password');
        }
        return $this->view('admin/users/password', [
            'title' => 'Change staff password', 'active' => 'users', 'staffUser' => $user,
        ], 'layouts/admin');
    }

    public function password(Request $request, string $id): Response
    {
        if ((int) $id === $this->auth->id()) {
            return $this->redirect('/admin/password');
        }
        $password = $request->input('password');
        if (!is_string($password) || mb_strlen($password) < 8 || mb_strlen($password) > 200 || str_contains($password, "\0")) {
            throw new ValidationException(['password' => 'Use a password between 8 and 200 characters.']);
        }
        if (!is_string($request->input('password_confirmation')) || !hash_equals($password, $request->input('password_confirmation'))) {
            throw new ValidationException(['password_confirmation' => 'The password confirmation must match.']);
        }
        $hash = \App\Helpers\Password::hash($password);
        $this->db->transaction(function () use ($id, $hash): void {
            $user = $this->managedStaff((int) $id, true);
            $this->db->run('UPDATE users SET password_hash = ?, must_change_password = 1, locked_until = NULL, failed_login_count = 0 WHERE id = ?', [$hash, (int) $id]);
            $this->db->run("UPDATE password_resets SET used_at = NOW() WHERE email = ? AND purpose = 'reset' AND used_at IS NULL", [$user['email']]);
            $this->audit->log($this->auth->id(), 'staff_password_changed', 'user', (int) $id);
        });
        flash('success', 'Staff password changed. Existing sessions are signed out. The staff member must change this temporary password after signing in.');
        return $this->redirect('/admin/users');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->validate($request, ['confirmed' => 'required|in:1']);
        $this->db->transaction(function () use ($id): void {
            $this->db->fetch("SELECT id FROM roles WHERE code = 'super_admin' FOR UPDATE");
            $user = $this->managedStaff((int) $id, true);
            if ((int) $id === $this->auth->id()) {
                throw new ValidationException(['user' => 'You cannot delete your own account.']);
            }
            if (in_array('super_admin', $user['roles'], true) && (int) $user['is_active'] === 1) {
                $other = $this->db->fetch("SELECT u.id FROM users u WHERE u.id <> ? AND u.is_active = 1 AND u.deleted_at IS NULL
                    AND EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code = 'super_admin') LIMIT 1 FOR UPDATE", [(int) $id]);
                if ($other === null) {
                    throw new ValidationException(['user' => 'Keep at least one active super administrator.']);
                }
            }
            $unassigned = $this->db->run('UPDATE cases SET assigned_to = NULL WHERE assigned_to = ?', [(int) $id])->rowCount();
            $unassignedQuotes = $this->db->run('UPDATE quote_requests SET assigned_to = NULL WHERE assigned_to = ?', [(int) $id])->rowCount();
            $this->db->run('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?', [(int) $id]);
            $this->db->run("UPDATE password_resets SET used_at = NOW() WHERE email = ? AND purpose = 'reset' AND used_at IS NULL", [$user['email']]);
            $this->audit->log($this->auth->id(), 'staff_deleted', 'user', (int) $id, ['unassigned_cases' => $unassigned, 'unassigned_quote_requests' => $unassignedQuotes]);
        });
        flash('success', 'Staff account deleted and access removed. Their cases and quote requests are now unassigned in Operations. History is retained.');
        return $this->redirect('/admin/users');
    }

    private function managedStaff(int $id, bool $lock = false): array
    {
        if (!$this->auth->isAdmin() || !$this->auth->can('users.manage')) {
            throw new HttpException(403, 'Only administrators can manage staff.');
        }
        $user = $this->db->fetch('SELECT id, name, email, is_active FROM users WHERE id = ? AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if ($user === null) {
            throw new HttpException(404, 'Staff account not found.');
        }
        $user['roles'] = array_column($this->db->fetchAll('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$id]), 'code');
        if (array_diff($user['roles'], ['customer']) === []) {
            throw new HttpException(403, 'Manage customer accounts from Customers.');
        }
        if (in_array('super_admin', $user['roles'], true) && !$this->canManagePrivileged()) {
            throw new HttpException(403, __('admin.forbidden_role'));
        }
        return $user;
    }

    private function canManagePrivileged(): bool
    {
        return $this->auth->can('settings.manage') || in_array('super_admin', $this->auth->user()['roles'] ?? [], true);
    }

    private function staffRoles(): array
    {
        return array_values(array_filter($this->db->fetchAll('SELECT * FROM roles WHERE code <> \'customer\' ORDER BY name'),
            fn (array $role): bool => $role['code'] !== 'super_admin' || $this->canManagePrivileged()));
    }

    private function validatedRole(string $code): array
    {
        if ($code === 'super_admin' && !$this->canManagePrivileged()) {
            throw new HttpException(403, __('admin.forbidden_role'));
        }
        $role = $this->db->fetch('SELECT id, code FROM roles WHERE code = ? AND code <> ?', [$code, 'customer']);
        if ($role === null) {
            throw new ValidationException(['role' => 'Choose a valid staff role.']);
        }

        return $role;
    }
}
