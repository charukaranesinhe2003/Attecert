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
use ReflectionProperty;

final class RequestQuotationTest extends DatabaseTestCase
{
    public function testUploadFormIsInAssignedRequestAndRemovedFromDocuments(): void
    {
        $id = $this->requestRow('Visible request');
        $other = $this->requestRow('Hidden request');
        $staff = $this->signInAsRole('processing_staff');
        $this->db->run('UPDATE quote_requests SET assigned_to = ? WHERE id = ?', [$staff, $id]);
        $list = $this->body($this->dispatch('GET', '/admin/quote-requests'));
        $this->assertStringContainsString('/admin/quote-requests/' . $id . '#upload-quotation', $list);
        $this->assertStringContainsString('Upload &amp; email quotation', $list);
        $this->assertStringNotContainsString('Hidden request', $list);
        $detail = $this->body($this->dispatch('GET', '/admin/quote-requests/' . $id));
        $this->assertStringContainsString('name="quotation_document"', $detail);
        $this->assertStringContainsString('action="/admin/quote-requests/' . $id . '/quotation"', $detail);
        $this->assertStringNotContainsString('name="case_id"', $detail);
        $this->denied('GET', '/admin/quote-requests/' . $other);
        $this->denied('GET', '/admin/documents', [], [], 403);
        $this->asUser($this->adminId);
        $documents = $this->body($this->dispatch('GET', '/admin/documents'));
        $this->assertStringNotContainsString('id="upload-quotation"', $documents);
        $this->assertStringNotContainsString('name="quotation_document"', $documents);
        $this->asUser($this->adminId);
        $this->assertStringContainsString('Hidden request', $this->body($this->dispatch('GET', '/admin/quote-requests')));
        $this->assertStringContainsString('name="quotation_document"', $this->body($this->dispatch('GET', '/admin/quote-requests/' . $other)));
    }

    public function testRequestAssigneeCanUploadDownloadAndEmailWithoutReassigningLinkedCase(): void
    {
        $this->mailReady();
        $id = $this->requestRow('Quotation enquiry', $this->caseId);
        $caseStaff = $this->signInAsRole('processing_staff');
        $requestStaff = $this->signInAsRole('customer_support');
        $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$caseStaff, $this->caseId]);
        $this->db->run('UPDATE quote_requests SET assigned_to = ? WHERE id = ?', [$requestStaff, $id]);
        $file = $this->pdf();
        $response = $this->dispatch('POST', '/admin/quote-requests/' . $id . '/quotation', $this->input() + ['case_id' => 99999999, 'email' => 'wrong@example.test'], ['quotation_document' => $file]);
        $quotation = $this->db->fetch('SELECT * FROM quotations ORDER BY id DESC LIMIT 1');
        $this->assertSame('/admin/quote-requests/' . $id . '#uploaded-quotations', $this->value($response, 'headers')['Location']);
        $this->assertSame($id, (int) $quotation['quote_request_id']);
        $this->assertSame($this->caseId, (int) $quotation['case_id']);
        $this->assertSame($caseStaff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        $this->denied('GET', '/admin/cases/' . $this->caseId);
        $this->denied('GET', '/admin/quotations/' . $quotation['id']);
        $path = '/admin/quote-requests/' . $id . '/quotations/' . $quotation['id'];
        $download = $this->dispatch('GET', $path . '/pdf');
        $this->assertSame(hash_file('sha256', $file['tmp_name']), hash_file('sha256', $this->value($download, 'body')));
        $this->dispatch('POST', $path . '/send');
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE related_type = 'quotation' AND related_id = ? ORDER BY id DESC LIMIT 1", [$quotation['id']]);
        $this->assertSame('customer@attecert.local', $mail['recipient']);
        $this->assertSame('queued', $mail['status']);
        $payload = json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$mail['id']]), true);
        $this->assertSame(hash_file('sha256', $file['tmp_name']), hash_file('sha256', storage_path('mail/' . $payload['attachment_path'])));
        $this->assertStringContainsString('Request quotation.pdf', $this->body($this->dispatch('GET', '/admin/quote-requests/' . $id)));
        $this->asUser($caseStaff);
        $this->denied('GET', '/admin/quote-requests/' . $id);
        $this->denied('GET', $path . '/pdf');
        $this->denied('POST', $path . '/send');
        $this->denied('POST', '/admin/quote-requests/' . $id . '/quotation', $this->input(), ['quotation_document' => $file]);
    }

    public function testNewRequestCreatesCaseOnlyForSuccessfulUploadAndIgnoresForgedContext(): void
    {
        $id = $this->requestRow('New upload request');
        $staff = $this->signInAsRole('finance_staff');
        $this->db->run('UPDATE quote_requests SET assigned_to = ? WHERE id = ?', [$staff, $id]);
        $before = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases');
        try {
            $this->dispatch('POST', '/admin/quote-requests/' . $id . '/quotation', $this->input());
            $this->fail('Missing PDF was accepted.');
        } catch (ValidationException) {
            $this->assertSame($before, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases'));
            $this->assertNull($this->db->fetchColumn('SELECT converted_case_id FROM quote_requests WHERE id = ?', [$id]));
        }
        $this->dispatch('POST', '/admin/quote-requests/' . $id . '/quotation', $this->input() + ['case_id' => $this->caseId, 'quote_request_id' => 99999999], ['quotation_document' => $this->pdf()]);
        $quotation = $this->db->fetch('SELECT * FROM quotations ORDER BY id DESC LIMIT 1');
        $this->assertSame($id, (int) $quotation['quote_request_id']);
        $this->assertNotSame($this->caseId, (int) $quotation['case_id']);
        $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$quotation['case_id']]));
        $this->assertSame($before + 1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases'));
    }

    public function testQuotationIdsCannotBeUsedAcrossRequestsAndReassignmentRevokesAccess(): void
    {
        $first = $this->requestRow('First enquiry', $this->caseId);
        $second = $this->requestRow('Second enquiry', $this->caseId);
        $staff = $this->signInAsRole('processing_staff');
        $this->db->run('UPDATE quote_requests SET assigned_to = ? WHERE id IN (?, ?)', [$staff, $first, $second]);
        $this->dispatch('POST', '/admin/quote-requests/' . $first . '/quotation', $this->input(), ['quotation_document' => $this->pdf()]);
        $id = (int) $this->db->fetchColumn('SELECT id FROM quotations ORDER BY id DESC LIMIT 1');
        $this->denied('GET', '/admin/quote-requests/' . $second . '/quotations/' . $id . '/pdf');
        $this->denied('POST', '/admin/quote-requests/' . $second . '/quotations/' . $id . '/send');
        $this->db->run('UPDATE quote_requests SET assigned_to = NULL WHERE id = ?', [$first]);
        $this->denied('GET', '/admin/quote-requests/' . $first);
        $this->denied('GET', '/admin/quote-requests/' . $first . '/quotations/' . $id . '/pdf');
        $this->denied('POST', '/admin/quote-requests/' . $first . '/quotations/' . $id . '/send');
        $this->asUser($this->adminId);
        $this->assertSame(200, $this->value($this->dispatch('GET', '/admin/quote-requests/' . $first . '/quotations/' . $id . '/pdf'), 'status'));
    }

    private function requestRow(string $name, ?int $caseId = null): int
    {
        return $this->db->insert('INSERT INTO quote_requests (name, email, message, converted_case_id) VALUES (?, ?, ?, ?)', [$name, 'customer@attecert.local', $name, $caseId]);
    }

    private function input(): array
    {
        return ['total' => '950.50', 'validity_days' => '14', 'notes' => 'Manually prepared quotation'];
    }

    private function pdf(): array
    {
        $path = storage_path('request-quotation-fixture.pdf');
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        return ['name' => 'Request quotation.pdf', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
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

    private function denied(string $method, string $path, array $body = [], array $files = [], int $status = 404): void
    {
        try { $this->dispatch($method, $path, $body, $files); $this->fail('Unauthorized request quotation access accepted.'); }
        catch (HttpException $e) { $this->assertSame($status, $e->status()); }
    }

    private function body(Response $response): string
    {
        $this->assertSame(200, $this->value($response, 'status'));
        return $this->value($response, 'body');
    }

    private function value(Response $response, string $name): mixed
    {
        return (new ReflectionProperty(Response::class, $name))->getValue($response);
    }
}
