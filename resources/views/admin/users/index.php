<p><a class="btn btn-primary" href="<?= e(url('/admin/users/create')) ?>"><?= e(__('admin.new_user')) ?></a></p>
<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<p><?= e(__('admin_lists.staff_hint')) ?> <a href="<?= e(url('/admin/customers')) ?>"><?= e(__('admin.customers')) ?></a></p>
<p>Admin and Super admin roles have full case and quote-request access. Other staff can manage only work assigned to them in <a href="<?= e(url('/admin/operations')) ?>">Operations</a>.</p>
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
    <form class="stack" method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?>
        <label><?= e(__('admin_lists.replace_roles')) ?><span class="sr-only">: <?= e($user['name']) ?></span><select name="role"><option value=""><?= e(__('admin_lists.keep_roles')) ?></option><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="is_active" value="0">
        <label class="consent"><input type="checkbox" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>> <?= e(__('admin.active')) ?><?= (int) $user['id'] === $currentUserId ? ' (' . e(__('admin_lists.your_account')) . ')' : '' ?><span class="sr-only">: <?= e($user['name']) ?></span></label>
        <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?><span class="sr-only">: <?= e($user['name']) ?></span></button>
    </form>
    <p class="staff-password-action"><a class="btn btn-ghost" href="<?= e(url((int) $user['id'] === $currentUserId ? '/admin/password' : '/admin/users/' . $user['id'] . '/password')) ?>">Change password<span class="sr-only">: <?= e($user['name']) ?></span></a></p>
    <?php if ((int) $user['id'] !== $currentUserId): ?>
    <details>
        <summary>Delete staff<span class="sr-only">: <?= e($user['name']) ?></span></summary>
        <form class="stack" method="post" action="<?= e(url('/admin/users/' . $user['id'] . '/delete')) ?>">
            <?= csrf_field() ?>
            <p>Remove this account's access and return their assigned cases and quote requests to Operations. History is retained.</p>
            <label class="consent"><input type="checkbox" name="confirmed" value="1" required> Delete <?= e($user['name']) ?> (<?= e($user['email']) ?>)</label>
            <button class="btn btn-ghost" type="submit">Delete staff account</button>
        </form>
    </details>
    <?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/users') ?>
<?php endif; ?>
