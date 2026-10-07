<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\DocumentService;

final class ApplicationController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private CaseService $cases,
        private DocumentService $documents
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        $customer = $this->auth->customer();

        return $this->view('customer/applications', [
            'title' => __('portal.applications'),
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'applications',
            'cases' => $this->cases->forCustomerList((int) $customer['id']),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $customer = $this->auth->customer();
        $case = $this->cases->forCustomer((int) $id, (int) $customer['id']);
        if ($case === null) {
            throw new HttpException(404);
        }
        $documents = array_values(array_filter(
            $this->documents->forCase((int) $case['id']),
            static fn (array $doc): bool => (int) $doc['customer_visible'] === 1
        ));

        return $this->view('customer/application', [
            'title' => $case['number'],
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'applications',
            'case' => $case,
            'documents' => $documents,
        ]);
    }

    public function tracking(): Response
    {
        return $this->view('customer/tracking', [
            'title' => __('portal.tracking'),
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'tracking',
            'cases' => $this->cases->forCustomerList((int) $this->auth->customer()['id']),
        ]);
    }
}
