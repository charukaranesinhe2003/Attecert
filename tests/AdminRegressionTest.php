<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Admin\CaseController;
use App\Controllers\Admin\ReportController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\CaseService;
use App\Services\DocumentService;
use App\Services\IntakeService;
use App\Services\InvoiceService;
use App\Services\MailService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\WorkflowService;
use ReflectionMethod;
use ReflectionProperty;

final class AdminRegressionTest extends DatabaseTestCase
{
    public function testIntakeToCustomerAcceptancePaymentProcessingAndAuthorizedDownload(): void
    {
        $serviceId = (int) $this->db->fetchColumn("SELECT id FROM services WHERE pricing_type = 'fixed' LIMIT 1");
        $result = app(IntakeService::class)->quote([
            'name' => 'Workflow Fixture', 'email' => 'flow-fixture@example.test', 'phone' => '0000000000',
            'country_id' => null, 'document_type_id' => null, 'document_count' => 1,
        ], [$serviceId], []);
        $case = app(CaseService::class)->find($result['case_id']);
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$case['customer_id']]);
        self::assertSame($case['number'], $result['number']);
        self::assertSame($result['case_id'], (int) $this->db->fetchColumn('SELECT converted_case_id FROM quote_requests WHERE id = ?', [$result['id']]));
        $quote = app(QuotationService::class)->save($result['case_id'], [
            'lines' => [['service_id' => $serviceId, 'quantity' => 1, 'description' => 'Attestation fixture']],
        ], $this->adminId, true, true);
        // The other signed-in customer cannot view this quotation.
        $_SESSION['user_id'] = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->resetContainer();
        try {
            app(\App\Controllers\Customer\QuotationController::class)->show($this->request(), (string) $quote);
            self::fail('Another customer cannot open the quotation.');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->status());
        }
        $_SESSION['user_id'] = (int) $customer['user_id'];
        $this->resetContainer();
        app(\App\Controllers\Customer\QuotationController::class)->accept($this->request(), (string) $quote);
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE quotation_id = ?', [$quote]);
        self::assertNotNull($invoice);
        self::assertSame('converted', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$quote]));
        $_SESSION['user_id'] = $this->adminId;
        $this->resetContainer();
        app(PaymentService::class)->record((int) $invoice['id'], ['amount' => $invoice['total'], 'method' => 'cash', 'paid_at' => date('Y-m-d')], $this->adminId);
        self::assertSame('payment_received', app(CaseService::class)->find($result['case_id'])['status_code']);
        $temporary = storage_path('flow-fixture.png');
        if (!is_dir(dirname($temporary))) mkdir(dirname($temporary), 0775, true);
        $image = imagecreatetruecolor(10, 10);
        imagepng($image, $temporary);
        imagedestroy($image);
        $documents = app(DocumentService::class);
        $document = $documents->store($result['case_id'], null, ['name' => 'complete.png', 'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK, 'size' => filesize($temporary)], 'completed', 'mofa', 'Fixture document', false, $this->adminId);
        try {
            $documents->download($document, (int) $customer['id'], (int) $customer['user_id'], false);
            self::fail('The private document must not be downloadable.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }
        $documents->setVisibility($document, true, $this->adminId);
        self::assertSame('logged', $documents->notificationDelivery()['status']);
        $documents->setVisibility($document, false, $this->adminId);
        app(\App\Controllers\Admin\DocumentController::class)->visibility(new Request('POST', '/admin', [], ['customer_visible' => '1', 'confirmed' => '1'], [], $_SERVER, []), (string) $document);
        self::assertStringContainsString('email was not sent', (string) $_SESSION['_flash']['error']);
        self::assertFileExists($documents->download($document, (int) $customer['id'], (int) $customer['user_id'], false)['path']);
        app(WorkflowService::class)->change($result['case_id'], 'completed', $this->adminId, 'Fixture completed', true, false);
        $tracking = app(CaseService::class)->track($result['number']);
        self::assertSame('completed', $tracking['status_code']);
        self::assertArrayNotHasKey('customer_email', $tracking);
        self::assertGreaterThan(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM document_download_logs WHERE document_id = ?', [$document]));
    }

    public function testSupportCaseScreenDoesNotDiscloseFinanceOrOfferProtectedActions(): void
    {
        $quote = $this->quote();
        app(InvoiceService::class)->createFromQuotation($quote, $this->adminId);
        $this->signInAsRole('customer_support');
        $html = $this->body(app(CaseController::class)->show($this->request(), (string) $this->caseId));
        self::assertStringNotContainsString('Sensitive custom finance item', $html);
        self::assertStringNotContainsString('98,765.43', $html);
        self::assertStringNotContainsString('data-tab="finance"', $html);
        self::assertStringNotContainsString('/' . $this->caseId . '/status"', $html);
        self::assertStringNotContainsString('name="assigned_to"', $html);
        self::assertStringNotContainsString('type="file"', $html);
    }

    public function testProcessingCanEditServicesWithoutExposingCustomQuotationData(): void
    {
        $this->quote();
        $this->signInAsRole('processing_staff');
        $html = $this->body(app(CaseController::class)->show($this->request(), (string) $this->caseId));
        self::assertStringContainsString('name="assigned_to"', $html);
        self::assertStringContainsString('/' . $this->caseId . '/mofa"', $html);
        self::assertStringNotContainsString('data-quote-builder', $html);
        self::assertStringNotContainsString('data-tab="finance"', $html);
        self::assertStringNotContainsString('Sensitive custom finance item', $html);
    }

    public function testCaseServiceReplacementValidatesBeforeDeletingAndPreservesMissingCustomPrice(): void
    {
        $serviceId = (int) $this->db->fetchColumn("SELECT id FROM services WHERE pricing_type = 'custom' LIMIT 1");
        $cases = app(CaseService::class);
        $cases->syncServices($this->caseId, [['service_id' => $serviceId, 'quantity' => 2, 'unit_price' => '73.45']], $this->adminId);
        try {
            $cases->syncServices($this->caseId, [['service_id' => 99999999, 'quantity' => 1]], $this->adminId);
            self::fail('Unknown services must fail without removing current services.');
        } catch (ValidationException) {
            self::assertSame('73.45', $this->db->fetchColumn('SELECT unit_price FROM case_services WHERE case_id = ?', [$this->caseId]));
        }
        $cases->syncServices($this->caseId, [['service_id' => $serviceId, 'quantity' => 3]], $this->adminId);
        self::assertSame('73.45', $this->db->fetchColumn('SELECT unit_price FROM case_services WHERE case_id = ?', [$this->caseId]));
        self::assertSame('3.00', $this->db->fetchColumn('SELECT quantity FROM case_services WHERE case_id = ?', [$this->caseId]));
    }

    public function testReportRejectsInvalidRangeAndExportsAllSectionsWithCurrency(): void
    {
        $controller = app(ReportController::class);
        $invalid = $controller->index($this->request(['from' => '2026-02-30', 'to' => '2026-01-01']));
        self::assertSame(422, (new ReflectionProperty(Response::class, 'status'))->getValue($invalid));
        self::assertStringNotContainsString('Current outstanding invoices', $this->body($invalid));
        try {
            $controller->export($this->request(['from' => '2026-12-01', 'to' => '2026-01-01']));
            self::fail('Reversed export dates must fail.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->status());
        }
        $invoice = app(InvoiceService::class)->createFromQuotation($this->quote(), $this->adminId);
        app(PaymentService::class)->record($invoice, ['amount' => '100.00', 'method' => 'cash', 'paid_at' => date('Y-m-d')], $this->adminId);
        app(PaymentService::class)->record($invoice, ['amount' => '25.00', 'method' => 'refund', 'paid_at' => date('Y-m-d')], $this->adminId);
        $query = ['from' => date('Y-m-01'), 'to' => date('Y-m-d')];
        $html = $this->body($controller->index($this->request($query)));
        self::assertStringContainsString('Net: LKR 75.00', $html);
        $csv = $this->body($controller->export($this->request($query)));
        foreach (['service,', 'outstanding,', 'summary,', 'payment,refund,1,-25.00,LKR'] as $expected) {
            self::assertStringContainsString($expected, $csv);
        }
    }

    public function testPdfAndMailRecoveryKeepAttachmentsInIsolatedStorage(): void
    {
        $quote = $this->quote();
        $invoice = app(InvoiceService::class)->createFromQuotation($quote, $this->adminId);
        $payment = app(PaymentService::class)->record($invoice, ['amount' => '100', 'method' => 'cash', 'paid_at' => date('Y-m-d')], $this->adminId);
        foreach ([app(QuotationService::class)->pdfPath($quote), app(InvoiceService::class)->pdfPath($invoice), app(PaymentService::class)->receiptPath($payment)] as $path) {
            self::assertStringStartsWith(storage_path(), $path);
            self::assertSame('%PDF', file_get_contents($path, false, null, 0, 4));
        }
        $method = new ReflectionMethod(MailService::class, 'resendAttachment');
        foreach (['quotation' => $quote, 'invoice' => $invoice] as $type => $id) {
            $attachment = $method->invoke(app(MailService::class), ['template' => $type, 'related_type' => $type, 'related_id' => $id]);
            self::assertFileExists($attachment['path']);
            self::assertStringEndsWith('.pdf', $attachment['name']);
        }
        $log = $this->db->insert('INSERT INTO email_logs (recipient, subject, template, status, body_path) VALUES (?, ?, ?, ?, ?)', ['fixture@example.test', 'Missing body', 'invoice', 'logged', 'missing.html']);
        self::assertSame('failed', app(MailService::class)->resend($log)['status']);
    }

    public function testBackendRoutesEnforceModulePermissions(): void
    {
        $this->signInAsRole('content_editor');
        $router = new Router();
        (require base_path('routes/web.php'))($router);
        try {
            $router->dispatch(new Request('GET', '/admin/cases', [], [], [], $_SERVER, []), app());
            self::fail('Content editor cannot access cases.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }
        $this->signInAsRole('customer_support');
        try {
            $router->dispatch(new Request('POST', '/admin/invoices/1/payments', [], ['amount' => '1'], [], $_SERVER, []), app());
            self::fail('Support cannot post payments.');
        } catch (HttpException $exception) {
            self::assertSame(403, $exception->status());
        }
    }

    public function testDraftIssuanceRequiresApprovalEvenWhenEmailIsRequested(): void
    {
        $draft = app(QuotationService::class)->save($this->caseId, ['lines' => [['description' => 'Draft fixture', 'quantity' => 1, 'unit_price' => 100]]], $this->adminId, false, true);
        $this->db->run("DELETE rp FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE r.code = 'finance_staff' AND p.code = 'quotations.approve'");
        $this->signInAsRole('finance_staff');
        $post = new Request('POST', '/admin', [], ['email' => '1'], [], $_SERVER, []);
        foreach ([
            fn () => app(CaseController::class)->quotation($post, (string) $this->caseId),
            fn () => app(\App\Controllers\Admin\QuotationController::class)->send($post, (string) $draft),
        ] as $action) {
            try {
                $action();
                self::fail('Emailing a draft must not bypass issuance permission.');
            } catch (HttpException $exception) {
                self::assertSame(403, $exception->status());
            }
        }
        self::assertSame('draft', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$draft]));
    }

    private function quote(): int
    {
        return app(QuotationService::class)->save($this->caseId, ['lines' => [['description' => 'Sensitive custom finance item', 'quantity' => '1', 'unit_price' => '98765.43']]], $this->adminId, true, true);
    }

    private function request(array $query = []): Request
    {
        return new Request('GET', '/admin', $query, [], [], $_SERVER, []);
    }

    private function body(Response $response): string
    {
        return (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }
}
