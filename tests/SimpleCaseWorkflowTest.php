<?php
declare(strict_types=1);
namespace Tests;
require_once __DIR__ . '/DatabaseTestCase.php';

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\CasePaymentService;
use App\Services\CaseService;
use App\Services\InvoiceService;
use App\Services\NumberService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\TrackingService;
use App\Services\WorkflowService;
use ReflectionProperty;

final class SimpleCaseWorkflowTest extends DatabaseTestCase
{
    public function testNewReferencesSkipArchivedCollisionsAndContinueAfterNinetyNine(): void
    {
        $prefix = 'KASS/Q/' . date('Ymd');
        $this->db->run('DELETE FROM sequences WHERE name = ?', ['tracking_' . date('Ymd')]);
        $this->db->run('UPDATE cases SET number = ?, deleted_at = NOW() WHERE id = ?', [$prefix . '01', $this->caseId]);
        $id = app(CaseService::class)->create($this->caseData(), [], $this->adminId);
        $number = (string) $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$id]);
        $this->assertSame($prefix . '02', $number);
        $this->assertSame($number, app(CaseService::class)->track($number)['number']);
        $this->db->run('UPDATE sequences SET current_value = 99 WHERE name = ?', ['tracking_' . date('Ymd')]);
        $this->assertSame($prefix . '100', app(NumberService::class)->nextTracking());
        $this->assertSame($prefix . '101', app(NumberService::class)->nextTracking());
    }

    public function testConcurrentConnectionsReserveDistinctDailyReferences(): void
    {
        $this->db->run('INSERT INTO sequences (name, year, current_value) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE current_value = current_value', ['tracking_' . date('Ymd'), date('Y')]);
        $this->db->pdo()->commit(); $this->db->pdo()->beginTransaction();
        $this->db->fetch('SELECT * FROM sequences WHERE name = ? FOR UPDATE', ['tracking_' . date('Ymd')]);
        $workers = [];
        try {
            foreach ([1, 2] as $i) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/tracking_number_worker.php'], [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
                $this->assertIsResource($process);
                fwrite($pipes[0], json_encode(['database' => config('database'), 'app' => config('app')]) . "\n"); fflush($pipes[0]);
                $this->assertSame("READY\n", fgets($pipes[1])); $workers[] = [$process, $pipes];
            }
            foreach ($workers as [, $pipes]) { fwrite($pipes[0], "GO\n"); fclose($pipes[0]); }
            usleep(100000); $this->db->pdo()->commit();
            $numbers = [];
            foreach ($workers as [$process, $pipes]) {
                array_push($numbers, ...json_decode(stream_get_contents($pipes[1]), true, 512, JSON_THROW_ON_ERROR));
                $this->assertSame('', stream_get_contents($pipes[2])); fclose($pipes[1]); fclose($pipes[2]);
                $this->assertSame(0, proc_close($process));
            }
            $workers = []; $this->assertCount(50, array_unique($numbers));
            foreach ($numbers as $number) { $this->assertMatchesRegularExpression('~^KASS/Q/' . date('Ymd') . '[0-9]{2,}$~D', $number); }
        } finally {
            if ($this->db->pdo()->inTransaction()) { $this->db->pdo()->rollBack(); }
            foreach ($workers as [$process, $pipes]) { proc_terminate($process); foreach ($pipes as $pipe) { if (is_resource($pipe)) fclose($pipe); } proc_close($process); }
        }
    }

    public function testManualPaymentNeedsConfirmationAndNotifiesExactlyOnceWithoutAnInvoice(): void
    {
        $this->deliveryReady(); $input = $this->payment() + ['notes' => 'PRIVATE BANK CHECK'];
        $id = app(CasePaymentService::class)->record($this->caseId, $input);
        $this->assertSame($id, app(CasePaymentService::class)->record($this->caseId, $input));
        $row = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        $this->assertNull($row['invoice_id']); $this->assertNull($row['confirmed_at']); $this->assertSame('pending', $row['status']); $this->assertSame(0, $this->smsCount());
        try { app(PaymentService::class)->receiptPath($id); $this->fail('Pending payment has a receipt.'); }
        catch (HttpException $e) { $this->assertSame(409, $e->status()); }
        $result = app(CasePaymentService::class)->confirm($this->caseId, $id);
        $this->assertTrue($result['changed']); $this->assertSame('queued', $result['delivery']['status']); $this->assertSame('queued', $result['delivery']['sms']['status']);
        $this->assertFalse(app(CasePaymentService::class)->confirm($this->caseId, $id)['changed']); $this->assertSame(1, $this->smsCount());
        $row = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        $this->assertSame('completed', $row['status']); $this->assertNotNull($row['confirmed_at']); $this->assertSame($this->adminId, (int) $row['confirmed_by']);
        $html = $this->latestMail();
        foreach (['Amount received', 'LKR 60.00', 'Receipt number', $row['number']] as $expected) { $this->assertStringContainsString($expected, $html); }
        $this->assertStringNotContainsString('PRIVATE BANK CHECK', $html);
        $this->assertStringStartsWith('%PDF-', file_get_contents(app(PaymentService::class)->receiptPath($id)));
        $this->assertStringContainsString($row['number'], $this->body($this->dispatch('GET', '/admin/payments')));
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->asUser($customerUser); $this->assertStringContainsString($row['number'], $this->body($this->dispatch('GET', '/portal/payments')));
    }

    public function testConfirmationRechecksInvoiceBalanceAndPreventsDoubleCounting(): void
    {
        $invoiceId = $this->invoice();
        $first = app(CasePaymentService::class)->record($this->caseId, $this->payment() + ['invoice_id' => $invoiceId]);
        $second = app(CasePaymentService::class)->record($this->caseId, $this->payment() + ['invoice_id' => $invoiceId]);
        $this->assertSame('0.00', $this->db->fetchColumn('SELECT amount_paid FROM invoices WHERE id = ?', [$invoiceId]));
        app(CasePaymentService::class)->confirm($this->caseId, $first);
        try { app(CasePaymentService::class)->confirm($this->caseId, $second); $this->fail('Overpayment accepted.'); }
        catch (ValidationException) { $this->assertSame('pending', $this->db->fetchColumn('SELECT status FROM payments WHERE id = ?', [$second])); }
        $this->assertSame('60.00', $this->db->fetchColumn('SELECT amount_paid FROM invoices WHERE id = ?', [$invoiceId])); $this->assertSame(1, $this->smsCount());
        app(CasePaymentService::class)->cancel($this->caseId, $second);
        $this->assertSame('cancelled', $this->db->fetchColumn('SELECT status FROM payments WHERE id = ?', [$second]));
        $this->expectException(ValidationException::class); app(CasePaymentService::class)->cancel($this->caseId, $first);
    }

    public function testCustomStatusIsCaseSpecificPublicEscapedAndNotOverwrittenByAutomaticEvents(): void
    {
        $this->deliveryReady(); $label = 'Courier <collection> arranged'; $service = app(TrackingService::class);
        $this->assertTrue($service->status($this->caseId, ['status_label' => $label, 'notes' => 'PRIVATE CUSTOMER UPDATE'], false)['changed']);
        $this->assertFalse($service->status($this->caseId, ['status_label' => $label], false)['changed']);
        $number = $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]); $case = app(CaseService::class)->track($number);
        $this->assertSame($label, $case['status_name']); $this->assertSame('custom', $case['status_code']); $this->assertSame($label, end($case['history'])['name']);
        $html = $this->body($this->dispatch('GET', '/track', [], ['number' => $number]));
        $this->assertStringContainsString('Courier &lt;collection&gt; arranged', $html); $this->assertStringNotContainsString('PRIVATE CUSTOMER UPDATE', $html);
        $this->assertStringContainsString('PRIVATE CUSTOMER UPDATE', $this->latestMail());
        $this->assertNotContains($label, array_column(app(WorkflowService::class)->statuses(), 'name'));
        $this->assertFalse(app(WorkflowService::class)->advance($this->caseId, 'ready_for_collection', $this->adminId, 'Automatic', true));
        $this->assertSame($label, app(CaseService::class)->find($this->caseId)['status_name']); $this->assertSame(1, $this->smsCount());
        $service->status($this->caseId, ['status_label' => 'Ready for collection'], false);
        $this->assertNull($this->db->fetchColumn('SELECT custom_status FROM cases WHERE id = ?', [$this->caseId])); $this->assertSame(2, $this->smsCount());
    }

    public function testCustomStatusReopeningStillRequiresConfirmationAndReason(): void
    {
        app(WorkflowService::class)->change($this->caseId, 'completed', $this->adminId, 'Finished', true, false);
        try { app(TrackingService::class)->status($this->caseId, ['status_label' => 'Awaiting corrected copy'], false); $this->fail('Reopened without confirmation.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('reason', strtolower($e->getMessage())); }
        app(TrackingService::class)->status($this->caseId, ['status_label' => 'Awaiting corrected copy', 'notes' => 'Customer requested correction'], true);
        $case = app(CaseService::class)->find($this->caseId);
        $this->assertNull($case['completed_at']); $this->assertSame('under_review', $case['status_code']); $this->assertSame('Awaiting corrected copy', $case['status_name']);
    }

    public function testStaffCannotReadOtherCaseDetailsOrMutateItsPaymentsAndStatuses(): void
    {
        $id = app(CasePaymentService::class)->record($this->caseId, $this->payment()); $staff = $this->signInAsRole('customer_support');
        foreach ([['GET', '/details', []], ['POST', '/payments', $this->payment()], ['POST', '/payments/' . $id . '/confirm', ['confirmed' => '1']], ['POST', '/tracking/status', ['status_label' => 'Unauthorized']]] as [$method, $suffix, $body]) {
            try { $this->dispatch($method, '/admin/cases/' . $this->caseId . $suffix, $body); $this->fail('Unassigned case accessed.'); }
            catch (HttpException $e) { $this->assertSame(404, $e->status()); }
        }
        $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$staff, $this->caseId]);
        $this->assertStringContainsString('Add a payment manually', $this->body($this->dispatch('GET', '/admin/cases/' . $this->caseId)));
        try { $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/payments/' . $id . '/confirm'); $this->fail('Missing confirmation accepted.'); }
        catch (ValidationException) { $this->assertSame('pending', $this->db->fetchColumn('SELECT status FROM payments WHERE id = ?', [$id])); }
        $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/payments/' . $id . '/confirm', ['confirmed' => '1']);
        $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT confirmed_by FROM payments WHERE id = ?', [$id]));
    }

    public function testPaymentCannotUseAnotherCaseInvoiceOrPaymentId(): void
    {
        $invoiceId = $this->invoice(); $other = app(CaseService::class)->create($this->caseData(), [], $this->adminId);
        try { app(CasePaymentService::class)->record($other, $this->payment() + ['invoice_id' => $invoiceId]); $this->fail('Foreign invoice accepted.'); }
        catch (HttpException $e) { $this->assertSame(404, $e->status()); }
        $paymentId = app(CasePaymentService::class)->record($this->caseId, $this->payment());
        $this->expectException(HttpException::class); app(CasePaymentService::class)->confirm($other, $paymentId);
    }

    public function testWorkflowIsAccessibleWithoutHiddenTabPanelsAndReturnsToTheCase(): void
    {
        $html = $this->body($this->dispatch('GET', '/admin/cases/' . $this->caseId));
        $dom = new \DOMDocument(); @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
        $this->assertSame(0, $xpath->query('//*[@data-case-tabs]')->length);
        foreach ($xpath->query('//nav[@aria-label="Case workflow"]/a') as $link) { $this->assertSame(1, $xpath->query('//*[@id="' . substr($link->getAttribute('href'), 1) . '"]')->length); }
        $this->assertSame(1, $xpath->query('//*[@id="case-status-options"]')->length);
        foreach ($xpath->query('//main//form//input[not(@type="hidden")]|//main//form//select|//main//form//textarea') as $control) { $this->assertSame('label', $control->parentNode->nodeName, $control->getAttribute('name') . ' must have a label.'); }
        $response = $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/tracking/steps', ['title' => 'Document received', 'state' => 'completed', 'sort_order' => '1', 'return_to' => 'case']);
        $this->assertSame('/admin/cases/' . $this->caseId . '#overview', (new ReflectionProperty(Response::class, 'headers'))->getValue($response)['Location']);
        $this->assertStringNotContainsString('Upload &amp; email quotation', $html);
    }

    private function payment(): array { return ['amount' => '60.00', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d'), 'reference' => 'BANK-001', 'request_key' => bin2hex(random_bytes(16))]; }
    private function caseData(): array { return ['customer_id' => $this->customerId, 'country_id' => null, 'document_type_id' => null, 'document_title' => 'Workflow test document']; }
    private function invoice(): int { $quote = app(QuotationService::class)->save($this->caseId, ['lines' => [['description' => 'Manual attestation', 'quantity' => 1, 'unit_price' => '100.00']]], $this->adminId, false, true); return app(InvoiceService::class)->createFromQuotation($quote, $this->adminId); }
    private function smsCount(): int { return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'); }
    private function latestMail(): string { $path = $this->db->fetchColumn("SELECT body_path FROM email_logs WHERE template = 'tracking-update' ORDER BY id DESC LIMIT 1"); return file_get_contents(storage_path('mail/' . $path)); }
    private function asUser(int $id): void { $_SESSION = ['locale' => 'en', 'user_id' => $id]; $this->resetContainer(); }
    private function dispatch(string $method, string $path, array $body = [], array $query = []): Response { $router = new Router(); (require base_path('routes/web.php'))($router); return $router->dispatch(new Request($method, $path, $query, $body, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app()); }
    private function body(Response $response): string { return (new ReflectionProperty(Response::class, 'body'))->getValue($response); }
    private function deliveryReady(): void { $property = new ReflectionProperty(Config::class, 'items'); $items = $property->getValue(); $items['mail']['host'] = '127.0.0.1'; $items['mail']['from_address'] = 'fixture@example.test'; $items['sms'] = ['base_url' => 'http://127.0.0.1:1', 'user_id' => 'fixture', 'api_key' => 'fixture', 'sender_id' => 'AtteCert', 'timeout' => 1]; $property->setValue(null, $items); $this->db->run("UPDATE customers SET phone = '0771234567' WHERE id = ?", [$this->customerId]); }
}
