<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CatalogService;
use App\Services\WorkflowService;

final class ProcessController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CatalogService $catalog,
        private WorkflowService $workflow,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function mofa(Request $request): Response
    {
        return $this->view('admin/process/mofa', [
            'title' => __('admin.mofa'),
            'robots' => 'noindex',
            'active' => 'mofa',
            'rows' => $this->db->fetchAll('SELECT m.*, c.number, cu.name AS customer_name FROM mofa_processes m INNER JOIN cases c ON c.id = m.case_id INNER JOIN customers cu ON cu.id = c.customer_id ORDER BY m.updated_at DESC'),
        ], 'layouts/admin');
    }

    public function embassy(Request $request): Response
    {
        return $this->view('admin/process/embassy', [
            'title' => __('admin.embassy'),
            'robots' => 'noindex',
            'active' => 'embassy',
            'rows' => $this->db->fetchAll('SELECT e.*, c.number, cu.name AS customer_name FROM embassy_processes e INNER JOIN cases c ON c.id = e.case_id INNER JOIN customers cu ON cu.id = c.customer_id ORDER BY e.updated_at DESC'),
            'countries' => $this->catalog->countries(false),
        ], 'layouts/admin');
    }

    public function saveMofa(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'reference_no' => 'nullable|max:80',
            'status' => 'required|in:not_started,submitted,processing,completed',
            'submitted_at' => 'nullable|max:20',
            'expected_at' => 'nullable|max:20',
            'completed_at' => 'nullable|max:20',
            'notes' => 'nullable|max:4000',
        ]);
        $this->db->run(
            'INSERT INTO mofa_processes (case_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['reference_no'], $data['submitted_at'] ?: null, $data['expected_at'] ?: null, $data['completed_at'] ?: null, $data['status'], $data['notes'], $this->auth->id()]
        );
        $map = ['submitted' => 'submitted_to_mofa', 'processing' => 'mofa_processing', 'completed' => 'mofa_completed'];
        if (isset($map[$data['status']]) && $request->input('update_case')) {
            $this->workflow->change((int) $id, $map[$data['status']], $this->auth->id(), $data['notes'], true, true);
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id);
    }

    public function saveEmbassy(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'embassy_name' => 'required|max:160',
            'country_id' => 'nullable|numeric',
            'reference_no' => 'nullable|max:80',
            'status' => 'required|in:not_started,submitted,processing,completed',
            'submitted_at' => 'nullable|max:20',
            'expected_at' => 'nullable|max:20',
            'completed_at' => 'nullable|max:20',
            'notes' => 'nullable|max:4000',
        ]);
        $this->db->insert(
            'INSERT INTO embassy_processes (case_id, embassy_name, country_id, reference_no, submitted_at, expected_at, completed_at, status, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['embassy_name'], $data['country_id'] ?: null, $data['reference_no'], $data['submitted_at'] ?: null, $data['expected_at'] ?: null, $data['completed_at'] ?: null, $data['status'], $data['notes'], $this->auth->id()]
        );
        $map = ['submitted' => 'submitted_to_embassy', 'processing' => 'embassy_processing', 'completed' => 'embassy_completed'];
        if (isset($map[$data['status']]) && $request->input('update_case')) {
            $this->workflow->change((int) $id, $map[$data['status']], $this->auth->id(), $data['notes'], true, true);
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id);
    }
}
