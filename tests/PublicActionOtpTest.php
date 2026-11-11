<?php
declare(strict_types=1);
namespace Tests;
require_once __DIR__ . '/DatabaseTestCase.php';

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\ValidationException;
use App\Services\CaseService;
use App\Services\PublicOtpService;
use ReflectionProperty;

final class PublicActionOtpTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $property = new ReflectionProperty(Config::class, 'items'); $items = $property->getValue();
        $items['verification']['public_required'] = true;
        $items['mail']['host'] = '127.0.0.1'; $items['mail']['from_address'] = 'fixture@example.test';
        $items['sms'] = ['base_url' => 'http://127.0.0.1:1', 'user_id' => 'fixture', 'api_key' => 'fixture', 'sender_id' => 'AtteCert', 'timeout' => 1];
        $property->setValue(null, $items);
        $this->db->run("UPDATE customers SET phone = '0771234567' WHERE id = ?", [$this->customerId]);
        $_SESSION = ['locale' => 'en']; $this->resetContainer();
    }

    public function testQuoteSubmissionRequiresOtpBeforeCreatingAnyRequestAndConsumesTheProof(): void
    {
        $form = $this->body($this->dispatch('GET', '/quote'));
        $this->assertStringContainsString('Verify your contact', $form);
        $this->assertStringNotContainsString('name="attachments[]"', $form);
        try { $this->dispatch('POST', '/quote', $this->quote()); $this->fail('Unverified quote accepted.'); }
        catch (ValidationException) { $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quote_requests')); }
        $this->verifyQuote();
        $form = $this->body($this->dispatch('GET', '/quote'));
        $this->assertStringContainsString('Contact verified', $form); $this->assertStringContainsString('name="attachments[]"', $form);
        $this->dispatch('POST', '/quote', $this->quote());
        $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quote_requests'));
        $this->assertNull(app(PublicOtpService::class)->quoteContact());
        try { $this->dispatch('POST', '/quote', $this->quote()); $this->fail('Quote OTP reused.'); }
        catch (ValidationException) { $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quote_requests')); }
    }

    public function testChangingVerifiedQuoteContactRequiresAnotherVerification(): void
    {
        $this->verifyQuote();
        foreach (['email' => 'other@example.test', 'phone' => '0777654321', 'name' => 'Other person'] as $field => $value) {
            try { $this->dispatch('POST', '/quote', array_replace($this->quote(), [$field => $value])); $this->fail('Verified contact changed.'); }
            catch (ValidationException) { $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quote_requests')); }
        }
        $this->assertNotNull(app(PublicOtpService::class)->quoteContact());
    }

    public function testTrackingIsHiddenUntilTheCaseCustomersEmailOtpIsVerified(): void
    {
        $number = $this->number();
        $page = $this->body($this->dispatch('GET', '/track', [], ['number' => $number]));
        foreach (['Nimal Perera', 'Degree certificate', 'MOFA processing', 'customer@attecert.local', '0771234567'] as $hidden) { $this->assertStringNotContainsString($hidden, $page); }
        $this->assertStringContainsString('Verify to view your document', $page);
        $this->dispatch('POST', '/track', ['number' => $number, 'channel' => 'email', 'email' => 'attacker@example.test']);
        $log = $this->db->fetch("SELECT * FROM email_logs WHERE template = 'public-action-otp' ORDER BY id DESC LIMIT 1");
        $this->assertSame('customer@attecert.local', $log['recipient']);
        $message = file_get_contents(storage_path('mail/' . $log['body_path']));
        $this->assertSame(1, preg_match('/>([0-9]{6})<\/p>/', $message, $match));
        $this->dispatch('POST', '/track/verification', ['code' => $match[1]]);
        $page = $this->body($this->dispatch('GET', '/track', [], ['number' => $number]));
        $this->assertStringContainsString('MOFA processing', $page); $this->assertStringNotContainsString('Verify to view your document', $page);
        $this->assertFalse(app(PublicOtpService::class)->verify('track', $match[1]));
    }

    public function testOtpProofIsBoundToBrowserPurposeAndExactCase(): void
    {
        $this->dispatch('POST', '/track', ['number' => $this->number(), 'channel' => 'sms']); $code = $this->smsCode();
        $saved = $_SESSION;
        $_SESSION = ['locale' => 'en']; $this->resetContainer();
        $this->assertFalse(app(PublicOtpService::class)->verify('track', $code));
        $_SESSION = $saved; $this->resetContainer();
        $this->assertFalse(app(PublicOtpService::class)->verify('quote', $code));
        $this->assertTrue(app(PublicOtpService::class)->verify('track', $code));
        $other = app(CaseService::class)->create(['customer_id' => $this->customerId, 'country_id' => null, 'document_type_id' => null, 'document_title' => 'Other case'], [], null);
        $otherNumber = $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$other]);
        $this->assertStringContainsString('Verify to view your document', $this->body($this->dispatch('GET', '/track', [], ['number' => $otherNumber])));
        $this->assertStringContainsString('Verify your contact', $this->body($this->dispatch('GET', '/quote')));
        $this->db->run("UPDATE customers SET phone = '0777654321' WHERE id = ?", [$this->customerId]);
        $this->assertFalse(app(PublicOtpService::class)->trackingAllowed($this->number()));
    }

    public function testInvalidAndExpiredCodesNeverGrantAccessAndHaveAttemptAndResendLimits(): void
    {
        $this->dispatch('POST', '/track', ['number' => $this->number(), 'channel' => 'sms']); $code = $this->smsCode();
        try { $this->dispatch('POST', '/track', ['number' => $this->number(), 'channel' => 'sms']); $this->fail('Cooldown bypassed.'); }
        catch (ValidationException) { $this->assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM public_action_otps')); }
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) { $this->assertFalse(app(PublicOtpService::class)->verify('track', $wrong)); }
        $this->assertFalse(app(PublicOtpService::class)->verify('track', $code));
        $this->db->run('UPDATE public_action_otps SET created_at = DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
        $this->dispatch('POST', '/track', ['number' => $this->number(), 'channel' => 'sms']); $next = $this->smsCode();
        $this->db->run('UPDATE public_action_otps SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)');
        $this->assertFalse(app(PublicOtpService::class)->verify('track', $next));
        $this->assertFalse(app(PublicOtpService::class)->trackingAllowed($this->number()));
    }

    public function testVerifiedGrantExpiresAndUnknownReferencesDoNotRevealCustomerDetails(): void
    {
        $this->dispatch('POST', '/track', ['number' => $this->number(), 'channel' => 'sms']);
        $this->assertTrue(app(PublicOtpService::class)->verify('track', $this->smsCode()));
        $this->db->run('UPDATE public_action_otps SET grant_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE), created_at = DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
        $this->assertStringContainsString('Verify to view your document', $this->body($this->dispatch('GET', '/track', [], ['number' => $this->number()])));
        $count = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages');
        $this->dispatch('POST', '/track', ['number' => 'UNKNOWN-REF-0001', 'channel' => 'sms']);
        $this->assertSame($count, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM sms_messages'));
        $this->assertStringContainsString('match our records', $_SESSION['_flash']['success']);
        $page = $this->body($this->dispatch('GET', '/track', [], ['number' => 'UNKNOWN-REF-0001']));
        $this->assertStringNotContainsString('customer@attecert.local', $page); $this->assertStringNotContainsString('Nimal Perera', $page);
        $this->assertFalse(app(PublicOtpService::class)->verify('track', '123456'));
    }

    public function testMissingProvidersAndMalformedInputsDoNotBypassVerification(): void
    {
        $property = new ReflectionProperty(Config::class, 'items'); $items = $property->getValue();
        $items['mail']['host'] = ''; $items['sms']['api_key'] = ''; $property->setValue(null, $items);
        $page = $this->body($this->dispatch('GET', '/quote'));
        $this->assertStringContainsString('Verification delivery is currently unavailable', $page);
        foreach ([['POST', '/quote/verification/send', $this->contact() + ['channel' => 'email']], ['POST', '/track', ['number' => $this->number(), 'channel' => 'sms']],
            ['POST', '/quote/verification', ['code' => ['123456']]], ['POST', '/track/verification', ['code' => '123456']], ['POST', '/quote/verification/send', ['name' => ['bad'], 'email' => 'bad', 'phone' => 123456]] ] as [$method,$path,$data]) {
            try { $this->dispatch($method, $path, $data); $this->fail('Invalid or unconfigured verification accepted.'); }
            catch (ValidationException) { $this->assertNull(app(PublicOtpService::class)->quoteContact()); }
        }
        $this->assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM public_action_otps'));
    }

    public function testResettingBrowserDoesNotBypassDestinationRequestLimits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $_SESSION = ['locale' => 'en']; $this->resetContainer();
            $this->dispatch('POST', '/quote/verification/send', $this->contact() + ['channel' => 'sms']);
        }
        $_SESSION = ['locale' => 'en']; $this->resetContainer();
        try { $this->dispatch('POST', '/quote/verification/send', $this->contact() + ['channel' => 'sms']); $this->fail('Destination limit bypassed.'); }
        catch (ValidationException) { $this->assertSame(5, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM public_action_otps')); }
    }

    private function contact(): array { return ['name' => 'Public quote customer', 'email' => 'public-quote@example.test', 'phone' => '0771234567']; }
    private function quote(): array { return $this->contact() + ['consent' => '1', 'document_count' => '1', 'message' => 'Please quote this attestation.']; }
    private function number(): string { return (string) $this->db->fetchColumn('SELECT number FROM cases WHERE id = ?', [$this->caseId]); }
    private function verifyQuote(): void { $this->dispatch('POST', '/quote/verification/send', $this->contact() + ['channel' => 'sms']); $this->dispatch('POST', '/quote/verification', ['code' => $this->smsCode()]); }
    private function smsCode(): string { $message = $this->db->fetchColumn("SELECT message FROM sms_messages WHERE related_type = 'public_otp' ORDER BY id DESC LIMIT 1"); $this->assertSame(1, preg_match('/code: ([0-9]{6})/', $message, $match)); return $match[1]; }
    private function body(Response $response): string { return (new ReflectionProperty(Response::class, 'body'))->getValue($response); }
    private function dispatch(string $method, string $path, array $data = [], array $query = []): Response { $router = new Router(); (require base_path('routes/web.php'))($router); return $router->dispatch(new Request($method, $path, $query, $data, [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_ACCEPT' => 'application/json'], []), app()); }
}
