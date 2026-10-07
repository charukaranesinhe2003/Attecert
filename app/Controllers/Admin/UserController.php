<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
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
            'users' => $this->db->fetchAll('SELECT u.*, GROUP_CONCAT(r.code) AS role_codes FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id WHERE u.deleted_at IS NULL GROUP BY u.id ORDER BY u.name'),
            'roles' => $this->db->fetchAll('SELECT * FROM roles ORDER BY name'),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->view('admin/users/form', [
            'title' => __('admin.new_user'),
            'robots' => 'noindex',
            'active' => 'users',
            'roles' => $this->db->fetchAll('SELECT * FROM roles WHERE code <> \'customer\' ORDER BY name'),
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
        if ($data['role'] === 'super_admin' && !$this->auth->can('settings.manage') && !in_array('super_admin', $this->auth->user()['roles'], true)) {
            flash('error', __('admin.forbidden_role'));

            return $this->redirect('/admin/users');
        }
        $id = $this->db->insert(
            'INSERT INTO users (name, email, password_hash, email_verified_at, must_change_password) VALUES (?, ?, ?, NOW(), 1)',
            [$data['name'], mb_strtolower($data['email']), password_hash($data['password'], PASSWORD_DEFAULT)]
        );
        $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', [$data['role']]);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $roleId]);
        $this->audit->log($this->auth->id(), 'staff_created', 'user', $id, ['role' => $data['role']]);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/users');
    }

    public function update(Request $request, string $id): Response
    {
        $active = $request->input('is_active') ? 1 : 0;
        $this->db->run('UPDATE users SET is_active = ? WHERE id = ?', [$active, (int) $id]);
        if ($request->input('role')) {
            $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', [(string) $request->input('role')]);
            if ($roleId > 0) {
                $this->db->run('DELETE FROM user_roles WHERE user_id = ?', [(int) $id]);
                $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [(int) $id, $roleId]);
            }
        }
        $this->audit->log($this->auth->id(), 'staff_updated', 'user', (int) $id, ['active' => $active]);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/users');
    }
}
