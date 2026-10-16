<form class="filters" method="get">
    <label><?= e(__('admin_lists.search')) ?><input type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="190"></label>
    <?php foreach (['method' => ['cash','bank_transfer','card','online','other','refund'], 'status' => ['completed','refunded']] as $field => $options): ?>
    <label><?= e(__('admin_lists.' . $field)) ?><select name="<?= e($field) ?>"><option value=""><?= e(__('admin_lists.all')) ?></option><?php foreach ($options as $option): ?><option value="<?= e($option) ?>"<?= selected($filters[$field], $option) ?>><?= e(__('admin_lists.' . $option)) ?></option><?php endforeach; ?></select></label>
    <?php endforeach; ?>
    <?php foreach (['from', 'to'] as $field): ?><label><?= e(__('admin_lists.' . $field)) ?><input type="date" name="<?= e($field) ?>" value="<?= e($filters[$field]) ?>"></label><?php endforeach; ?>
    <button class="btn btn-primary"><?= e(__('actions.filter')) ?></button><a class="btn btn-ghost" href="<?= e(url('/admin/payments')) ?>"><?= e(__('admin_lists.clear')) ?></a>
</form>
<?php foreach ($rangeErrors as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<p><?= e(__('admin_lists.results', ['count' => (string) $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><?php foreach (['number','customer','amount','method','date','reference','receipt'] as $field): ?><th><?= e(__('admin_lists.' . $field)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?><tr>
    <td data-label="<?= e(__('admin_lists.number')) ?>"><?= e($row['number']) ?><br><?php if (app(\App\Services\AuthService::class)->can('invoices.create')): ?><a href="<?= e(url('/admin/invoices/' . $row['invoice_id'])) ?>"><?= e($row['invoice_number']) ?></a><?php else: ?><?= e($row['invoice_number']) ?><?php endif; ?></td>
    <td data-label="<?= e(__('admin_lists.customer')) ?>"><?= e($row['customer_name']) ?></td>
    <td data-label="<?= e(__('admin_lists.amount')) ?>"><?= e(money($row['amount'], $row['currency'])) ?></td>
    <td data-label="<?= e(__('admin_lists.method')) ?>"><?= e(__('admin_lists.' . $row['method'])) ?><br><?= e(__('admin_lists.' . $row['status'])) ?></td>
    <td data-label="<?= e(__('admin_lists.date')) ?>"><?= e(format_date($row['paid_at'])) ?></td>
    <td data-label="<?= e(__('admin_lists.reference')) ?>"><?= e((string) $row['reference']) ?></td>
    <td data-label="<?= e(__('admin_lists.receipt')) ?>"><a href="<?= e(url('/admin/payments/' . $row['id'] . '/receipt')) ?>"><?= e(__('actions.download')) ?></a></td>
</tr><?php endforeach; ?></tbody></table></div><?= paginator($page, '/admin/payments') ?>
<?php endif; ?>
