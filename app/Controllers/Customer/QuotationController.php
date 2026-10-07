<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\QuotationService;

final class QuotationController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private Database $db,
        private QuotationService $quotations
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        $rows = $this->db->fetchAll(
            'SELECT q.*, c.number AS case_number FROM quotations q INNER JOIN cases c ON c.id = q.case_id WHERE q.customer_id = ? AND q.status <> \'draft\' ORDER BY q.created_at DESC',
            [(int) $this->auth->customer()['id']]
        );

        return $this->view('customer/quotations', [
            'title' => __('portal.quotations'),
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'quotations',
            'quotations' => $rows,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $bundle = $this->owned((int) $id);
        $this->quotations->markViewed((int) $id);

        return $this->view('customer/quotation', [
            'title' => $bundle['quotation']['number'],
            'robots' => 'noindex',
            'accountFrame' => true,
            'active' => 'quotations',
            'bundle' => $this->quotations->bundle((int) $id),
        ]);
    }

    public function accept(Request $request, string $id): Response
    {
        $this->owned((int) $id);
        try {
            $invoiceId = $this->quotations->accept((int) $id, (int) $this->auth->id(), $request->ip());
            flash('success', __('quotations.accepted'));

            return $this->redirect('/portal/invoices/' . $invoiceId);
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());

            return $this->redirect('/portal/quotations/' . $id);
        }
    }

    public function reject(Request $request, string $id): Response
    {
        $this->owned((int) $id);
        $reason = trim((string) $request->input('reason', ''));
        try {
            $this->quotations->reject((int) $id, (int) $this->auth->id(), $reason);
            flash('success', __('quotations.rejected'));
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/portal/quotations/' . $id);
    }

    public function pdf(Request $request, string $id): Response
    {
        $bundle = $this->owned((int) $id);
        $path = $this->quotations->pdfPath((int) $id);

        return Response::download($path, $bundle['quotation']['number'] . '.pdf', 'application/pdf');
    }

    private function owned(int $id): array
    {
        $bundle = $this->quotations->bundle($id);
        if ((int) $bundle['customer']['id'] !== (int) $this->auth->customer()['id'] || $bundle['quotation']['status'] === 'draft') {
            throw new HttpException(404);
        }

        return $bundle;
    }
}
