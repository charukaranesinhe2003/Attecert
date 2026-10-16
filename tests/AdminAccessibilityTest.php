<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\CaseController;
use App\Controllers\Admin\DocumentController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\ReportController;
use App\Core\Request;
use App\Core\Response;
use DOMDocument;
use DOMElement;
use DOMXPath;
use ReflectionProperty;

final class AdminAccessibilityTest extends DatabaseTestCase
{
    public function testCaseTabsHaveLinkedPanelsAndOneKeyboardEntryPoint(): void
    {
        $html = $this->html(app(CaseController::class)->show($this->get('/admin/cases/' . $this->caseId), (string) $this->caseId));
        $xpath = $this->xpath($html);
        $this->assertSame(1, $xpath->query('//*[@data-case-tabs and @role="tablist" and string-length(@aria-label)>0]')->length);
        $tabs = $xpath->query('//*[@data-case-tabs]/*[@role="tab"]');
        $this->assertGreaterThan(1, $tabs->length);
        $this->assertSame(1, $xpath->query('//*[@role="tab" and @aria-selected="true" and @tabindex="0"]')->length);
        $this->assertSame($tabs->length - 1, $xpath->query('//*[@role="tab" and @aria-selected="false" and @tabindex="-1"]')->length);
        foreach ($tabs as $tab) {
            $panel = $xpath->query('//*[@id="' . $tab->getAttribute('aria-controls') . '"]')->item(0);
            $this->assertInstanceOf(DOMElement::class, $panel);
            $this->assertSame('tabpanel', $panel->getAttribute('role'));
            $this->assertSame($tab->getAttribute('id'), $panel->getAttribute('aria-labelledby'));
            $this->assertSame('0', $panel->getAttribute('tabindex'));
            $this->assertSame($tab->getAttribute('aria-selected') === 'false', $panel->hasAttribute('hidden'));
        }
        $this->assertUniqueIds($xpath);
    }

    public function testDocumentFiltersAndReleaseConfirmationsHaveAccessibleNames(): void
    {
        $id = $this->db->insert('INSERT INTO documents (case_id, category, original_name, stored_name, mime, size_bytes, customer_visible, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, 0, ?)', [$this->caseId, 'completed', 'Accessible document.pdf', 'fixture.pdf', 'application/pdf', 10, $this->adminId]);
        $html = $this->html(app(DocumentController::class)->index($this->get('/admin/documents')));
        $xpath = $this->xpath($html);
        $this->assertNamedControls($xpath);
        $this->assertSame(5, $xpath->query('//main//table/thead/tr/th[@scope="col"]')->length);
        $this->assertSame(1, $xpath->query('//main//table/caption[string-length(normalize-space(.))>0]')->length);
        $this->assertGreaterThanOrEqual(1, $xpath->query('//details/summary[contains(., "Accessible document.pdf")]')->length);
        $this->assertSame(1, $xpath->query('//form[contains(@action,"/admin/documents/' . $id . '/visibility")]//input[@name="confirmed" and @required]')->length);
        $this->signInAsRole('finance_staff');
        $restricted = $this->html(app(DocumentController::class)->index($this->get('/admin/documents')));
        $this->assertStringNotContainsString('/admin/documents/' . $id . '/visibility', $restricted);
        $this->assertStringNotContainsString('/admin/documents/' . $id . '/archive', $restricted);
    }

    public function testReportDateErrorsAreLinkedAndOutstandingTableHasHeaders(): void
    {
        $invalid = app(ReportController::class)->index($this->get('/admin/reports', ['from' => '2026-12-01', 'to' => '2026-01-01']));
        $this->assertSame(422, (new ReflectionProperty(Response::class, 'status'))->getValue($invalid));
        $xpath = $this->xpath($this->html($invalid));
        $this->assertNamedControls($xpath);
        $this->assertSame(1, $xpath->query('//*[@id="report-to" and @aria-invalid="true" and @aria-describedby="report-to-error"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="report-to-error" and @role="alert" and string-length(normalize-space(.))>0]')->length);
        $this->invoice();
        $valid = $this->html(app(ReportController::class)->index($this->get('/admin/reports', ['from' => '2026-01-01', 'to' => '2026-12-31'])));
        $xpath = $this->xpath($valid);
        $this->assertSame(4, $xpath->query('//main//table/thead/tr/th[@scope="col"]')->length);
        $this->assertSame(1, $xpath->query('//main//table/caption[string-length(normalize-space(.))>0]')->length);
        $this->assertStringNotContainsString('admin_polish.', $valid);
    }

    public function testInvoiceRestoresOnlyTheFailedPaymentOrRefundWithLinkedInlineErrors(): void
    {
        $id = $this->invoice();
        foreach (['payment', 'refund'] as $kind) {
            $_SESSION['_view_old'] = [
                '_formkind' => $kind, 'invoice_id' => (string) $id, 'amount' => '99.99',
                'method' => $kind === 'refund' ? 'refund' : 'cash', 'paid_at' => '2026-10-14',
                'reference' => 'Preserved reference', 'notes' => 'Preserved notes <script>',
            ];
            $_SESSION['_view_errors'] = ['amount' => 'Check this amount.'];
            $html = $this->html(app(InvoiceController::class)->show($this->get('/admin/invoices/' . $id), (string) $id));
            $xpath = $this->xpath($html);
            $other = $kind === 'payment' ? 'refund' : 'payment';
            $this->assertSame(1, $xpath->query('//form[@id="invoice-' . $kind . '"]//input[@name="amount" and @value="99.99" and @aria-invalid="true"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="invoice-' . $kind . '"]//*[@id="invoice-' . $kind . '-amount-error" and @role="alert"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="invoice-' . $other . '"]//input[@name="amount" and @value=""]')->length);
            $this->assertSame(0, $xpath->query('//form[@id="invoice-' . $other . '"]//*[@aria-invalid="true"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="invoice-' . $kind . '"]//input[@name="paid_at" and @value="2026-10-14"]')->length);
            $this->assertSame(1, $xpath->query('//form[@id="invoice-' . $kind . '"]//input[@name="reference" and @value="Preserved reference"]')->length);
            $this->assertStringContainsString('Preserved notes &lt;script&gt;', $html);
            $this->assertSame(0, $xpath->query('//main//script')->length);
            $this->assertNamedControls($xpath);
            $this->assertUniqueIds($xpath);
        }
    }

    public function testInvoiceDoesNotRestoreOtherInvoiceDataOrExposeUnauthorizedForms(): void
    {
        $id = $this->invoice();
        $_SESSION['_view_old'] = ['_formkind' => 'refund', 'invoice_id' => $id + 1, 'reference' => 'Other invoice reference'];
        $_SESSION['_view_errors'] = ['amount' => 'Other invoice error'];
        $html = $this->html(app(InvoiceController::class)->show($this->get('/admin/invoices/' . $id), (string) $id));
        $this->assertStringNotContainsString('Other invoice reference', $html);
        $this->assertStringNotContainsString('Other invoice error', $html);
        $this->signInAsRole('processing_staff');
        $restricted = $this->html(app(InvoiceController::class)->show($this->get('/admin/invoices/' . $id), (string) $id));
        $this->assertStringNotContainsString('id="invoice-payment"', $restricted);
        $this->assertStringNotContainsString('id="invoice-refund"', $restricted);
        $this->assertStringNotContainsString('/admin/invoices/' . $id . '/send', $restricted);
    }

    private function invoice(): int
    {
        $id = $this->db->insert('INSERT INTO invoices (number, case_id, customer_id, status, total, amount_paid, due_at) VALUES (?, ?, ?, ?, 100, 40, ?)', ['I-ACCESS-' . bin2hex(random_bytes(4)), $this->caseId, $this->customerId, 'partially_paid', '2026-10-30']);
        $this->db->run('INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total) VALUES (?, ?, 1, 100, 100)', [$id, 'Accessible invoice item']);

        return $id;
    }

    private function assertNamedControls(DOMXPath $xpath): void
    {
        foreach ($xpath->query('//input[not(@type="hidden")] | //select | //textarea') as $control) {
            $hasName = trim($control->getAttribute('aria-label')) !== '' || trim($control->getAttribute('aria-labelledby')) !== '';
            if (!$hasName && $control->getAttribute('id') !== '') {
                $hasName = $xpath->query('//label[@for="' . $control->getAttribute('id') . '" and string-length(normalize-space(.))>0]')->length > 0;
            }
            if (!$hasName) {
                $hasName = $xpath->query('ancestor::label[string-length(normalize-space(.))>0]', $control)->length > 0;
            }
            $this->assertTrue($hasName, 'Unlabelled control: ' . $control->nodeName . ' ' . $control->getAttribute('name'));
            foreach (preg_split('/\s+/', trim($control->getAttribute('aria-describedby'))) ?: [] as $id) {
                if ($id !== '') {
                    $this->assertSame(1, $xpath->query('//*[@id="' . $id . '" and string-length(normalize-space(.))>0]')->length);
                }
            }
        }
    }

    private function assertUniqueIds(DOMXPath $xpath): void
    {
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $ids[] = $node->getAttribute('id');
        }
        $this->assertSame(count($ids), count(array_unique($ids)), 'Every id must be unique.');
    }

    private function get(string $path, array $query = []): Request
    {
        $_GET = $query;

        return new Request('GET', $path, $query, [], [], [], []);
    }

    private function html(Response $response): string
    {
        return (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
