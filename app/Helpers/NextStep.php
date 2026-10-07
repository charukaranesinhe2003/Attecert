<?php

declare(strict_types=1);

namespace App\Helpers;

final class NextStep
{
    public static function forCase(array $case, array $quotations, array $invoices, ?array $mofa, ?array $embassy): array
    {
        $status = (string) ($case['status_code'] ?? '');
        $latestQuote = $quotations[0] ?? null;
        $latestInvoice = $invoices[0] ?? null;
        $quoteStatus = (string) ($latestQuote['status'] ?? '');
        $invoiceStatus = (string) ($latestInvoice['status'] ?? '');

        if ($status === 'completed') {
            return ['label' => 'This case is complete.', 'tab' => 'overview'];
        }
        if ($status === 'cancelled') {
            return ['label' => 'This case is cancelled. Reopen it only if work should continue.', 'tab' => 'overview'];
        }
        if ($quotations === []) {
            return ['label' => 'Review the request, confirm the services, and prepare a quotation.', 'tab' => 'quote'];
        }
        if (in_array($quoteStatus, ['draft'], true)) {
            return ['label' => 'A draft quotation is ready. Check the total, then send it to the customer.', 'tab' => 'quote'];
        }
        if (in_array($quoteStatus, ['sent', 'viewed'], true)) {
            return ['label' => 'The quotation has been sent. Wait for the customer, or follow up if it is close to expiry.', 'tab' => 'quote'];
        }
        if ($latestInvoice && in_array($invoiceStatus, ['issued', 'sent', 'partially_paid', 'overdue'], true)) {
            return ['label' => 'The quotation is accepted. Send the invoice if needed, then record the payment.', 'tab' => 'finance'];
        }
        if (($mofa['status'] ?? '') === 'completed' && in_array(($embassy['status'] ?? 'not_started'), ['', 'not_started'], true)) {
            return ['label' => 'MOFA is complete. Upload the returned document if it is not already here, then start embassy processing.', 'tab' => 'processing'];
        }
        if (in_array($status, ['mofa_processing', 'submitted_to_mofa'], true) || ($mofa['status'] ?? '') === 'processing') {
            return ['label' => 'MOFA is in progress. Update the reference and dates, or upload the document when it returns.', 'tab' => 'processing'];
        }
        if (in_array($status, ['embassy_processing', 'submitted_to_embassy'], true)) {
            return ['label' => 'Embassy work is in progress. Update the record, then upload the final document when it returns.', 'tab' => 'processing'];
        }
        if (in_array($invoiceStatus, ['paid'], true) || in_array($status, ['payment_received', 'verification'], true)) {
            return ['label' => 'Payment is recorded. Begin processing and update MOFA or embassy when the file moves.', 'tab' => 'processing'];
        }
        if (in_array($status, ['ready_for_collection', 'ready_for_delivery', 'embassy_completed', 'mofa_completed'], true)) {
            return ['label' => 'Confirm the customer can download the final file, then mark the case completed.', 'tab' => 'documents'];
        }

        return ['label' => 'Update the status when the next real step happens. Internal notes stay hidden from the customer.', 'tab' => 'overview'];
    }
}
