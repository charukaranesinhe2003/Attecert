<?php

declare(strict_types=1);

namespace App\Helpers;

final class NextStep
{
    public static function forCase(array $case, array $quotations, array $invoices, ?array $mofa, ?array $embassy): array
    {
        $status = (string) ($case['status_code'] ?? '');
        $quoteStatus = (string) ($quotations[0]['status'] ?? '');
        $hasPaidInvoice = array_filter($invoices, static fn (array $invoice): bool => ($invoice['status'] ?? '') === 'paid') !== [];
        $pendingInvoices = array_filter($invoices, static fn (array $invoice): bool => in_array((string) ($invoice['status'] ?? ''), ['issued', 'sent', 'partially_paid', 'overdue', 'refunded'], true));
        $plan = self::processingPlan($case, $mofa, $embassy);

        if ($status === 'completed') {
            return ['label' => __('admin_workflow.complete'), 'tab' => 'overview'];
        }
        if ($status === 'cancelled') {
            return ['label' => __('admin_workflow.cancelled'), 'tab' => 'overview'];
        }
        if (in_array($status, ['ready_for_collection', 'ready_for_delivery'], true)) {
            return ['label' => __('admin_workflow.ready'), 'tab' => 'documents'];
        }
        if ($status === 'translation_processing') {
            return ['label' => __('admin_workflow.translation_active'), 'tab' => 'documents'];
        }
        if (in_array(self::processStatus($case, $embassy, 'embassy'), ['processing', 'submitted'], true)) {
            return ['label' => __('admin_workflow.embassy_active'), 'tab' => 'processing'];
        }
        if (in_array(self::processStatus($case, $mofa, 'mofa'), ['processing', 'submitted'], true)) {
            return ['label' => __('admin_workflow.mofa_active'), 'tab' => 'processing'];
        }
        $mofaComplete = self::processStatus($case, $mofa, 'mofa') === 'completed';
        $embassyComplete = self::processStatus($case, $embassy, 'embassy') === 'completed';
        if ($mofaComplete || $embassyComplete) {
            if ($plan['embassy'] && !$embassyComplete) {
                return ['label' => __('admin_workflow.start_embassy'), 'tab' => 'processing'];
            }
            if ($plan['mofa'] && !$mofaComplete) {
                return ['label' => __('admin_workflow.remaining_mofa'), 'tab' => 'processing'];
            }
            if ($plan['translation']) {
                return ['label' => __('admin_workflow.confirm_translation'), 'tab' => 'documents'];
            }
            return ['label' => __('admin_workflow.release_files'), 'tab' => 'documents'];
        }
        if ($pendingInvoices !== []) {
            return ['label' => __('admin_workflow.outstanding_invoices'), 'tab' => 'finance'];
        }
        if ($hasPaidInvoice || in_array($status, ['payment_received', 'verification'], true)) {
            if ($plan['translation'] && !$plan['mofa'] && !$plan['embassy']) {
                return ['label' => __('admin_workflow.paid_translation'), 'tab' => 'documents'];
            }
            if ($plan['mofa'] || $plan['embassy']) {
                return ['label' => __('admin_workflow.paid_processing'), 'tab' => 'processing'];
            }
            return ['label' => __('admin_workflow.paid_other'), 'tab' => 'documents'];
        }
        if ($quotations === []) {
            return ['label' => __('admin_workflow.prepare_quote'), 'tab' => 'quote'];
        }
        if ($quoteStatus === 'draft') {
            return ['label' => __('admin_workflow.draft_quote'), 'tab' => 'quote'];
        }
        if (in_array($quoteStatus, ['sent', 'viewed'], true)) {
            return ['label' => __('admin_workflow.sent_quote'), 'tab' => 'quote'];
        }
        if (in_array($quoteStatus, ['rejected', 'expired'], true)) {
            return ['label' => __('admin_workflow.revise_quote'), 'tab' => 'quote'];
        }
        if ($quoteStatus === 'accepted' && $invoices === []) {
            return ['label' => __('admin_workflow.generate_invoice'), 'tab' => 'quote'];
        }

        return ['label' => __('admin_workflow.update_actual_stage'), 'tab' => 'overview'];
    }

    /** A service selection, an actual process record, or an explicit stage makes a branch relevant. */
    public static function processingPlan(array $case, ?array $mofa, ?array $embassy): array
    {
        $status = (string) ($case['status_code'] ?? '');
        $plan = [
            'mofa' => $mofa !== null || str_contains($status, 'mofa'),
            'embassy' => $embassy !== null || str_contains($status, 'embassy'),
            'translation' => $status === 'translation_processing',
            'other' => false,
        ];
        foreach ($case['services'] ?? [] as $service) {
            $category = strtolower((string) ($service['category'] ?? ''));
            // These codes also support callers with older service projections.
            $category = match (strtoupper((string) ($service['code'] ?? ''))) {
                'TRANS' => 'translation', 'MOFA-APP', 'MOFA-ATT' => 'mofa', 'EMB-LEG', 'EMB-SVC' => 'embassy',
                default => $category,
            };
            if (array_key_exists($category, $plan)) {
                $plan[$category] = true;
            } elseif ($category !== 'delivery') {
                $plan['other'] = true;
            }
        }

        return $plan;
    }

    /** Progress reports the latest recorded branch state, without assuming MOFA is required before embassy. */
    public static function progress(array $case, array $quotations, array $invoices, ?array $mofa, ?array $embassy): array
    {
        $status = (string) ($case['status_code'] ?? '');
        $plan = self::processingPlan($case, $mofa, $embassy);
        $started = $status === 'translation_processing'
            || in_array(self::processStatus($case, $mofa, 'mofa'), ['submitted', 'processing', 'completed'], true)
            || in_array(self::processStatus($case, $embassy, 'embassy'), ['submitted', 'processing', 'completed'], true);
        $ready = in_array($status, ['ready_for_collection', 'ready_for_delivery', 'completed'], true);
        $accepted = in_array((string) ($quotations[0]['status'] ?? ''), ['accepted', 'converted'], true);
        $paid = $invoices !== [] && array_reduce($invoices, static fn (bool $done, array $invoice): bool => $done && in_array($invoice['status'], ['paid', 'cancelled'], true), true)
            && count(array_filter($invoices, static fn (array $invoice): bool => $invoice['status'] === 'paid')) > 0;
        $progress = [
            ['key' => 'received', 'label' => __('admin.step_received'), 'done' => true],
            ['key' => 'documents', 'label' => __('admin.step_documents'), 'done' => !in_array($status, ['new_request', 'under_review', 'awaiting_documents'], true)],
            ['key' => 'quotation', 'label' => __('admin.step_quotation'), 'done' => $accepted || $invoices !== []],
            ['key' => 'payment', 'label' => __('admin.step_payment'), 'done' => $paid || ($invoices === [] && $status === 'payment_received')],
        ];
        foreach (['mofa', 'embassy'] as $kind) {
            if ($plan[$kind]) {
                $progress[] = ['key' => $kind, 'label' => __('admin.step_' . $kind), 'done' => self::processStatus($case, $kind === 'mofa' ? $mofa : $embassy, $kind) === 'completed'];
            }
        }
        if ($plan['translation']) {
            $progress[] = ['key' => 'translation', 'label' => __('services.translation'), 'done' => $ready];
        }
        if ($plan['other']) {
            $progress[] = ['key' => 'processing', 'label' => __('admin.tab_processing'), 'done' => $ready];
        }
        $progress[] = ['key' => 'completed', 'label' => __('admin.step_completed'), 'done' => $status === 'completed'];
        $current = match (true) {
            $ready => 'completed',
            $status === 'translation_processing' => 'translation',
            in_array($status, ['submitted_to_embassy', 'embassy_processing'], true) && $embassy === null => 'embassy',
            in_array((string) ($embassy['status'] ?? ''), ['submitted', 'processing'], true) => 'embassy',
            in_array($status, ['submitted_to_mofa', 'mofa_processing'], true) && $mofa === null => 'mofa',
            in_array((string) ($mofa['status'] ?? ''), ['submitted', 'processing'], true) => 'mofa',
            default => null,
        };
        if ($current === null && $started) {
            foreach ($progress as $step) {
                if (in_array($step['key'], ['mofa', 'embassy', 'translation', 'processing', 'completed'], true) && !$step['done']) {
                    $current = $step['key'];
                    break;
                }
            }
        }
        foreach ($progress as &$step) {
            if ($current === null && !$step['done'] && $status !== 'cancelled') {
                $current = $step['key'];
            }
            $step['current'] = $status !== 'cancelled' && $step['key'] === $current;
        }
        unset($step);

        return $progress;
    }

    private static function processStatus(array $case, ?array $process, string $kind): string
    {
        if ($process !== null) {
            return (string) ($process['status'] ?? 'not_started');
        }
        return match ((string) ($case['status_code'] ?? '')) {
            'submitted_to_' . $kind => 'submitted',
            $kind . '_processing' => 'processing',
            $kind . '_completed' => 'completed',
            default => 'not_started',
        };
    }
}
