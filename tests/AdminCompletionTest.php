<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Admin\ContactController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\QuoteRequestController;
use App\Controllers\Admin\SettingsController;
use App\Controllers\Admin\TranslationController;
use App\Core\Config;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Helpers\Decimal;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\SettingsService;
use App\Services\TranslationService;
use ReflectionProperty;

final class AdminCompletionTest extends DatabaseTestCase
{
    public function testSettingsValidationIsAtomicPreservesValuesAndNormalizesCurrency(): void
    {
        $controller = app(SettingsController::class);
        $before = app(SettingsService::class)->all();
        foreach ([['email' => 'bad'], ['map_url' => 'javascript:alert(1)'], ['map_url' => 'https://user:secret@example.test'], ['currency' => 'long'], ['invoice_due_days' => '2.5'], ['quote_validity_days' => '0'], ['upload_max_mb' => '99999']] as $change) {
            try {
                $controller->update($this->post($change));
                self::fail('Invalid settings must fail before writing any values.');
            } catch (ValidationException) {
                self::assertSame($before, app(SettingsService::class)->all());
            }
        }
        $controller->update($this->post(['currency' => 'usd', 'phone' => 'new-phone']));
        self::assertSame('USD', app(SettingsService::class)->get('currency'));
        self::assertSame($before['company_name'], app(SettingsService::class)->get('company_name'));
        self::assertSame('new-phone', app(SettingsService::class)->get('phone'));
        self::assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action = 'settings_updated'"));
        $_SESSION['_view_old'] = ['settings_form' => '1', 'company_name' => 'Retained company', 'email_verification' => '0'];
        $_SESSION['_view_errors'] = ['email' => 'Invalid email fixture'];
        $html = $this->body($controller->edit());
        self::assertStringContainsString('Retained company', $html);
        self::assertStringContainsString('Invalid email fixture', $html);
    }

    public function testDecimalInputsRespectDatabasePrecisionAndRejectMalformedAmounts(): void
    {
        self::assertSame('0.50', Decimal::amount('.50'));
        self::assertSame('9999999999.99', Decimal::amount('9999999999.99'));
        foreach (['-1', '1e3', 'NaN', '1,000', '1.001', '10000000000.00', [], INF] as $value) {
            try {
                Decimal::amount($value);
                self::fail('Malformed or out-of-range amounts must not reach BCMath or SQL.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('amount', $exception->errors());
            }
        }
        try {
            (new Validator())->validate(['email' => ['nested']], ['email' => 'required|email']);
            self::fail('An array cannot be an email.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
        }
    }

    public function testInvalidQuotationsCannotWritePartialRecordsAndCurrencyIsNotRelabelled(): void
    {
        $quotes = app(QuotationService::class);
        $payload = ['lines' => [['description' => 'Fixture line', 'quantity' => 1, 'unit_price' => 100]]];
        foreach ([['tax_rate' => '100.01'], ['discount_amount' => '-1'], ['additional_amount' => '1e3'], ['validity_days' => '2.5'], ['lines' => [['description' => 'Overflow', 'quantity' => 999999, 'unit_price' => 9999999999]]], ['lines' => [['description' => 'Zero quantity', 'quantity' => 0, 'unit_price' => 100]]]] as $change) {
            try {
                $quotes->save($this->caseId, array_replace($payload, $change), $this->adminId, false, true);
                self::fail('Invalid quote must fail before writes.');
            } catch (ValidationException) {
                self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations'));
            }
        }
        $service = (int) $this->db->fetchColumn('SELECT id FROM services LIMIT 1');
        $this->db->run('UPDATE services SET currency = ? WHERE id = ?', ['USD', $service]);
        $this->expectException(ValidationException::class);
        $quotes->save($this->caseId, ['lines' => [['service_id' => $service, 'quantity' => 1]]], $this->adminId, false, true);
    }

    public function testContactFiltersAndStatusChangesRetainTriageContext(): void
    {
        for ($i = 0; $i < 18; $i++) {
            $this->db->insert('INSERT INTO contact_messages (name, email, subject, message, source, status) VALUES (?, ?, ?, ?, ?, ?)', ['Triage ' . $i, 'fixture@example.test', 'Needle subject', 'A sufficiently detailed fixture message', 'support', $i === 17 ? 'closed' : 'new']);
        }
        $controller = app(ContactController::class);
        $html = $this->body($controller->index($this->get(['q' => 'Needle', 'status' => 'new', 'source' => 'support'])));
        self::assertStringContainsString('17 matching records', $html);
        self::assertStringContainsString('data-label="Message"', $html);
        self::assertStringContainsString('rel="next"', $html);
        $id = (int) $this->db->fetchColumn('SELECT id FROM contact_messages LIMIT 1');
        $response = $controller->status($this->post(['status' => 'contacted', 'q' => 'Needle', 'source' => 'support', 'return_status' => 'new']), (string) $id);
        $location = (new ReflectionProperty(Response::class, 'headers'))->getValue($response)['Location'];
        self::assertStringContainsString('q=Needle', $location);
        self::assertStringContainsString('status=new', $location);
    }

    public function testRequestStatusRequiresActualCaseConversion(): void
    {
        $id = $this->db->insert('INSERT INTO quote_requests (name, email, status) VALUES (?, ?, ?)', ['Request needle', 'fixture@example.test', 'new']);
        $controller = app(QuoteRequestController::class);
        self::assertStringContainsString('1 matching records', $this->body($controller->index($this->get(['q' => 'Request needle', 'status' => 'new']))));
        try {
            $controller->status($this->post(['status' => 'converted']), (string) $id);
            self::fail('Status label alone cannot complete a case conversion.');
        } catch (ValidationException) {
            self::assertSame('new', $this->db->fetchColumn('SELECT status FROM quote_requests WHERE id = ?', [$id]));
        }
        $this->db->run('UPDATE quote_requests SET converted_case_id = ? WHERE id = ?', [$this->caseId, $id]);
        $controller->status($this->post(['status' => 'converted']), (string) $id);
        self::assertSame('converted', $this->db->fetchColumn('SELECT status FROM quote_requests WHERE id = ?', [$id]));
        self::assertSame(1, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action = 'quote_request_status_updated'"));
    }

    public function testPaymentFiltersAndFailedInputPreservation(): void
    {
        $invoice = $this->invoice();
        app(PaymentService::class)->record($invoice, ['amount' => 100, 'method' => 'cash', 'paid_at' => date('Y-m-d')], $this->adminId);
        app(PaymentService::class)->record($invoice, ['amount' => 20, 'method' => 'refund', 'paid_at' => date('Y-m-d')], $this->adminId);
        $controller = app(PaymentController::class);
        $html = $this->body($controller->index($this->get(['method' => 'refund', 'from' => date('Y-m-d'), 'to' => date('Y-m-d')])));
        self::assertStringContainsString('1 matching records', $html);
        self::assertStringContainsString('LKR -20.00', $html);
        $invalid = $controller->index($this->get(['from' => '2026-02-30']));
        self::assertSame(422, (new ReflectionProperty(Response::class, 'status'))->getValue($invalid));
        $controller->store($this->post(['amount' => '1e3', 'method' => 'refund', 'paid_at' => date('Y-m-d'), 'notes' => 'Keep this explanation']), (string) $invoice);
        self::assertSame('refund', $_SESSION['_old']['_formkind']);
        self::assertSame('Keep this explanation', $_SESSION['_old']['notes']);
        self::assertArrayHasKey('amount', $_SESSION['_errors']);
    }

    public function testTranslationsHaveRealPaginationSearchAndConfirmedPerLanguageReset(): void
    {
        $controller = app(TranslationController::class);
        $first = $this->body($controller->index($this->get()));
        $second = $this->body($controller->index($this->get(['page' => '2'])));
        preg_match_all('/name="trans_key" value="([^"]+)"/', $first, $firstKeys);
        preg_match_all('/name="trans_key" value="([^"]+)"/', $second, $secondKeys);
        self::assertCount(20, array_unique($firstKeys[1]));
        self::assertSame([], array_intersect($firstKeys[1], $secondKeys[1]));
        app(TranslationService::class)->set('si', 'nav.home', 'CustomNeedleTranslation');
        self::assertStringContainsString('CustomNeedleTranslation', $this->body($controller->index($this->get(['q' => 'CustomNeedleTranslation']))));
        try {
            $controller->update($this->post(['trans_key' => 'nav.home', 'reset_locale' => 'si']));
            self::fail('Reset requires confirmation.');
        } catch (HttpException $exception) {
            self::assertSame(422, $exception->status());
        }
        $controller->update($this->post(['trans_key' => 'nav.home', 'reset_locale' => 'si', 'confirm_reset' => '1']));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM ui_translations WHERE trans_key = ? AND locale = ?', ['nav.home', 'si']));
    }

    public function testMissingCommercialRecordsReturnNotFoundAndPaginationStaysBounded(): void
    {
        foreach ([fn () => app(InvoiceService::class)->bundle(9999999), fn () => app(QuotationService::class)->bundle(9999999), fn () => app(PaymentController::class)->receipt($this->get(), '9999999')] as $action) {
            try { $action(); self::fail('Missing record must be a 404.'); } catch (HttpException $exception) { self::assertSame(404, $exception->status()); }
        }
        $_GET = ['q' => 'Keep filter'];
        $html = paginator(['page' => 500000, 'pages' => 1000000], '/admin/cases');
        self::assertLessThanOrEqual(9, substr_count($html, '<a '));
        self::assertStringContainsString('q=Keep+filter', $html);
        self::assertStringContainsString('aria-current="page"', $html);
    }

    public function testMalformedScalarFieldsAndFiltersFailWithoutWarningsOrWrites(): void
    {
        set_error_handler(static function (int $severity, string $message): never { throw new \ErrorException($message, 0, $severity); });
        try {
            foreach ([[], ['nested']] as $value) {
                try {
                    app(SettingsController::class)->update($this->post(['phone' => $value]));
                    self::fail('Nullable scalar settings must reject arrays.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('phone', $exception->errors());
                }
            }
            self::assertSame(0, (int) $this->db->fetchColumn("SELECT COUNT(*) FROM audit_logs WHERE action = 'settings_updated'"));
            $_SESSION['_view_old'] = ['settings_form' => '1', 'phone' => []];
            $_SESSION['_view_errors'] = ['phone' => 'Invalid scalar fixture'];
            self::assertStringContainsString('Invalid scalar fixture', $this->body(app(SettingsController::class)->edit()));
            foreach ([ContactController::class, QuoteRequestController::class, PaymentController::class, TranslationController::class, \App\Controllers\Admin\AuditController::class] as $type) {
                $response = app($type)->index($this->get(['q' => [], 'status' => [], 'source' => [], 'method' => []]));
                self::assertSame(200, (new ReflectionProperty(Response::class, 'status'))->getValue($response));
            }
            $response = app(\App\Controllers\Admin\AuditController::class)->emails($this->get(['q' => [], 'status' => []]));
            self::assertSame(200, (new ReflectionProperty(Response::class, 'status'))->getValue($response));
            foreach (['from', 'to'] as $key) {
                $response = app(PaymentController::class)->index($this->get([$key => []]));
                self::assertSame(422, (new ReflectionProperty(Response::class, 'status'))->getValue($response));
            }
        } finally {
            restore_error_handler();
        }
    }

    public function testTranslationFailuresRetainOnlyTheirRowAndBundledDefaultsStayUnshadowed(): void
    {
        $controller = app(TranslationController::class);
        $translations = app(TranslationService::class);
        $defaults = $translations->defaults('nav.home');
        $values = ['trans_key' => 'nav.home'];
        foreach ($defaults as $language => $text) { $values['value_' . $language] = $text; }
        $controller->update($this->post($values));
        self::assertSame(0, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM ui_translations WHERE trans_key = ?', ['nav.home']));
        $values['value_en'] = 'Custom home fixture';
        $controller->update($this->post($values));
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM ui_translations WHERE trans_key = ?', ['nav.home']));
        $values['value_en'] = str_repeat('x', 10001);
        $values['value_si'] = 'Retain this translation fixture';
        try {
            $controller->update($this->post($values));
            self::fail('Oversized translation must fail atomically.');
        } catch (ValidationException $exception) {
            $_SESSION['_view_old'] = $exception->input();
            $_SESSION['_view_errors'] = $exception->errors();
        }
        $html = $this->body($controller->index($this->get(['q' => 'nav.'])));
        self::assertSame(1, substr_count($html, 'Retain this translation fixture'));
        self::assertStringContainsString(str_repeat('x', 10001), $html);
        self::assertSame('Custom home fixture', $this->db->fetchColumn('SELECT value FROM ui_translations WHERE trans_key = ? AND locale = ?', ['nav.home', 'en']));
    }

    public function testQueuedCommercialEmailHasSuccessFeedbackWithoutClaimingDelivery(): void
    {
        $property = new ReflectionProperty(Config::class, 'items');
        $items = $property->getValue(); $items['mail']['host'] = '127.0.0.1'; $property->setValue(null, $items);
        $invoice = $this->invoice();
        app(\App\Controllers\Admin\InvoiceController::class)->send($this->post(), (string) $invoice);
        self::assertStringContainsString('queued', strtolower($_SESSION['_flash']['success']));
        self::assertArrayNotHasKey('error', $_SESSION['_flash']);
        self::assertSame('queued', $this->db->fetchColumn("SELECT status FROM email_logs WHERE related_type = 'invoice' ORDER BY id DESC LIMIT 1"));
    }

    private function invoice(): int
    {
        $quote = app(QuotationService::class)->save($this->caseId, ['lines' => [['description' => 'Completion fixture', 'quantity' => 1, 'unit_price' => 1000]]], $this->adminId, false, true);
        return app(InvoiceService::class)->createFromQuotation($quote, $this->adminId);
    }
    private function get(array $query = []): Request { return new Request('GET', '/admin', $query, [], [], $_SERVER, []); }
    private function post(array $body = []): Request { return new Request('POST', '/admin', [], $body, [], $_SERVER, []); }
    private function body(Response $response): string { return (string) (new ReflectionProperty(Response::class, 'body'))->getValue($response); }
}
