<form class="card-block stack" method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<p>Admin and Super admin roles can access all cases and quote requests. Every other staff role can only access work assigned to them in Operations.</p>
<label>Name<input name="name" value="<?= e((string) old('name')) ?>" required maxlength="160"></label>
<label>Email<input type="email" name="email" value="<?= e((string) old('email')) ?>" required maxlength="190"></label>
<label>Temporary password<input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="200"></label>
<p>The staff member must change this temporary password at their first sign-in.</p>
<label>Role<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"<?= selected(old('role', 'processing_staff'), $role['code']) ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
