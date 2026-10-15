<p><a class="btn btn-primary" href="<?= e(url('/admin/users/create')) ?>"><?= e(__('admin.new_user')) ?></a></p>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<p>Customer accounts are managed from <a href="<?= e(url('/admin/customers')) ?>"><?= e(__('admin.customers')) ?></a>. Leave the role unchanged to retain all current roles.</p>
<?php if ($users === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Name / email</th><th>Current roles</th><th>Account access</th></tr></thead><tbody>
<?php foreach ($users as $user): $roleCodes = explode(',', (string) $user['role_codes']); ?>
<tr>
<td><?= e($user['name']) ?><br><?= e($user['email']) ?></td>
<td><?= e(implode(', ', $roleCodes)) ?></td>
<td>
<?php if (in_array('super_admin', $roleCodes, true) && !$canManagePrivileged): ?>
    <span>Super administrator access is protected.</span>
<?php else: ?>
    <form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?>
        <label>Replace roles<select name="role"><option value="">Keep current roles</option><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="is_active" value="0">
        <label class="consent"><input type="checkbox" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>> <?= e(__('admin.active')) ?><?= (int) $user['id'] === $currentUserId ? ' (your account)' : '' ?></label>
        <button type="submit"><?= e(__('actions.save')) ?></button>
    </form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
