<?php
$invoice = $bundle['invoice'];
$auth = app(\App\Services\AuthService::class);
$canRecord = $auth->can('payments.create') && $invoice['status'] !== 'cancelled';
$failedKind = old('_formkind', '');
$failedInvoice = old('invoice_id', '');
$failedKind = is_string($failedKind) && is_scalar($failedInvoice) && (string) $failedInvoice === (string) $invoice['id'] ? $failedKind : '';
?>
<p><?= e($invoice['number']) ?> · <?= status_badge(__('admin_polish.invoice_status_' . $invoice['status'])) ?> · <?php if ($auth->can('cases.view')): ?><a href="<?= e(url('/admin/cases/' . $invoice['case_id'] . '#finance')) ?>"><?= e($bundle['case']['number']) ?></a><?php else: ?><?= e($bundle['case']['number']) ?><?php endif; ?></p>
<div class="table-wrap"><table>
    <caption class="sr-only"><?= e(__('admin_polish.invoice_items')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('fields.description')) ?></th><th scope="col"><?= e(__('fields.quantity')) ?></th><th scope="col"><?= e(__('fields.price')) ?></th><th scope="col"><?= e(__('fields.total')) ?></th></tr></thead>
    <tbody><?php foreach ($bundle['items'] as $item): ?><tr><td data-label="<?= e(__('fields.description')) ?>"><?= e($item['description']) ?></td><td data-label="<?= e(__('fields.quantity')) ?>"><?= e($item['quantity']) ?></td><td data-label="<?= e(__('fields.price')) ?>"><?= e(money($item['unit_price'], $invoice['currency'])) ?></td><td data-label="<?= e(__('fields.total')) ?>"><?= e(money($item['line_total'], $invoice['currency'])) ?></td></tr><?php endforeach; ?></tbody>
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
        $restore = $failedKind === $kind;
        $formErrors = $restore ? errors() : [];
        $value = static function (string $field, string $default = '') use ($restore): string {
            $raw = $restore ? old($field, $default) : $default;

            return is_scalar($raw) ? (string) $raw : '';
        };
        $invalid = static fn (string $field): string => isset($formErrors[$field]) ? 'aria-invalid="true" aria-describedby="invoice-' . $kind . '-' . $field . '-error"' : '';
        $fieldError = static function (string $field) use ($formErrors, $kind): string {
            return isset($formErrors[$field]) ? '<p class="form-error" role="alert" id="invoice-' . $kind . '-' . $field . '-error">' . e((string) $formErrors[$field]) . '</p>' : '';
        };
    ?>
        <form class="card-block stack" id="invoice-<?= e($kind) ?>" aria-labelledby="invoice-<?= e($kind) ?>-title" method="post" action="<?= e(url('/admin/invoices/' . $invoice['id'] . '/payments')) ?>" data-busy="<?= e(__('admin.saving')) ?>" <?= $refund ? 'data-confirm="' . e(__('admin_polish.refund_confirm')) . '"' : '' ?>><?= csrf_field() ?>
            <input type="hidden" name="_formkind" value="<?= e($kind) ?>">
            <h2 id="invoice-<?= e($kind) ?>-title"><?= e($refund ? __('admin_polish.refund_title') : __('admin.record_payment')) ?></h2>
            <p><?= $refund ? e(__('admin_polish.refund_available', ['amount' => money($maximum, $invoice['currency'])])) : e(__('fields.due') . ': ' . money($maximum, $invoice['currency'])) ?></p>
            <?php if ($formErrors !== []): ?><p role="alert"><?= e(__('admin_polish.form_errors')) ?></p><?php endif; ?>
            <?php foreach (array_diff_key($formErrors, array_fill_keys(['amount', 'method', 'paid_at', 'reference', 'notes'], true)) as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
            <label for="invoice-<?= e($kind) ?>-amount"><?= e(__('fields.amount')) ?></label><input id="invoice-<?= e($kind) ?>-amount" type="number" min="0.01" step="0.01" max="<?= e((string) $maximum) ?>" name="amount" value="<?= e($value('amount')) ?>" required <?= $invalid('amount') ?>>
            <?= $fieldError('amount') ?>
            <?php if ($refund): ?><input type="hidden" name="method" value="refund"><?php else: ?>
                <label for="invoice-payment-method"><?= e(__('fields.method')) ?></label><select id="invoice-payment-method" name="method" <?= $invalid('method') ?>><?php foreach (['bank_transfer', 'cash', 'card', 'online', 'other'] as $method): ?><option value="<?= e($method) ?>" <?= selected($value('method', 'bank_transfer'), $method) ?>><?= e(__('admin_lists.' . $method)) ?></option><?php endforeach; ?></select>
            <?php endif; ?>
            <?= $fieldError('method') ?>
            <label for="invoice-<?= e($kind) ?>-paid_at"><?= e(__('fields.date')) ?></label><input id="invoice-<?= e($kind) ?>-paid_at" type="date" name="paid_at" value="<?= e($value('paid_at', date('Y-m-d'))) ?>" required <?= $invalid('paid_at') ?>>
            <?= $fieldError('paid_at') ?>
            <label for="invoice-<?= e($kind) ?>-reference"><?= e(__('fields.reference')) ?></label><input id="invoice-<?= e($kind) ?>-reference" name="reference" maxlength="80" value="<?= e($value('reference')) ?>" <?= $invalid('reference') ?>>
            <?= $fieldError('reference') ?>
            <label for="invoice-<?= e($kind) ?>-notes"><?= e(__('fields.notes')) ?></label><textarea id="invoice-<?= e($kind) ?>-notes" name="notes" maxlength="1000" <?= $invalid('notes') ?>><?= e($value('notes')) ?></textarea>
            <?= $fieldError('notes') ?>
            <button class="btn btn-primary" type="submit"><?= e($refund ? __('admin_polish.refund_title') : __('admin.record_payment')) ?></button>
        </form>
    <?php endforeach; ?>
<?php endif; ?>
<?php if ($auth->can('invoices.cancel') && !in_array($invoice['status'], ['cancelled', 'paid'], true) && \App\Helpers\Money::cmp($invoice['amount_paid'], '0') <= 0): ?>
    <form method="post" action="<?= e(url('/admin/invoices/' . $invoice['id'] . '/cancel')) ?>" data-confirm="<?= e(__('admin.confirm_cancel')) ?>"><?= csrf_field() ?><input name="reason" maxlength="255" aria-label="<?= e(__('fields.notes')) ?>" placeholder="<?= e(__('fields.notes')) ?>"><button class="btn btn-ghost" type="submit"><?= e(__('actions.cancel')) ?></button></form>
<?php endif; ?>
<?php if ($auth->can('payments.view') && $bundle['payments'] !== []): ?>
    <section class="card-block"><h2><?= e(__('admin.payments')) ?></h2><ul>
        <?php foreach ($bundle['payments'] as $payment): ?><li><?= e($payment['number']) ?> · <?= e(__('admin_lists.' . $payment['method'])) ?> · <?= e(money($payment['amount'], $payment['currency'])) ?> · <?= e(format_date($payment['paid_at'])) ?> <a href="<?= e(url('/admin/payments/' . $payment['id'] . '/receipt')) ?>"><?= e(__('actions.download')) ?><span class="sr-only"> <?= e($payment['number']) ?></span></a></li><?php endforeach; ?>
    </ul></section>
<?php endif; ?>
