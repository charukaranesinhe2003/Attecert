<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Money;

final class PaymentService
{
    public function __construct(
        private Database $db,
        private NumberService $numbers,
        private AuditService $audit,
        private NotificationService $notifications,
        private MailService $mail,
        private WorkflowService $workflow,
        private PdfService $pdf
    ) {
    }

    public function record(int $invoiceId, array $data, int $staffId): int
    {
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if ($invoice === null || $invoice['status'] === 'cancelled') {
            throw new \RuntimeException('Invoice cannot accept payments.');
        }
        $amount = Money::norm($data['amount']);
        if (Money::cmp($amount, '0') <= 0) {
            throw new \RuntimeException('Enter a payment amount greater than zero.');
        }
        $isRefund = ($data['method'] ?? '') === 'refund';
        if (!$isRefund && Money::cmp($amount, $invoice['amount_due']) > 0) {
            throw new \RuntimeException('Payment exceeds the amount due.');
        }
        if ($isRefund && Money::cmp($amount, $invoice['amount_paid']) > 0) {
            throw new \RuntimeException('Refund exceeds the amount paid.');
        }

        return $this->db->transaction(function () use ($invoice, $data, $amount, $isRefund, $staffId): int {
            $paymentId = $this->db->insert(
                'INSERT INTO payments (number, invoice_id, case_id, customer_id, amount, currency, method, status, paid_at, reference, receipt_reference, notes, recorded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $this->numbers->next('payment', 'PAY'),
                    $invoice['id'],
                    $invoice['case_id'],
                    $invoice['customer_id'],
                    $isRefund ? Money::mul($amount, '-1') : $amount,
                    $invoice['currency'],
                    $data['method'],
                    $isRefund ? 'refunded' : 'completed',
                    $data['paid_at'],
                    $data['reference'] ?? null,
                    $data['receipt_reference'] ?? null,
                    $data['notes'] ?? null,
                    $staffId,
                ]
            );
            $paid = $isRefund ? Money::sub($invoice['amount_paid'], $amount) : Money::add($invoice['amount_paid'], $amount);
            $status = $this->statusFor($paid, (string) $invoice['total'], $isRefund, (string) $invoice['status']);
            $this->db->run('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $status, $invoice['id']]);
            $this->audit->log($staffId, $isRefund ? 'payment_refunded' : 'payment_recorded', 'payment', $paymentId, [
                'invoice_id' => (int) $invoice['id'],
                'amount' => $amount,
            ]);
            $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$invoice['customer_id']]);
            if (!$isRefund && $customer) {
                $this->notifications->notify(
                    $customer['user_id'] !== null ? (int) $customer['user_id'] : null,
                    __('notifications.payment_title'),
                    __('notifications.payment_body', ['number' => $invoice['number']]),
                    '/portal/invoices/' . $invoice['id']
                );
                $this->mail->send(
                    $customer['email'],
                    __('mail.payment_subject', ['number' => $invoice['number']]),
                    'payment',
                    [
                        'name' => $customer['name'],
                        'number' => $invoice['number'],
                        'amount' => money($amount, $invoice['currency']),
                        'link' => absolute_url('/portal/invoices/' . $invoice['id']),
                    ],
                    'payment',
                    $paymentId
                );
            }
            if ($status === 'paid') {
                $this->workflow->change((int) $invoice['case_id'], 'payment_received', $staffId, 'Payment recorded', true, false);
            }

            return $paymentId;
        });
    }

    public function receiptPath(int $paymentId): string
    {
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ?', [$payment['invoice_id']]);
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$payment['customer_id']]);

        return $this->pdf->receipt($payment, $invoice, $customer);
    }

    private function statusFor(string $paid, string $total, bool $refund, string $current): string
    {
        if ($current === 'cancelled') {
            return 'cancelled';
        }
        if (Money::cmp($paid, '0') <= 0 && $refund) {
            return 'refunded';
        }
        if (Money::cmp($paid, '0') <= 0) {
            return 'issued';
        }
        if (Money::cmp($paid, $total) >= 0) {
            return 'paid';
        }

        return 'partially_paid';
    }
}
