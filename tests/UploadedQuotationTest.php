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
use App\Services\DocumentService;
use App\Services\QuotationService;
use ReflectionProperty;

final class UploadedQuotationTest extends DatabaseTestCase
{
    public function testAssignedStaffUploadsOriginalPdfAndQueuesItToTheCaseCustomer(): void
    {
        $this->mailReady();
        $staff = $this->signInAsRole('processing_staff');
        $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$staff, $this->caseId]);
        $file = $this->pdf('Original prepared quotation.pdf');
        $hash = hash_file('sha256', $file['tmp_name']);
        $response = $this->dispatch('POST', '/admin/quotations/upload', $this->input() + ['email' => 'wrong-recipient@example.test'], ['quotation_document' => $file]);
        $quote = $this->latest();
        $this->assertSame('/admin/quotations/' . $quote['id'], $this->value($response, 'headers')['Location']);
        $this->assertSame('1250.75', $quote['total']);
        $this->assertNotNull($quote['uploaded_document_id']);
        $service = app(QuotationService::class);
        $this->assertSame($hash, hash_file('sha256', $service->pdfPath((int) $quote['id'])));
        $this->assertSame('Original prepared quotation.pdf', $service->downloadName((int) $quote['id']));
        $this->assertFileDoesNotExist(storage_path('quotations/' . $quote['number'] . '.pdf'));
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE related_type = 'quotation' AND related_id = ? ORDER BY id DESC LIMIT 1", [$quote['id']]);
        $this->assertSame('queued', $mail['status']);
        $this->assertSame('customer@attecert.local', $mail['recipient']);
        $payload = json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$mail['id']]), true);
        $this->assertSame('Original prepared quotation.pdf', $payload['attachment_name']);
        $this->assertSame($hash, hash_file('sha256', storage_path('mail/' . $payload['attachment_path'])));
        $html = $this->value($this->dispatch('GET', '/admin/cases/' . $this->caseId), 'body');
        $this->assertStringContainsString('Original prepared quotation.pdf', $html);
        $this->assertStringContainsString('/admin/quotations/' . $quote['id'], $html);
        $this->signInAsRole('finance_staff');
        $this->denied('GET', '/admin/quotations/' . $quote['id'] . '/pdf');
        $this->denied('POST', '/admin/quotations/upload', $this->input(), ['quotation_document' => $file]);
        $this->expectException(HttpException::class);
        $this->dispatch('GET', '/admin/documents');
    }

    public function testCustomerDownloadsExactFileAndCannotReadAnotherCustomersQuotation(): void
    {
        $file = $this->pdf('Customer quotation.pdf');
        $this->dispatch('POST', '/admin/quotations/upload', $this->input(), ['quotation_document' => $file]);
        $quote = $this->latest();
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->asUser($customerUser);
        $download = $this->dispatch('GET', '/portal/quotations/' . $quote['id'] . '/pdf');
        $this->assertSame(hash_file('sha256', $file['tmp_name']), hash_file('sha256', $this->value($download, 'body')));
        $this->assertSame(200, $this->value($this->dispatch('GET', '/portal/quotations/' . $quote['id']), 'status'));
        $stranger = $this->signInAsRole('customer');
        $this->db->insert('INSERT INTO customers (user_id, name, email) VALUES (?, ?, ?)', [$stranger, 'Other customer', 'other@example.test']);
        $this->resetContainer();
        $this->denied('GET', '/portal/quotations/' . $quote['id'] . '/pdf');
    }

    public function testInvalidFilesAmountsAndOldBuilderRequestsCannotCreateQuotations(): void
    {
        $valid = $this->pdf('valid.pdf');
        $text = $this->pdf('malware.pdf', '<?php echo 1;');
        foreach ([[], $text, $valid] as $index => $file) {
            if ($index === 2) { $file['name'] = 'document.php'; }
            $this->invalidUpload($file, $this->input());
        }
        foreach (['0', '-1', 'NaN', ['bad']] as $total) {
            $this->invalidUpload($valid, array_replace($this->input(), ['total' => $total]));
        }
        $huge = $this->pdf('huge.pdf', "%PDF-1.4\n" . str_repeat('x', 1024 * 1024 + 1));
        $this->db->run("UPDATE settings SET setting_value = '1' WHERE setting_key = 'upload_max_mb'");
        $this->resetContainer();
        $this->invalidUpload($huge, $this->input());
        try {
            $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/quotation', ['lines' => [], 'issue' => '1']);
            $this->fail('Former quotation builder still accepts generated quotation requests.');
        } catch (ValidationException) {
            $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations'));
        }
    }

    public function testSmtpMissingIsReportedAndSavedQuotationCanBeRetried(): void
    {
        $this->dispatch('POST', '/admin/quotations/upload', $this->input(), ['quotation_document' => $this->pdf('Saved quotation.pdf')]);
        $quote = $this->latest();
        $this->assertStringContainsString('SMTP is not configured', $_SESSION['_flash']['error']);
        $this->assertStringNotContainsString('Email sent', $_SESSION['_flash']['success']);
        $this->assertSame('logged', $this->db->fetchColumn("SELECT status FROM email_logs WHERE related_type = 'quotation' AND related_id = ?", [$quote['id']]));
        $this->mailReady();
        $result = app(QuotationService::class)->send((int) $quote['id'], $this->adminId);
        $this->assertSame('queued', $result['status']);
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations'));
    }

    public function testRevisionsRetainOriginalFilesAndAcceptanceUsesManuallyEnteredTotal(): void
    {
        $first = app(QuotationService::class)->upload($this->caseId, $this->pdf('First quotation.pdf'), $this->input(), $this->adminId);
        app(QuotationService::class)->send($first, $this->adminId);
        $oldHash = hash_file('sha256', app(QuotationService::class)->pdfPath($first));
        $next = app(QuotationService::class)->upload($this->caseId, $this->pdf('Revised quotation.pdf', "%PDF-1.4\n% revised document\n%%EOF\n"), ['total' => '2400.00', 'validity_days' => '30'], $this->adminId);
        app(QuotationService::class)->send($next, $this->adminId);
        $this->assertSame('superseded', $this->db->fetchColumn('SELECT status FROM quotations WHERE id = ?', [$first]));
        $this->assertSame(2, (int) $this->db->fetchColumn('SELECT version FROM quotations WHERE id = ?', [$next]));
        $this->assertSame($oldHash, hash_file('sha256', app(QuotationService::class)->pdfPath($first)));
        $doc = (int) $this->db->fetchColumn('SELECT uploaded_document_id FROM quotations WHERE id = ?', [$first]);
        try {
            app(DocumentService::class)->archive($doc, $this->adminId);
            $this->fail('Quotation document was archived.');
        } catch (HttpException $e) { $this->assertSame(422, $e->status()); }
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->asUser($customerUser);
        $this->dispatch('POST', '/portal/quotations/' . $next . '/accept');
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE quotation_id = ?', [$next]);
        $this->assertNotNull($invoice);
        $this->assertSame('2400.00', $invoice['total']);
        $this->assertSame('2400.00', $this->db->fetchColumn('SELECT line_total FROM invoice_items WHERE invoice_id = ?', [$invoice['id']]));
    }

    private function input(): array
    {
        return ['case_id' => (string) $this->caseId, 'total' => '1250.75', 'validity_days' => '14', 'notes' => 'Prepared manually'];
    }

    private function pdf(string $name, string $bytes = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n"): array
    {
        if (!is_dir(storage_path('fixtures'))) { mkdir(storage_path('fixtures'), 0775, true); }
        $path = storage_path('fixtures/' . bin2hex(random_bytes(8)) . '.pdf');
        file_put_contents($path, $bytes);
        return ['name' => $name, 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    private function invalidUpload(array $file, array $body): void
    {
        try {
            $this->dispatch('POST', '/admin/quotations/upload', $body, $file === [] ? [] : ['quotation_document' => $file]);
            $this->fail('Invalid quotation upload accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations'));
        }
    }

    private function latest(): array
    {
        return $this->db->fetch('SELECT * FROM quotations ORDER BY id DESC LIMIT 1');
    }

    private function caseNumber(): string
    {
        return (string) $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]);
    }

    private function mailReady(): void
    {
        $prop = new ReflectionProperty(Config::class, 'items');
        $items = $prop->getValue();
        $items['mail']['host'] = '127.0.0.1';
        $items['mail']['from_address'] = 'fixture@example.test';
        $prop->setValue(null, $items);
    }

    private function asUser(int $id): void
    {
        $_SESSION = ['locale' => 'en', 'user_id' => $id];
        $this->resetContainer();
    }

    private function dispatch(string $method, string $path, array $body = [], array $files = []): Response
    {
        $router = new Router();
        (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request($method, $path, [], $body, $files, ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app());
    }

    private function denied(string $method, string $path, array $body = [], array $files = []): void
    {
        try { $this->dispatch($method, $path, $body, $files); $this->fail('Unauthorized quotation access accepted.'); }
        catch (HttpException $e) { $this->assertSame(404, $e->status()); }
    }

    private function value(Response $response, string $name): mixed
    {
        return (new ReflectionProperty(Response::class, $name))->getValue($response);
    }
}
