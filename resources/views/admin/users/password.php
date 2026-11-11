<form class="card-block stack" method="post" action="<?= e(url('/admin/users/' . $staffUser['id'] . '/password')) ?>">
    <?= csrf_field() ?>
    <h2><?= e($staffUser['name']) ?></h2>
    <p><?= e($staffUser['email']) ?></p>
    <p>This signs out the staff member's existing sessions. Give them the temporary password securely; they must change it at their next sign-in.</p>
    <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
    <label>Temporary password<input type="password" name="password" autocomplete="new-password" required minlength="8" maxlength="200"></label>
    <label>Confirm temporary password<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" maxlength="200"></label>
    <button class="btn btn-primary" type="submit">Change staff password</button>
    <a href="<?= e(url('/admin/users')) ?>">Back to staff</a>
</form>
