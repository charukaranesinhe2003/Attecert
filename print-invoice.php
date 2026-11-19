<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid invoice ID.");
}

$invoice = db()->fetch('SELECT * FROM invoices WHERE id = ?', [$id]);
if ($invoice === null) {
    die("Invoice not found.");
}

$items = db()->fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC', [$id]);
$case = db()->fetch('SELECT c.*, co.name AS country_name FROM cases c LEFT JOIN countries co ON co.id = c.country_id WHERE c.id = ?', [$invoice['case_id']]) ?? [];
$customer = db()->fetch('SELECT * FROM customers WHERE id = ?', [$invoice['customer_id']]) ?? [];

// Permission check: allow customer owner or staff
$user = current_user();
if ($user) {
    $cust = account_customer();
    if ($cust && (int) $cust['id'] !== (int) $invoice['customer_id'] && !auth()->isStaff()) {
        die("Unauthorized.");
    }
}

$company = [
    'name' => (string) setting('company_name', 'AtteCert'),
    'email' => (string) setting('email', ''),
    'phone' => (string) setting('phone', ''),
    'address' => (string) setting('address', 'Colombo, Sri Lanka'),
    'registration' => (string) setting('registration_number', ''),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['number']) ?> | <?= htmlspecialchars($company['name']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #10243c; font-size: 13px; line-height: 1.6; padding: 40px; max-width: 800px; margin: 0 auto; background: #fff; }
        .no-print { margin-bottom: 24px; padding: 12px; background: #f1f5f9; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn-back { color: #475569; text-decoration: none; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { text-align: left; background: #092c4c; color: #fff; padding: 10px 12px; font-size: 12px; text-transform: uppercase; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right; }
        .muted { color: #64748b; }
        .company-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #092c4c; padding-bottom: 20px; }
        .meta-box { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .total-box { margin-top: 20px; text-align: right; }
        .total-box strong { font-size: 1.25rem; color: #092c4c; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .badge-paid { background: #dcfce7; color: #15803d; }
        .badge-unpaid { background: #fef3c7; color: #b45309; }
        .badge-overdue { background: #fee2e2; color: #b91c1c; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <a href="javascript:history.back()" class="btn-back">&larr; Back</a>
    <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="company-header">
    <div>
        <h1 style="color: #092c4c; margin: 0 0 6px 0; font-size: 1.8rem;"><?= htmlspecialchars($company['name']) ?></h1>
        <div class="muted">
            <?= htmlspecialchars($company['address']) ?><br>
            Phone: <?= htmlspecialchars($company['phone']) ?> · Email: <?= htmlspecialchars($company['email']) ?><br>
            <?= htmlspecialchars($company['registration']) ?>
        </div>
    </div>
    <div class="right">
        <h2 style="margin: 0; color: #0284c7;">TAX INVOICE</h2>
        <strong><?= htmlspecialchars($invoice['number']) ?></strong><br>
        Date: <?= htmlspecialchars(format_date($invoice['issued_at'] ?? $invoice['created_at'])) ?><br>
        Due Date: <?= htmlspecialchars(format_date($invoice['due_at'])) ?><br>
        Status: <span class="badge badge-<?= htmlspecialchars($invoice['status']) ?>"><?= htmlspecialchars($invoice['status']) ?></span>
    </div>
</div>

<div class="meta-box">
    <div>
        <strong style="color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Billed To:</strong><br>
        <strong><?= htmlspecialchars($customer['name'] ?? 'Customer') ?></strong><br>
        Email: <?= htmlspecialchars($customer['email'] ?? '') ?><br>
        Phone: <?= htmlspecialchars((string) ($customer['phone'] ?? '')) ?>
    </div>
    <div class="right">
        <strong style="color: #64748b; font-size: 0.8rem; text-transform: uppercase;">Case Details:</strong><br>
        Case #: <strong><?= htmlspecialchars($case['number'] ?? '') ?></strong><br>
        <?php if (!empty($case['country_name'])): ?>Destination: <?= htmlspecialchars($case['country_name']) ?><br><?php endif; ?>
        Document: <?= htmlspecialchars($case['document_title'] ?? 'Attestation Services') ?>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Description</th>
            <th class="right">Qty</th>
            <th class="right">Unit Price</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['description']) ?></td>
                <td class="right"><?= htmlspecialchars((string) $item['quantity']) ?></td>
                <td class="right"><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $item['unit_price'], 2) ?></td>
                <td class="right"><?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $item['line_total'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="total-box">
    <p>
        Subtotal: <?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['subtotal'], 2) ?><br>
        <?php if ((float) $invoice['discount_amount'] > 0): ?>
            Discount: -<?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['discount_amount'], 2) ?><br>
        <?php endif; ?>
        <?php if ((float) $invoice['tax_amount'] > 0): ?>
            Tax: <?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['tax_amount'], 2) ?><br>
        <?php endif; ?>
        <strong>Total Amount: <?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['total'], 2) ?></strong><br>
        Amount Paid: <?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['amount_paid'], 2) ?><br>
        <span style="color: #b91c1c; font-weight: bold;">Balance Due: <?= htmlspecialchars($invoice['currency']) ?> <?= number_format((float) $invoice['amount_due'], 2) ?></span>
    </p>
</div>

<?php if (!empty($invoice['notes'])): ?>
    <div style="margin-top: 30px; background: #f8fafc; padding: 14px; border-radius: 6px;">
        <strong>Payment Instructions:</strong><br>
        <?= nl2br(htmlspecialchars((string) $invoice['notes'])) ?>
<div style="margin-top: 30px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 8px;">
    <strong style="color: #092c4c; font-size: 0.95rem;">Payment Instructions & Bank Account Details:</strong>
    <p style="margin: 6px 0 12px 0; color: #475569; font-size: 0.85rem;">Please transfer the balance due to any of the following accounts and send the receipt to <strong>info@attecert.lk</strong> or WhatsApp <strong>+94 718885769</strong> with reference <strong><?= htmlspecialchars($invoice['number']) ?></strong>.</p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; font-size: 0.85rem;">
        <div style="background: #fff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <strong style="color: #0284c7;">Bank of Ceylon (BOC)</strong><br>
            Name: <strong>K.A. Mohammed</strong><br>
            Acc No: <strong>3806066</strong><br>
            Branch: Wellawatha Branch
        </div>
        <div style="background: #fff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <strong style="color: #0284c7;">Hatton National Bank (HNB)</strong><br>
            Name: <strong>K.A. Mohammed</strong><br>
            Acc No: <strong>009020835573</strong><br>
            Branch: Wellawatha Branch<br>
            Swift: <strong>HBLILKLX</strong>
        </div>
        <div style="background: #fff; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px;">
            <strong style="color: #0284c7;">Commercial Bank</strong><br>
            Name: <strong>K.A. Mohammed</strong><br>
            Acc No: <strong>8110912841</strong><br>
            Branch: Wellawatha Branch
        </div>
    </div>
<?php endif; ?>
    <?php if (!empty($invoice['notes'])): ?>
        <div style="margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 8px; font-size: 0.85rem;">
            <?= nl2br(htmlspecialchars((string) $invoice['notes'])) ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
