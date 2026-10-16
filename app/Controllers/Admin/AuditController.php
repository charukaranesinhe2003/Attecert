<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\MailService;
use App\Services\AuditService;
use App\Services\AuthService;

final class AuditController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private MailService $mail, private AuthService $auth, private AuditService $audit)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $sql = 'SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (a.action LIKE ? OR a.entity LIKE ? OR u.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

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
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $statuses = ['queued', 'sending', 'sent', 'failed', 'logged', 'uncertain'];
        if (!in_array($status, $statuses, true)) $status = '';
        $queueAvailable = $this->mail->queueAvailable();
        $sql = $queueAvailable
            ? 'SELECT e.*, o.attempts, o.max_attempts, o.available_at, o.last_attempt_at FROM email_logs e LEFT JOIN mail_outbox o ON o.email_log_id = e.id WHERE 1 = 1'
            : 'SELECT e.*, NULL AS attempts, NULL AS max_attempts, NULL AS available_at, NULL AS last_attempt_at FROM email_logs e WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (e.recipient LIKE ? OR e.subject LIKE ?)';
            $params = ['%' . $q . '%', '%' . $q . '%'];
        }
        if ($status !== '') {
            $sql .= ' AND e.status = ?';
            $params[] = $status;
        }
        $types = ['quotation', 'invoice', 'payment', 'document', 'case', 'quote_request', 'contact', 'user'];
        $permitted = array_values(array_filter($types, fn (string $type): bool => $this->auth->can($this->mailPermission($type))));
        $scope = [];
        if ($permitted !== []) {
            $scope[] = 'e.related_type IN (' . implode(',', array_fill(0, count($permitted), '?')) . ')';
            array_push($params, ...$permitted);
        }
        if ($this->auth->can('users.manage')) {
            $scope[] = '(e.related_type IS NULL OR e.related_type NOT IN (' . implode(',', array_fill(0, count($types), '?')) . '))';
            array_push($params, ...$types);
        }
        $sql .= $scope === [] ? ' AND 1 = 0' : ' AND (' . implode(' OR ', $scope) . ')';
        $page = $this->db->paginate($sql . ' ORDER BY e.created_at DESC, e.id DESC', $params, $this->pageQuery($request));
        foreach ($page['rows'] as &$row) $row['can_retry'] = $this->auth->can($this->mailPermission($row['related_type']));
        unset($row);

        return $this->view('admin/audit/emails', [
            'title' => __('admin.emails'),
            'robots' => 'noindex',
            'active' => 'emails',
            'page' => $page,
            'smtp' => $this->mail->smtpReady(),
            'queueAvailable' => $queueAvailable,
            'q' => $q, 'status' => $status, 'statuses' => $statuses,
        ], 'layouts/admin');
    }

    public function resend(Request $request, string $id): Response
    {
        $log = $this->db->fetch('SELECT related_type FROM email_logs WHERE id = ?', [(int) $id]);
        if ($log === null) {
            throw new HttpException(404, 'Email log not found.');
        }
        $permission = $this->mailPermission($log['related_type']);
        if (!$this->auth->can($permission)) {
            throw new HttpException(403, 'You do not have permission to resend this message.');
        }
        $result = $this->mail->resend((int) $id, $request->input('confirmed') === '1');
        $this->audit->log($this->auth->id(), 'email_retry_requested', 'email_log', (int) $id, ['mail_status' => $result['status']]);
        $success = in_array($result['status'], ['sent', 'queued'], true);
        flash($success ? 'success' : 'error', match ($result['status']) {
            'sent' => __('admin.email_already_sent'),
            'queued' => __('admin.email_queued'),
            default => (string) $result['error'],
        });

        return $this->redirect('/admin/emails');
    }

    private function mailPermission(?string $relatedType): string
    {
        return match ($relatedType) {
            'quotation' => 'quotations.send', 'invoice' => 'invoices.send',
            'payment' => 'payments.create', 'document' => 'documents.upload',
            'case' => 'cases.status_update', 'quote_request', 'contact' => 'quotes.manage',
            default => 'users.manage',
        };
    }
}
