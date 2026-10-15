<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\DocumentService;

final class DocumentController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private DocumentService $documents,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $sql = 'SELECT d.*, c.number AS case_number FROM documents d LEFT JOIN cases c ON c.id = d.case_id LEFT JOIN customers cu ON cu.id = c.customer_id WHERE d.deleted_at IS NULL AND (d.case_id IS NULL OR (c.id IS NOT NULL AND c.deleted_at IS NULL AND cu.id IS NOT NULL AND cu.deleted_at IS NULL))';
        $params = [];
        if ($filters['case_id'] > 0) {
            $sql .= ' AND d.case_id = ?';
            $params[] = $filters['case_id'];
        }
        if ($filters['category'] !== 'all') {
            $sql .= ' AND d.category = ?';
            $params[] = $filters['category'];
            if ($filters['category'] === 'completed') {
                $sql .= ' AND c.id IS NOT NULL';
            }
        }
        if ($filters['visibility'] !== 'all') {
            $sql .= ' AND d.customer_visible = ?';
            $params[] = $filters['visibility'] === 'visible' ? 1 : 0;
        }
        if ($filters['q'] !== '') {
            $sql .= ' AND (d.original_name LIKE ? OR c.number LIKE ?)';
            $params[] = '%' . $filters['q'] . '%';
            $params[] = '%' . $filters['q'] . '%';
        }
        $sql .= ' ORDER BY d.created_at DESC, d.id DESC';

        return $this->view('admin/documents/index', [
            'title' => __('admin.documents'),
            'robots' => 'noindex',
            'active' => 'documents',
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
            'filters' => $filters,
            'canRelease' => $this->auth->can('documents.upload'),
            'canArchive' => $this->auth->can('documents.delete'),
            'canViewCase' => $this->auth->can('cases.view'),
        ], 'layouts/admin');
    }

    public function download(Request $request, string $id): Response
    {
        $file = $this->documents->download((int) $id, null, (int) $this->auth->id(), true);

        return Response::download($file['path'], $file['name'], $file['mime']);
    }

    public function archive(Request $request, string $id): Response
    {
        $this->validate($request, ['confirmed' => 'required|in:1']);
        $this->documents->archive((int) $id, (int) $this->auth->id());
        flash('success', __('admin.archived'));

        return $this->redirect($this->listingPath($request));
    }

    public function visibility(Request $request, string $id): Response
    {
        $data = $this->validate($request, ['customer_visible' => 'required|in:0,1', 'confirmed' => 'required|in:1']);
        $changed = $this->documents->setVisibility((int) $id, (int) $data['customer_visible'] === 1, (int) $this->auth->id());
        flash('success', $changed ? ((int) $data['customer_visible'] === 1 ? 'Document released to the customer.' : 'Document is now private.') : 'Document visibility is already up to date.');

        return $this->redirect($this->listingPath($request));
    }

    private function filters(Request $request): array
    {
        $category = (string) $request->input('category', 'all');
        $visibility = (string) $request->input('visibility', 'all');

        return [
            'case_id' => max(0, (int) $request->input('case_id', 0)),
            'category' => in_array($category, ['all', 'customer', 'internal', 'completed'], true) ? $category : 'all',
            'visibility' => in_array($visibility, ['all', 'private', 'visible'], true) ? $visibility : 'all',
            'q' => mb_substr(trim((string) $request->input('q', '')), 0, 190),
        ];
    }

    private function listingPath(Request $request): string
    {
        return '/admin/documents?' . http_build_query($this->filters($request));
    }
}
