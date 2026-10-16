<form class="filters" method="get">
    <label><span class="sr-only"><?= e(__('actions.search')) ?></span><input name="q" value="<?= e($q) ?>" placeholder="<?= e(__('actions.search')) ?>"></label>
    <label><span class="sr-only"><?= e(__('fields.status')) ?></span><select name="status"><option value=""><?= e(__('admin_overview.all_statuses')) ?></option><?php foreach (['draft','sent','viewed','accepted','rejected','expired','converted','superseded'] as $state): ?><option value="<?= e($state) ?>" <?= selected($status, $state) ?>><?= e($state) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/quotations')) ?>"><?= e(__('admin_overview.reset')) ?></a>
</form>
<p class="overview-note"><?= e(__('admin_overview.results', ['count' => $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table>
    <thead><tr><th><?= e(__('fields.number')) ?></th><th><?= e(__('admin.cases')) ?></th><th><?= e(__('admin.customers')) ?></th><th><?= e(__('fields.status')) ?></th><th><?= e(__('fields.total')) ?></th></tr></thead>
    <tbody><?php foreach ($page['rows'] as $row): ?><tr>
        <td data-label="<?= e(__('fields.number')) ?>"><a href="<?= e(url('/admin/quotations/' . $row['id'])) ?>"><?= e($row['number']) ?></a></td>
        <td data-label="<?= e(__('admin.cases')) ?>"><?= e($row['case_number']) ?></td>
        <td data-label="<?= e(__('admin.customers')) ?>"><?= e($row['customer_name']) ?></td>
        <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status'], in_array($row['status'], ['accepted', 'converted'], true) ? 'success' : 'neutral') ?></td>
        <td data-label="<?= e(__('fields.total')) ?>"><?= e(money($row['total'], $row['currency'])) ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?= paginator($page, '/admin/quotations') ?>
<?php endif; ?>
