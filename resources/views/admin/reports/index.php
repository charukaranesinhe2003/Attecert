<form class="filters" method="get">
    <label>From<input type="date" name="from" value="<?= e($from) ?>" required></label>
    <label>To<input type="date" name="to" value="<?= e($to) ?>" required></label>
    <button class="btn btn-primary"><?= e(__('actions.filter')) ?></button>
    <?php if ($report !== null): ?><a class="btn btn-ghost" href="<?= e(url('/admin/reports/export?' . http_build_query(['from' => $from, 'to' => $to]))) ?>"><?= e(__('actions.export')) ?> CSV</a><?php endif; ?>
</form>
<?php foreach ($rangeErrors as $message): ?><p class="toast toast-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<?php if ($report !== null): ?>
<p class="muted">Cases, services, countries, customers and quotations use their creation date. Payments and refunds use payment date; turnaround uses completion date. Outstanding invoices show the current balance across all dates. Amounts are shown in their recorded currency.</p>
<div class="admin-grid">
<?php foreach (['by_status' => 'Case status', 'by_country' => 'Destination country', 'by_service' => 'Selected services'] as $key => $label): ?>
    <section class="card-block">
        <h2><?= e($label) ?></h2>
        <?php if ($report[$key] === []): ?><p class="empty">No records in this date range.</p><?php endif; ?>
        <?php foreach ($report[$key] as $row): ?><p><?= e($row['name']) ?>: <strong><?= (int) $row['total'] ?></strong></p><?php endforeach; ?>
    </section>
<?php endforeach; ?>
<section class="card-block">
    <h2>Receipts and refunds</h2>
    <p class="muted">Refund entries reduce the net amount. Each currency is calculated separately.</p>
    <?php $net = []; foreach ($report['payments'] as $row): $net[$row['currency']] = \App\Helpers\Money::add($net[$row['currency']] ?? '0', $row['amount']); ?>
        <p><?= e(ucwords(str_replace('_', ' ', $row['method']))) ?> (<?= (int) $row['total'] ?>): <?= e(money($row['amount'], $row['currency'])) ?></p>
    <?php endforeach; ?>
    <?php if ($net === []): ?><p class="empty">No receipts or refunds in this date range.</p><?php endif; ?>
    <?php foreach ($net as $currency => $amount): ?><p><strong>Net: <?= e(money($amount, $currency)) ?></strong></p><?php endforeach; ?>
    <p>Average completion time: <?= $report['turnaround'] === null ? 'No completed cases' : e((string) $report['turnaround']) . ' days' ?></p>
    <p>New customers: <?= (int) $report['customers'] ?></p>
    <p>Quotations created: <?= (int) ($report['quotations']['total'] ?? 0) ?>; currently accepted or converted: <?= (int) ($report['quotations']['accepted'] ?? 0) ?></p>
</section>
</div>
<section class="card-block">
    <h2>Current outstanding invoices</h2>
    <?php if ($report['outstanding'] === []): ?><p class="empty">No outstanding invoices.</p><?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>Invoice</th><th>Status</th><th>Due date</th><th>Balance</th></tr></thead>
        <tbody><?php foreach ($report['outstanding'] as $row): ?><tr>
            <td data-label="Invoice"><?php if (app(\App\Services\AuthService::class)->can('invoices.create')): ?><a href="<?= e(url('/admin/invoices/' . $row['id'])) ?>"><?= e($row['number']) ?></a><?php else: ?><?= e($row['number']) ?><?php endif; ?></td>
            <td data-label="Status"><?= e(ucwords(str_replace('_', ' ', $row['status']))) ?></td>
            <td data-label="Due date"><?= e(format_date($row['due_at'])) ?></td>
            <td data-label="Balance"><?= e(money($row['amount_due'], $row['currency'])) ?></td>
        </tr><?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>
