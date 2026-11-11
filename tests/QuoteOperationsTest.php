<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use ReflectionProperty;

final class QuoteOperationsTest extends DatabaseTestCase
{
    public function testOperationsHasQuoteTabAndSearchAndAssignmentFilters(): void
    {
        $id = $this->quote('Operations enquiry');
        $this->assertStringContainsString('type=quote_requests', $this->body($this->dispatch('GET', '/admin/operations')));
        $html = $this->body($this->dispatch('GET', '/admin/operations', [], ['type' => 'quote_requests', 'assignee' => 'unassigned', 'q' => 'Operations enquiry']));
        $this->assertStringContainsString('/admin/operations/quote-requests/' . $id . '/assign', $html);
        $this->assertStringContainsString('1 matching quote requests', $html);
        $this->assertStringContainsString('name="type" value="quote_requests"', $html);
        $this->assertStringNotContainsString('Operations enquiry', $this->body($this->dispatch('GET', '/admin/operations', [], ['type' => 'quote_requests', 'q' => 'no-match'])));
    }

    public function testAssignedRequestsStatusSearchAndAttachmentsArePrivateAndReassignmentRevokesAccess(): void
    {
        $id = $this->quote('Private assigned enquiry');
        $other = $this->quote('Other private enquiry');
        $doc = $this->document($id, 'assigned-request.pdf');
        $otherDoc = $this->document($other, 'other-request.pdf');
        $first = $this->signInAsRole('processing_staff');
        $second = $this->signInAsRole('customer_support');
        $this->asUser($first);
        $this->denied('GET', '/admin/quote-requests/' . $id);
        $this->denied('POST', '/admin/quote-requests/' . $id . '/status', ['status' => 'closed']);
        $this->denied('POST', '/admin/quote-requests/' . $id . '/convert');
        $this->denied('GET', '/admin/quote-requests/' . $id . '/documents/' . $doc . '/download');
        $this->assertStringNotContainsString('Private assigned enquiry', $this->body($this->dispatch('GET', '/admin/quote-requests')));
        $this->asUser($this->adminId);
        $this->assign($id, $first);
        $this->asUser($first);
        $html = $this->body($this->dispatch('GET', '/admin/quote-requests'));
        $this->assertStringContainsString('Private assigned enquiry', $html);
        $this->assertStringNotContainsString('Other private enquiry', $html);
        $this->assertStringNotContainsString('href="/admin/contacts"', $html);
        $this->assertStringContainsString('assigned-request.pdf', $this->body($this->dispatch('GET', '/admin/quote-requests/' . $id)));
        $file = $this->dispatch('GET', '/admin/quote-requests/' . $id . '/documents/' . $doc . '/download');
        $this->assertSame('1', $this->value($file, 'headers')['__file']);
        $this->denied('GET', '/admin/quote-requests/' . $id . '/documents/' . $otherDoc . '/download');
        $this->dispatch('POST', '/admin/quote-requests/' . $id . '/status', ['status' => 'contacted']);
        $this->assertSame('contacted', $this->db->fetchColumn('SELECT status FROM quote_requests WHERE id = ?', [$id]));
        $search = json_decode($this->body($this->dispatch('GET', '/admin/search', [], ['q' => 'Private assigned enquiry'])), true);
        $this->assertSame('Quote requests', $search['results'][0]['label']);
        $this->denied('POST', '/admin/operations/quote-requests/' . $id . '/assign', ['assigned_to' => $second], 403);
        $this->asUser($this->adminId);
        $this->assign($id, $second, $first);
        $this->asUser($first);
        $this->denied('GET', '/admin/quote-requests/' . $id);
        $this->denied('GET', '/admin/quote-requests/' . $id . '/documents/' . $doc . '/download');
        $this->assertStringNotContainsString('Private assigned enquiry', $this->body($this->dispatch('GET', '/admin/quote-requests')));
        $this->asUser($second);
        $this->assertStringContainsString('Private assigned enquiry', $this->body($this->dispatch('GET', '/admin/quote-requests/' . $id)));
    }

    public function testNewConvertedCaseInheritsAssigneeAndConversionIsIdempotent(): void
    {
        $id = $this->quote('Convert assigned enquiry');
        $doc = $this->document($id, 'convert-request.pdf');
        $staff = $this->signInAsRole('finance_staff');
        $this->asUser($this->adminId);
        $this->assign($id, $staff);
        $this->asUser($staff);
        $response = $this->dispatch('POST', '/admin/quote-requests/' . $id . '/convert');
        $caseId = (int) $this->db->fetchColumn('SELECT converted_case_id FROM quote_requests WHERE id = ?', [$id]);
        $this->assertGreaterThan(0, $caseId);
        $this->assertSame('/admin/cases/' . $caseId, $this->value($response, 'headers')['Location']);
        $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$caseId]));
        $this->assertSame($caseId, (int) $this->db->fetchColumn('SELECT case_id FROM documents WHERE id = ?', [$doc]));
        $this->assertStringContainsString('Convert assigned enquiry', $this->body($this->dispatch('GET', '/admin/cases/' . $caseId . '/details')));
        $this->dispatch('POST', '/admin/quote-requests/' . $id . '/convert');
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases WHERE quote_request_id = ?', [$id]));
    }

    public function testExistingCaseAssignmentIsIndependentFromRequestAssignment(): void
    {
        $id = $this->quote('Already converted enquiry');
        $this->db->run("UPDATE quote_requests SET converted_case_id = ?, status = 'converted' WHERE id = ?", [$this->caseId, $id]);
        $staff = $this->signInAsRole('processing_staff');
        $this->asUser($this->adminId);
        $this->assign($id, $staff);
        $this->asUser($staff);
        $html = $this->body($this->dispatch('GET', '/admin/quote-requests/' . $id));
        $this->assertStringContainsString('Case access is assigned separately', $html);
        $this->assertStringNotContainsString('href="/admin/cases/' . $this->caseId . '"', $html);
        $response = $this->dispatch('POST', '/admin/quote-requests/' . $id . '/convert');
        $this->assertSame('/admin/quote-requests/' . $id, $this->value($response, 'headers')['Location']);
        $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        $this->denied('GET', '/admin/cases/' . $this->caseId);
    }

    public function testInvalidAndStaleAssignmentsUnassignmentAndStaffDeletion(): void
    {
        $id = $this->quote('Assignment checks enquiry');
        $staff = $this->signInAsRole('processing_staff');
        $inactive = $this->signInAsRole('customer_support');
        $this->db->run('UPDATE users SET is_active = 0 WHERE id = ?', [$inactive]);
        $this->asUser($this->adminId);
        $customer = $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        foreach ([$customer, $inactive, 999999999, '1.5', ['invalid']] as $invalid) {
            try {
                $this->assign($id, $invalid);
                $this->fail('Invalid assignee accepted.');
            } catch (ValidationException) {
                $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM quote_requests WHERE id = ?', [$id]));
            }
        }
        $this->assign($id, $staff);
        try {
            $this->assign($id, '');
            $this->fail('Stale assignment accepted.');
        } catch (ValidationException) {
            $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM quote_requests WHERE id = ?', [$id]));
        }
        $this->assign($id, '', $staff);
        $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM quote_requests WHERE id = ?', [$id]));
        $this->assign($id, $staff);
        $this->dispatch('POST', '/admin/users/' . $staff . '/delete', ['confirmed' => '1']);
        $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM quote_requests WHERE id = ?', [$id]));
        $this->assertStringContainsString('Assignment checks enquiry', $this->body($this->dispatch('GET', '/admin/operations', [], ['type' => 'quote_requests', 'assignee' => 'unassigned'])));
    }

    private function quote(string $name): int
    {
        return $this->db->insert('INSERT INTO quote_requests (name, email, message) VALUES (?, ?, ?)', [$name, 'customer@attecert.local', $name]);
    }

    private function document(int $quoteId, string $name): int
    {
        if (!is_dir(storage_path('documents'))) { mkdir(storage_path('documents'), 0775, true); }
        file_put_contents(storage_path('documents/' . $name), '%PDF fixture');
        return $this->db->insert("INSERT INTO documents (quote_request_id, category, original_name, stored_name, mime, size_bytes) VALUES (?, 'customer', ?, ?, 'application/pdf', 12)", [$quoteId, $name, $name]);
    }

    private function assign(int $id, mixed $staff, mixed $previous = ''): Response
    {
        return $this->dispatch('POST', '/admin/operations/quote-requests/' . $id . '/assign', ['assigned_to' => $staff, 'previous_assigned_to' => $previous]);
    }

    private function asUser(int $id): void
    {
        $_SESSION = ['locale' => 'en', 'user_id' => $id];
        $this->resetContainer();
    }

    private function dispatch(string $method, string $path, array $body = [], array $query = []): Response
    {
        $router = new Router();
        (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request($method, $path, $query, $body, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app());
    }

    private function denied(string $method, string $path, array $body = [], int $status = 404): void
    {
        try { $this->dispatch($method, $path, $body); $this->fail('Unauthorized request accepted.'); }
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
