<p><a class="btn btn-primary" href="<?= e(url('/admin/users/create')) ?>"><?= e(__('admin.new_user')) ?></a></p>
<div class="table-wrap"><table><tbody>
<?php foreach ($users as $user): ?>
<tr>
<td><?= e($user['name']) ?><br><?= e($user['email']) ?></td>
<td><?= e((string) $user['role_codes']) ?></td>
<td><form method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>"><?= csrf_field() ?>
<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>" <?= str_contains((string) $user['role_codes'], $role['code']) ? 'selected' : '' ?>><?= e($role['code']) ?></option><?php endforeach; ?></select>
<label class="consent"><input type="checkbox" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
<button><?= e(__('actions.save')) ?></button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
