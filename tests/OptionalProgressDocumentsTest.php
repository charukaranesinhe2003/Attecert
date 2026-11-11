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
use App\Services\DocumentService;
use App\Services\PaymentService;
use App\Services\ProgressHistoryService;
use App\Services\TrackingService;
use App\Services\WorkflowService;
use ReflectionProperty;

final class OptionalProgressDocumentsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $property = new ReflectionProperty(Config::class, 'items'); $items = $property->getValue();
        $items['mail']['host'] = '127.0.0.1'; $items['mail']['from_address'] = 'fixture@example.test';
        $items['sms'] = ['base_url' => 'http://127.0.0.1:1', 'user_id' => 'fixture', 'api_key' => 'fixture', 'sender_id' => 'AtteCert', 'timeout' => 1];
        $property->setValue(null, $items);
        $this->db->run("UPDATE customers SET phone = '0771234567' WHERE id = ?", [$this->customerId]);
    }

    public function testEveryStepStateWorksWithoutAFileAndCanReceiveAnOptionalDocument(): void
    {
        foreach (['pending', 'current', 'completed'] as $i => $state) {
            $input = $this->step('Optional document step ' . $state, $state, $i + 1);
            $this->dispatch('POST', $this->path('/tracking/steps'), $input);
            $id = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps WHERE case_id = ? ORDER BY id DESC LIMIT 1', [$this->caseId]);
            $file = $this->pdf('Step ' . $state . '.pdf'); $hash = hash_file('sha256', $file['tmp_name']);
            $this->dispatch('POST', $this->path('/tracking/steps/' . $id), $input, ['step_document' => $file]);
            $document = $this->db->fetch('SELECT * FROM documents WHERE tracking_step_id = ?', [$id]);
            $this->assertSame($this->caseId, (int) $document['case_id']);
            $this->assertSame(1, (int) $document['customer_visible']);
            $this->assertSame($state, $this->db->fetchColumn('SELECT state FROM case_tracking_steps WHERE id = ?', [$id]));
            $this->assertSame($hash, hash_file('sha256', storage_path('documents/' . $document['stored_name'])));
            $payload = $this->latestPayload();
            $this->assertSame($file['name'], $payload['attachment_name']);
            $this->assertSame($hash, hash_file('sha256', storage_path('mail/' . $payload['attachment_path'])));
            $this->assertNull(app(TrackingService::class)->saveStep($this->caseId, $id, $input, ['error' => UPLOAD_ERR_NO_FILE]));
        }
        $this->assertSame(6, $this->smsCount());
        $this->assertSame(6, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'tracking-update'"));
        $this->assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'document-delivery'"));
        $html = $this->latestEmail();
        foreach (['Previous steps &amp; updates', 'Optional document step pending', 'Optional document step current', 'Optional document step completed', 'Attached document:'] as $text) { $this->assertStringContainsString($text, $html); }
    }

    public function testPaymentConfirmationWithPdfSavesAndEmailsTheOriginalWithoutDuplicateConfirmation(): void
    {
        $id = app(CasePaymentService::class)->record($this->caseId, $this->payment());
        $file = $this->pdf('PRIVATE_BANK_CONFIRMATION.pdf'); $hash = hash_file('sha256', $file['tmp_name']);
        $this->dispatch('POST', $this->path('/payments/' . $id . '/confirm'), ['confirmed' => '1', 'email' => 'wrong@example.test'], ['receipt_document' => $file]);
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        $this->assertSame('completed', $payment['status']); $this->assertNotNull($payment['receipt_document_id']);
        $this->assertSame($hash, hash_file('sha256', app(PaymentService::class)->receiptPath($id)));
        $document = $this->db->fetch('SELECT * FROM documents WHERE id = ?', [$payment['receipt_document_id']]);
        $this->assertSame('payment_receipt', $document['subcategory']); $this->assertSame(1, (int) $document['customer_visible']);
        $this->assertSame('customer@attecert.local', $this->db->fetchColumn('SELECT recipient FROM email_logs ORDER BY id DESC LIMIT 1'));
        $payload = $this->latestPayload();
        $this->assertSame($file['name'], $payload['attachment_name']);
        $this->assertSame($hash, hash_file('sha256', storage_path('mail/' . $payload['attachment_path'])));
        $this->assertFalse(app(CasePaymentService::class)->confirm($this->caseId, $id, $file)['changed']);
        $this->assertSame(1, $this->smsCount());
        try { app(DocumentService::class)->archive((int) $document['id'], $this->adminId); $this->fail('Receipt PDF was archived.'); }
        catch (HttpException $e) { $this->assertSame(422, $e->status()); }
        $public = $this->publicPage();
        foreach ([$file['name'], 'BANK-PRIVATE-001', 'LKR 60.00', '/documents/', $document['stored_name']] as $hidden) { $this->assertStringNotContainsString($hidden, $public); }
        $this->assertStringContainsString('Payment received', $public);
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->asUser($customerUser);
        $download = $this->dispatch('GET', '/portal/documents/' . $document['id'] . '/download');
        $this->assertSame($hash, hash_file('sha256', $this->body($download)));
    }

    public function testPaymentCanBeConfirmedWithoutDocumentAndReceivePdfLaterWithoutChangingMoney(): void
    {
        $id = app(CasePaymentService::class)->record($this->caseId, $this->payment());
        app(CasePaymentService::class)->confirm($this->caseId, $id, ['error' => UPLOAD_ERR_NO_FILE]);
        $before = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        $this->assertNull($before['receipt_document_id']);
        $result = app(CasePaymentService::class)->confirm($this->caseId, $id, $this->pdf());
        $after = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        foreach (['amount', 'confirmed_at', 'confirmed_by', 'status'] as $field) { $this->assertSame($before[$field], $after[$field]); }
        $this->assertSame('Payment confirmation PDF added.', $result['message']);
        $this->assertSame('60.00', $this->db->fetchColumn('SELECT SUM(amount) FROM payments WHERE case_id = ?', [$this->caseId]));
        $this->assertSame(2, $this->smsCount());
    }

    public function testInvalidOptionalUploadsCannotConfirmPaymentOrChangeStep(): void
    {
        $paymentId = app(CasePaymentService::class)->record($this->caseId, $this->payment());
        $step = $this->step('Document validation', 'pending', 1);
        app(TrackingService::class)->saveStep($this->caseId, null, $step);
        $stepId = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps WHERE case_id = ?', [$this->caseId]);
        $path = storage_path('invalid-receipt.pdf'); file_put_contents($path, '<html>Not a PDF</html>');
        $badFiles = [ ['name' => 'receipt.pdf', 'tmp_name' => $path, 'size' => 1, 'error' => UPLOAD_ERR_OK],
            ['name' => 'receipt.pdf', 'tmp_name' => $path, 'size' => 1, 'error' => UPLOAD_ERR_PARTIAL] ];
        foreach ($badFiles as $file) {
            try { app(CasePaymentService::class)->confirm($this->caseId, $paymentId, $file); $this->fail('Invalid receipt accepted.'); }
            catch (ValidationException) { $this->assertSame('pending', $this->db->fetchColumn('SELECT status FROM payments WHERE id = ?', [$paymentId])); }
            try { app(TrackingService::class)->saveStep($this->caseId, $stepId, array_replace($step, ['state' => 'completed']), $file); $this->fail('Invalid step attachment accepted.'); }
            catch (ValidationException|HttpException) { $this->assertSame('pending', $this->db->fetchColumn('SELECT state FROM case_tracking_steps WHERE id = ?', [$stepId])); }
        }
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM documents'));
        $this->assertSame(1, $this->smsCount());
    }

    public function testEarlierStepsAndStatusesRemainVisibleAfterEditingAndRemovingSteps(): void
    {
        $tracking = app(TrackingService::class);
        $tracking->status($this->caseId, ['status_label' => 'Courier has received request', 'notes' => 'PRIVATE STATUS MESSAGE'], false);
        $input = $this->step('Original submitted step', 'pending', 1);
        $tracking->saveStep($this->caseId, null, $input);
        $id = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps WHERE case_id = ?', [$this->caseId]);
        $tracking->saveStep($this->caseId, $id, array_replace($input, ['title' => 'Corrected completed step', 'state' => 'completed']));
        $tracking->deleteStep($this->caseId, $id);
        $tracking->saveStep($this->caseId, null, $this->step('Next processing step', 'current', 2));
        $public = $this->publicPage(); $admin = $this->body($this->dispatch('GET', $this->path()));
        foreach (['Original submitted step', 'Corrected completed step', 'Courier has received request', 'Next processing step', 'MOFA processing'] as $visible) {
            $this->assertStringContainsString($visible, $public); $this->assertStringContainsString($visible, $admin); $this->assertStringContainsString($visible, $this->latestEmail());
        }
        $this->assertStringNotContainsString('PRIVATE STATUS MESSAGE', $public);
        $this->assertSame(5, $this->smsCount());
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]); $this->asUser($customerUser);
        $customerPage = $this->body($this->dispatch('GET', '/portal/applications/' . $this->caseId));
        $this->assertStringContainsString('Original submitted step', $customerPage); $this->assertStringContainsString('Corrected completed step', $customerPage);
    }

    public function testUnassignedStaffAndOtherCustomersCannotAccessStepOrPaymentAttachments(): void
    {
        app(TrackingService::class)->saveStep($this->caseId, null, $this->step('Assigned step', 'completed', 1));
        $stepId = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps WHERE case_id = ?', [$this->caseId]);
        $paymentId = app(CasePaymentService::class)->record($this->caseId, $this->payment());
        $file = $this->pdf(); $staff = $this->signInAsRole('processing_staff');
        foreach ([[$this->path('/tracking/steps/' . $stepId), $this->step('Bad edit', 'completed', 1), ['step_document' => $file]],
            [$this->path('/payments/' . $paymentId . '/confirm'), ['confirmed' => '1'], ['receipt_document' => $file]]] as [$path, $input, $files]) {
            try { $this->dispatch('POST', $path, $input, $files); $this->fail('Unassigned upload accepted.'); }
            catch (HttpException $e) { $this->assertSame(404, $e->status()); }
        }
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM documents'));
        $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$staff, $this->caseId]);
        $this->dispatch('POST', $this->path('/payments/' . $paymentId . '/confirm'), ['confirmed' => '1'], ['receipt_document' => $file]);
        $documentId = (int) $this->db->fetchColumn('SELECT receipt_document_id FROM payments WHERE id = ?', [$paymentId]);
        $otherUser = $this->signInAsRole('customer');
        $this->db->insert('INSERT INTO customers (user_id, name, email) VALUES (?, ?, ?)', [$otherUser, 'Other customer', 'other-attachment@example.test']); $this->resetContainer();
        $this->expectException(HttpException::class); $this->dispatch('GET', '/portal/documents/' . $documentId . '/download');
    }

    public function testOptionalUploadFormsDoNotRequireFilesAndHistoryDoesNotDuplicateStatusEvents(): void
    {
        app(TrackingService::class)->status($this->caseId, ['status_label' => 'Ready for collection'], false);
        $events = app(ProgressHistoryService::class)->forCase($this->caseId);
        $this->assertSame(1, count(array_filter($events, static fn (array $event): bool => $event['title'] === 'Ready for collection')));
        app(CasePaymentService::class)->record($this->caseId, $this->payment());
        $html = $this->body($this->dispatch('GET', $this->path()));
        $dom = new \DOMDocument(); @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
        foreach (['step_document', 'receipt_document'] as $name) {
            $fields = $xpath->query('//input[@type="file" and @name="' . $name . '"]'); $this->assertGreaterThan(0, $fields->length);
            foreach ($fields as $field) { $this->assertFalse($field->hasAttribute('required')); $this->assertSame('multipart/form-data', $field->parentNode->parentNode->getAttribute('enctype')); }
        }
        $this->assertStringContainsString('Previous steps &amp; updates', $html);
    }

    private function pdf(string $name = 'Confirmation.pdf'): array { $path = storage_path('optional-fixture-' . bin2hex(random_bytes(4)) . '.pdf'); if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true); file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n"); return ['name' => $name, 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK]; }
    private function step(string $title, string $state, int $order): array { return ['title' => $title, 'state' => $state, 'sort_order' => (string) $order, 'public_description' => 'Public progress description', 'step_date' => date('Y-m-d')]; }
    private function payment(): array { return ['amount' => '60.00', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d'), 'reference' => 'BANK-PRIVATE-001', 'notes' => 'PRIVATE STAFF PAYMENT NOTE', 'request_key' => bin2hex(random_bytes(16))]; }
    private function path(string $suffix = ''): string { return '/admin/cases/' . $this->caseId . $suffix; }
    private function smsCount(): int { return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'); }
    private function latestPayload(): array { return json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox ORDER BY email_log_id DESC LIMIT 1'), true, 512, JSON_THROW_ON_ERROR); }
    private function latestEmail(): string { return file_get_contents(storage_path('mail/' . $this->db->fetchColumn('SELECT body_path FROM email_logs ORDER BY id DESC LIMIT 1'))); }
    private function publicPage(): string { $number = $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]); return $this->body($this->dispatch('GET', '/track', [], [], ['number' => $number])); }
    private function asUser(int $id): void { $_SESSION = ['locale' => 'en', 'user_id' => $id]; $this->resetContainer(); }
    private function body(Response $response): string { return (new ReflectionProperty(Response::class, 'body'))->getValue($response); }
    private function dispatch(string $method, string $path, array $input = [], array $files = [], array $query = []): Response { $router = new Router(); (require base_path('routes/web.php'))($router); return $router->dispatch(new Request($method, $path, $query, $input, $files, ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app()); }
}
