<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\AuthService;
use App\Services\CaseService;
use ReflectionProperty;

final class QuoteConversionAccountTest extends DatabaseTestCase
{
    public function testDeletedStaffEmailConvertsWithoutRestoringStaffOrCreatingDuplicateLogin(): void
    {
        $staff = $this->signInAsRole('customer_support');
        $email = $this->db->fetchColumn('SELECT email FROM users WHERE id = ?', [$staff]);
        $oldSession = $_SESSION;
        $this->db->run('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?', [$staff]);
        $original = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$staff]);
        $roles = $this->roles($staff);
        $this->asAdmin();
        $id = $this->requestRow($email);
        $response = $this->convert($id);
        $case = $this->db->fetch('SELECT * FROM cases WHERE quote_request_id = ?', [$id]);
        $this->assertNotNull($case);
        $this->assertSame('/admin/cases/' . $case['id'], (new ReflectionProperty(Response::class, 'headers'))->getValue($response)['Location']);
        $contact = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$case['customer_id']]);
        $this->assertNull($contact['user_id']);
        $this->assertSame($email, $contact['email']);
        $this->assertSame($original, $this->db->fetch('SELECT * FROM users WHERE id = ?', [$staff]));
        $this->assertSame($roles, $this->roles($staff));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users WHERE email = ?', [$email]));
        $this->assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE recipient = ? AND template = 'welcome'", [$email]));
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE related_type = 'case' AND related_id = ? AND template = 'case-opened'", [$case['id']]);
        $body = file_get_contents(storage_path('mail/' . $mail['body_path']));
        $this->assertStringContainsString('/track?number=', $body);
        $this->assertStringNotContainsString('/login', $body);
        $this->convert($id);
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases WHERE quote_request_id = ?', [$id]));
        $_SESSION = $oldSession;
        $this->resetContainer(false);
        $this->assertFalse(app(AuthService::class)->check());
    }

    public function testExistingActiveAndInactiveLoginsKeepTheirCredentialsRolesAndStatus(): void
    {
        foreach ([1, 0] as $active) {
            $staff = $this->signInAsRole('processing_staff');
            $this->db->run('UPDATE users SET is_active = ? WHERE id = ?', [$active, $staff]);
            $original = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$staff]);
            $roles = $this->roles($staff);
            $this->asAdmin();
            $id = $this->requestRow($original['email']);
            $caseId = app(CaseService::class)->createFromQuote($id, $this->adminId);
            $this->assertSame($staff, (int) $this->db->fetchColumn('SELECT cu.user_id FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id WHERE c.id = ?', [$caseId]));
            $this->assertSame($original, $this->db->fetch('SELECT * FROM users WHERE id = ?', [$staff]));
            $this->assertSame($roles, $this->roles($staff));
        }
    }

    public function testArchivedCustomerProfileStaysArchivedWithoutDuplicateUserLink(): void
    {
        $original = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]);
        $this->db->run('UPDATE customers SET is_active = 0, deleted_at = NOW() WHERE id = ?', [$this->customerId]);
        $archived = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]);
        $id = $this->requestRow($original['email']);
        $caseId = app(CaseService::class)->createFromQuote($id, $this->adminId);
        $new = $this->db->fetch('SELECT cu.* FROM customers cu INNER JOIN cases c ON c.customer_id = cu.id WHERE c.id = ?', [$caseId]);
        $this->assertNotSame($this->customerId, (int) $new['id']);
        $this->assertNull($new['user_id']);
        $this->assertSame($archived, $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]));
        $anotherId = $this->requestRow($original['email']);
        $anotherCaseId = app(CaseService::class)->createFromQuote($anotherId, $this->adminId);
        $this->assertSame((int) $new['id'], (int) $this->db->fetchColumn('SELECT customer_id FROM cases WHERE id = ?', [$anotherCaseId]));
        $this->assertSame(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customers WHERE email = ?', [$original['email']]));
    }

    public function testFreshEmailCreatesOneCustomerLoginAndRepeatedRequestsReuseIt(): void
    {
        $email = 'fresh-conversion@example.test';
        $first = $this->requestRow(strtoupper($email));
        $second = $this->requestRow($email);
        $firstCase = app(CaseService::class)->createFromQuote($first, $this->adminId);
        $secondCase = app(CaseService::class)->createFromQuote($second, $this->adminId);
        $this->assertSame($this->db->fetchColumn('SELECT customer_id FROM cases WHERE id = ?', [$firstCase]), $this->db->fetchColumn('SELECT customer_id FROM cases WHERE id = ?', [$secondCase]));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM users WHERE email = ?', [$email]));
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ?', [$email]);
        $this->assertNull($user['deleted_at']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertSame(['customer'], $this->roles((int) $user['id']));
        $this->assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM email_logs WHERE recipient = ? AND template = 'welcome'", [$email]));
    }

    public function testExistingCustomerIsReusedWithoutTouchingArchivedLinkedLogin(): void
    {
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$this->customerId]);
        $this->db->run('UPDATE users SET is_active = 0, deleted_at = NOW() WHERE id = ?', [$customer['user_id']]);
        $original = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$customer['user_id']]);
        $id = $this->requestRow($customer['email']);
        $caseId = app(CaseService::class)->createFromQuote($id, $this->adminId);
        $this->assertSame($this->customerId, (int) $this->db->fetchColumn('SELECT customer_id FROM cases WHERE id = ?', [$caseId]));
        $this->assertSame($original, $this->db->fetch('SELECT * FROM users WHERE id = ?', [$customer['user_id']]));
    }

    private function requestRow(string $email): int
    {
        return $this->db->insert('INSERT INTO quote_requests (name, email, phone, message) VALUES (?, ?, ?, ?)', ['Conversion contact', $email, '0771234567', 'Document request']);
    }

    private function asAdmin(): void
    {
        $_SESSION = ['locale' => 'en', 'user_id' => $this->adminId];
        $this->resetContainer();
    }

    private function roles(int $id): array
    {
        return array_column($this->db->fetchAll('SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ? ORDER BY r.code', [$id]), 'code');
    }

    private function convert(int $id): Response
    {
        $router = new Router();
        (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request('POST', '/admin/quote-requests/' . $id . '/convert', [], [], [], ['REMOTE_ADDR' => '127.0.0.1'], []), app());
    }
}
