<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\Admin\CaseController;
use App\Controllers\Admin\ProcessController;
use App\Core\Request;
use App\Helpers\NextStep;
use App\Services\CaseService;
use App\Services\PaymentService;
use App\Services\QuotationService;
use App\Services\WorkflowService;

final class WorkflowCompletionTest extends DatabaseTestCase
{
    public function testLateQuotationAcceptanceAndPaymentDoNotRegressProcessing(): void
    {
        $this->setStatus('embassy_processing');
        $history = $this->historyCount();
        $audit = $this->statusAuditCount();
        $quoteId = app(QuotationService::class)->save($this->caseId, $this->quotePayload(), $this->adminId, true, true);
        $invoiceId = app(QuotationService::class)->accept($quoteId, $this->adminId, '127.0.0.1');
        app(PaymentService::class)->record($invoiceId, ['amount' => '100', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d')], $this->adminId);

        self::assertSame('embassy_processing', $this->caseStatus());
        self::assertSame('paid', $this->db->fetchColumn('SELECT status FROM invoices WHERE id = ?', [$invoiceId]));
        self::assertSame($history, $this->historyCount());
        self::assertSame($audit, $this->statusAuditCount());
    }

    public function testLateProcessSnapshotsRemainRecordedWithoutRegressingLaterOrClosedCases(): void
    {
        $controller = app(ProcessController::class);
        $this->setStatus('embassy_processing');
        $history = $this->historyCount();
        $controller->saveMofa($this->request(['status' => 'submitted', 'update_case' => '1', 'notes' => 'Late filing entry']), (string) $this->caseId);
        self::assertSame('embassy_processing', $this->caseStatus());
        self::assertSame('submitted', $this->db->fetchColumn('SELECT status FROM mofa_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [$this->caseId]));
        $this->setStatus('ready_for_collection');
        $controller->saveEmbassy($this->request(['status' => 'completed', 'embassy_name' => 'Fixture Embassy', 'update_case' => '1']), (string) $this->caseId);
        self::assertSame('ready_for_collection', $this->caseStatus());
        $this->setStatus('completed');
        $controller->saveMofa($this->request(['status' => 'processing', 'update_case' => '1']), (string) $this->caseId);
        self::assertSame('completed', $this->caseStatus());
        self::assertSame($history, $this->historyCount());
        self::assertSame(2, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['mofa_updated', $this->caseId]));
        self::assertSame(1, (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['embassy_updated', $this->caseId]));
    }

    public function testAutomaticUpdatesCannotReopenTerminalCasesAndNoopsHaveNoSideEffects(): void
    {
        $workflow = app(WorkflowService::class);
        foreach (['completed', 'cancelled'] as $closed) {
            $this->setStatus($closed);
            $before = $this->sideEffects();
            $timestamps = $this->db->fetch('SELECT completed_at, cancelled_at FROM cases WHERE id = ?', [$this->caseId]);
            foreach (['quotation_sent', 'invoice_generated', 'payment_received', 'mofa_completed', 'embassy_completed', 'completed'] as $target) {
                self::assertFalse($workflow->advance($this->caseId, $target, $this->adminId, 'Late event', true));
            }
            self::assertFalse($workflow->change($this->caseId, $closed, $this->adminId, null, true));
            self::assertSame($closed, $this->caseStatus());
            self::assertSame($before, $this->sideEffects());
            self::assertSame($timestamps, $this->db->fetch('SELECT completed_at, cancelled_at FROM cases WHERE id = ?', [$this->caseId]));
        }
    }

    public function testAdvanceIsIdempotentAndBackwardManualChangeRequiresReasonAndConfirmation(): void
    {
        $this->setStatus('new_request');
        $workflow = app(WorkflowService::class);
        self::assertTrue($workflow->advance($this->caseId, 'payment_received', $this->adminId, 'Paid in full', true));
        $after = $this->sideEffects();
        self::assertFalse($workflow->advance($this->caseId, 'payment_received', $this->adminId, 'Duplicate event', true));
        self::assertFalse($workflow->advance($this->caseId, 'quotation_sent', $this->adminId, 'Late quotation', true));
        self::assertSame($after, $this->sideEffects());
        foreach ([[null, false], ['Correction needed', false], [' ', true]] as [$reason, $confirmed]) {
            try {
                $workflow->change($this->caseId, 'under_review', $this->adminId, $reason, false, false, $confirmed);
                self::fail('A backwards transition requires both a reason and confirmation.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('reason and explicit confirmation', $exception->getMessage());
            }
        }
        self::assertTrue($workflow->change($this->caseId, 'under_review', $this->adminId, 'Corrected the wrong case', false, false, true));
        self::assertSame('under_review', $this->caseStatus());
        $metadata = json_decode($this->db->fetchColumn('SELECT metadata FROM audit_logs WHERE action = ? AND entity_id = ? ORDER BY id DESC LIMIT 1', ['case_status_changed', $this->caseId]), true);
        self::assertTrue($metadata['confirmed_reversal']);
        self::assertSame('payment_received', $metadata['from_status']);
    }

    public function testManualTransitionsCanSkipUnselectedStagesAndReopenWithAnAuditedReason(): void
    {
        $this->setStatus('under_review');
        $workflow = app(WorkflowService::class);
        self::assertTrue($workflow->change($this->caseId, 'translation_processing', $this->adminId, 'Translation only', false, false));
        self::assertTrue($workflow->change($this->caseId, 'completed', $this->adminId, 'Delivered translation', true, false));
        self::assertNotNull($this->db->fetchColumn('SELECT completed_at FROM cases WHERE id = ?', [$this->caseId]));
        try {
            $workflow->change($this->caseId, 'translation_processing', $this->adminId, 'Customer requested correction', false, false);
            self::fail('Closed cases require explicit confirmation.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('closed case', $exception->getMessage());
        }
        self::assertTrue($workflow->change($this->caseId, 'translation_processing', $this->adminId, 'Customer requested correction', false, false, true));
        self::assertNull($this->db->fetchColumn('SELECT completed_at FROM cases WHERE id = ?', [$this->caseId]));
        self::assertNull($this->db->fetchColumn('SELECT cancelled_at FROM cases WHERE id = ?', [$this->caseId]));
        self::assertSame('Customer requested correction', $this->db->fetchColumn('SELECT notes FROM case_status_history WHERE case_id = ? ORDER BY id DESC LIMIT 1', [$this->caseId]));
    }

    public function testManualControllerRequiresSubmittedReversalConfirmation(): void
    {
        $this->setStatus('completed');
        $controller = app(CaseController::class);
        $controller->status($this->request(['status' => 'under_review', 'notes' => 'Customer correction']), (string) $this->caseId);
        self::assertSame('completed', $this->caseStatus());
        $controller->status($this->request(['status' => 'under_review', 'notes' => 'Customer correction', 'confirm_reversal' => '1']), (string) $this->caseId);
        self::assertSame('under_review', $this->caseStatus());
    }

    public function testInactiveStatusesAreRejectedWithoutChangingTheCase(): void
    {
        $this->setStatus('new_request');
        $this->db->run('UPDATE workflow_statuses SET is_active = 0 WHERE code = ?', ['under_review']);
        $before = $this->sideEffects();
        try {
            app(WorkflowService::class)->change($this->caseId, 'under_review', $this->adminId, 'Test', false);
            self::fail('Inactive status must not be accepted.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('active case status', $exception->getMessage());
        }
        self::assertSame('new_request', $this->caseStatus());
        self::assertSame($before, $this->sideEffects());
    }

    public function testAutomaticEventsCannotReplaceAStatusAtTheSameConfiguredOrder(): void
    {
        $this->setStatus('under_review');
        $this->db->run('UPDATE workflow_statuses SET sort_order = (SELECT sort_value FROM (SELECT sort_order AS sort_value FROM workflow_statuses WHERE code = ?) current_stage) WHERE code = ?', ['under_review', 'quotation_sent']);
        $before = $this->sideEffects();
        self::assertFalse(app(WorkflowService::class)->advance($this->caseId, 'quotation_sent', $this->adminId, 'Late event', true));
        self::assertSame('under_review', $this->caseStatus());
        self::assertSame($before, $this->sideEffects());
    }

    public function testTranslationAndEmbassyOnlyCasesShowRelevantProgressAndGuidance(): void
    {
        $translation = ['status_code' => 'payment_received', 'services' => [['category' => 'translation', 'code' => 'TRANS']]];
        $step = NextStep::forCase($translation, [], [['status' => 'paid']], null, null);
        self::assertStringContainsString('translation', $step['label']);
        self::assertStringNotContainsString('embassy', strtolower($step['label']));
        self::assertStringNotContainsString('mofa', strtolower($step['label']));
        $progress = NextStep::progress($translation, [], [['status' => 'paid']], null, null);
        self::assertContains('translation', array_column($progress, 'key'));
        self::assertNotContains('embassy', array_column($progress, 'key'));
        self::assertNotContains('mofa', array_column($progress, 'key'));
        $embassy = ['status_code' => 'embassy_processing', 'services' => [['category' => 'embassy']]];
        $progress = NextStep::progress($embassy, [], [], null, ['status' => 'processing']);
        self::assertNotContains('mofa', array_column($progress, 'key'));
        self::assertSame('embassy', array_values(array_filter($progress, static fn (array $step): bool => $step['current']))[0]['key']);
        self::assertStringContainsString('Embassy work is in progress', NextStep::forCase($embassy, [], [], null, ['status' => 'processing'])['label']);
    }

    public function testReadyAndCompleteStatesPrecedeFinanceAndLatestSnapshotsDetermineProgress(): void
    {
        foreach (['ready_for_collection', 'ready_for_delivery', 'completed', 'cancelled'] as $status) {
            $case = ['status_code' => $status, 'services' => [['category' => 'translation']]];
            $step = NextStep::forCase($case, [['status' => 'draft']], [['status' => 'paid']], null, null);
            self::assertSame(in_array($status, ['completed', 'cancelled'], true) ? 'overview' : 'documents', $step['tab']);
            self::assertStringNotContainsString('quotation', $step['label']);
        }
        $case = ['status_code' => 'embassy_completed', 'services' => [['category' => 'mofa'], ['category' => 'embassy']]];
        $progress = array_column(NextStep::progress($case, [], [], ['status' => 'processing'], ['status' => 'completed']), null, 'key');
        self::assertFalse($progress['mofa']['done']);
        self::assertTrue($progress['mofa']['current']);
        self::assertTrue($progress['embassy']['done']);
        $mofaOnly = ['status_code' => 'mofa_completed', 'services' => [['category' => 'mofa']]];
        $step = NextStep::forCase($mofaOnly, [], [['status' => 'paid']], ['status' => 'completed'], null);
        self::assertSame('documents', $step['tab']);
        self::assertStringNotContainsString('embassy', strtolower($step['label']));
        $mofaOnly['services'][] = ['category' => 'embassy'];
        self::assertStringContainsString('selected embassy service', NextStep::forCase($mofaOnly, [], [], ['status' => 'completed'], null)['label']);
    }

    public function testAnonymousTrackingTimelineDoesNotExposeActorsOrFreeTextNotes(): void
    {
        $workflow = app(WorkflowService::class);
        $this->setStatus('new_request');
        $workflow->change($this->caseId, 'under_review', $this->adminId, 'Passport owner private@example.test; call staff Jane', true, false);
        $workflow->change($this->caseId, 'awaiting_documents', $this->adminId, 'Internal-only passport detail', false, false);
        $service = app(CaseService::class);
        $case = $service->find($this->caseId);
        $result = $service->track($case['number']);
        self::assertNotEmpty($result['history']);
        foreach ($result['history'] as $row) {
            self::assertSame(['code', 'badge_tone', 'name', 'created_at', 'notes'], array_keys($row));
            self::assertNull($row['notes']);
            self::assertNotSame('awaiting_documents', $row['code']);
        }
        self::assertStringNotContainsString('private@example.test', json_encode($result['history']));
        $customer = $service->forCustomer($this->caseId, (int) $case['customer_id']);
        self::assertStringContainsString('private@example.test', json_encode($customer['history']));
        self::assertNotEmpty(array_filter($case['history'], static fn (array $row): bool => $row['staff_name'] !== null));
        self::assertSame('mofa', array_values(array_filter($case['services'], static fn (array $row): bool => $row['code'] === 'MOFA-ATT'))[0]['category']);
    }

    public function testOlderOutstandingAndRefundedInvoicesAreNotHiddenByANewerPaidInvoice(): void
    {
        $case = ['status_code' => 'payment_received', 'services' => [['category' => 'translation']]];
        foreach (['issued', 'partially_paid', 'overdue', 'refunded'] as $status) {
            $invoices = [['status' => 'paid'], ['status' => $status]];
            self::assertSame('finance', NextStep::forCase($case, [['status' => 'converted']], $invoices, null, null)['tab']);
            $progress = array_column(NextStep::progress($case, [], $invoices, null, null), null, 'key');
            self::assertFalse($progress['payment']['done']);
        }
    }

    public function testWorkflowGuidanceUsesTheSelectedSinhalaOrTamilLocale(): void
    {
        foreach (['si' => '/[\x{0D80}-\x{0DFF}]/u', 'ta' => '/[\x{0B80}-\x{0BFF}]/u'] as $locale => $script) {
            $_SESSION['locale'] = $locale;
            $case = ['status_code' => 'translation_processing', 'services' => [['category' => 'translation']]];
            self::assertMatchesRegularExpression($script, NextStep::forCase($case, [], [], null, null)['label']);
            $progress = array_column(NextStep::progress($case, [], [], null, null), null, 'key');
            self::assertMatchesRegularExpression($script, $progress['translation']['label']);
        }
    }

    public function testAuditFailureRollsBackStatusAndHistoryInAnOwnedTransaction(): void
    {
        // DDL and the owned transaction are isolated to the disposable fixture database.
        $this->db->pdo()->rollBack();
        $before = $this->sideEffects();
        $status = $this->caseStatus();
        $trigger = 'workflow_audit_' . bin2hex(random_bytes(5));
        $this->db->run('CREATE TRIGGER ' . $trigger . " BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Fixture audit failure'");
        try {
            try {
                app(WorkflowService::class)->change($this->caseId, 'completed', $this->adminId, 'Fixture completion', true);
                self::fail('The fixture audit trigger must fail.');
            } catch (\PDOException $exception) {
                self::assertStringContainsString('Fixture audit failure', $exception->getMessage());
            }
            self::assertSame($status, $this->caseStatus());
            self::assertSame($before, $this->sideEffects());
        } finally {
            $this->db->run('DROP TRIGGER IF EXISTS ' . $trigger);
            $this->db->pdo()->beginTransaction();
        }
    }

    private function request(array $body): Request
    {
        return new Request('POST', '/admin', [], $body, [], $_SERVER, []);
    }

    private function quotePayload(): array
    {
        return ['lines' => [['description' => 'Late financial entry', 'quantity' => '1', 'unit_price' => '100']], 'validity_days' => 14];
    }

    private function setStatus(string $code): void
    {
        $this->db->run('UPDATE cases SET status_id = ?, completed_at = ?, cancelled_at = ? WHERE id = ?', [
            app(WorkflowService::class)->idFor($code), $code === 'completed' ? '2025-01-01 10:00:00' : null,
            $code === 'cancelled' ? '2025-01-02 10:00:00' : null, $this->caseId,
        ]);
    }

    private function caseStatus(): string
    {
        return (string) $this->db->fetchColumn('SELECT s.code FROM cases c INNER JOIN workflow_statuses s ON s.id = c.status_id WHERE c.id = ?', [$this->caseId]);
    }

    private function historyCount(): int
    {
        return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM case_status_history WHERE case_id = ?', [$this->caseId]);
    }

    private function statusAuditCount(): int
    {
        return (int) $this->db->fetchColumn('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND entity_id = ?', ['case_status_changed', $this->caseId]);
    }

    private function sideEffects(): array
    {
        return [
            $this->historyCount(), $this->statusAuditCount(),
            (int) $this->db->fetchColumn('SELECT COUNT(*) FROM notifications'),
            (int) $this->db->fetchColumn('SELECT COUNT(*) FROM email_logs'),
        ];
    }
}
