<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Admin\CaseController;
use App\Controllers\Admin\ProcessController;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Core\View;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use ReflectionProperty;

final class FinanceWorkflowTest extends DatabaseTestCase
{
    public function testInvoiceConversionIsIdempotentAndCopiesHistoricalItems(): void
    {
        $quoteId = $this->quotation();
        $invoices = app(InvoiceService::class);
        $first = $invoices->createFromQuotation($quoteId, $this->adminId);
        $second = $invoices->createFromQuotation($quoteId, $this->adminId);

        self::assertSame($first, $second);
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE quotation_id = ?', [$quoteId]));
        $bundle = $invoices->bundle($first);
        self::assertSame('100.00', $bundle['invoice']['total']);
        self::assertSame('Historical custom service', $bundle['items'][0]['description']);
        self::assertSame('100.00', $bundle['items'][0]['unit_price']);
    }

    public function testPaymentsAndRefundsKeepLedgerAndInvoiceBalanced(): void
    {
        $invoiceId = app(InvoiceService::class)->createFromQuotation($this->quotation(), $this->adminId);
        $payments = app(PaymentService::class);
        $payments->record($invoiceId, $this->payment('60.00'), $this->adminId);
        $payments->record($invoiceId, $this->payment('40.00'), $this->adminId);
        self::assertSame('paid', $this->db->fetchColumn('SELECT status FROM invoices WHERE id = ?', [$invoiceId]));
        $payments->record($invoiceId, $this->payment('30.00', 'refund'), $this->adminId);
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        self::assertSame('70.00', $invoice['amount_paid']);
        self::assertSame('30.00', $invoice['amount_due']);
        self::assertSame('partially_paid', $invoice['status']);
        self::assertSame('70.00', $this->db->fetchColumn('SELECT SUM(amount) FROM payments WHERE invoice_id = ?', [$invoiceId]));
        foreach ([$this->payment('31.00'), $this->payment('71.00', 'refund')] as $invalid) {
            try {
                $payments->record($invoiceId, $invalid, $this->adminId);
                self::fail('An out-of-bounds amount must be rejected.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('exceeds', $exception->getMessage());
            }
        }
        self::assertSame(3, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM payments WHERE invoice_id = ?', [$invoiceId]));
    }

    public function testCancellationRequiresRefundAndCancelledInvoiceRejectsPayment(): void
    {
        $invoices = app(InvoiceService::class);
        $invoiceId = $invoices->createFromQuotation($this->quotation(), $this->adminId);
        $payments = app(PaymentService::class);
        $payments->record($invoiceId, $this->payment('100.00'), $this->adminId);
        try {
            $invoices->cancel($invoiceId, $this->adminId, 'Test');
            self::fail('Paid invoices require a refund before cancellation.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('refund', $exception->getMessage());
        }
        $payments->record($invoiceId, $this->payment('100.00', 'refund'), $this->adminId);
        $invoices->cancel($invoiceId, $this->adminId, 'Refunded in full');
        self::assertSame('cancelled', $this->db->fetchColumn('SELECT status FROM invoices WHERE id = ?', [$invoiceId]));
        $this->expectException(\RuntimeException::class);
        $payments->record($invoiceId, $this->payment('1.00'), $this->adminId);
    }

    public function testRevisionCannotSupersedeAnotherCasesQuotation(): void
    {
        $quoteId = $this->quotation();
        $otherCase = $this->db->insert(
            'INSERT INTO cases (number, customer_id, document_title, status_id) SELECT ?, customer_id, document_title, status_id FROM cases WHERE id = ?',
            ['TEST-OTHER-' . bin2hex(random_bytes(3)), $this->caseId]
        );
        try {
            app(QuotationService::class)->save($otherCase, $this->payload() + ['quotation_id' => $quoteId], $this->adminId, true, true);
            self::fail('A quotation from another case must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('does not belong', $exception->getMessage());
        }
        self::assertSame('sent', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$quoteId]));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations WHERE case_id = ?', [$otherCase]));
    }

    public function testRevisionSupersedesUnacceptedQuoteAndAcceptanceIsFinal(): void
    {
        $quotes = app(QuotationService::class);
        $old = $this->quotation();
        $new = $quotes->save($this->caseId, $this->payload() + ['quotation_id' => $old], $this->adminId, true, true);
        $revision = $this->db->fetch('SELECT * FROM quotations WHERE id = ?', [$new]);
        self::assertSame($old, (int) $revision['parent_id']);
        self::assertSame(2, (int) $revision['version']);
        self::assertSame('superseded', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$old]));
        $invoiceId = $quotes->accept($new, $this->adminId, '127.0.0.1');
        self::assertGreaterThan(0, $invoiceId);
        self::assertSame('converted', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$new]));
        foreach (['accept', 'reject'] as $action) {
            try {
                $action === 'accept' ? $quotes->accept($new, $this->adminId, '127.0.0.1') : $quotes->reject($new, $this->adminId, 'Too late');
                self::fail('A converted quotation cannot be accepted or rejected again.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('cannot be', $exception->getMessage());
            }
        }
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE quotation_id = ?', [$new]));
    }

    public function testExpiredAcceptancePersistsExpiryWithoutInvoice(): void
    {
        $quoteId = $this->quotation();
        $this->db->run('UPDATE quotations SET valid_until = ? WHERE id = ?', ['2020-01-01', $quoteId]);
        try {
            app(QuotationService::class)->accept($quoteId, $this->adminId, '127.0.0.1');
            self::fail('Expired quotations cannot be accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('expired', $exception->getMessage());
        }
        self::assertSame('expired', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$quoteId]));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE quotation_id = ?', [$quoteId]));
    }

    public function testPaidInvoiceShowsRefundAndDraftRestoresCustomFields(): void
    {
        $quoteId = $this->quotation(false);
        $invoiceId = app(InvoiceService::class)->createFromQuotation($quoteId, $this->adminId);
        app(PaymentService::class)->record($invoiceId, $this->payment('100.00'), $this->adminId);
        $invoiceHtml = View::render('admin/invoices/show', ['bundle' => app(InvoiceService::class)->bundle($invoiceId)]);
        self::assertStringContainsString('Record refund', $invoiceHtml);
        self::assertStringContainsString('name="method" value="refund"', $invoiceHtml);

        $html = $this->body(app(CaseController::class)->show($this->request('GET'), (string) $this->caseId));
        self::assertStringContainsString('name="quotation_id" value="' . $quoteId . '"', $html);
        self::assertStringContainsString('value="Historical custom service"', $html);
        self::assertStringContainsString('Saved quotation note', $html);
        self::assertStringContainsString('Saved quotation terms', $html);
    }

    public function testProcessingListsOnlyLatestSnapshotAndAuditsUnlinkedUpdate(): void
    {
        $controller = app(ProcessController::class);
        $controller->saveMofa($this->request('POST', ['status' => 'processing']), (string) $this->caseId);
        $this->db->run('UPDATE cases SET status_id = (SELECT id FROM workflow_statuses WHERE code = ?) WHERE id = ?', ['mofa_processing', $this->caseId]);
        $active = $this->body($controller->mofa($this->request('GET', [], ['status' => 'active'])));
        $caseNumber = $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]);
        self::assertStringContainsString($caseNumber, $active);
        $controller->saveMofa($this->request('POST', ['status' => 'completed']), (string) $this->caseId);
        $after = $this->body($controller->mofa($this->request('GET', [], ['status' => 'active'])));
        self::assertStringNotContainsString($caseNumber, $after);
        self::assertSame(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE entity = ? AND entity_id = ? AND action = ?', ['case', $this->caseId, 'mofa_updated']));
        self::assertGreaterThanOrEqual(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM mofa_processes WHERE case_id = ?', [$this->caseId]));
    }

    public function testStrictCalendarDatesRejectImpossibleAndMalformedDates(): void
    {
        $validator = new Validator();
        self::assertSame(['paid_at' => '2024-02-29'], $validator->validate(['paid_at' => '2024-02-29'], ['paid_at' => 'required|date']));
        foreach (['2026-02-29', '2026-13-01', '2026-1-01', '0000-01-01', "2026-01\0-01", 'tomorrow'] as $invalid) {
            try {
                $validator->validate(['paid_at' => $invalid], ['paid_at' => 'required|date']);
                self::fail('Invalid calendar dates must fail validation.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('paid_at', $exception->errors());
            }
        }
    }

    public function testConcurrentPaymentsRecheckLockedBalanceBeforeRecording(): void
    {
        $invoiceId = app(InvoiceService::class)->createFromQuotation($this->quotation(), $this->adminId);
        $results = $this->concurrentWorkers('SELECT id FROM invoices WHERE id = ? FOR UPDATE', $invoiceId, ['invoice_id' => $invoiceId]);
        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['ok'])));
        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => !$result['ok'] && str_contains($result['error'], 'exceeds'))));
        self::assertSame('60.00', $this->db->fetchColumn('SELECT amount_paid FROM invoices WHERE id = ?', [$invoiceId]));
        self::assertSame('60.00', $this->db->fetchColumn('SELECT SUM(amount) FROM payments WHERE invoice_id = ?', [$invoiceId]));
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM payments WHERE invoice_id = ?', [$invoiceId]));
    }

    public function testConcurrentInvoiceConversionsReturnOneCompleteInvoice(): void
    {
        $quoteId = $this->quotation();
        $results = $this->concurrentWorkers('SELECT id FROM quotations WHERE id = ? FOR UPDATE', $quoteId, ['operation' => 'invoice', 'quotation_id' => $quoteId]);
        self::assertTrue($results[0]['ok']);
        self::assertTrue($results[1]['ok']);
        self::assertSame($results[0]['invoice_id'], $results[1]['invoice_id']);
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE quotation_id = ?', [$quoteId]));
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoice_items WHERE invoice_id = ?', [$results[0]['invoice_id']]));
    }

    private function concurrentWorkers(string $lockSql, int $id, array $operation): array
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('Subprocess support is required for concurrent finance tests.');
        }
        // Make this setup visible to independent worker connections, still in the disposable database.
        $this->db->pdo()->commit();
        $this->db->pdo()->beginTransaction();
        $this->db->fetch($lockSql, [$id]);
        $workers = [];
        try {
            foreach ([1, 2] as $workerNumber) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/finance_worker.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fwrite($pipes[0], json_encode($operation + ['database' => config('database'), 'app' => config('app'), 'mail' => config('mail'), 'staff_id' => $this->adminId]) . "\n");
                fflush($pipes[0]);
                self::assertSame("READY\n", fgets($pipes[1]));
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) {
                fwrite($pipes[0], "GO\n");
                fclose($pipes[0]);
            }
            // Both requests encounter the same parent lock before either can commit.
            usleep(300000);
            $this->db->pdo()->commit();
            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $results[] = json_decode(trim(stream_get_contents($pipes[1])), true, 512, JSON_THROW_ON_ERROR);
                self::assertSame('', stream_get_contents($pipes[2]));
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process));
            }
            $workers = [];
            return $results;
        } finally {
            if ($this->db->pdo()->inTransaction()) {
                $this->db->pdo()->rollBack();
            }
            foreach ($workers as [$process, $pipes]) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) fclose($pipe);
                }
                proc_close($process);
            }
        }
    }

    private function quotation(bool $issue = true): int
    {
        return app(QuotationService::class)->save($this->caseId, $this->payload(), $this->adminId, $issue, true);
    }

    private function payload(): array
    {
        return ['lines' => [['description' => 'Historical custom service', 'quantity' => '1', 'unit_price' => '100']], 'notes' => 'Saved quotation note', 'terms' => 'Saved quotation terms', 'validity_days' => 14];
    }

    private function payment(string $amount, string $method = 'bank_transfer'): array
    {
        return ['amount' => $amount, 'method' => $method, 'paid_at' => date('Y-m-d')];
    }

    private function request(string $method, array $body = [], array $query = []): Request
    {
        return new Request($method, '/admin', $query, $body, [], $_SERVER, []);
    }

    private function body(Response $response): string
    {
        return (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }
}
