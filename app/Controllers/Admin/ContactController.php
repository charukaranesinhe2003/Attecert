<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class ContactController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/contacts/index', [
            'title' => __('admin.contacts'),
            'robots' => 'noindex',
            'active' => 'contacts',
            'page' => $this->db->paginate('SELECT * FROM contact_messages ORDER BY created_at DESC', [], $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:new,contacted,closed,spam']);
        $this->db->run('UPDATE contact_messages SET status = ? WHERE id = ?', [$data['status'], (int) $id]);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/contacts');
    }
}
