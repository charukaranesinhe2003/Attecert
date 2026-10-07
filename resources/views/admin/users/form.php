<form class="card-block stack" method="post" action="<?= e(url('/admin/users')) ?>"><?= csrf_field() ?>
<label>Name<input name="name" required></label>
<label>Email<input type="email" name="email" required></label>
<label>Temporary password<input type="password" name="password" required minlength="8"></label>
<label>Role<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role['code']) ?>"><?= e($role['code']) ?></option><?php endforeach; ?></select></label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
