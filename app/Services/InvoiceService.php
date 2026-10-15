<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Money;

final class InvoiceService
{
    public function __construct(
        private Database $db,
        private NumberService $numbers,
        private AuditService $audit,
        private PdfService $pdf,
        private MailService $mail,
        private NotificationService $notifications
    ) {
    }

    public function createFromQuotation(int $quotationId, int $userId): int
    {
        return $this->db->transaction(function () use ($quotationId, $userId): int {
        // Lock the parent even when no invoice exists, so concurrent conversions serialize.
        $quotation = $this->db->fetch('SELECT * FROM quotations WHERE id = ? FOR UPDATE', [$quotationId]);
        if ($quotation === null) {
            throw new \RuntimeException('Quotation not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM invoices WHERE quotation_id = ? AND status <> ?',
            [$quotationId, 'cancelled']
        );
        if ($existing) {
            return (int) $existing['id'];
        }
        $items = $this->db->fetchAll('SELECT * FROM quotation_items WHERE quotation_id = ?', [$quotationId]);
        $days = (int) setting('invoice_due_days', 7);
        $invoiceId = $this->db->insert(
            'INSERT INTO invoices (number, case_id, customer_id, quotation_id, status, currency, subtotal, discount_amount, tax_amount, additional_amount, total, amount_paid, issued_at, due_at, notes, terms, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), ?, ?, ?, ?)',
            [
                $this->numbers->next('invoice', 'INV'),
                $quotation['case_id'],
                $quotation['customer_id'],
                $quotationId,
                'issued',
                $quotation['currency'],
                $quotation['subtotal'],
                $quotation['discount_amount'],
                $quotation['tax_amount'],
                $quotation['additional_amount'],
                $quotation['total'],
                date('Y-m-d', strtotime('+' . $days . ' days')),
                $quotation['notes'],
                $quotation['terms'] ?: setting('invoice_terms', ''),
                $userId,
            ]
        );
        foreach ($items as $item) {
            $this->db->insert(
                'INSERT INTO invoice_items (invoice_id, service_id, description, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)',
                [$invoiceId, $item['service_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['line_total']]
            );
        }
        $customer = $this->db->fetch('SELECT user_id FROM customers WHERE id = ?', [$quotation['customer_id']]);
        $number = (string) $this->db->fetchColumn('SELECT number FROM invoices WHERE id = ?', [$invoiceId]);
        if ($customer && $customer['user_id']) {
            $this->notifications->notify(
                (int) $customer['user_id'],
                __('notifications.invoice_title'),
                __('notifications.invoice_body', ['number' => $number]),
                '/portal/invoices/' . $invoiceId
            );
        }
        $this->audit->log($userId, 'invoice_created', 'invoice', $invoiceId, ['quotation_id' => $quotationId, 'total' => $quotation['total']]);

        return $invoiceId;
        });
    }

    public function send(int $invoiceId, int $staffId): array
    {
        $bundle = $this->bundle($invoiceId);
        $path = $this->pdf->invoice($bundle['invoice'], $bundle['items'], $bundle['customer'], $bundle['case']);
        $result = $this->mail->send(
            $bundle['customer']['email'],
            __('mail.invoice_subject', ['number' => $bundle['invoice']['number']]),
            'invoice',
            [
                'name' => $bundle['customer']['name'],
                'number' => $bundle['invoice']['number'],
                'total' => money($bundle['invoice']['total'], $bundle['invoice']['currency']),
                'due' => format_date($bundle['invoice']['due_at']),
                'link' => absolute_url('/portal/invoices/' . $invoiceId),
            ],
            'invoice',
            $invoiceId,
            $path,
            $bundle['invoice']['number'] . '.pdf'
        );
        if ($result['status'] === 'sent') {
            $this->db->run('UPDATE invoices SET status = CASE WHEN status = \'issued\' THEN \'sent\' ELSE status END, sent_at = NOW() WHERE id = ?', [$invoiceId]);
        }
        $this->audit->log($staffId, 'invoice_emailed', 'invoice', $invoiceId, ['mail_status' => $result['status']]);

        return $result;
    }

    public function cancel(int $invoiceId, int $staffId, string $reason): void
    {
        $this->db->transaction(function () use ($invoiceId, $staffId, $reason): void {
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ? FOR UPDATE', [$invoiceId]);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found.');
        }
        if (Money::cmp($invoice['amount_paid'], '0') > 0) {
            throw new \RuntimeException('Record a refund before cancelling an invoice that has payments.');
        }
        if (in_array($invoice['status'], ['cancelled', 'paid'], true)) {
            throw new \RuntimeException('This invoice cannot be cancelled.');
        }
        $this->db->run(
            'UPDATE invoices SET status = ?, cancelled_at = NOW(), cancel_reason = ? WHERE id = ?',
            ['cancelled', $reason, $invoiceId]
        );
        $this->audit->log($staffId, 'invoice_cancelled', 'invoice', $invoiceId, ['reason' => $reason]);
        });
    }

    public function bundle(int $invoiceId): array
    {
        $invoice = $this->db->fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if ($invoice === null) {
            throw new \RuntimeException('Invoice not found.');
        }
        $items = $this->db->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ?', [$invoiceId]);
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$invoice['customer_id']]);
        $case = $this->db->fetch(
            'SELECT c.*, ct.name AS country_name FROM cases c LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = \'en\' WHERE c.id = ?',
            [$invoice['case_id']]
        );
        $payments = $this->db->fetchAll('SELECT * FROM payments WHERE invoice_id = ? ORDER BY paid_at', [$invoiceId]);

        return compact('invoice', 'items', 'customer', 'case', 'payments');
    }

    public function pdfPath(int $invoiceId): string
    {
        $bundle = $this->bundle($invoiceId);

        return $this->pdf->invoice($bundle['invoice'], $bundle['items'], $bundle['customer'], $bundle['case']);
    }

    public function refreshOverdue(): int
    {
        $count = $this->db->run(
            'UPDATE invoices SET status = \'overdue\'
             WHERE due_at < CURDATE() AND status IN (\'issued\', \'sent\', \'partially_paid\') AND amount_paid < total'
        );

        return $count->rowCount();
    }
}
