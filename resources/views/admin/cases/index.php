<form class="filters" method="get">
    <label><span class="sr-only"><?= e(__('actions.search')) ?></span><input name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('actions.search')) ?>"></label>
    <label><span class="sr-only"><?= e(__('fields.status')) ?></span><select name="status">
        <option value=""><?= e(__('admin_overview.all_statuses')) ?></option>
        <option value="active" <?= selected($filters['status'], 'active') ?>><?= e(__('admin.active_cases')) ?></option>
        <option value="unquoted" <?= selected($filters['status'], 'unquoted') ?>><?= e(__('admin_overview.unquoted')) ?></option>
        <?php foreach ($statuses as $status): ?><option value="<?= e($status['code']) ?>" <?= selected($filters['status'], $status['code']) ?>><?= e($status['name']) ?></option><?php endforeach; ?>
    </select></label>
    <label><span class="sr-only"><?= e(__('admin.countries')) ?></span><select name="country_id">
        <option value=""><?= e(__('admin_overview.all_countries')) ?></option>
        <?php foreach ($countries as $country): ?><option value="<?= (int) $country['id'] ?>" <?= selected($filters['country_id'], $country['id']) ?>><?= e($country['name'] ?? $country['code']) ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/cases')) ?>"><?= e(__('admin_overview.reset')) ?></a>
</form>
<p class="overview-note"><?= e(__('admin_overview.results', ['count' => $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?>
<p class="empty"><?= e(__('empty.results')) ?></p>
<?php else: ?>
<div class="table-wrap"><table>
    <thead><tr><th><?= e(__('fields.number')) ?></th><th><?= e(__('admin.customers')) ?></th><th><?= e(__('admin.countries')) ?></th><th><?= e(__('fields.status')) ?></th><th><?= e(__('fields.date')) ?></th></tr></thead>
    <tbody><?php foreach ($page['rows'] as $row): ?><tr>
        <td data-label="<?= e(__('fields.number')) ?>"><a href="<?= e(url('/admin/cases/' . $row['id'])) ?>"><?= e($row['number']) ?></a><small class="record-detail"><?= e($row['document_title']) ?></small></td>
        <td data-label="<?= e(__('admin.customers')) ?>"><?= e($row['customer_name']) ?></td>
        <td data-label="<?= e(__('admin.countries')) ?>"><?= e($row['country_name'] ?? '—') ?></td>
        <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status_name'] ?: $row['status_code'], $row['badge_tone']) ?></td>
        <td data-label="<?= e(__('fields.date')) ?>"><?= e(format_date($row['created_at'])) ?></td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?= paginator($page, '/admin/cases') ?>
<?php endif; ?>
