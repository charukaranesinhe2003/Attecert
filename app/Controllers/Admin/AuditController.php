<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\MailService;

final class AuditController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private MailService $mail)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $sql = 'SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (a.action LIKE ? OR a.entity LIKE ? OR u.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY a.created_at DESC';

        return $this->view('admin/audit/index', [
            'title' => __('admin.audit'),
            'robots' => 'noindex',
            'active' => 'audit',
            'q' => $q,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function emails(Request $request): Response
    {
        return $this->view('admin/audit/emails', [
            'title' => __('admin.emails'),
            'robots' => 'noindex',
            'active' => 'emails',
            'page' => $this->db->paginate('SELECT * FROM email_logs ORDER BY created_at DESC', [], $this->pageQuery($request)),
            'smtp' => $this->mail->smtpReady(),
        ], 'layouts/admin');
    }

    public function resend(Request $request, string $id): Response
    {
        $result = $this->mail->resend((int) $id);
        flash($result['status'] === 'sent' ? 'success' : 'error', $result['status'] === 'sent' ? __('admin.email_sent') : (string) $result['error']);

        return $this->redirect('/admin/emails');
    }
}
