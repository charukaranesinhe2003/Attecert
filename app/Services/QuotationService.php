<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Money;
use App\Helpers\Decimal;

final class QuotationService
{
    public function __construct(
        private Database $db,
        private NumberService $numbers,
        private CatalogService $catalog,
        private AuditService $audit,
        private PdfService $pdf,
        private MailService $mail,
        private NotificationService $notifications,
        private WorkflowService $workflow,
        private InvoiceService $invoices
    ) {
    }

    public function calculate(int $caseId, array $lines, string $discount, string $taxRate, string $additional, bool $allowDiscount): array
    {
        $case = $this->db->fetch('SELECT * FROM cases WHERE id = ? AND deleted_at IS NULL', [$caseId]);
        if ($case === null) {
            throw new \App\Core\HttpException(404, 'Case not found.');
        }
        $discount = Decimal::amount($discount, 'discount_amount');
        $taxRate = Decimal::amount($taxRate, 'tax_rate', '100.00');
        $additional = Decimal::amount($additional, 'additional_amount');
        $items = [];
        $subtotal = '0.00';
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new \App\Core\ValidationException(['lines' => __('admin_validation.lines')]);
            }
            if (isset($line['description']) && !is_string($line['description'])) {
                throw new \App\Core\ValidationException(['lines' => __('admin_validation.lines')]);
            }
            if (empty($line['service_id']) && trim((string) ($line['description'] ?? '')) === '') {
                continue;
            }
            $service = !empty($line['service_id']) ? $this->catalog->service((int) $line['service_id']) : null;
            if (!empty($line['service_id']) && $service === null) {
                throw new \App\Core\ValidationException(['lines' => __('admin_validation.service_missing')]);
            }
            if ($service !== null && $service['currency'] !== $case['currency']) {
                throw new \App\Core\ValidationException(['lines' => __('admin_validation.service_currency')]);
            }
            $pricing = $service['pricing_type'] ?? ($line['pricing_type'] ?? 'custom');
            if ($pricing === 'fixed' && $service !== null) {
                $unit = $this->catalog->priceFor($service, $case['country_id'] ? (int) $case['country_id'] : null);
                if ($unit === null) {
                    throw new \RuntimeException('Fixed price is missing for ' . $service['code']);
                }
            } else {
                $unit = Decimal::amount($line['unit_price'] ?? '0', 'unit_price');
            }
            $unit = Decimal::amount($unit, 'unit_price');
            $qty = Decimal::quantity($line['quantity'] ?? '1');
            $lineTotal = Decimal::amount(Money::mul($qty, $unit), 'line_total');
            $subtotal = Decimal::amount(Money::add($subtotal, $lineTotal), 'subtotal');
            $name = $line['description'] ?? '';
            if ($name === '' && $service !== null) {
                $translated = $this->db->fetch('SELECT name FROM service_translations WHERE service_id = ? AND locale = \'en\'', [$service['id']]);
                $name = $translated['name'] ?? $service['code'];
            }
            $items[] = [
                'service_id' => $service['id'] ?? null,
                'description' => $name,
                'quantity' => $qty,
                'unit_price' => Money::norm($unit),
                'line_total' => $lineTotal,
                'pricing_type' => $pricing,
            ];
        }
        if ($items === []) {
            throw new \RuntimeException('Add at least one quotation item.');
        }
        $discountAmount = $allowDiscount ? Money::norm($discount) : '0.00';
        if (Money::cmp($discountAmount, $subtotal) > 0) {
            $discountAmount = $subtotal;
        }
        $taxable = Money::sub($subtotal, $discountAmount);
        $tax = Money::percent($taxable, $taxRate);
        $total = Decimal::amount(Money::add(Money::add($taxable, $tax), $additional), 'total');

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_rate' => Money::norm($taxRate),
            'tax_amount' => $tax,
            'additional_amount' => Money::norm($additional),
            'total' => $total,
        ];
    }

    public function save(int $caseId, array $payload, int $staffId, bool $issue, bool $allowDiscount): int
    {
        if (!is_array($payload['lines'] ?? null)) {
            throw new \App\Core\ValidationException(['lines' => __('admin_validation.lines')]);
        }
        $totals = $this->calculate(
            $caseId,
            $payload['lines'],
            Decimal::amount($payload['discount_amount'] ?? '0', 'discount_amount'),
            Decimal::amount($payload['tax_rate'] ?? '0', 'tax_rate', '100.00'),
            Decimal::amount($payload['additional_amount'] ?? '0', 'additional_amount'),
            $allowDiscount
        );
        $case = $this->db->fetch('SELECT * FROM cases WHERE id = ?', [$caseId]);
        $days = (int) ($payload['validity_days'] ?? setting('quote_validity_days', 14));
        $rawDays = $payload['validity_days'] ?? setting('quote_validity_days', 14);
        if (!is_scalar($rawDays) || !preg_match('/^\d+$/D', (string) $rawDays) || $days < 1 || $days > 3650) {
            throw new \App\Core\ValidationException(['validity_days' => __('admin_validation.days')]);
        }

        return $this->db->transaction(function () use ($case, $totals, $payload, $staffId, $issue, $days): int {
            $existingId = (int) ($payload['quotation_id'] ?? 0);
            $source = $existingId > 0 ? $this->db->fetch('SELECT * FROM quotations WHERE id = ? AND case_id = ? FOR UPDATE', [$existingId, $case['id']]) : null;
            if ($existingId > 0 && $source === null) {
                throw new \RuntimeException('The source quotation does not belong to this case.');
            }
            $existing = $source;
            if ($existing && $existing['status'] !== 'draft') {
                $existing = null;
            }
            $previous = null;
            if ($existing) {
                $quotationId = (int) $existing['id'];
                $this->db->run('DELETE FROM quotation_items WHERE quotation_id = ?', [$quotationId]);
                $this->db->run(
                    'UPDATE quotations SET subtotal = ?, discount_amount = ?, tax_rate = ?, tax_amount = ?, additional_amount = ?, total = ?, valid_until = ?, notes = ?, terms = ?, status = ?, issued_at = ? WHERE id = ?',
                    [
                        $totals['subtotal'], $totals['discount_amount'], $totals['tax_rate'], $totals['tax_amount'], $totals['additional_amount'], $totals['total'],
                        date('Y-m-d', strtotime('+' . $days . ' days')),
                        $payload['notes'] ?? null,
                        $payload['terms'] ?? setting('quote_terms', ''),
                        $issue ? 'sent' : 'draft',
                        $issue ? date('Y-m-d H:i:s') : null,
                        $quotationId,
                    ]
                );
            } else {
                if ($existingId > 0) {
                    $previous = $source;
                    if ($previous && !in_array($previous['status'], ['draft', 'accepted', 'converted'], true)) {
                        $this->db->run('UPDATE quotations SET status = ? WHERE id = ?', ['superseded', $previous['id']]);
                    }
                }
                $version = 1;
                $parentId = null;
                if (!empty($previous)) {
                    $version = ((int) $previous['version']) + 1;
                    $parentId = (int) $previous['id'];
                }
                $quotationId = $this->db->insert(
                    'INSERT INTO quotations (number, case_id, customer_id, parent_id, version, status, currency, subtotal, discount_amount, tax_rate, tax_amount, additional_amount, total, valid_until, notes, terms, issued_at, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $this->numbers->next('quotation', 'QT'),
                        $case['id'],
                        $case['customer_id'],
                        $parentId,
                        $version,
                        $issue ? 'sent' : 'draft',
                        $case['currency'],
                        $totals['subtotal'], $totals['discount_amount'], $totals['tax_rate'], $totals['tax_amount'], $totals['additional_amount'], $totals['total'],
                        date('Y-m-d', strtotime('+' . $days . ' days')),
                        $payload['notes'] ?? null,
                        $payload['terms'] ?? setting('quote_terms', ''),
                        $issue ? date('Y-m-d H:i:s') : null,
                        $staffId,
                    ]
                );
            }
            foreach ($totals['items'] as $item) {
                $this->db->insert(
                    'INSERT INTO quotation_items (quotation_id, service_id, description, quantity, unit_price, line_total, pricing_type) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$quotationId, $item['service_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['line_total'], $item['pricing_type']]
                );
            }
            $this->snapshot($quotationId, $staffId);
            $this->audit->log($staffId, $issue ? 'quotation_issued' : 'quotation_saved', 'quotation', $quotationId, ['total' => $totals['total']]);
            if ($issue) {
                $this->workflow->advance((int) $case['id'], 'quotation_sent', $staffId, 'Quotation issued', true, false);
            }

            return $quotationId;
        });
    }

    public function send(int $quotationId, int $staffId): array
    {
        $bundle = $this->bundle($quotationId);
        $path = $this->pdf->quotation($bundle['quotation'], $bundle['items'], $bundle['customer'], $bundle['case']);
        $result = $this->mail->send(
            $bundle['customer']['email'],
            __('mail.quotation_subject', ['number' => $bundle['quotation']['number']]),
            'quotation',
            [
                'name' => $bundle['customer']['name'],
                'number' => $bundle['quotation']['number'],
                'total' => money($bundle['quotation']['total'], $bundle['quotation']['currency']),
                'link' => absolute_url('/portal/quotations/' . $quotationId),
            ],
            'quotation',
            $quotationId,
            $path,
            $bundle['quotation']['number'] . '.pdf'
        );
        if ($bundle['quotation']['status'] === 'draft') {
            $this->db->run('UPDATE quotations SET status = ?, issued_at = COALESCE(issued_at, NOW()) WHERE id = ? AND status = ?', ['sent', $quotationId, 'draft']);
        }
        if ($result['status'] === 'sent') {
            $this->db->run('UPDATE quotations SET sent_at = NOW() WHERE id = ?', [$quotationId]);
        }
        if ($bundle['customer']['user_id'] !== null) {
            $this->notifications->notify(
                (int) $bundle['customer']['user_id'],
                __('notifications.quotation_title'),
                __('notifications.quotation_body', ['number' => $bundle['quotation']['number']]),
                '/portal/quotations/' . $quotationId
            );
        }
        $this->audit->log($staffId, 'quotation_emailed', 'quotation', $quotationId, ['mail_status' => $result['status']]);

        return $result;
    }

    public function accept(int $quotationId, int $userId, string $ip): int
    {
        $invoiceId = $this->db->transaction(function () use ($quotationId, $userId, $ip): int {
            $quotation = $this->db->fetch('SELECT * FROM quotations WHERE id = ? FOR UPDATE', [$quotationId]);
            if ($quotation === null || !in_array($quotation['status'], ['sent', 'viewed'], true)) {
                throw new \RuntimeException('This quotation cannot be accepted.');
            }
            if ($quotation['valid_until'] !== null && $quotation['valid_until'] < date('Y-m-d')) {
                $this->db->run('UPDATE quotations SET status = ? WHERE id = ?', ['expired', $quotationId]);
                return 0;
            }
            $this->db->run(
                'UPDATE quotations SET status = ?, accepted_at = NOW(), accepted_ip = ?, accepted_by = ? WHERE id = ?',
                ['accepted', $ip, $userId, $quotation['id']]
            );
            $invoiceId = $this->invoices->createFromQuotation((int) $quotation['id'], $userId);
            $this->db->run('UPDATE quotations SET status = ? WHERE id = ?', ['converted', $quotation['id']]);
            $this->workflow->advance((int) $quotation['case_id'], 'invoice_generated', $userId, 'Quotation accepted', true, false);
            $this->audit->log($userId, 'quotation_accepted', 'quotation', (int) $quotation['id'], ['ip' => $ip, 'invoice_id' => $invoiceId]);

            return $invoiceId;
        });
        if ($invoiceId === 0) {
            throw new \RuntimeException('This quotation has expired.');
        }

        return $invoiceId;
    }

    public function reject(int $quotationId, int $userId, string $reason): void
    {
        $this->db->transaction(function () use ($quotationId, $userId, $reason): void {
            $quotation = $this->db->fetch('SELECT * FROM quotations WHERE id = ? FOR UPDATE', [$quotationId]);
            if ($quotation === null || !in_array($quotation['status'], ['sent', 'viewed'], true)) {
                throw new \RuntimeException('This quotation cannot be rejected.');
            }
            $this->db->run(
                'UPDATE quotations SET status = ?, rejected_at = NOW(), rejection_reason = ? WHERE id = ?',
                ['rejected', $reason, $quotationId]
            );
            $this->audit->log($userId, 'quotation_rejected', 'quotation', $quotationId, ['reason' => $reason]);
        });
    }

    public function markViewed(int $quotationId): void
    {
        $this->db->run('UPDATE quotations SET status = ?, viewed_at = NOW() WHERE id = ? AND status = ?', ['viewed', $quotationId, 'sent']);
    }

    public function bundle(int $quotationId): array
    {
        $quotation = $this->db->fetch('SELECT * FROM quotations WHERE id = ?', [$quotationId]);
        if ($quotation === null) {
            throw new \App\Core\HttpException(404, 'Quotation not found.');
        }
        $items = $this->db->fetchAll('SELECT * FROM quotation_items WHERE quotation_id = ?', [$quotationId]);
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$quotation['customer_id']]);
        $case = $this->db->fetch(
            'SELECT c.*, ct.name AS country_name FROM cases c LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = \'en\' WHERE c.id = ?',
            [$quotation['case_id']]
        );

        return compact('quotation', 'items', 'customer', 'case');
    }

    public function pdfPath(int $quotationId): string
    {
        $bundle = $this->bundle($quotationId);

        return $this->pdf->quotation($bundle['quotation'], $bundle['items'], $bundle['customer'], $bundle['case']);
    }

    private function snapshot(int $quotationId, int $staffId): void
    {
        $bundle = $this->bundle($quotationId);
        $this->db->run(
            'INSERT INTO quotation_versions (quotation_id, version, snapshot, created_by) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE snapshot = VALUES(snapshot)',
            [$quotationId, $bundle['quotation']['version'], json_encode($bundle, JSON_UNESCAPED_UNICODE), $staffId]
        );
    }
}
