<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Core\Container;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use ReflectionProperty;

final class StaffOperationsTest extends DatabaseTestCase
{
    public function testAdminCreatesStaffWithRoleAndTemporaryPassword(): void
    {
        $response = $this->dispatch('POST', '/admin/users', [
            'name' => 'Operations staff', 'email' => 'operations@example.test',
            'password' => 'Staff-Temporary-123!', 'role' => 'processing_staff',
        ]);
        $this->assertSame(302, $this->value($response, 'status'));
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', ['operations@example.test']);
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('Staff-Temporary-123!', $user['password_hash']));
        $this->assertSame(1, (int) $user['must_change_password']);
        $this->assertSame('processing_staff', $this->db->fetchColumn('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?', [$user['id']]));
        $this->assertStringContainsString('/password', $this->html($this->dispatch('GET', '/admin/users')));
        $this->assertStringContainsString('name="assigned_to"', $this->html($this->dispatch('GET', '/admin/operations')));
    }

    public function testEveryNonAdminRoleStartsWithEmptyCaseWorkAndCannotAccessGlobalAdministration(): void
    {
        $number = (string) $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]);
        foreach (['manager', 'processing_staff', 'finance_staff', 'content_editor', 'customer_support'] as $role) {
            $this->signInAsRole($role);
            foreach (['/admin', '/admin/cases', '/admin/quotations', '/admin/invoices', '/admin/payments', '/admin/quote-requests'] as $path) {
                $html = $this->html($this->dispatch('GET', $path));
                $this->assertStringNotContainsString($number, $html, $role . ' ' . $path);
                $this->assertStringNotContainsString('href="/admin/users"', $html);
                $this->assertStringNotContainsString('href="/admin/operations"', $html);
            }
            $this->assertSame([], $this->json($this->dispatch('GET', '/admin/search', [], ['q' => $number]))['results']);
            foreach (['/admin/documents', '/admin/processing', '/admin/mofa', '/admin/embassy', '/admin/users', '/admin/operations', '/admin/customers', '/admin/reports', '/admin/reports/export', '/admin/audit', '/admin/settings', '/admin/emails', '/admin/content', '/admin/contacts', '/admin/cases/create'] as $path) {
                $this->denied('GET', $path, [], 403);
            }
            $this->denied('GET', '/admin/cases/' . $this->caseId);
            $this->denied('POST', '/admin/cases', [], 403);
            $this->denied('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => '1'], 403);
        }
    }

    public function testAssignmentAllowsAllCaseWorkAndReassignmentImmediatelyRevokesAccess(): void
    {
        $staff = $this->signInAsRole('customer_support');
        $secondStaff = $this->signInAsRole('processing_staff');
        $this->asUser($this->adminId);
        $other = app(CaseService::class)->create(['customer_id' => $this->customerId, 'country_id' => null,
            'document_type_id' => null, 'document_title' => 'UNASSIGNED PRIVATE CASE'], [], $this->adminId);
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $staff]);
        $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        $this->asUser($staff);
        $html = $this->html($this->dispatch('GET', '/admin/cases/' . $this->caseId));
        $this->assertStringContainsString('id="finance"', $html);
        $this->assertStringContainsString('Add a payment manually', $html);
        $details = $this->html($this->dispatch('GET', '/admin/cases/' . $this->caseId . '/details'));
        $this->assertStringContainsString('name="internal_notes"', $details);
        $this->assertStringNotContainsString('name="assigned_to"', $html);
        $this->assertStringNotContainsString('UNASSIGNED PRIVATE CASE', $this->html($this->dispatch('GET', '/admin/cases', [], ['assigned_to' => $secondStaff])));
        $this->dispatch('POST', '/admin/cases/' . $this->caseId, ['document_title' => 'Edited by assigned staff', 'internal_notes' => 'Staff case note']);
        $this->assertSame('Staff case note', $this->db->fetchColumn('SELECT internal_notes FROM cases WHERE id = ?', [$this->caseId]));
        $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        $this->denied('POST', '/admin/cases/' . $this->caseId, ['document_title' => 'Bad reassignment', 'assigned_to' => $secondStaff], 403);
        $this->dispatch('POST', '/admin/cases/' . $this->caseId . '/mofa', ['status' => 'processing', 'notes' => 'Assigned processing']);
        $this->assertSame('Assigned processing', $this->db->fetchColumn('SELECT notes FROM mofa_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [$this->caseId]));
        $this->denied('POST', '/admin/cases/' . $other . '/status', ['status' => 'cancelled']);
        $this->denied('POST', '/admin/cases/' . $other . '/services');
        $this->denied('POST', '/admin/cases/' . $other . '/documents');
        $this->denied('POST', '/admin/cases/' . $other . '/quotation');
        $this->denied('POST', '/admin/cases/' . $other . '/embassy');
        $this->asUser($this->adminId);
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $secondStaff, 'previous_assigned_to' => $staff]);
        $this->asUser($staff);
        $this->denied('GET', '/admin/cases/' . $this->caseId);
        $this->denied('POST', '/admin/cases/' . $this->caseId, ['document_title' => 'Revoked edit']);
        $this->asUser($secondStaff);
        $this->assertStringContainsString('Edited by assigned staff', $this->html($this->dispatch('GET', '/admin/cases/' . $this->caseId)));
    }

    public function testFinancialRecordsFilesAndSearchAreScopedAndDirectLinksCannotBypassAssignment(): void
    {
        $quote = app(QuotationService::class)->save($this->caseId, ['lines' => [
            ['service_id' => null, 'description' => 'PRIVATE-FINANCE-FIXTURE', 'quantity' => 1, 'unit_price' => '200.00'],
        ]], $this->adminId, true, true);
        $invoice = app(InvoiceService::class)->createFromQuotation($quote, $this->adminId);
        $payment = app(PaymentService::class)->record($invoice, ['amount' => '25.00', 'method' => 'cash', 'paid_at' => date('Y-m-d')], $this->adminId);
        $document = $this->db->insert("INSERT INTO documents (case_id, category, subcategory, original_name, stored_name, mime, size_bytes, uploaded_by)
            VALUES (?, 'internal', 'other', 'PRIVATE-DOCUMENT.pdf', 'fixture.pdf', 'application/pdf', 10, ?)", [$this->caseId, $this->adminId]);
        $staff = $this->signInAsRole('finance_staff');
        foreach (['/admin/quotations/' . $quote, '/admin/quotations/' . $quote . '/pdf', '/admin/invoices/' . $invoice,
            '/admin/invoices/' . $invoice . '/pdf', '/admin/payments/' . $payment . '/receipt', '/admin/documents/' . $document . '/download'] as $path) {
            $this->denied('GET', $path);
        }
        foreach (['/admin/quotations/' . $quote . '/send', '/admin/quotations/' . $quote . '/invoice', '/admin/invoices/' . $invoice . '/send',
            '/admin/invoices/' . $invoice . '/cancel', '/admin/invoices/' . $invoice . '/payments', '/admin/documents/' . $document . '/archive',
            '/admin/documents/' . $document . '/visibility'] as $path) {
            $this->denied('POST', $path);
        }
        $number = (string) $this->db->fetchColumn('SELECT number FROM quotations WHERE id = ?', [$quote]);
        $this->assertStringNotContainsString($number, $this->html($this->dispatch('GET', '/admin/quotations')));
        $this->denied('GET', '/admin/documents', [], 403);
        $this->assertSame([], $this->json($this->dispatch('GET', '/admin/search', [], ['q' => $number]))['results']);
        $this->asUser($this->adminId);
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $staff]);
        $this->asUser($staff);
        $this->assertStringContainsString($number, $this->html($this->dispatch('GET', '/admin/quotations')));
        $this->assertStringContainsString('PRIVATE-DOCUMENT.pdf', $this->html($this->dispatch('GET', '/admin/cases/' . $this->caseId)));
        $this->assertNotSame([], $this->json($this->dispatch('GET', '/admin/search', [], ['q' => $number]))['results']);
        $this->assertStringContainsString('PRIVATE-FINANCE-FIXTURE', $this->html($this->dispatch('GET', '/admin/quotations/' . $quote)));
        $this->dispatch('POST', '/admin/invoices/' . $invoice . '/payments', ['amount' => '25.00', 'method' => 'cash', 'paid_at' => date('Y-m-d')]);
        $this->assertSame('50.00', $this->db->fetchColumn('SELECT amount_paid FROM invoices WHERE id = ?', [$invoice]));
    }

    public function testPasswordResetRevokesOldSessionsAndTokensWithoutDisclosingPasswords(): void
    {
        $staff = $this->signInAsRole('processing_staff');
        $oldSession = $_SESSION;
        $email = $this->db->fetchColumn('SELECT email FROM users WHERE id = ?', [$staff]);
        $token = bin2hex(random_bytes(32));
        $this->db->insert("INSERT INTO password_resets (email, purpose, token_hash, expires_at) VALUES (?, 'reset', ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))", [$email, hash('sha256', $token)]);
        $this->asUser($this->adminId);
        $this->dispatch('POST', '/admin/users/' . $staff . '/password', ['password' => ' New-Temporary-123! ', 'password_confirmation' => ' New-Temporary-123! ']);
        $user = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$staff]);
        $this->assertTrue(password_verify(' New-Temporary-123! ', $user['password_hash']));
        $this->assertSame(1, (int) $user['must_change_password']);
        $this->assertFalse(app(AuthService::class)->resetPassword($token, 'Stale-Token-123!'));
        $this->assertStringNotContainsString('New-Temporary', json_encode($_SESSION));
        $this->assertStringNotContainsString('New-Temporary', (string) $this->db->fetchColumn("SELECT metadata FROM audit_logs WHERE action = 'staff_password_changed' AND entity_id = ?", [$staff]));
        $_SESSION = $oldSession;
        $container = new Container();
        $container->instance(Database::class, $this->db);
        $GLOBALS['app_container'] = $container;
        $this->assertFalse(app(AuthService::class)->check());
        $this->asUser($staff);
        $response = $this->dispatch('GET', '/admin/cases');
        $this->assertSame('/admin/password', $this->json($response)['redirect']);
    }

    public function testDeleteUnassignsCasesRemovesLoginAndProtectsAdminAndCustomerAccounts(): void
    {
        $staff = $this->signInAsRole('processing_staff');
        $this->asUser($this->adminId);
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $staff]);
        $this->dispatch('POST', '/admin/users/' . $staff . '/delete', ['confirmed' => '1']);
        $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        $this->assertNotNull($this->db->fetchColumn('SELECT deleted_at FROM users WHERE id = ?', [$staff]));
        $this->assertNotNull(app(CaseService::class)->find($this->caseId));
        $this->asUser($staff);
        $this->assertFalse(app(AuthService::class)->check());
        $this->asUser($this->adminId);
        try {
            $this->dispatch('POST', '/admin/users/' . $this->adminId . '/delete', ['confirmed' => '1']);
            $this->fail('Self deletion accepted.');
        } catch (ValidationException) {
            $this->assertNull($this->db->fetchColumn('SELECT deleted_at FROM users WHERE id = ?', [$this->adminId]));
        }
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->denied('POST', '/admin/users/' . $customerUser . '/delete', ['confirmed' => '1'], 403);
        $this->signInAsRole('admin');
        $this->denied('POST', '/admin/users/' . $this->adminId . '/delete', ['confirmed' => '1'], 403);
        $this->denied('POST', '/admin/users/' . $this->adminId . '/password', ['password' => 'Protected-123!', 'password_confirmation' => 'Protected-123!'], 403);
    }

    public function testInvalidAssigneesAndStaleAssignmentsAreRejected(): void
    {
        $staff = $this->signInAsRole('processing_staff');
        $this->asUser($this->adminId);
        $customerUser = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        foreach ([$customerUser, 99999999, '1.5', ['bad']] as $invalid) {
            try {
                $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $invalid]);
                $this->fail('Invalid assignment accepted.');
            } catch (ValidationException) {
                $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
            }
        }
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => $staff]);
        try {
            $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => '']);
            $this->fail('Stale assignment accepted.');
        } catch (ValidationException) {
            $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
        }
        $this->dispatch('POST', '/admin/operations/' . $this->caseId . '/assign', ['assigned_to' => '', 'previous_assigned_to' => $staff]);
        $this->assertNull($this->db->fetchColumn('SELECT assigned_to FROM cases WHERE id = ?', [$this->caseId]));
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
        try {
            $this->dispatch($method, $path, $body);
            $this->fail('Unauthorized access accepted: ' . $path);
        } catch (HttpException $e) {
            $this->assertSame($status, $e->status(), $path);
        }
    }

    private function html(Response $response): string
    {
        $this->assertSame(200, $this->value($response, 'status'));
        return $this->value($response, 'body');
    }

    private function value(Response $response, string $name): mixed
    {
        return (new ReflectionProperty(Response::class, $name))->getValue($response);
    }

    private function json(Response $response): array
    {
        return json_decode($this->value($response, 'body'), true, 512, JSON_THROW_ON_ERROR);
    }
}
