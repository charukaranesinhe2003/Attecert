<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Helpers\Decimal;
use App\Helpers\Money;

final class CasePaymentService
{
    public function __construct(private Database $db, private CaseAccessService $access, private AuthService $auth,
        private Validator $validator, private NumberService $numbers, private AuditService $audit,
        private CustomerUpdateService $updates, private WorkflowService $workflow) {}

    private function authorize(int $caseId): array
    {
        if (!$this->auth->isStaff() || !$this->auth->can('payments.create')) { throw new HttpException(403); }
        $this->access->requireCase($caseId, true);
        $case = $this->db->fetch('SELECT c.* FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
            WHERE c.id = ? AND c.deleted_at IS NULL AND cu.deleted_at IS NULL', [$caseId]);
        if ($case === null) { throw new HttpException(404, 'Case not found.'); }
        return $case;
    }

    public function record(int $caseId, array $input): int
    {
        $data = $this->validator->validate($input, ['amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,card,online,other', 'paid_at' => 'required|date',
            'invoice_id' => 'nullable|integer|min:1', 'reference' => 'nullable|max:80',
            'notes' => 'nullable|max:1000', 'request_key' => 'required|max:32']);
        $amount = Decimal::amount($data['amount'], 'amount', '9999999999.99', '0.01');
        if (!preg_match('/^[a-f0-9]{32}$/D', (string) $data['request_key'])) {
            throw new ValidationException(['request_key' => 'Reload the case page before adding a payment.']);
        }
        if ($data['paid_at'] > date('Y-m-d')) { throw new ValidationException(['paid_at' => 'The payment date cannot be in the future.']); }
        return $this->db->transaction(function () use ($caseId, $data, $amount): int {
            $case = $this->authorize($caseId);
            $existing = $this->db->fetch('SELECT id, case_id FROM payments WHERE request_key = ?', [$data['request_key']]);
            if ($existing !== null) {
                if ((int) $existing['case_id'] !== $caseId) { throw new ValidationException(['request_key' => 'Reload the case page before adding a payment.']); }
                return (int) $existing['id'];
            }
            $invoiceId = empty($data['invoice_id']) ? null : (int) $data['invoice_id'];
            if ($invoiceId !== null) {
                $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ? AND case_id = ? FOR UPDATE', [$invoiceId, $caseId]);
                if ($invoice === null) { throw new HttpException(404, 'Invoice not found.'); }
                $this->checkInvoice($invoice, $amount, $case);
            }
            $id = $this->db->insert("INSERT INTO payments (number, invoice_id, case_id, customer_id, amount, currency,
                method, status, paid_at, reference, notes, recorded_by, request_key)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)",
                [$this->numbers->next('payment', 'PAY'), $invoiceId, $caseId, $case['customer_id'], $amount,
                    $case['currency'], $data['method'], $data['paid_at'], $data['reference'], $data['notes'], $this->auth->id(), $data['request_key']]);
            $this->audit->log($this->auth->id(), 'case_payment_added', 'payment', $id, ['case_id' => $caseId, 'amount' => $amount]);
            return $id;
        });
    }

    public function confirm(int $caseId, int $paymentId, ?array $receiptFile = null): array
    {
        return $this->db->transaction(function () use ($caseId, $paymentId, $receiptFile): array {
            $case = $this->authorize($caseId);
            $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ? AND case_id = ? FOR UPDATE', [$paymentId, $caseId]);
            if ($payment === null) { throw new HttpException(404, 'Payment not found.'); }
            $alreadyConfirmed = $payment['status'] === 'completed';
            $hasFile = $receiptFile !== null && $receiptFile !== [] && ($receiptFile['error'] ?? null) !== UPLOAD_ERR_NO_FILE;
            if ($alreadyConfirmed && (!$hasFile || $payment['receipt_document_id'] !== null)) { return ['changed' => false, 'delivery' => null]; }
            if (!$alreadyConfirmed && $payment['status'] !== 'pending') { throw new ValidationException(['payment' => 'Only a pending payment can be confirmed.']); }
            if ($hasFile && !$this->auth->can('documents.upload')) { throw new HttpException(403); }
            $invoiceStatus = null;
            if (!$alreadyConfirmed && $payment['invoice_id'] !== null) {
                $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ? AND case_id = ? FOR UPDATE', [$payment['invoice_id'], $caseId]);
                if ($invoice === null) { throw new HttpException(404, 'Invoice not found.'); }
                $this->checkInvoice($invoice, $payment['amount'], $case);
                $paid = Money::add($invoice['amount_paid'], $payment['amount']);
                $invoiceStatus = Money::cmp($paid, $invoice['total']) >= 0 ? 'paid' : 'partially_paid';
                $this->db->run('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $invoiceStatus, $invoice['id']]);
            }
            $attachment = null;
            $documentId = app(DocumentService::class)->optionalUpload($caseId, $receiptFile, 'payment_receipt', 'Payment confirmation: ' . $payment['number'], (int) $this->auth->id(), true);
            if ($documentId !== null) {
                $this->db->run('UPDATE documents SET customer_visible = 1 WHERE id = ?', [$documentId]);
                $attachment = app(DocumentService::class)->download($documentId, null, (int) $this->auth->id(), true);
                $this->db->run('UPDATE payments SET receipt_document_id = ? WHERE id = ?', [$documentId, $paymentId]);
            }
            if (!$alreadyConfirmed) {
                $this->db->run("UPDATE payments SET status = 'completed', confirmed_at = NOW(), confirmed_by = ? WHERE id = ?", [$this->auth->id(), $paymentId]);
            }
            $this->audit->log($this->auth->id(), $alreadyConfirmed ? 'payment_receipt_pdf_added' : 'case_payment_confirmed', 'payment', $paymentId,
                ['case_id' => $caseId, 'amount' => $payment['amount'], 'document_id' => $documentId]);
            if ($invoiceStatus === 'paid') {
                $this->workflow->advance($caseId, 'payment_received', $this->auth->id(), 'Payment confirmed', true, false);
            }
            app(ProgressHistoryService::class)->record($caseId, $alreadyConfirmed ? 'Payment confirmation document added' : 'Payment received', '', 'completed',
                $payment['paid_at'], null, null, $alreadyConfirmed ? 'payment_document_added' : 'payment_confirmed');
            $this->db->run('UPDATE cases SET updated_at = NOW() WHERE id = ?', [$caseId]);
            $delivery = $this->updates->send($caseId, $alreadyConfirmed ? 'Payment confirmation document added' : 'Payment received: ' . money($payment['amount'], $payment['currency']),
                'We have confirmed receipt of your payment. Thank you.', 'status', null, [
                    'Receipt number' => $payment['number'], 'Amount received' => money($payment['amount'], $payment['currency']),
                    'Payment date' => format_date($payment['paid_at']), 'Payment method' => ucwords(str_replace('_', ' ', $payment['method'])),
                    'Reference' => $payment['reference'] ?: '—',
                ], $attachment);
            return ['changed' => true, 'delivery' => $delivery, 'message' => $alreadyConfirmed ? 'Payment confirmation PDF added.' : 'Payment receipt confirmed.'];
        });
    }

    public function cancel(int $caseId, int $paymentId): void
    {
        $this->db->transaction(function () use ($caseId, $paymentId): void {
            $this->authorize($caseId);
            $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ? AND case_id = ? FOR UPDATE', [$paymentId, $caseId]);
            if ($payment === null) { throw new HttpException(404, 'Payment not found.'); }
            if ($payment['status'] !== 'pending') { throw new ValidationException(['payment' => 'Only a pending payment can be cancelled.']); }
            $this->db->run("UPDATE payments SET status = 'cancelled' WHERE id = ?", [$paymentId]);
            $this->audit->log($this->auth->id(), 'case_payment_cancelled', 'payment', $paymentId, ['case_id' => $caseId]);
        });
    }

    private function checkInvoice(array $invoice, string $amount, array $case): void
    {
        if ($invoice['status'] === 'cancelled' || $invoice['currency'] !== $case['currency'] || (int) $invoice['customer_id'] !== (int) $case['customer_id']) {
            throw new ValidationException(['invoice_id' => 'This invoice cannot accept the payment.']);
        }
        if (Money::cmp($amount, $invoice['amount_due']) > 0) { throw new ValidationException(['amount' => 'Payment exceeds the invoice amount due.']); }
    }
}
