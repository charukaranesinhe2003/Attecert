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
use App\Middleware\CsrfMiddleware;
use App\Services\CaseService;
use App\Services\TrackingService;
use ReflectionProperty;

final class TrackingManagementTest extends DatabaseTestCase
{
    public function testAdminPublishesOrderedStepsAndSummaryWithoutExposingPrivateCaseData(): void
    {
        $this->db->run('UPDATE cases SET internal_notes = ?, customer_notes = ? WHERE id = ?', ['PRIVATE STAFF NOTE', 'PRIVATE CUSTOMER NOTE', $this->caseId]);
        $this->assertStringContainsString('href="/admin/tracking"', $this->body($this->dispatch('GET', '/admin/tracking')));
        $this->post('/tracking', ['tracking_expected_on' => '2026-12-15', 'tracking_message' => 'Your documents are being processed.']);
        $this->post('/tracking/steps', $this->step('Embassy legalization', 'pending', 3));
        $this->post('/tracking/steps', $this->step('Request received', 'completed', 1) + ['public_description' => 'We received your request.']);
        $this->post('/tracking/steps', $this->step('MOFA attestation', 'current', 2) + ['public_description' => 'Processing at the Ministry of Foreign Affairs.']);
        $case = app(CaseService::class)->track($this->number());
        $this->assertSame(['Request received', 'MOFA attestation', 'Embassy legalization'], array_column($case['steps'], 'title'));
        $this->assertSame('2026-12-15', $case['tracking_expected_on']);
        $html = $this->body($this->dispatch('GET', '/track', [], ['number' => $this->number()]));
        foreach (['Document Status Tracking Center', 'Your documents are being processed.', 'Ministry of Foreign Affairs.', '15 Dec 2026', 'is-current', 'is-completed', 'is-pending'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['PRIVATE STAFF NOTE', 'PRIVATE CUSTOMER NOTE', 'customer@attecert.local', 'staff_name', '/admin/', '/portal/documents/', 'Demo timeline'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
        $this->assertArrayNotHasKey('customer_email', $case);
        $this->assertArrayNotHasKey('created_by', $case['steps'][0]);
    }

    public function testUnassignedStaffCannotReadOrModifyTrackingAndReassignmentRevokesAccess(): void
    {
        foreach (['manager', 'processing_staff', 'finance_staff', 'content_editor', 'customer_support'] as $role) {
            $staff = $this->signInAsRole($role);
            $html = $this->body($this->dispatch('GET', '/admin/tracking'));
            $this->assertStringNotContainsString($this->number(), $html);
            $this->denied('GET', '/admin/tracking', [], ['case_id' => $this->caseId]);
            foreach (['/tracking', '/tracking/status', '/tracking/steps', '/tracking/steps/1', '/tracking/steps/1/delete', '/tracking/documents', '/tracking/documents/send'] as $suffix) {
                $this->denied('POST', '/admin/cases/' . $this->caseId . $suffix, ['confirmed' => '1']);
            }
            $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$staff, $this->caseId]);
            $this->assertStringContainsString('Add tracking step', $this->body($this->dispatch('GET', '/admin/tracking', [], ['case_id' => $this->caseId])));
            $this->post('/tracking/steps', $this->step('Assigned staff step', 'current', 1));
            $this->db->run('UPDATE cases SET assigned_to = NULL WHERE id = ?', [$this->caseId]);
            $this->denied('POST', '/admin/cases/' . $this->caseId . '/tracking/steps', $this->step('Revoked edit', 'completed', 2));
        }
        $this->assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM case_tracking_steps WHERE title = 'Revoked edit'"));
    }

    public function testStepUpdatesDeletesAndCurrentStateAreScopedToTheirCase(): void
    {
        $this->post('/tracking/steps', $this->step('First step', 'current', 1));
        $first = (int) $this->db->fetchColumn('SELECT id FROM case_tracking_steps ORDER BY id LIMIT 1');
        $this->post('/tracking/steps', $this->step('Second step', 'current', 2));
        $this->assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM case_tracking_steps WHERE state = 'current'"));
        $this->post('/tracking/steps/' . $first, $this->step('Corrected first step', 'completed', 1));
        $other = app(CaseService::class)->create(['customer_id' => $this->customerId, 'country_id' => null, 'document_type_id' => null, 'document_title' => 'Another case'], [], $this->adminId);
        $this->denied('POST', '/admin/cases/' . $other . '/tracking/steps/' . $first, $this->step('Cross case edit', 'completed', 1));
        $this->denied('POST', '/admin/cases/' . $other . '/tracking/steps/' . $first . '/delete', ['confirmed' => '1']);
        $this->post('/tracking/steps/' . $first . '/delete', ['confirmed' => '1']);
        $this->assertFalse($this->db->fetchColumn('SELECT id FROM case_tracking_steps WHERE id = ?', [$first]));
    }

    public function testStatusUpdatesEmailCustomerAndRequireConfirmedCorrections(): void
    {
        $this->mailReady();
        $this->post('/tracking/status', ['status' => 'ready_for_collection', 'notes' => 'PRIVATE EMAIL REASON']);
        $tracked = app(CaseService::class)->track($this->number());
        $this->assertSame('ready_for_collection', $tracked['status_code']);
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE template = 'tracking-update' ORDER BY id DESC LIMIT 1");
        $this->assertSame('queued', $mail['status']);
        $this->assertSame('customer@attecert.local', $mail['recipient']);
        $html = $this->body($this->dispatch('GET', '/track', [], ['number' => $this->number()]));
        $this->assertStringContainsString('Ready now', $html);
        $this->assertStringNotContainsString('PRIVATE EMAIL REASON', $html);
        $count = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'tracking-update'");
        $this->post('/tracking/status', ['status' => 'ready_for_collection']);
        $this->assertSame($count, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'tracking-update'"));
        try {
            $this->post('/tracking/status', ['status' => 'under_review']);
            $this->fail('Backward transition was accepted without confirmation.');
        } catch (ValidationException) {
            $this->assertSame('ready_for_collection', app(CaseService::class)->track($this->number())['status_code']);
        }
        $this->post('/tracking/status', ['status' => 'under_review', 'confirm_reversal' => '1', 'notes' => 'Corrected the selected stage']);
        $this->assertSame('under_review', app(CaseService::class)->track($this->number())['status_code']);
    }

    public function testDocumentEmailAttachesOriginalFileAndProtectsCustomerAccess(): void
    {
        $this->mailReady();
        $staff = $this->signInAsRole('processing_staff');
        $this->db->run('UPDATE cases SET assigned_to = ? WHERE id = ?', [$staff, $this->caseId]);
        $file = $this->pdf();
        $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/tracking/documents', ['description' => 'Final attested file'], [], ['document' => $file]);
        $document = $this->db->fetch('SELECT * FROM documents ORDER BY id DESC LIMIT 1');
        $this->assertSame(1, (int) $document['customer_visible']);
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE template = 'document-delivery' ORDER BY id DESC LIMIT 1");
        $this->assertSame('queued', $mail['status']);
        $this->assertSame('customer@attecert.local', $mail['recipient']);
        $payload = json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$mail['id']]), true);
        $this->assertSame('Attested document.pdf', $payload['attachment_name']);
        $this->assertSame(hash_file('sha256', $file['tmp_name']), hash_file('sha256', storage_path('mail/' . $payload['attachment_path'])));
        $this->assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'document-ready'"));
        $this->post('/tracking/documents/send', ['document_id' => $document['id']]);
        $this->assertSame(2, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'document-delivery'"));
        $customer = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->asUser($customer);
        $download = $this->dispatch('GET', '/portal/documents/' . $document['id'] . '/download');
        $this->assertSame(hash_file('sha256', $file['tmp_name']), hash_file('sha256', $this->value($download, 'body')));
        $other = $this->signInAsRole('customer');
        $this->db->insert('INSERT INTO customers (user_id, name, email) VALUES (?, ?, ?)', [$other, 'Other person', 'other@example.test']);
        $this->resetContainer();
        $this->denied('GET', '/portal/documents/' . $document['id'] . '/download', [], [], [], 403);
    }

    public function testCannotEmailAnotherCaseOrInternalDocuments(): void
    {
        $file = $this->pdf();
        $internal = app(\App\Services\DocumentService::class)->store($this->caseId, null, $file, 'internal', 'other', '', false, $this->adminId);
        $this->denied('POST', '/admin/cases/' . $this->caseId . '/tracking/documents/send', ['document_id' => $internal]);
        $other = app(CaseService::class)->create(['customer_id' => $this->customerId, 'country_id' => null, 'document_type_id' => null, 'document_title' => 'Other file case'], [], $this->adminId);
        $document = app(\App\Services\DocumentService::class)->store($other, null, $file, 'completed', 'other', '', false, $this->adminId);
        $this->denied('POST', '/admin/cases/' . $this->caseId . '/tracking/documents/send', ['document_id' => $document]);
        $this->assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'document-delivery'"));
    }

    public function testValidationCsrfPublicEscapingAndStatusHistoryFallback(): void
    {
        $number = $this->number();
        $fallback = $this->body($this->dispatch('GET', '/track', [], ['number' => $number]));
        $this->assertStringContainsString('MOFA processing', $fallback);
        foreach ([['title' => ['bad']], ['state' => 'invented'], ['step_date' => '2026-02-30'], ['sort_order' => '1.5']] as $invalid) {
            try { $this->post('/tracking/steps', array_replace($this->step('Valid step', 'pending', 1), $invalid)); $this->fail('Invalid step accepted.'); }
            catch (ValidationException) { $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM case_tracking_steps')); }
        }
        $this->post('/tracking/steps', $this->step('<script>alert(1)</script>', 'completed', 1) + ['public_description' => '<img src=x onerror=alert(1)>']);
        $public = $this->body($this->dispatch('GET', '/track', [], ['number' => $number]));
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $public);
        $this->assertStringNotContainsString('<img src=x onerror=', $public);
        $unknown = $this->body($this->dispatch('GET', '/track', [], ['number' => 'UNKNOWN-12345']));
        $this->assertStringNotContainsString('tracking-result-heading', $unknown);
        $request = new Request('POST', '/admin/cases/' . $this->caseId . '/tracking', [], [], [], $_SERVER, []);
        try { app(CsrfMiddleware::class)->handle($request, static fn (): Response => Response::html('bad')); $this->fail('Missing CSRF token accepted.'); }
        catch (HttpException $e) { $this->assertSame(419, $e->status()); }
    }

    public function testEveryStepQueuesEmailAndSmsAndMissingSettingsAreHonest(): void
    {
        $this->post('/tracking/steps', $this->step('Awaiting document check', 'pending', 1));
        $this->assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE template = 'tracking-update'"));
        $this->post('/tracking/steps', $this->step('Document check completed', 'completed', 2) + ['email_customer' => '1']);
        $this->assertSame(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'));
        $this->assertSame('logged', $this->db->fetchColumn("SELECT status FROM email_logs WHERE template = 'tracking-update'"));
        $this->assertStringContainsString('SMTP is not configured', $_SESSION['_flash']['error']);
        $this->assertStringNotContainsString('Email sent', $_SESSION['_flash']['success']);
    }

    private function step(string $title, string $state, int $order): array
    {
        return ['title' => $title, 'state' => $state, 'sort_order' => (string) $order, 'step_date' => '2026-11-11'];
    }

    private function post(string $suffix, array $body): Response
    {
        return $this->dispatch('POST', '/admin/cases/' . $this->caseId . $suffix, $body);
    }

    private function number(): string
    {
        return $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]);
    }

    private function pdf(): array
    {
        $path = storage_path('document-fixture.pdf');
        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0775, true); }
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n");
        return ['name' => 'Attested document.pdf', 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK];
    }

    private function asUser(int $id): void
    {
        $_SESSION = ['locale' => 'en', 'user_id' => $id];
        $this->resetContainer();
    }

    private function mailReady(): void
    {
        $property = new ReflectionProperty(Config::class, 'items');
        $items = $property->getValue();
        $items['mail']['host'] = '127.0.0.1';
        $items['mail']['from_address'] = 'fixture@example.test';
        $property->setValue(null, $items);
    }

    private function dispatch(string $method, string $path, array $body = [], array $query = [], array $files = []): Response
    {
        $router = new Router();
        (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request($method, $path, $query, $body, $files, ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app());
    }

    private function denied(string $method, string $path, array $body = [], array $query = [], array $files = [], int $status = 404): void
    {
        try { $this->dispatch($method, $path, $body, $query, $files); $this->fail('Unauthorized tracking access accepted.'); }
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
