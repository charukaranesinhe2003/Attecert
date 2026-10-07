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
        $source = (string) $request->query('source', 'all');
        if (!in_array($source, ['all', 'guest', 'support'], true)) {
            $source = 'all';
        }
        $sql = 'SELECT m.*, c.id AS linked_customer_id FROM contact_messages m LEFT JOIN customers c ON c.id = m.customer_id WHERE 1 = 1';
        $params = [];
        if ($source !== 'all') {
            $sql .= ' AND m.source = ?';
            $params[] = $source;
        }
        $sql .= ' ORDER BY m.created_at DESC';

        return $this->view('admin/contacts/index', [
            'title' => __('admin.contacts'),
            'robots' => 'noindex',
            'active' => 'contacts',
            'source' => $source,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
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
