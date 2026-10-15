<form class="card-block stack" method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<label>Name<input name="name" value="<?= e((string) old('name')) ?>" required maxlength="160"></label>
<label>Email<input type="email" name="email" value="<?= e((string) old('email')) ?>" required maxlength="190"></label>
<label>Temporary password<input type="password" name="password" required minlength="8"></label>
<label>Role<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"<?= selected(old('role'), $role['code']) ?>><?= e($role['name']) ?></option><?php endforeach; ?></select></label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
