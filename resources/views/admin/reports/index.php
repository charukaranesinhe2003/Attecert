<form class="filters" method="get">
    <?php if (locale() !== 'en'): ?><input type="hidden" name="lang" value="<?= e(locale()) ?>"><?php endif; ?>
    <label for="report-from"><?= e(__('admin_polish.report_from')) ?><input id="report-from" type="date" name="from" value="<?= e($from) ?>" required <?= isset($rangeErrors['from']) ? 'aria-invalid="true" aria-describedby="report-from-error"' : '' ?>></label>
    <label for="report-to"><?= e(__('admin_polish.report_to')) ?><input id="report-to" type="date" name="to" value="<?= e($to) ?>" required <?= isset($rangeErrors['to']) ? 'aria-invalid="true" aria-describedby="report-to-error"' : '' ?>></label>
    <button class="btn btn-primary"><?= e(__('actions.filter')) ?></button>
    <?php if ($report !== null): ?><a class="btn btn-ghost" href="<?= e(url('/admin/reports/export?' . http_build_query(['from' => $from, 'to' => $to]))) ?>"><?= e(__('actions.export')) ?></a><?php endif; ?>
</form>
<?php foreach ($rangeErrors as $field => $message): ?><p class="toast toast-error" id="report-<?= e($field) ?>-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<?php if ($report !== null): ?>
<p class="muted"><?= e(__('admin_polish.report_scope')) ?></p>
<div class="admin-grid">
<?php foreach (['by_status' => 'case_status', 'by_country' => 'destination_country', 'by_service' => 'selected_services'] as $key => $label): ?>
    <section class="card-block">
        <h2><?= e(__('admin_polish.' . $label)) ?></h2>
        <?php if ($report[$key] === []): ?><p class="empty"><?= e(__('admin_polish.no_records')) ?></p><?php endif; ?>
        <?php foreach ($report[$key] as $row): ?><p><?= e($row['name']) ?>: <strong><?= (int) $row['total'] ?></strong></p><?php endforeach; ?>
    </section>
<?php endforeach; ?>
<section class="card-block">
    <h2><?= e(__('admin_polish.receipts_refunds')) ?></h2>
    <p class="muted"><?= e(__('admin_polish.refund_totals_help')) ?></p>
    <?php $net = []; foreach ($report['payments'] as $row): $net[$row['currency']] = \App\Helpers\Money::add($net[$row['currency']] ?? '0', $row['amount']); ?>
        <p><?= e(__('admin_lists.' . $row['method'])) ?> (<?= (int) $row['total'] ?>): <?= e(money($row['amount'], $row['currency'])) ?></p>
    <?php endforeach; ?>
    <?php if ($net === []): ?><p class="empty"><?= e(__('admin_polish.no_receipts')) ?></p><?php endif; ?>
    <?php foreach ($net as $currency => $amount): ?><p><strong><?= e(__('admin_polish.net', ['amount' => money($amount, $currency)])) ?></strong></p><?php endforeach; ?>
    <p><?= e(__('admin_polish.completion_average', ['value' => $report['turnaround'] === null ? __('admin_polish.no_completed_cases') : __('admin_polish.completion_days', ['days' => (string) $report['turnaround']])])) ?></p>
    <p><?= e(__('admin_polish.new_customers', ['count' => (int) $report['customers']])) ?></p>
    <p><?= e(__('admin_polish.quotations_summary', ['total' => (int) ($report['quotations']['total'] ?? 0), 'accepted' => (int) ($report['quotations']['accepted'] ?? 0)])) ?></p>
</section>
</div>
<section class="card-block">
    <h2><?= e(__('admin_polish.outstanding_invoices')) ?></h2>
    <?php if ($report['outstanding'] === []): ?><p class="empty"><?= e(__('admin_polish.no_outstanding')) ?></p><?php else: ?>
    <div class="table-wrap"><table>
        <caption class="sr-only"><?= e(__('admin_polish.outstanding_invoices')) ?></caption>
        <thead><tr><th scope="col"><?= e(__('admin_polish.invoice')) ?></th><th scope="col"><?= e(__('admin_polish.status')) ?></th><th scope="col"><?= e(__('admin_polish.due_date')) ?></th><th scope="col"><?= e(__('admin_polish.balance')) ?></th></tr></thead>
        <tbody><?php foreach ($report['outstanding'] as $row): ?><tr>
            <td data-label="<?= e(__('admin_polish.invoice')) ?>"><?php if (app(\App\Services\AuthService::class)->can('invoices.create')): ?><a href="<?= e(url('/admin/invoices/' . $row['id'])) ?>"><?= e($row['number']) ?></a><?php else: ?><?= e($row['number']) ?><?php endif; ?></td>
            <td data-label="<?= e(__('admin_polish.status')) ?>"><?= e(__('admin_polish.invoice_status_' . $row['status'])) ?></td>
            <td data-label="<?= e(__('admin_polish.due_date')) ?>"><?= e(format_date($row['due_at'])) ?></td>
            <td data-label="<?= e(__('admin_polish.balance')) ?>"><?= e(money($row['amount_due'], $row['currency'])) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
