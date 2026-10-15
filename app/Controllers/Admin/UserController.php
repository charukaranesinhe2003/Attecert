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

    public function index(): Response
    {
        return $this->view('admin/users/index', [
            'title' => __('admin.users'),
            'robots' => 'noindex',
            'active' => 'users',
            'users' => $this->db->fetchAll('SELECT u.id, u.name, u.email, u.is_active, GROUP_CONCAT(r.code) AS role_codes FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.deleted_at IS NULL AND EXISTS (SELECT 1 FROM user_roles sr INNER JOIN roles rr ON rr.id = sr.role_id WHERE sr.user_id = u.id AND rr.code <> \'customer\') GROUP BY u.id, u.name, u.email, u.is_active ORDER BY u.name'),
            'roles' => $this->staffRoles(),
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
        $email = mb_strtolower(trim((string) $data['email']));
        $safeInput = array_diff_key($data, ['password' => true]);
        if ($this->db->fetchColumn('SELECT id FROM users WHERE email = ?', [$email])) {
            throw new ValidationException(['email' => __('auth.email_taken')], $safeInput);
        }
        try {
            $this->db->transaction(function () use ($data, $email, $role): void {
                $id = $this->db->insert(
                    'INSERT INTO users (name, email, password_hash, email_verified_at, must_change_password) VALUES (?, ?, ?, NOW(), 1)',
                    [$data['name'], $email, password_hash($data['password'], PASSWORD_DEFAULT)]
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
