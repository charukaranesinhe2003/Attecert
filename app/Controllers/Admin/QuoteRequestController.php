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

final class QuoteRequestController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CaseService $cases,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $sql = 'SELECT q.*, c.name AS country_name FROM quote_requests q LEFT JOIN country_translations c ON c.country_id = q.country_id AND c.locale = \'en\' WHERE 1 = 1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY q.created_at DESC';

        return $this->view('admin/quotes/index', [
            'title' => __('admin.quote_requests'),
            'robots' => 'noindex',
            'active' => 'quotes',
            'status' => $status,
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
        $this->db->run('UPDATE quote_requests SET status = ? WHERE id = ?', [$data['status'], (int) $id]);
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
