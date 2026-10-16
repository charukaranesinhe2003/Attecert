<p><a class="btn btn-primary" href="<?= e(url('/admin/users/create')) ?>"><?= e(__('admin.new_user')) ?></a></p>
<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<p><?= e(__('admin_lists.staff_hint')) ?> <a href="<?= e(url('/admin/customers')) ?>"><?= e(__('admin.customers')) ?></a></p>
<?= \App\Core\View::render('admin/partials/list-filters', ['listPath' => '/admin/users', 'filters' => $filters, 'page' => $page, 'roleOptions' => $roles, 'statusOptions' => ['active' => __('admin_lists.active'), 'inactive' => __('admin_lists.inactive')]]) ?>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.name_email')) ?></th><th><?= e(__('admin_lists.current_roles')) ?></th><th><?= e(__('admin_lists.account_access')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $user): $roleCodes = explode(',', (string) $user['role_codes']); ?>
<tr>
<td data-label="<?= e(__('admin_lists.name_email')) ?>"><?= e($user['name']) ?><br><?= e($user['email']) ?></td>
<td data-label="<?= e(__('admin_lists.current_roles')) ?>"><?= e(implode(', ', $roleCodes)) ?></td>
<td data-label="<?= e(__('admin_lists.account_access')) ?>">
<?php if (in_array('super_admin', $roleCodes, true) && !$canManagePrivileged): ?>
    <span><?= e(__('admin_lists.protected')) ?></span>
<?php else: ?>
    <form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?>
        <label><?= e(__('admin_lists.replace_roles')) ?><span class="sr-only">: <?= e($user['name']) ?></span><select name="role"><option value=""><?= e(__('admin_lists.keep_roles')) ?></option><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="is_active" value="0">
        <label class="consent"><input type="checkbox" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>> <?= e(__('admin.active')) ?><?= (int) $user['id'] === $currentUserId ? ' (' . e(__('admin_lists.your_account')) . ')' : '' ?><span class="sr-only">: <?= e($user['name']) ?></span></label>
        <button type="submit"><?= e(__('actions.save')) ?><span class="sr-only">: <?= e($user['name']) ?></span></button>
    </form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/users') ?>
<?php endif; ?>
