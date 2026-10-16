<?php
$restoring = old('settings_form', '') === '1';
$value = static function (string $key) use ($restoring, $settings): string {
    $raw = $restoring ? old($key, $settings[$key] ?? '') : ($settings[$key] ?? '');
    return is_scalar($raw) ? (string) $raw : '';
};
$groups = [
    'company' => ['company_name', 'email', 'phone', 'whatsapp', 'address', 'hours', 'registration'],
    'finance' => ['currency', 'quote_validity_days', 'invoice_due_days'],
    'links' => ['map_url', 'facebook', 'instagram', 'linkedin'],
];
?>
<?php if (!$smtp): ?><p class="form-error" role="status"><?= e(__('admin.smtp_missing')) ?></p><?php endif; ?>
<form class="card-block stack" method="post" action="<?= e(url('/admin/settings')) ?>">
    <?= csrf_field() ?><input type="hidden" name="settings_form" value="1">
    <?php foreach ($groups as $group => $keys): ?>
    <fieldset class="stack"><legend><?= e(__('admin_settings.' . $group)) ?></legend>
        <?php foreach ($keys as $key): $isDays = str_ends_with($key, '_days'); ?>
        <label for="setting-<?= e($key) ?>"><?= e(__('admin_settings.' . $key)) ?></label>
        <input id="setting-<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($value($key)) ?>" type="<?= $isDays ? 'number' : ($key === 'email' ? 'email' : (in_array($group, ['links'], true) ? 'url' : 'text')) ?>" <?= $isDays ? 'min="1" max="3650" step="1"' : '' ?> <?= $key === 'currency' ? 'minlength="3" maxlength="3" pattern="[A-Za-z]{3}"' : '' ?> <?= in_array($key, ['company_name', 'email', 'currency', 'quote_validity_days', 'invoice_due_days'], true) ? 'required' : '' ?> aria-describedby="setting-<?= e($key) ?>-error" <?= error($key) ? 'aria-invalid="true"' : '' ?>>
        <span class="form-error" id="setting-<?= e($key) ?>-error"><?= e(error($key)) ?></span>
        <?php endforeach; ?>
    </fieldset>
    <?php endforeach; ?>
    <fieldset class="stack"><legend><?= e(__('admin_settings.terms')) ?></legend>
        <?php foreach (['quote_terms', 'invoice_terms'] as $key): ?>
        <label for="setting-<?= e($key) ?>"><?= e(__('admin_settings.' . $key)) ?></label>
        <textarea id="setting-<?= e($key) ?>" name="<?= e($key) ?>" maxlength="10000" aria-describedby="setting-<?= e($key) ?>-error"><?= e($value($key)) ?></textarea>
        <span class="form-error" id="setting-<?= e($key) ?>-error"><?= e(error($key)) ?></span>
        <?php endforeach; ?>
    </fieldset>
    <fieldset class="stack"><legend><?= e(__('admin_settings.access')) ?></legend>
        <label for="setting-upload_max_mb"><?= e(__('admin_settings.upload_max_mb')) ?></label>
        <input id="setting-upload_max_mb" type="number" name="upload_max_mb" min="1" max="<?= (int) $uploadLimit ?>" step="1" value="<?= e($value('upload_max_mb')) ?>" required aria-describedby="upload-help upload-error">
        <span id="upload-help"><?= e(__('admin_settings.upload_help', ['max' => (string) $uploadLimit])) ?></span>
        <span class="form-error" id="upload-error"><?= e(error('upload_max_mb')) ?></span>
        <?php foreach (['email_verification', 'maintenance_mode'] as $key): ?>
        <input type="hidden" name="<?= e($key) ?>" value="0">
        <label class="consent"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= checked($value($key) === '1') ?>> <?= e(__('admin_settings.' . $key)) ?></label>
        <p class="form-error"><?= e(error($key)) ?></p>
        <?php endforeach; ?>
    </fieldset>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
