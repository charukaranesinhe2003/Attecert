<?php
declare(strict_types=1);
namespace Tests;
require_once __DIR__ . '/DatabaseTestCase.php';
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\AuthService;
use App\Services\CustomerOtpService;
use App\Services\SmsService;
use ReflectionProperty;

final class CustomerOtpSmsTest extends DatabaseTestCase
{
    private int $userId;
    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (int) $this->db->fetchColumn('SELECT user_id FROM customers WHERE id = ?', [$this->customerId]);
        $this->db->run("UPDATE users SET phone = '0771234567', email_verified_at = NULL, otp_verified_at = NULL WHERE id = ?", [$this->userId]);
        $this->db->run("UPDATE customers SET phone = '0771234567' WHERE id = ?", [$this->customerId]);
        $this->configure(['verification' => ['required' => true], 'mail' => ['host' => '127.0.0.1', 'from_address' => 'otp@example.test'],
            'sms' => ['user_id' => 'fixture-user', 'api_key' => 'fixture-key', 'sender_id' => 'AtteCert', 'base_url' => 'http://127.0.0.1:1/api'], 'app' => ['env' => 'test']]);
        $this->asUser($this->userId);
    }

    public function testPortalRequiresOtpEvenForAnOldEmailVerifiedFlagAndStaffIsExempt(): void
    {
        $this->db->run('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$this->userId]);
        $this->resetContainer();
        foreach (['/account', '/portal/documents', '/portal/applications', '/portal/quotations'] as $path) {
            $response = $this->dispatch('GET', $path);
            $this->assertSame(403, $this->value($response, 'status'));
            $this->assertSame('verification_required', json_decode($this->value($response, 'body'), true)['error']);
        }
        $this->assertSame(403, $this->value($this->dispatch('POST', '/portal/profile', ['name' => 'Bypass']), 'status'));
        $html = $this->value($this->dispatch('GET', '/verify-customer'), 'body');
        $this->assertStringContainsString('c***@attecert.local', $html);
        $this->assertStringContainsString('***4567', $html);
        $this->asUser($this->adminId);
        $this->assertFalse(app(AuthService::class)->needsVerification());
    }

    public function testEmailOtpIsHashedSingleUseAndUnlocksPortal(): void
    {
        $result = app(CustomerOtpService::class)->send('email');
        $row = $this->db->fetch('SELECT * FROM customer_otps WHERE id = ?', [$result['id']]);
        $mail = $this->db->fetch("SELECT * FROM email_logs WHERE template = 'customer-otp' ORDER BY id DESC LIMIT 1");
        $body = file_get_contents(storage_path('mail/' . $mail['body_path']));
        preg_match('/>([0-9]{6})<\//', $body, $match);
        $this->assertNotEmpty($match);
        $code = $match[1];
        $this->assertTrue(password_verify($code, $row['code_hash']));
        $this->assertNotSame($code, $row['code_hash']);
        $this->assertStringNotContainsString($code, json_encode($_SESSION));
        $payload = json_decode($this->db->fetchColumn('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$mail['id']]), true);
        $this->assertSame($row['expires_at'], $payload['expires_at']);
        $this->assertTrue(app(CustomerOtpService::class)->verify($code));
        $this->assertFalse(app(AuthService::class)->needsVerification());
        $this->assertSame(200, $this->value($this->dispatch('GET', '/account'), 'status'));
        $this->assertFalse(app(CustomerOtpService::class)->verify($code));
    }

    public function testSmsCodeNormalizesPhoneAndPhoneChangeRequiresVerificationAgain(): void
    {
        [$code, $id] = $this->smsCode();
        $row = $this->db->fetch('SELECT * FROM customer_otps WHERE id = ?', [$id]);
        $this->assertSame('+94771234567', $row['destination']);
        $this->assertTrue(app(CustomerOtpService::class)->verify($code));
        $this->assertFalse(app(AuthService::class)->needsVerification());
        app(AuthService::class)->updateProfile($this->userId, 'Customer', '0777654321', 'en');
        $this->assertTrue(app(AuthService::class)->needsVerification());
        $this->assertNull($this->db->fetchColumn('SELECT phone_verified_at FROM users WHERE id = ?', [$this->userId]));
    }

    public function testAttemptsResendCooldownExpiryAndAccountBindingAreEnforced(): void
    {
        [$code, $id] = $this->smsCode();
        for ($i = 0; $i < 5; $i++) { $this->assertFalse(app(CustomerOtpService::class)->verify($code === '111111' ? '222222' : '111111')); }
        $this->assertFalse(app(CustomerOtpService::class)->verify($code));
        try { app(CustomerOtpService::class)->send('sms'); $this->fail('Resend cooldown bypassed.'); }
        catch (ValidationException) { $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customer_otps')); }
        $this->db->run('UPDATE customer_otps SET created_at = DATE_SUB(NOW(), INTERVAL 61 SECOND) WHERE id = ?', [$id]);
        [$newCode, $newId] = $this->smsCode();
        $this->assertNotNull($this->db->fetchColumn('SELECT used_at FROM customer_otps WHERE id = ?', [$id]));
        $this->db->run('UPDATE customer_otps SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = ?', [$newId]);
        $this->assertFalse(app(CustomerOtpService::class)->verify($newCode));
        $this->db->run('UPDATE customer_otps SET expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?', [$newId]);
        $this->db->run("UPDATE users SET phone = '0777654321' WHERE id = ?", [$this->userId]);
        $this->assertFalse(app(CustomerOtpService::class)->verify($newCode));
    }

    public function testMissingDeliverySettingsCannotMarkCustomerVerified(): void
    {
        $this->configure(['sms' => ['api_key' => ''], 'mail' => ['host' => '']]);
        foreach (['email', 'sms'] as $channel) {
            try { app(CustomerOtpService::class)->send($channel); $this->fail('Unconfigured channel accepted.'); }
            catch (ValidationException) { $this->assertTrue(app(AuthService::class)->needsVerification()); }
        }
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customer_otps'));
        $this->assertSame(403, $this->value($this->dispatch('GET', '/account'), 'status'));
    }

    public function testPasswordChangesAndAnotherSessionAccountCannotUseAChallenge(): void
    {
        [$code, $id] = $this->smsCode();
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash('New-Password-123!', PASSWORD_DEFAULT), $this->userId]);
        $this->assertFalse(app(CustomerOtpService::class)->verify($code));
        $other = $this->signInAsRole('customer');
        $_SESSION['_customer_otp'] = $id;
        $this->assertFalse(app(CustomerOtpService::class)->verify($code));
        $this->assertNull($this->db->fetchColumn('SELECT otp_verified_at FROM users WHERE id = ?', [$other]));
    }

    public function testNewCustomerRegistrationRequiresOtpAndLegacyLinksCannotBypassIt(): void
    {
        $id = app(AuthService::class)->register('New verified customer', 'new-otp@example.test', '0771234567', 'Customer-Password-123!', 'en');
        $row = $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
        $this->assertNull($row['email_verified_at']);
        $this->assertNull($row['otp_verified_at']);
        $this->asUser($id);
        $this->db->run('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$id]);
        $this->resetContainer();
        $this->assertTrue(app(AuthService::class)->needsVerification());
        $this->assertSame('/verify-customer', app(AuthService::class)->homePath());
    }

    public function testMissingMailQueueDoesNotClaimAnOtpWasQueued(): void
    {
        $mail = app(\App\Services\MailService::class);
        (new ReflectionProperty($mail, 'queueAvailable'))->setValue($mail, false);
        try { app(CustomerOtpService::class)->send('email'); $this->fail('Unavailable mail queue accepted.'); }
        catch (ValidationException) {
            $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM customer_otps'));
            $this->assertTrue(app(AuthService::class)->needsVerification());
        }
    }

    private function smsCode(): array
    {
        $result = app(CustomerOtpService::class)->send('sms');
        $message = $this->db->fetchColumn("SELECT message FROM sms_messages WHERE related_type = 'otp' AND related_id = ?", [$result['id']]);
        preg_match('/code: ([0-9]{6})/', $message, $match);
        return [$match[1], $result['id']];
    }
    private function configure(array $changes): void
    {
        $prop = new ReflectionProperty(Config::class, 'items'); $items = $prop->getValue();
        foreach ($changes as $group => $values) { $items[$group] = array_replace($items[$group] ?? [], $values); }
        $prop->setValue(null, $items);
    }
    private function asUser(int $id): void { $_SESSION = ['locale' => 'en', 'user_id' => $id]; $this->resetContainer(); }
    private function dispatch(string $method, string $path, array $body = []): Response
    {
        $router = new Router(); (require base_path('routes/web.php'))($router);
        return $router->dispatch(new Request($method, $path, [], $body, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app());
    }
    private function value(Response $r, string $name): mixed { return (new ReflectionProperty(Response::class, $name))->getValue($r); }
}
