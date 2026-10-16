<form class="filters" method="get">
    <label><?= e(__('admin_lists.search')) ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="190"></label>
    <label><?= e(__('admin_lists.source')) ?><select name="source"><?php foreach (['all' => 'admin.messages_all', 'guest' => 'admin.messages_guest', 'support' => 'admin.messages_support'] as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($source, $value) ?>><?= e(__($label)) ?></option><?php endforeach; ?></select></label>
    <label><?= e(__('admin_lists.status')) ?><select name="status"><option value=""><?= e(__('admin_lists.all')) ?></option><?php foreach (['new','contacted','closed','spam'] as $state): ?><option value="<?= e($state) ?>"<?= selected($status, $state) ?>><?= e(__('admin_lists.' . $state)) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary"><?= e(__('actions.filter')) ?></button><a class="btn btn-ghost" href="<?= e(url('/admin/contacts')) ?>"><?= e(__('admin_lists.clear')) ?></a>
</form>
<p><?= e(__('admin_lists.results', ['count' => (string) $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.contact')) ?></th><th><?= e(__('admin_lists.source')) ?></th><th><?= e(__('admin_lists.message')) ?></th><th><?= e(__('admin_lists.status')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?><tr>
    <td data-label="<?= e(__('admin_lists.contact')) ?>"><?= e($row['name']) ?><br><?= e($row['email']) ?><br><?= e(format_datetime($row['created_at'])) ?><?php if (!empty($row['customer_id']) && app(\App\Services\AuthService::class)->can('customers.view')): ?><br><a href="<?= e(url('/admin/customers/' . $row['customer_id'])) ?>"><?= e(customer_code(['id' => $row['customer_id']])) ?></a><?php endif; ?></td>
    <td data-label="<?= e(__('admin_lists.source')) ?>"><?= e($row['source'] === 'support' ? __('admin.messages_support') : __('admin.messages_guest')) ?></td>
    <td data-label="<?= e(__('admin_lists.message')) ?>"><strong><?= e((string) $row['subject']) ?></strong><p><?= e(str_limit($row['message'], 160)) ?></p><details><summary><?= e(__('admin_lists.show_details')) ?></summary><p><?= nl2br(e($row['message'])) ?></p></details></td>
    <td data-label="<?= e(__('admin_lists.status')) ?>"><form method="post" action="<?= e(url('/admin/contacts/' . $row['id'] . '/status')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="source" value="<?= e($source) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="return_status" value="<?= e($status) ?>">
        <label class="sr-only" for="message-status-<?= (int) $row['id'] ?>"><?= e(__('admin_lists.status')) ?>: <?= e($row['name']) ?></label>
        <select id="message-status-<?= (int) $row['id'] ?>" name="status"><?php foreach (['new','contacted','closed','spam'] as $state): ?><option value="<?= e($state) ?>"<?= selected($row['status'], $state) ?>><?= e(__('admin_lists.' . $state)) ?></option><?php endforeach; ?></select>
        <button><?= e(__('actions.save')) ?></button></form></td>
</tr><?php endforeach; ?>
</tbody></table></div><?= paginator($page, '/admin/contacts') ?>
<?php endif; ?>
