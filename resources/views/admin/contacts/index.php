<div class="filters">
    <a class="btn <?= $source === 'all' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/contacts?source=all')) ?>"><?= e(__('admin.messages_all')) ?></a>
    <a class="btn <?= $source === 'guest' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/contacts?source=guest')) ?>"><?= e(__('admin.messages_guest')) ?></a>
    <a class="btn <?= $source === 'support' ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/contacts?source=support')) ?>"><?= e(__('admin.messages_support')) ?></a>
</div>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('fields.name')) ?></th><th><?= e(__('admin.source')) ?></th><th><?= e(__('fields.message')) ?></th><th><?= e(__('fields.status')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?>
<tr>
<td><?= e($row['name']) ?><br><?= e($row['email']) ?><?php if (!empty($row['customer_id'])): ?><br><?= e(customer_code(['id' => $row['customer_id']])) ?><?php endif; ?></td>
<td><?= e($row['source'] === 'support' ? __('admin.messages_support') : __('admin.messages_guest')) ?></td>
<td><?= e($row['subject']) ?><br><?= e($row['message']) ?></td>
<td>
<form method="post" action="<?= e(url('/admin/contacts/' . $row['id'] . '/status')) ?>"><?= csrf_field() ?>
<select name="status"><?php foreach (['new','contacted','closed','spam'] as $state): ?><option value="<?= e($state) ?>" <?= selected($row['status'], $state) ?>><?= e($state) ?></option><?php endforeach; ?></select>
<button><?= e(__('actions.save')) ?></button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/contacts') ?>
<?php endif; ?>
