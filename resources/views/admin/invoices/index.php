<form class="filters" method="get">
    <label><span class="sr-only"><?= e(__('actions.search')) ?></span><input name="q" value="<?= e($q) ?>" placeholder="<?= e(__('actions.search')) ?>"></label>
    <label><span class="sr-only"><?= e(__('fields.status')) ?></span><select name="status">
        <option value=""><?= e(__('admin_overview.all_statuses')) ?></option>
        <option value="unpaid" <?= selected($status, 'unpaid') ?>><?= e(__('admin_overview.unpaid')) ?></option>
        <?php foreach (['issued', 'sent', 'partially_paid', 'overdue', 'paid', 'cancelled', 'refunded'] as $state): ?><option value="<?= e($state) ?>" <?= selected($status, $state) ?>><?= e($state === 'overdue' ? __('admin_overview.overdue') : str_replace('_', ' ', $state)) ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/invoices')) ?>"><?= e(__('admin_overview.reset')) ?></a>
</form>
<p class="overview-note"><?= e(__('admin_overview.results', ['count' => $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table>
    <thead><tr><th><?= e(__('fields.number')) ?></th><th><?= e(__('admin.cases')) ?></th><th><?= e(__('admin.customers')) ?></th><th><?= e(__('fields.status')) ?></th><th><?= e(__('fields.due')) ?></th><th><?= e(__('admin_overview.due')) ?></th></tr></thead>
    <tbody><?php foreach ($page['rows'] as $row): ?><tr>
        <td data-label="<?= e(__('fields.number')) ?>"><a href="<?= e(url('/admin/invoices/' . $row['id'])) ?>"><?= e($row['number']) ?></a></td>
        <td data-label="<?= e(__('admin.cases')) ?>"><?= e($row['case_number']) ?></td>
        <td data-label="<?= e(__('admin.customers')) ?>"><?= e($row['customer_name']) ?></td>
        <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status'], $row['status'] === 'paid' ? 'success' : ($row['status'] === 'overdue' ? 'danger' : 'neutral')) ?></td>
        <td data-label="<?= e(__('fields.due')) ?>"><?= e(money($row['amount_due'], $row['currency'])) ?></td>
        <td data-label="<?= e(__('admin_overview.due')) ?>"><?= e($row['due_at'] ? format_date($row['due_at']) : '—') ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?= paginator($page, '/admin/invoices') ?>
<?php endif; ?>
