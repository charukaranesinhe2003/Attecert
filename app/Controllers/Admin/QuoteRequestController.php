<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\AuditService;

final class QuoteRequestController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CaseService $cases,
        private AuthService $auth,
        private AuditService $audit
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $status = in_array($status, ['new', 'contacted', 'converted', 'closed', 'spam'], true) ? $status : '';
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $sql = 'SELECT q.*, c.name AS country_name FROM quote_requests q LEFT JOIN country_translations c ON c.country_id = q.country_id AND c.locale = \'en\' WHERE 1 = 1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= ' AND (q.name LIKE ? OR q.email LIKE ? OR q.phone LIKE ? OR EXISTS (SELECT 1 FROM cases linked WHERE linked.id = q.converted_case_id AND linked.number LIKE ?))';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        $sql .= ' ORDER BY q.created_at DESC, q.id DESC';

        return $this->view('admin/quotes/index', [
            'title' => __('admin.quote_requests'),
            'robots' => 'noindex',
            'active' => 'quotes',
            'status' => $status,
            'q' => $q,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function show(Request $request, string $id): Response
    {
        $quote = $this->db->fetch('SELECT * FROM quote_requests WHERE id = ?', [(int) $id]);
        if ($quote === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/quotes/show', [
            'title' => __('admin.quote_requests'),
            'robots' => 'noindex',
            'active' => 'quotes',
            'quote' => $quote,
            'services' => $this->db->fetchAll('SELECT s.id, t.name FROM quote_request_services qs INNER JOIN services s ON s.id = qs.service_id LEFT JOIN service_translations t ON t.service_id = s.id AND t.locale = \'en\' WHERE qs.quote_request_id = ?', [(int) $id]),
            'files' => $this->db->fetchAll('SELECT * FROM documents WHERE quote_request_id = ? AND deleted_at IS NULL', [(int) $id]),
        ], 'layouts/admin');
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['status' => 'required|in:new,contacted,converted,closed,spam']);
        $this->db->transaction(function () use ($data, $id): void {
            $quote = $this->db->fetch('SELECT id, status, converted_case_id FROM quote_requests WHERE id = ? FOR UPDATE', [(int) $id]);
            if ($quote === null) {
                throw new HttpException(404, 'Quote request not found.');
            }
            if ($data['status'] === 'converted' && $quote['converted_case_id'] === null) {
                throw new \App\Core\ValidationException(['status' => __('admin_lists.convert_required')]);
            }
            if ($quote['status'] !== $data['status']) {
                $this->db->run('UPDATE quote_requests SET status = ? WHERE id = ?', [$data['status'], (int) $id]);
                $this->audit->log($this->auth->id(), 'quote_request_status_updated', 'quote_request', (int) $id, ['previous_status' => $quote['status'], 'status' => $data['status']]);
            }
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/quote-requests/' . $id);
    }

    public function convert(Request $request, string $id): Response
    {
        try {
            $caseId = $this->cases->createFromQuote((int) $id, (int) $this->auth->id());
            flash('success', __('admin.converted'));

            return $this->redirect('/admin/cases/' . $caseId);
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());

            return $this->redirect('/admin/quote-requests/' . $id);
        }
    }
}
