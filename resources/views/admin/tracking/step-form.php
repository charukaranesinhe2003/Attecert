<?php
$formKey = (string) ($step['id'] ?? 'new') . '-' . $caseId;
$restore = (string) old('step_form', '') === $formKey;
$value = static fn (string $field, mixed $default = ''): mixed => $restore ? old($field, $default) : ($step[$field] ?? $default);
?>
<form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/steps' . (!empty($step['id']) ? '/' . $step['id'] : ''))) ?>">
    <?= csrf_field() ?>
    <?php if (($returnTo ?? '') === 'case'): ?><input type="hidden" name="return_to" value="case"><?php endif; ?>
    <input type="hidden" name="step_form" value="<?= e($formKey) ?>">
    <label>Step title<input name="title" value="<?= e((string) $value('title')) ?>" required minlength="2" maxlength="160" placeholder="For example, MOFA attestation"></label>
    <label>Public description<textarea name="public_description" rows="2" maxlength="1000"><?= e((string) $value('public_description')) ?></textarea></label>
    <p class="muted">The title and description appear on the public tracking page.</p>
    <div class="form-grid">
        <label>Step date<input type="date" name="step_date" value="<?= e((string) $value('step_date')) ?>"></label>
        <label>Progress<select name="state"><?php foreach (['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed'] as $state => $label): ?><option value="<?= e($state) ?>"<?= selected($value('state', 'pending'), $state) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label>Display order<input type="number" name="sort_order" min="1" max="1000" value="<?= (int) $value('sort_order', $nextOrder ?? 1) ?>" required></label>
    </div>
    <?php if (app(\App\Services\AuthService::class)->can('documents.upload')): ?>
    <label>Step document (optional)<input type="file" name="step_document" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
    <p class="muted">Optional PDF or image, up to <?= (int) setting('upload_max_mb', 10) ?> MB. Files stay with this step and are attached to its update emails.</p>
    <?php endif; ?>
    <p class="muted">Updates notify the customer by SMS and email. Previous updates stay in the tracking history.</p>
    <button class="btn btn-primary" type="submit"><?= empty($step['id']) ? 'Add tracking step' : 'Save step' ?></button>
</form>
