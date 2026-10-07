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
        $sql = 'SELECT d.*, c.number AS case_number FROM documents d LEFT JOIN cases c ON c.id = d.case_id WHERE d.deleted_at IS NULL ORDER BY d.created_at DESC';

        return $this->view('admin/documents/index', [
            'title' => __('admin.documents'),
            'robots' => 'noindex',
            'active' => 'documents',
            'page' => $this->db->paginate($sql, [], $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function download(Request $request, string $id): Response
    {
        $file = $this->documents->download((int) $id, null, (int) $this->auth->id(), true);

        return Response::download($file['path'], $file['name'], $file['mime']);
    }

    public function archive(Request $request, string $id): Response
    {
        $this->documents->archive((int) $id, (int) $this->auth->id());
        flash('success', __('admin.archived'));

        return $this->redirect('/admin/documents');
    }
}
