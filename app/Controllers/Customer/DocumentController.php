<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\DocumentService;

final class DocumentController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private DocumentService $documents
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('customer/documents', [
            'title' => __('portal.documents'),
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'documents',
            'documents' => $this->documents->forCustomer((int) $this->auth->customer()['id']),
        ]);
    }

    public function download(Request $request, string $id): Response
    {
        $file = $this->documents->download((int) $id, (int) $this->auth->customer()['id'], (int) $this->auth->id(), false);

        return Response::download($file['path'], $file['name'], $file['mime']);
    }
}
