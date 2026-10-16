<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\SecurityController;
use App\Controllers\AuthController;
use App\Core\HttpException;
use App\Core\Container;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Services\AuthService;
use ReflectionProperty;

final class StaffSecurityTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        parent::tearDown();
    }

    public function testTemporaryPasswordBlocksStaffAndCustomerAreasAndJsonWrites(): void
    {
        $this->requirePasswordChange();
        foreach (['/admin', '/admin/cases', '/portal/profile'] as $path) {
            $response = $this->router()->dispatch($this->request('GET', $path), app());
            $this->assertSame('/admin/password', $this->property($response, 'headers')['Location']);
        }
        $before = $this->db->fetchColumn('SELECT status_id FROM cases WHERE id = ?', [$this->caseId]);
        $response = $this->router()->dispatch($this->request('POST', '/admin/cases/' . $this->caseId . '/status', ['status' => 'cancelled'], true), app());
        $this->assertSame(403, $this->property($response, 'status'));
        $this->assertSame('password_change_required', $this->json($response)['error']);
        $this->assertSame('/admin/password', $this->json($response)['redirect']);
        $this->assertSame($before, $this->db->fetchColumn('SELECT status_id FROM cases WHERE id = ?', [$this->caseId]));
    }

    public function testRequiredFormHasNoBlockedNavigationAndLogoutRemainsAvailable(): void
    {
        $this->requirePasswordChange();
        $response = $this->router()->dispatch($this->request('GET', '/admin/password'), app());
        $html = $this->property($response, 'body');
        $this->assertSame(200, $this->property($response, 'status'));
        $this->assertStringContainsString('action="/admin/password"', $html);
        $this->assertStringContainsString('action="/logout"', $html);
        $this->assertStringNotContainsString('action="/admin/search"', $html);
        $this->assertStringNotContainsString('href="/admin/cases', $html);
        $response = app(AuthMiddleware::class)->handle($this->request('POST', '/logout'), static fn (): Response => Response::json(['allowed' => true]));
        $this->assertTrue($this->json($response)['allowed']);
    }

    public function testEveryStaffRoleCanChangeOwnPasswordWithoutManagementPermission(): void
    {
        foreach (['content_editor', 'processing_staff', 'finance_staff', 'customer_support'] as $role) {
            $this->signInAsRole($role);
            $response = $this->router()->dispatch($this->request('GET', '/admin/password'), app());
            $this->assertSame(200, $this->property($response, 'status'));
        }
        $this->signInAsRole('customer');
        try {
            $this->router()->dispatch($this->request('GET', '/admin/password'), app());
            $this->fail('Customer reached the staff password screen.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->status());
        }
        unset($_SESSION['user_id']);
        $this->resetContainer();
        $response = $this->router()->dispatch($this->request('GET', '/admin/password'), app());
        $this->assertSame('/login', $this->property($response, 'headers')['Location']);
    }

    public function testForcedChangeClearsFlagInvalidatesResetTokensAndRotatesSessionAndCsrf(): void
    {
        $this->requirePasswordChange();
        $this->startSession();
        $oldSession = session_id();
        $oldCsrf = csrf_token();
        $token = $this->resetToken($this->adminId);
        $response = $this->router()->dispatch($this->request('POST', '/admin/password', $this->passwordInput(), true), app());
        $this->assertSame(200, $this->property($response, 'status'));
        $this->assertTrue($this->json($response)['ok']);
        $this->assertSame('/admin', $this->json($response)['redirect']);
        $this->assertNotSame($oldSession, session_id());
        $this->assertNotSame($oldCsrf, csrf_token());
        $this->assertSame(csrf_token(), $this->json($response)['csrf_token']);
        $this->assertSame($this->adminId, $_SESSION['user_id']);
        $this->assertFalse(app(AuthService::class)->mustChangePassword());
        $this->assertSame('/admin', app(AuthService::class)->homePath());
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT must_change_password FROM users WHERE id = ?', [$this->adminId]));
        $hash = $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]);
        $this->assertTrue(password_verify('Staff-New-123!', $hash));
        $this->assertFalse(password_verify('Admin@12345', $hash));
        $this->assertFalse(app(AuthService::class)->resetPassword($token, 'Old-Token-123!'));
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['password_changed', $this->adminId]));
        $this->assertSame(200, $this->property($this->router()->dispatch($this->request('GET', '/admin'), app()), 'status'));
        try {
            app(CsrfMiddleware::class)->handle($this->request('POST', '/admin/password', ['_token' => $oldCsrf]), static fn (): Response => Response::json(['bad' => true]));
            $this->fail('The pre-change CSRF token was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(419, $exception->status());
        }
    }

    public function testVoluntaryChangePreservesWhitespaceAndDoesNotChangeAnotherAccount(): void
    {
        $id = $this->signInAsRole('content_editor');
        $this->startSession();
        $adminHash = $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]);
        $next = ' Staff-New-123! ';
        $response = $this->router()->dispatch($this->request('POST', '/admin/password', [
            'current_password' => 'Fixture-Only-123!', 'password' => $next, 'password_confirmation' => $next, 'user_id' => $this->adminId,
        ]), app());
        $this->assertSame('/admin', $this->property($response, 'headers')['Location']);
        $hash = $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$id]);
        $this->assertTrue(password_verify($next, $hash));
        $this->assertFalse(password_verify(trim($next), $hash));
        $this->assertSame($adminHash, $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]));
        $email = $this->db->fetchColumn('SELECT email FROM users WHERE id = ?', [$id]);
        $response = app(AuthController::class)->login($this->request('POST', '/login', ['email' => $email, 'password' => $next]));
        $this->assertSame('/admin', $this->property($response, 'headers')['Location']);
    }

    public function testInvalidChangeKeepsAccountGatedAndSecretsOutOfResponseAndSession(): void
    {
        $this->requirePasswordChange();
        $hash = $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]);
        foreach ([
            ['current_password' => 'Wrong-Password-123!'],
            ['password_confirmation' => 'Does-Not-Match-123!'],
            ['password' => 'short', 'password_confirmation' => 'short'],
            ['password' => str_repeat('a', 201), 'password_confirmation' => str_repeat('a', 201)],
            ['password' => ['array']],
            ['current_password' => ''],
            ['password' => 'Admin@12345', 'password_confirmation' => 'Admin@12345'],
        ] as $invalid) {
            $response = app(SecurityController::class)->update($this->request('POST', '/admin/password', array_replace($this->passwordInput(), $invalid)));
            $this->assertSame(422, $this->property($response, 'status'));
            $html = $this->property($response, 'body');
            $this->assertStringContainsString('aria-invalid="true"', $html);
            $this->assertStringNotContainsString('Staff-New-123!', $html);
            $this->assertStringNotContainsString('Admin@12345', $html);
            $this->assertStringNotContainsString('Wrong-Password-123!', $html);
            $this->assertArrayNotHasKey('_old', $_SESSION);
            $this->assertArrayNotHasKey('_errors', $_SESSION);
            $this->assertTrue(app(AuthService::class)->mustChangePassword());
        }
        $this->assertSame($hash, $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]));
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['password_changed', $this->adminId]));
    }

    public function testValidationJsonHasFieldErrorsAndPasswordRouteRetainsCsrfProtection(): void
    {
        $this->requirePasswordChange();
        $response = app(SecurityController::class)->update($this->request('POST', '/admin/password', ['password' => []], true));
        $this->assertSame(422, $this->property($response, 'status'));
        $this->assertArrayHasKey('password', $this->json($response)['errors']);
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Session expired');
        app(CsrfMiddleware::class)->handle($this->request('POST', '/admin/password', $this->passwordInput()), fn (Request $request): Response => $this->router()->dispatch($request, app()));
    }

    public function testSuccessfulResetClearsTemporaryFlagAndCannotBeReplayed(): void
    {
        $this->requirePasswordChange();
        $auth = app(AuthService::class);
        $this->assertTrue($auth->mustChangePassword());
        $token = $this->resetToken($this->adminId);
        $other = $this->resetToken($this->adminId);
        $this->assertTrue($auth->resetPassword($token, 'Reset-Staff-123!'));
        $this->assertFalse($auth->mustChangePassword());
        $this->assertTrue(password_verify('Reset-Staff-123!', $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId])));
        $this->assertFalse($auth->resetPassword($token, 'Replay-Staff-123!'));
        $this->assertFalse($auth->resetPassword($other, 'Other-Staff-123!'));
    }

    public function testInvalidResetDoesNotClearTemporaryFlag(): void
    {
        $this->requirePasswordChange();
        $token = $this->resetToken($this->adminId);
        $this->db->run('UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token_hash = ?', [hash('sha256', $token)]);
        $this->assertFalse(app(AuthService::class)->resetPassword($token, 'Never-Used-123!'));
        $this->assertTrue(app(AuthService::class)->mustChangePassword());
    }

    public function testLoginCannotUseIntendedPathToSkipTemporaryPasswordChange(): void
    {
        $this->requirePasswordChange();
        $this->startSession();
        $_SESSION['_intended'] = '/admin/cases';
        $response = app(AuthController::class)->login($this->request('POST', '/login', ['email' => 'admin@attecert.local', 'password' => 'Admin@12345']));
        $this->assertSame('/admin/password', $this->property($response, 'headers')['Location']);
        $this->assertArrayNotHasKey('_intended', $_SESSION);
    }

    public function testLoginFallsBackFromUnsafeOrLoopingIntendedPaths(): void
    {
        $this->startSession();
        foreach (['https://outside.example.test', '//outside.example.test', '/\\outside.example.test', "/admin\r\nX-Test: 1", '/login'] as $target) {
            $_SESSION['_intended'] = $target;
            $response = app(AuthController::class)->login($this->request('POST', '/login', ['email' => 'admin@attecert.local', 'password' => 'Admin@12345']));
            $this->assertSame('/admin', $this->property($response, 'headers')['Location']);
        }
    }

    public function testLongStaffPasswordsVerifyTheirEntireValue(): void
    {
        $this->requirePasswordChange();
        $chosen = str_repeat('Long-Password-', 7) . 'Selected tail';
        $different = str_repeat('Long-Password-', 7) . 'Different tail';
        $response = app(SecurityController::class)->update($this->request('POST', '/admin/password', [
            'current_password' => 'Admin@12345', 'password' => $chosen, 'password_confirmation' => $chosen,
        ], true));
        $this->assertSame(200, $this->property($response, 'status'));
        $hash = $this->db->fetchColumn('SELECT password_hash FROM users WHERE id = ?', [$this->adminId]);
        $this->assertTrue(password_verify($chosen, $hash));
        $this->assertFalse(password_verify($different, $hash));
        $this->assertFalse(password_verify(substr($chosen, 0, 72), $hash));
    }

    public function testPasswordChangeKeepsRenewedSessionAndRejectsAnotherTemporarySession(): void
    {
        $this->requirePasswordChange();
        $otherSession = $_SESSION;
        $response = app(SecurityController::class)->update($this->request('POST', '/admin/password', $this->passwordInput(), true));
        $this->assertTrue($this->json($response)['ok']);
        $renewedSession = $_SESSION;
        $this->assertNotSame($otherSession['_password_fingerprint'], $renewedSession['_password_fingerprint']);

        $_SESSION = $otherSession;
        $this->newRequestContainer();
        $response = $this->router()->dispatch($this->request('GET', '/admin', [], true), app());
        $this->assertSame(401, $this->property($response, 'status'));
        $this->assertSame('authentication_required', $this->json($response)['error']);
        $this->assertArrayNotHasKey('user_id', $_SESSION);

        $_SESSION = $renewedSession;
        $this->newRequestContainer();
        $this->assertTrue(app(AuthService::class)->check());
        $this->assertFalse(app(AuthService::class)->mustChangePassword());
        $response = $this->router()->dispatch($this->request('GET', '/admin'), app());
        $this->assertSame(200, $this->property($response, 'status'));
    }

    public function testPasswordResetRevokesEveryExistingSessionAndLegacySessionsRequireLogin(): void
    {
        $this->requirePasswordChange();
        $sessionA = $_SESSION;
        $sessionB = $_SESSION;
        $sessionB['_csrf'] = bin2hex(random_bytes(32));
        $token = $this->resetToken($this->adminId);
        $this->assertTrue(app(AuthService::class)->resetPassword($token, 'Reset-Sessions-123!'));
        foreach ([$sessionA, $sessionB, ['locale' => 'en', 'user_id' => $this->adminId]] as $oldSession) {
            $_SESSION = $oldSession;
            $this->newRequestContainer();
            $this->assertFalse(app(AuthService::class)->check());
            $this->assertArrayNotHasKey('user_id', $_SESSION);
        }
    }

    private function newRequestContainer(): void
    {
        // Deliberately preserve the simulated session version; fixture login helpers must not refresh it.
        $container = new Container();
        $container->instance(Database::class, $this->db);
        $GLOBALS['app_container'] = $container;
    }

    private function requirePasswordChange(): void
    {
        $this->db->run('UPDATE users SET must_change_password = 1 WHERE id = ?', [$this->adminId]);
        $this->resetContainer();
    }

    private function passwordInput(): array
    {
        return ['current_password' => 'Admin@12345', 'password' => 'Staff-New-123!', 'password_confirmation' => 'Staff-New-123!'];
    }

    private function resetToken(int $id): string
    {
        $email = $this->db->fetchColumn('SELECT email FROM users WHERE id = ?', [$id]);
        $token = bin2hex(random_bytes(32));
        $this->db->insert('INSERT INTO password_resets (email, purpose, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))', [$email, 'reset', hash('sha256', $token)]);

        return $token;
    }

    private function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $data = $_SESSION;
            $directory = storage_path('sessions');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            session_start(['save_path' => $directory, 'use_cookies' => 0, 'cache_limiter' => '']);
            $_SESSION = $data;
        }
    }

    private function router(): Router
    {
        $router = new Router();
        $routes = require base_path('routes/web.php');
        $routes($router);

        return $router;
    }

    private function request(string $method, string $path, array $body = [], bool $json = false): Request
    {
        return new Request($method, $path, [], $body, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => $json ? 'application/json' : 'text/html'], []);
    }

    private function property(Response $response, string $name): mixed
    {
        return (new ReflectionProperty(Response::class, $name))->getValue($response);
    }

    private function json(Response $response): array
    {
        return json_decode($this->property($response, 'body'), true, 512, JSON_THROW_ON_ERROR);
    }
}
