<?php $invoice = $bundle['invoice']; ?>
<p><?= e($invoice['status']) ?> · <?= e($bundle['case']['number']) ?></p>
<div class="table-wrap"><table><tbody><?php foreach ($bundle['items'] as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e(money($item['line_total'], $invoice['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p><?= e(__('fields.total')) ?>: <?= e(money($invoice['total'], $invoice['currency'])) ?></p>
<p><?= e(__('fields.paid')) ?>: <?= e(money($invoice['amount_paid'], $invoice['currency'])) ?></p>
<p><?= e(__('fields.due')) ?>: <?= e(money($invoice['amount_due'], $invoice['currency'])) ?></p>
<div style="display:flex;gap:0.75rem;margin-top:1rem;">
    <a class="btn btn-primary" href="<?= e(url('/portal/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a>
    <?php if (\App\Helpers\Money::cmp($invoice['amount_due'], '0') > 0): ?>
        <a class="btn btn-ghost" href="<?= e(url('/bank-details')) ?>" target="_blank">View Bank Details for Payment</a>
    <?php endif; ?>
</div>
