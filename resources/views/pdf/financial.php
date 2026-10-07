<?php
$kindLabel = ($kind ?? '') === 'invoice' ? 'Invoice' : 'Quotation';
$doc = $document;
?>
<style>
body { font-family: sans-serif; color: #10243c; font-size: 12px; }
h1 { color: #092c4c; font-size: 22px; margin: 0; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; background: #092c4c; color: #fff; padding: 6px; }
td { padding: 6px; border-bottom: 1px solid #e4eae8; }
.right { text-align: right; }
.muted { color: #4d6278; }
</style>
<table><tr>
<td><?php if (is_file($company['logo'])): ?><img src="<?= e($company['logo']) ?>" width="48"><?php endif; ?><h1><?= e($company['name']) ?></h1><div class="muted"><?= e($company['address']) ?><br><?= e($company['phone']) ?> · <?= e($company['email']) ?><br><?= e($company['registration']) ?></div></td>
<td class="right"><strong><?= e($kindLabel) ?></strong><br><?= e($doc['number']) ?><br><?= e(format_date($doc['issued_at'] ?? $doc['created_at'])) ?></td>
</tr></table>
<p><strong><?= e($customer['name']) ?></strong><br><?= e($customer['email']) ?><br><?= e((string) $customer['phone']) ?></p>
<p>Case <?= e($case['number']) ?><?php if (!empty($case['country_name'])): ?> · <?= e($case['country_name']) ?><?php endif; ?><br><?= e($case['document_title'] ?? '') ?></p>
<table>
<tr><th>Description</th><th>Qty</th><th>Price</th><th>Total</th></tr>
<?php foreach ($items as $item): ?>
<tr><td><?= e($item['description']) ?></td><td><?= e((string) $item['quantity']) ?></td><td><?= e(number_format((float) $item['unit_price'], 2)) ?></td><td><?= e(number_format((float) $item['line_total'], 2)) ?></td></tr>
<?php endforeach; ?>
</table>
<p class="right">Subtotal <?= e($doc['currency']) ?> <?= e(number_format((float) $doc['subtotal'], 2)) ?><br>
Discount <?= e(number_format((float) $doc['discount_amount'], 2)) ?><br>
Tax <?= e(number_format((float) $doc['tax_amount'], 2)) ?><br>
Additional <?= e(number_format((float) $doc['additional_amount'], 2)) ?><br>
<strong>Total <?= e($doc['currency']) ?> <?= e(number_format((float) $doc['total'], 2)) ?></strong>
<?php if (($kind ?? '') === 'invoice'): ?><br>Paid <?= e(number_format((float) $doc['amount_paid'], 2)) ?><br>Due <?= e(number_format((float) $doc['amount_due'], 2)) ?><?php endif; ?>
</p>
<?php if (!empty($doc['notes'])): ?><p><?= nl2br(e((string) $doc['notes'])) ?></p><?php endif; ?>
<?php if (!empty($doc['terms'])): ?><p class="muted"><?= nl2br(e((string) $doc['terms'])) ?></p><?php endif; ?>
