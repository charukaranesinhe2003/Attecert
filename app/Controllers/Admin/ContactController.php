<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\AuthService;

final class ContactController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private AuthService $auth, private AuditService $audit)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $source = is_string($request->query('source')) ? $request->query('source') : 'all';
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $status = in_array($status, ['new', 'contacted', 'closed', 'spam'], true) ? $status : '';
        if (!in_array($source, ['all', 'guest', 'support'], true)) {
            $source = 'all';
        }
        $sql = 'SELECT m.*, c.id AS linked_customer_id FROM contact_messages m LEFT JOIN customers c ON c.id = m.customer_id WHERE 1 = 1';
        $params = [];
        if ($source !== 'all') {
            $sql .= ' AND m.source = ?';
            $params[] = $source;
        }
        if ($status !== '') {
            $sql .= ' AND m.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= ' AND (m.name LIKE ? OR m.email LIKE ? OR m.phone LIKE ? OR m.subject LIKE ? OR m.message LIKE ?)';
            array_push($params, ...array_fill(0, 5, '%' . $q . '%'));
        }
        $sql .= ' ORDER BY m.created_at DESC, m.id DESC';

        return $this->view('admin/contacts/index', [
            'title' => __('admin.contacts'),
            'robots' => 'noindex',
            'active' => 'contacts',
            'source' => $source,
            'q' => $q,
            'status' => $status,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:new,contacted,closed,spam']);
        $this->db->transaction(function () use ($data, $id): void {
            $message = $this->db->fetch('SELECT id, status FROM contact_messages WHERE id = ? FOR UPDATE', [(int) $id]);
            if ($message === null) {
                throw new HttpException(404, 'Message not found.');
            }
            if ($message['status'] !== $data['status']) {
                $this->db->run('UPDATE contact_messages SET status = ? WHERE id = ?', [$data['status'], (int) $id]);
                $this->audit->log($this->auth->id(), 'contact_status_updated', 'contact_message', (int) $id, ['previous_status' => $message['status'], 'status' => $data['status']]);
            }
        });
        flash('success', __('admin.saved'));

        $source = is_string($request->input('source')) ? $request->input('source') : 'all';
        $status = is_string($request->input('return_status')) ? $request->input('return_status') : '';

        return $this->redirect('/admin/contacts?' . http_build_query([
            'source' => in_array($source, ['guest', 'support'], true) ? $source : 'all',
            'q' => mb_substr(trim(is_string($request->input('q')) ? $request->input('q') : ''), 0, 190),
            'status' => in_array($status, ['new', 'contacted', 'closed', 'spam'], true) ? $status : '',
        ]));
    }
}
