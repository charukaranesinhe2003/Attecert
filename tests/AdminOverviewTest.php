<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/DatabaseTestCase.php';

use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\CaseController;
use App\Controllers\Admin\DocumentController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\SearchController;
use App\Core\Request;
use App\Core\Response;
use App\Services\CaseService;
use App\Services\ReportService;
use ReflectionProperty;

final class AdminOverviewTest extends DatabaseTestCase
{
    public function testContentEditorCannotSeeOperationalOverviewOrGlobalSearchRecords(): void
    {
        $customer = $this->db->fetch('SELECT name, email FROM customers WHERE id = ?', [$this->customerId]);
        $this->signInAsRole('content_editor');
        $metrics = app(ReportService::class)->dashboard();
        foreach (['active', 'unquoted', 'awaiting_payment', 'ready_docs', 'recent_cases', 'recent_quotes'] as $key) {
            $this->assertNull($metrics[$key]);
        }
        $this->assertSame([], $metrics['attention']);
        $payload = json_decode($this->body(app(SearchController::class)->index($this->get(['q' => $customer['email']], true))), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $payload['results']);
        $html = $this->body(app(DashboardController::class)->index($this->get()));
        $this->assertStringNotContainsString($customer['name'], $html);
        $this->assertStringContainsString('Your workspace', $html);
        $this->assertStringContainsString('Website', $html);
        $this->assertStringNotContainsString('href="/admin/invoices', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)[^>]*>/i', $html);
    }

    public function testProcessingRoleSeesItsQueuesWithoutFinanceOrQuotationRecords(): void
    {
        $this->signInAsRole('processing_staff');
        $metrics = app(ReportService::class)->dashboard();
        $this->assertNotNull($metrics['active']);
        $this->assertNull($metrics['unquoted']);
        $this->assertNull($metrics['awaiting_payment']);
        $this->assertArrayHasKey('mofa', $metrics['attention']);
        $this->assertArrayHasKey('ready', $metrics['attention']);
        $this->assertArrayNotHasKey('draft_quotes', $metrics['attention']);
        $this->assertArrayNotHasKey('overdue', $metrics['attention']);
        $payload = json_decode($this->body(app(SearchController::class)->index($this->get(['q' => '%'], true))), true, 512, JSON_THROW_ON_ERROR);
        foreach ($payload['results'] as $group) {
            $this->assertContains($group['label'], ['Cases', 'Customers']);
        }
    }

    public function testActiveAndUnquotedCardsMatchCaseListFiltersAndQueuesHaveFullCounts(): void
    {
        $new = $this->newCase('new_request');
        $this->newCase('under_review');
        $withDraft = $this->newCase('new_request');
        $this->db->insert('INSERT INTO quotations (number, case_id, customer_id, status) VALUES (?, ?, ?, ?)', ['Q-OVERVIEW-DRAFT', $withDraft, $this->customerId, 'draft']);
        $this->newCase('completed');
        for ($i = 0; $i < 5; $i++) {
            $this->newCase('new_request');
        }
        $metrics = app(ReportService::class)->dashboard();
        $active = app(CaseService::class)->search(['status' => 'active'], 1);
        $unquoted = app(CaseService::class)->search(['status' => 'unquoted'], 1);
        $this->assertSame($active['total'], $metrics['active']);
        $this->assertSame($unquoted['total'], $metrics['unquoted']);
        $this->assertSame(7, $metrics['unquoted']);
        $this->assertCount(5, $metrics['attention']['unquoted']['rows']);
        $this->assertContains($new, array_map('intval', array_column($unquoted['rows'], 'id')));
        $this->assertNotContains($withDraft, array_map('intval', array_column($unquoted['rows'], 'id')));
        $this->assertSame(1, $metrics['attention']['draft_quotes']['total']);
    }

    public function testUnpaidAndPastDueCountsUseAllOutstandingInvoiceStatuses(): void
    {
        foreach (['issued', 'sent', 'partially_paid', 'overdue', 'paid', 'cancelled'] as $status) {
            $this->db->insert(
                'INSERT INTO invoices (number, case_id, customer_id, status, total, amount_paid, due_at) VALUES (?, ?, ?, ?, 100, ?, ?)',
                ['I-OV-' . strtoupper($status), $this->caseId, $this->customerId, $status, $status === 'paid' ? 100 : 0, date('Y-m-d', strtotime('-2 days'))]
            );
        }
        $metrics = app(ReportService::class)->dashboard();
        $this->assertSame(4, $metrics['awaiting_payment']);
        $this->assertSame(4, $metrics['attention']['overdue']['total']);
        foreach (['unpaid', 'overdue'] as $filter) {
            $html = $this->body(app(InvoiceController::class)->index($this->get(['status' => $filter])));
            $this->assertStringContainsString('4 matching records', $html);
            $this->assertStringContainsString('I-OV-ISSUED', $html);
            $this->assertStringContainsString('I-OV-PARTIALLY_PAID', $html);
            $this->assertStringNotContainsString('I-OV-PAID', $html);
            $this->assertStringNotContainsString('I-OV-CANCELLED', $html);
        }
    }

    public function testOnlyLatestOpenProcessingSnapshotsAppearInAttention(): void
    {
        $this->db->insert('INSERT INTO mofa_processes (case_id, status) VALUES (?, ?)', [$this->caseId, 'completed']);
        $open = $this->newCase('mofa_processing');
        foreach (['submitted', 'processing'] as $status) {
            $this->db->insert('INSERT INTO mofa_processes (case_id, status) VALUES (?, ?)', [$open, $status]);
        }
        $closed = $this->newCase('completed');
        $this->db->insert('INSERT INTO mofa_processes (case_id, status) VALUES (?, ?)', [$closed, 'processing']);
        $this->db->insert('INSERT INTO embassy_processes (case_id, embassy_name, status) VALUES (?, ?, ?)', [$open, 'Test embassy', 'submitted']);
        $metrics = app(ReportService::class)->dashboard();
        $this->assertSame(1, $metrics['attention']['mofa']['total']);
        $this->assertSame([$open], array_map('intval', array_column($metrics['attention']['mofa']['rows'], 'id')));
        $this->assertSame(1, $metrics['attention']['embassy']['total']);
        $this->db->insert('INSERT INTO embassy_processes (case_id, embassy_name, status) VALUES (?, ?, ?)', [$open, 'Test embassy', 'completed']);
        $this->assertSame(0, app(ReportService::class)->dashboard()['attention']['embassy']['total']);
    }

    public function testPrivateCompletedFileMetricMatchesReleaseList(): void
    {
        $this->document($this->caseId, 'ready-private.pdf', false);
        $this->document($this->caseId, 'already-released.pdf', true);
        $this->document(null, 'orphan-completed.pdf', false);
        $archivedCase = $this->newCase('completed');
        $this->document($archivedCase, 'archived-case.pdf', false);
        $this->db->run('UPDATE cases SET deleted_at = NOW() WHERE id = ?', [$archivedCase]);
        $metrics = app(ReportService::class)->dashboard();
        $this->assertSame(1, $metrics['ready_docs']);
        $this->assertSame(1, $metrics['attention']['ready']['total']);
        $html = $this->body(app(DocumentController::class)->index($this->get(['category' => 'completed', 'visibility' => 'private'])));
        $this->assertStringContainsString('ready-private.pdf', $html);
        foreach (['already-released.pdf', 'orphan-completed.pdf', 'archived-case.pdf'] as $excluded) {
            $this->assertStringNotContainsString($excluded, $html);
        }
    }

    public function testRoleScopedCasePanelsRemainSiblingsWithMatchingTabs(): void
    {
        $this->db->insert('INSERT INTO quotations (number, case_id, customer_id, status) VALUES (?, ?, ?, ?)', ['Q-ROLE-PRIVATE', $this->caseId, $this->customerId, 'draft']);
        $this->db->insert('INSERT INTO invoices (number, case_id, customer_id, status) VALUES (?, ?, ?, ?)', ['I-ROLE-PRIVATE', $this->caseId, $this->customerId, 'issued']);
        foreach (['processing_staff', 'customer_support', 'finance_staff'] as $role) {
            $this->signInAsRole($role);
            $html = $this->body(app(CaseController::class)->show($this->get(), (string) $this->caseId));
            $document = new \DOMDocument();
            $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $panels = [];
            foreach ($xpath->query('//article[contains(@class, "case-workspace")]/section[@data-panel]') as $node) {
                $panels[] = $node->getAttribute('data-panel');
            }
            $tabs = [];
            foreach ($xpath->query('//nav[@data-case-tabs]/button[@data-tab]') as $node) {
                $tabs[] = $node->getAttribute('data-tab');
            }
            $this->assertSame($tabs, $panels, $role . ' must have one top-level panel per tab.');
            $this->assertContains('overview', $panels);
            $this->assertContains('activity', $panels);
            if ($role === 'finance_staff') {
                $this->assertStringContainsString('Q-ROLE-PRIVATE', $html);
                $this->assertStringContainsString('I-ROLE-PRIVATE', $html);
                $this->assertContains('finance', $panels);
            } else {
                $this->assertStringNotContainsString('Q-ROLE-PRIVATE', $html);
                $this->assertStringNotContainsString('I-ROLE-PRIVATE', $html);
                $this->assertNotContains('finance', $panels);
            }
        }
    }

    private function newCase(string $status): int
    {
        return $this->db->insert(
            'INSERT INTO cases (number, customer_id, document_title, status_id) VALUES (?, ?, ?, (SELECT id FROM workflow_statuses WHERE code = ?))',
            ['ATT-OV-' . bin2hex(random_bytes(4)), $this->customerId, 'Overview fixture document', $status]
        );
    }

    private function document(?int $caseId, string $name, bool $visible): void
    {
        $this->db->insert(
            'INSERT INTO documents (case_id, category, original_name, stored_name, mime, size_bytes, customer_visible) VALUES (?, ?, ?, ?, ?, 10, ?)',
            [$caseId, 'completed', $name, $name, 'application/pdf', $visible ? 1 : 0]
        );
    }

    private function get(array $query = [], bool $json = false): Request
    {
        return new Request('GET', '/admin', $query, [], [], $json ? ['HTTP_ACCEPT' => 'application/json'] : [], []);
    }

    private function body(Response $response): string
    {
        return (new ReflectionProperty(Response::class, 'body'))->getValue($response);
    }
}
