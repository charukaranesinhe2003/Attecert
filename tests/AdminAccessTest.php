<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\ContactController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\DocumentController;
use App\Controllers\Admin\UserController;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\DocumentService;
use ReflectionProperty;

final class AdminAccessTest extends DatabaseTestCase
{
    public function testAdminCannotPromoteSelfOrEditSuperAdministrator(): void
    {
        $actor = $this->signInAsRole('admin');
        $this->assertForbidden(fn () => app(UserController::class)->update($this->post(['is_active' => '1', 'role' => 'super_admin']), (string) $actor));
        $this->assertForbidden(fn () => app(UserController::class)->store($this->post(['name' => 'Forbidden administrator', 'email' => 'forbidden@example.test', 'password' => 'Temporary-123!', 'role' => 'super_admin'])));
        $this->assertSame('admin', $this->roles($actor));
        $this->assertForbidden(fn () => app(UserController::class)->update($this->post(['is_active' => '0']), (string) $this->adminId));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT is_active FROM users WHERE id = ?', [$this->adminId]));
    }

    public function testStaffCreationRejectsInvalidRoleAndDuplicateEmailBeforeWrites(): void
    {
        $before = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users');
        foreach (['customer', 'missing-role'] as $role) {
            try {
                app(UserController::class)->store($this->post(['name' => 'New staff', 'email' => 'new@example.test', 'password' => 'Temporary-123!', 'role' => $role]));
                $this->fail('Invalid staff role was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('role', $exception->errors());
            }
        }
        $email = (string) $this->db->fetchColumn('SELECT email FROM users WHERE id = ?', [$this->adminId]);
        try {
            app(UserController::class)->store($this->post(['name' => 'New staff', 'email' => $email, 'password' => 'Temporary-123!', 'role' => 'manager']));
            $this->fail('Duplicate email was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayNotHasKey('password', $exception->input());
        }
        $this->assertSame($before, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users'));
    }

    public function testCreatingStaffProducesAccountRoleAndAudit(): void
    {
        app(UserController::class)->store($this->post(['name' => 'New staff', 'email' => 'NEW@example.test', 'password' => 'Temporary-123!', 'role' => 'manager']));
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', ['new@example.test']);
        $this->assertNotNull($user);
        $this->assertTrue(password_verify('Temporary-123!', $user['password_hash']));
        $this->assertSame('manager', $this->roles((int) $user['id']));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['staff_created', $user['id']]));
    }

    public function testRoutineStaffSavePreservesAllExistingRoles(): void
    {
        $actor = $this->signInAsRole('manager');
        $this->db->run('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$actor, 'processing_staff']);
        $_SESSION['user_id'] = $this->adminId;
        $this->resetContainer();
        app(UserController::class)->update($this->post(['is_active' => '1', 'role' => '']), (string) $actor);
        $this->assertSame('manager,processing_staff', $this->roles($actor));
    }

    public function testSelfDeactivationAndLastSuperAdministratorDemotionAreRejected(): void
    {
        foreach ([['is_active' => '0'], ['is_active' => '1', 'role' => 'admin']] as $data) {
            try {
                app(UserController::class)->update($this->post($data), (string) $this->adminId);
                $this->fail('Administrator lockout was accepted.');
            } catch (ValidationException) {
                $this->assertSame(1, (int) $this->db->fetchColumn('SELECT is_active FROM users WHERE id = ?', [$this->adminId]));
                $this->assertSame('super_admin', $this->roles($this->adminId));
            }
        }
    }

    public function testUncheckingCustomerActiveSuspendsLinkedLoginAndAudits(): void
    {
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]);
        app(CustomerController::class)->update($this->post(['name' => $customer['name']]), (string) $this->customerId);
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT is_active FROM customers WHERE id = ?', [$this->customerId]));
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT is_active FROM users WHERE id = ?', [$customer['user_id']]));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['customer_updated', $this->customerId]));
    }

    public function testCustomerEditingCannotChangeLinkedStaffAccount(): void
    {
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE code = ?', [$customer['user_id'], 'processing_staff']);
        $this->signInAsRole('customer_support');
        $this->assertForbidden(fn () => app(CustomerController::class)->update($this->post(['name' => 'Changed staff', 'is_active' => '0']), (string) $this->customerId));
        $this->assertSame($customer['name'], $this->db->fetchColumn('SELECT name FROM customers WHERE id = ?', [$this->customerId]));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT is_active FROM users WHERE id = ?', [$customer['user_id']]));
    }

    public function testCustomerDetailsHideUnauthorizedActionsAndFinance(): void
    {
        $this->signInAsRole('processing_staff');
        $html = $this->body(app(CustomerController::class)->show($this->get(), (string) $this->customerId));
        $this->assertStringNotContainsString('name="is_active"', $html);
        $this->assertStringNotContainsString('/admin/quotations/', $html);
        $this->assertStringNotContainsString('/admin/invoices/', $html);
        $this->assertStringNotContainsString('/admin/payments/', $html);
    }

    public function testCompletedDocumentReleaseIsIdempotentAuditedAndReversible(): void
    {
        $id = $this->document('completed');
        $service = app(DocumentService::class);
        $before = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM notifications');
        $this->assertTrue($service->setVisibility($id, true, $this->adminId));
        $this->assertFalse($service->setVisibility($id, true, $this->adminId));
        $this->assertSame($before + 1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM notifications'));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['document_released', $id]));
        $userId = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->assertFileExists($service->download($id, $this->customerId, $userId, false)['path']);
        $this->assertForbidden(fn () => $service->download($id, $this->customerId + 999999, $userId, false));
        $this->assertTrue($service->setVisibility($id, false, $this->adminId));
        $this->assertForbidden(fn () => $service->download($id, $this->customerId, $userId, false));
    }

    public function testReleaseRejectsMissingFileInternalCategoryAndUnconfirmedRequests(): void
    {
        $internal = $this->document('internal');
        try {
            app(DocumentService::class)->setVisibility($internal, true, $this->adminId);
            $this->fail('Internal document was released.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->status());
        }
        $id = $this->document('completed', false);
        try {
            app(DocumentService::class)->setVisibility($id, true, $this->adminId);
            $this->fail('Missing file was released.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->status());
        }
        $this->expectException(ValidationException::class);
        app(DocumentController::class)->visibility($this->post(['customer_visible' => '1']), (string) $id);
    }

    public function testReleaseRouteEnforcesExistingUploadPermission(): void
    {
        $id = $this->document('completed');
        $this->signInAsRole('finance_staff');
        $router = new Router();
        $routes = require base_path('routes/web.php');
        $routes($router);
        $this->assertForbidden(fn () => $router->dispatch($this->post(['customer_visible' => '1', 'confirmed' => '1'], '/admin/documents/' . $id . '/visibility'), app()));
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT customer_visible FROM documents WHERE id = ?', [$id]));
    }

    public function testArchiveHidesFileAndContactUpdatesVerifyTargetAndAudit(): void
    {
        $id = $this->document('completed');
        app(DocumentController::class)->archive($this->post(['confirmed' => '1']), (string) $id);
        $this->assertNotNull($this->db->fetchColumn('SELECT deleted_at FROM documents WHERE id = ?', [$id]));
        $messageId = $this->db->insert('INSERT INTO contact_messages (name, email, message, status) VALUES (?, ?, ?, ?)', ['Fixture', 'fixture@example.test', 'Support request', 'new']);
        app(ContactController::class)->status($this->post(['status' => 'closed']), (string) $messageId);
        $this->assertSame('closed', $this->db->fetchColumn('SELECT status FROM contact_messages WHERE id = ?', [$messageId]));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['contact_status_updated', $messageId]));
        $this->expectException(HttpException::class);
        app(ContactController::class)->status($this->post(['status' => 'closed']), '99999999');
    }

    private function document(string $category, bool $exists = true): int
    {
        $name = 'case-' . $this->caseId . '/' . bin2hex(random_bytes(8)) . '.pdf';
        if ($exists) {
            $dir = dirname(storage_path('documents/' . $name));
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents(storage_path('documents/' . $name), "%PDF-1.4\n%%EOF");
        }

        return $this->db->insert('INSERT INTO documents (case_id, category, original_name, stored_name, mime, size_bytes, customer_visible, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?)', [$this->caseId, $category, 'fixture.pdf', $name, 'application/pdf', 15, $this->adminId]);
    }

    private function roles(int $id): string
    {
        return implode(',', array_column($this->db->fetchAll('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.code', [$id]), 'code'));
    }

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Forbidden operation succeeded.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->status());
        }
    }

    private function body(Response $response): string
    {
        return (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }

    private function post(array $body, string $path = '/admin'): Request
    {
        return new Request('POST', $path, [], $body, [], ['REMOTE_ADDR' => '127.0.0.1'], []);
    }

    private function get(): Request
    {
        return new Request('GET', '/admin/customers', [], [], [], [], []);
    }
}
