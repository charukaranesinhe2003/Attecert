<form class="filters" method="get">
    <label><?= e(__('admin_lists.search')) ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="190"></label>
    <label><?= e(__('admin_lists.status')) ?><select name="status"><option value=""><?= e(__('admin_lists.all')) ?></option><?php foreach (['new','contacted','converted','closed','spam'] as $state): ?><option value="<?= e($state) ?>"<?= selected($status, $state) ?>><?= e(__('admin_lists.' . $state)) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary"><?= e(__('actions.filter')) ?></button><a class="btn btn-ghost" href="<?= e(url('/admin/quote-requests')) ?>"><?= e(__('admin_lists.clear')) ?></a>
</form>
<p><?= e(__('admin_lists.results', ['count' => (string) $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.customer')) ?></th><th><?= e(__('admin_lists.contact')) ?></th><th><?= e(__('admin_lists.date')) ?></th><th><?= e(__('admin_lists.status')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?><tr>
    <td data-label="<?= e(__('admin_lists.customer')) ?>"><a href="<?= e(url('/admin/quote-requests/' . $row['id'])) ?>"><?= e($row['name']) ?></a><br><?= e((string) $row['country_name']) ?></td>
    <td data-label="<?= e(__('admin_lists.contact')) ?>"><?= e($row['email']) ?><br><?= e((string) $row['phone']) ?></td>
    <td data-label="<?= e(__('admin_lists.date')) ?>"><?= e(format_datetime($row['created_at'])) ?></td>
    <td data-label="<?= e(__('admin_lists.status')) ?>"><?= e(__('admin_lists.' . $row['status'])) ?></td>
</tr><?php endforeach; ?></tbody></table></div><?= paginator($page, '/admin/quote-requests') ?>
<?php endif; ?>
