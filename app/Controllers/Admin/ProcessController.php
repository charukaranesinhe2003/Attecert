<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\CatalogService;
use App\Services\WorkflowService;

final class ProcessController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CatalogService $catalog,
        private WorkflowService $workflow,
        private AuthService $auth,
        private AuditService $audit
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/process/index', [
            'title' => __('admin.processing'),
            'robots' => 'noindex',
            'active' => 'processing',
            'pageHeading' => __('admin.processing'),
            'pageDescription' => __('admin.processing_hint'),
            'mofa' => $this->currentProcesses('mofa', true),
            'embassy' => $this->currentProcesses('embassy', true),
        ], 'layouts/admin');
    }

    public function mofa(Request $request): Response
    {
        $status = $this->statusFilter($request);
        return $this->view('admin/process/mofa', [
            'title' => __('admin.mofa'),
            'robots' => 'noindex',
            'active' => 'mofa',
            'rows' => $this->currentProcesses('mofa', false, $status),
            'status' => $status,
        ], 'layouts/admin');
    }

    public function embassy(Request $request): Response
    {
        $status = $this->statusFilter($request);
        return $this->view('admin/process/embassy', [
            'title' => __('admin.embassy'),
            'robots' => 'noindex',
            'active' => 'embassy',
            'rows' => $this->currentProcesses('embassy', false, $status),
            'status' => $status,
            'countries' => $this->catalog->countries(false),
        ], 'layouts/admin');
    }

    public function saveMofa(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'reference_no' => 'nullable|max:80',
            'status' => 'required|in:not_started,submitted,processing,completed',
            'submitted_at' => 'nullable|date',
            'expected_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'notes' => 'nullable|max:4000',
        ]);
        $this->db->transaction(function () use ($id, $data, $request): void {
        $this->requireCase((int) $id);
        $processId = $this->db->insert(
            'INSERT INTO mofa_processes (case_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['reference_no'], $data['submitted_at'] ?: null, $data['expected_at'] ?: null, $data['completed_at'] ?: null, $data['status'], $data['notes'], $this->auth->id()]
        );
        $map = ['submitted' => 'submitted_to_mofa', 'processing' => 'mofa_processing', 'completed' => 'mofa_completed'];
        if (isset($map[$data['status']]) && $request->input('update_case')) {
            $this->workflow->change((int) $id, $map[$data['status']], $this->auth->id(), $data['notes'], true, true);
        }
        $this->audit->log($this->auth->id(), 'mofa_updated', 'case', (int) $id, ['process_id' => $processId, 'status' => $data['status']]);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id . '#processing');
    }

    public function saveEmbassy(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'embassy_name' => 'required|max:160',
            'country_id' => 'nullable|numeric',
            'reference_no' => 'nullable|max:80',
            'status' => 'required|in:not_started,submitted,processing,completed',
            'submitted_at' => 'nullable|date',
            'expected_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'notes' => 'nullable|max:4000',
        ]);
        $this->db->transaction(function () use ($id, $data, $request): void {
        $this->requireCase((int) $id);
        $processId = $this->db->insert(
            'INSERT INTO embassy_processes (case_id, embassy_name, country_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['embassy_name'], $data['country_id'] ?: null, $data['reference_no'], $data['submitted_at'] ?: null, $data['expected_at'] ?: null, $data['completed_at'] ?: null, $data['status'], $data['notes'], $this->auth->id()]
        );
        $map = ['submitted' => 'submitted_to_embassy', 'processing' => 'embassy_processing', 'completed' => 'embassy_completed'];
        if (isset($map[$data['status']]) && $request->input('update_case')) {
            $this->workflow->change((int) $id, $map[$data['status']], $this->auth->id(), $data['notes'], true, true);
        }
        $this->audit->log($this->auth->id(), 'embassy_updated', 'case', (int) $id, ['process_id' => $processId, 'status' => $data['status']]);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id . '#processing');
    }

    private function currentProcesses(string $kind, bool $limit = false, string $status = ''): array
    {
        $table = $kind === 'mofa' ? 'mofa_processes' : 'embassy_processes';
        $where = '';
        $params = [];
        if ($status === 'active') {
            $where = ' AND p.status IN (\'submitted\', \'processing\') AND s.is_terminal = 0';
        } elseif ($status !== '') {
            $where = ' AND p.status = ?';
            $params[] = $status;
        }

        return $this->db->fetchAll(
            'SELECT p.*, c.number, cu.name AS customer_name FROM ' . $table . ' p
             INNER JOIN cases c ON c.id = p.case_id
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             INNER JOIN customers cu ON cu.id = c.customer_id
             WHERE c.deleted_at IS NULL
               AND NOT EXISTS (SELECT 1 FROM ' . $table . ' newer WHERE newer.case_id = p.case_id AND newer.id > p.id)
             ' . $where . ' ORDER BY p.updated_at DESC' . ($limit ? ' LIMIT 30' : ''),
            $params
        );
    }

    private function statusFilter(Request $request): string
    {
        $status = (string) $request->query('status', '');

        return in_array($status, ['', 'active', 'not_started', 'submitted', 'processing', 'completed'], true) ? $status : '';
    }

    private function requireCase(int $id): void
    {
        if ($this->db->fetch('SELECT id FROM cases WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]) === null) {
            throw new \App\Core\HttpException(404, 'Case not found.');
        }
    }
}
