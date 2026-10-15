<?php
$invoice = $bundle['invoice'];
$auth = app(\App\Services\AuthService::class);
$canRecord = $auth->can('payments.create') && $invoice['status'] !== 'cancelled';
?>
<p><?= e($invoice['number']) ?> · <?= status_badge($invoice['status']) ?> · <a href="<?= e(url('/admin/cases/' . $invoice['case_id'] . '#finance')) ?>"><?= e($bundle['case']['number']) ?></a></p>
<div class="table-wrap"><table>
    <thead><tr><th><?= e(__('fields.description')) ?></th><th><?= e(__('fields.quantity')) ?></th><th><?= e(__('fields.price')) ?></th><th><?= e(__('fields.total')) ?></th></tr></thead>
    <tbody><?php foreach ($bundle['items'] as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e(money($item['unit_price'], $invoice['currency'])) ?></td><td><?= e(money($item['line_total'], $invoice['currency'])) ?></td></tr><?php endforeach; ?></tbody>
</table></div>
<p><?= e(__('fields.total')) ?> <?= e(money($invoice['total'], $invoice['currency'])) ?> · <?= e(__('fields.paid')) ?> <?= e(money($invoice['amount_paid'], $invoice['currency'])) ?> · <?= e(__('fields.due')) ?> <?= e(money($invoice['amount_due'], $invoice['currency'])) ?></p>
<div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('/admin/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a>
    <?php if ($auth->can('invoices.send')): ?><form method="post" action="<?= e(url('/admin/invoices/' . $invoice['id'] . '/send')) ?>" data-busy="<?= e(__('admin.sending')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('actions.email')) ?></button></form><?php endif; ?>
</div>
<?php if ($canRecord): ?>
    <?php foreach (['payment', 'refund'] as $kind):
        $refund = $kind === 'refund';
        $maximum = $refund ? $invoice['amount_paid'] : $invoice['amount_due'];
        if (\App\Helpers\Money::cmp($maximum, '0') <= 0) continue;
    ?>
        <form class="card-block stack" method="post" action="<?= e(url('/admin/invoices/' . $invoice['id'] . '/payments')) ?>" data-busy="<?= e(__('admin.saving')) ?>" <?= $refund ? 'data-confirm="Record this refund?"' : '' ?>><?= csrf_field() ?>
            <h2><?= $refund ? 'Record refund' : e(__('admin.record_payment')) ?></h2>
            <p><?= $refund ? 'Available to refund: ' : e(__('fields.due')) . ': ' ?><?= e(money($maximum, $invoice['currency'])) ?></p>
            <label><?= e(__('fields.amount')) ?><input type="number" min="0.01" step="0.01" max="<?= e((string) $maximum) ?>" name="amount" required></label>
            <?php if ($refund): ?><input type="hidden" name="method" value="refund"><?php else: ?>
                <label><?= e(__('fields.method')) ?><select name="method"><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="card">Card</option><option value="online">Online</option><option value="other">Other</option></select></label>
            <?php endif; ?>
            <label><?= e(__('fields.date')) ?><input type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label><?= e(__('fields.reference')) ?><input name="reference" maxlength="80"></label>
            <label><?= e(__('fields.notes')) ?><textarea name="notes" maxlength="1000"></textarea></label>
            <button class="btn btn-primary" type="submit"><?= $refund ? 'Record refund' : e(__('admin.record_payment')) ?></button>
        </form>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($auth->can('invoices.cancel') && !in_array($invoice['status'], ['cancelled', 'paid'], true) && \App\Helpers\Money::cmp($invoice['amount_paid'], '0') <= 0): ?>
    <form method="post" action="<?= e(url('/admin/invoices/' . $invoice['id'] . '/cancel')) ?>" data-confirm="<?= e(__('admin.confirm_cancel')) ?>"><?= csrf_field() ?><input name="reason" maxlength="255" aria-label="<?= e(__('fields.notes')) ?>" placeholder="<?= e(__('fields.notes')) ?>"><button class="btn btn-ghost" type="submit"><?= e(__('actions.cancel')) ?></button></form>
<?php endif; ?>
<?php if ($auth->can('payments.view') && $bundle['payments'] !== []): ?>
    <section class="card-block"><h2><?= e(__('admin.payments')) ?></h2><ul>
        <?php foreach ($bundle['payments'] as $payment): ?><li><?= e($payment['number']) ?> · <?= e($payment['method']) ?> · <?= e(money($payment['amount'], $payment['currency'])) ?> · <?= e(format_date($payment['paid_at'])) ?> <a href="<?= e(url('/admin/payments/' . $payment['id'] . '/receipt')) ?>"><?= e(__('actions.download')) ?></a></li><?php endforeach; ?>
    </ul></section>
<?php endif; ?>
