<?php if ($requiredChange): ?>
    <p class="toast toast-error" role="alert"><?= e(__('staff_security.required')) ?></p>
<?php endif; ?>
<form class="card-block stack" method="post" action="<?= e(url('/admin/password')) ?>" aria-describedby="password-help">
    <?= csrf_field() ?>
    <p id="password-help"><?= e(__('staff_security.help')) ?></p>
    <?php foreach ([
        'current_password' => ['current', 'current-password'],
        'password' => ['next', 'new-password'],
        'password_confirmation' => ['confirmation', 'new-password'],
    ] as $field => [$label, $autocomplete]): ?>
        <label for="staff-<?= e($field) ?>"><?= e(__('staff_security.' . $label)) ?></label>
        <input id="staff-<?= e($field) ?>" type="password" name="<?= e($field) ?>" autocomplete="<?= e($autocomplete) ?>" required minlength="8" maxlength="200" <?= isset($fieldErrors[$field]) ? 'aria-invalid="true" aria-describedby="staff-' . e($field) . '-error"' : '' ?>>
        <?php if (isset($fieldErrors[$field])): ?>
            <p class="form-error" id="staff-<?= e($field) ?>-error" role="alert"><?= e($fieldErrors[$field]) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit"><?= e(__('staff_security.save')) ?></button>
</form>
