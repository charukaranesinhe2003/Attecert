<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid payment ID.");
}

$payment = db()->fetch('SELECT * FROM payments WHERE id = ?', [$id]);
if ($payment === null) {
    die("Payment record not found.");
}

$invoice = db()->fetch('SELECT * FROM invoices WHERE id = ?', [$payment['invoice_id']]) ?? [];
$customer = db()->fetch('SELECT * FROM customers WHERE id = ?', [$payment['customer_id']]) ?? [];

$company = [
    'name' => (string) setting('company_name', 'AtteCert'),
    'email' => (string) setting('email', ''),
    'phone' => (string) setting('phone', ''),
    'address' => (string) setting('address', 'Colombo, Sri Lanka'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?= htmlspecialchars($payment['number']) ?> | <?= htmlspecialchars($company['name']) ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #10243c; font-size: 13px; line-height: 1.6; padding: 40px; max-width: 650px; margin: 0 auto; background: #fff; }
        .no-print { margin-bottom: 24px; padding: 12px; background: #f1f5f9; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .btn-print { background: #0284c7; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; }
        .btn-back { color: #475569; text-decoration: none; font-weight: 500; }
        .receipt-card { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; background: #f8fafc; }
        .header { text-align: center; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
        .amount-box { text-align: center; background: #dcfce7; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin: 24px 0; }
        .amount { font-size: 2rem; font-weight: 800; color: #15803d; }
        .row { display: flex; justify-content: space-between; margin-bottom: 10px; }
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

<div class="receipt-card">
    <div class="header">
        <h1 style="color: #092c4c; margin: 0; font-size: 1.6rem;"><?= htmlspecialchars($company['name']) ?></h1>
        <p style="margin: 4px 0; color: #64748b;"><?= htmlspecialchars($company['address']) ?> · <?= htmlspecialchars($company['phone']) ?></p>
        <h2 style="color: #0284c7; margin: 12px 0 0 0; font-size: 1.2rem;">OFFICIAL PAYMENT RECEIPT</h2>
        <span style="color: #64748b; font-size: 0.9rem;">Receipt #: <strong><?= htmlspecialchars($payment['number']) ?></strong></span>
    </div>

    <div class="amount-box">
        <span style="font-size: 0.85rem; text-transform: uppercase; color: #166534; font-weight: 600;">Amount Received</span><br>
        <span class="amount"><?= htmlspecialchars($payment['currency']) ?> <?= number_format((float) $payment['amount'], 2) ?></span>
    </div>

    <div class="row">
        <span style="color: #64748b;">Received From:</span>
        <strong><?= htmlspecialchars($customer['name'] ?? 'Customer') ?></strong>
    </div>
    <div class="row">
        <span style="color: #64748b;">Invoice Reference:</span>
        <strong><?= htmlspecialchars($invoice['number'] ?? 'N/A') ?></strong>
    </div>
    <div class="row">
        <span style="color: #64748b;">Payment Method:</span>
        <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $payment['method']))) ?></strong>
    </div>
    <div class="row">
        <span style="color: #64748b;">Transaction Reference:</span>
        <strong><?= htmlspecialchars($payment['reference'] ?? 'Direct Receipt') ?></strong>
    </div>
    <div class="row">
        <span style="color: #64748b;">Date & Time:</span>
        <strong><?= htmlspecialchars(format_date($payment['paid_at'] ?? $payment['created_at'])) ?></strong>
    </div>
    <div class="row">
        <span style="color: #64748b;">Status:</span>
        <strong style="color: #15803d; text-transform: uppercase;">Confirmed</strong>
    </div>

    <div style="text-align: center; margin-top: 30px; font-size: 0.85rem; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px;">
        Thank you for choosing <?= htmlspecialchars($company['name']) ?>. This is a computer generated receipt.
    </div>
</div>
</body>
</html>
