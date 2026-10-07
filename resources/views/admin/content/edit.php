<form method="post" action="<?= e(url('/admin/content')) ?>"><?= csrf_field() ?>
<?php foreach ($blocks as $key => $locales): if (!is_string($key)) { continue; } ?>
<fieldset class="card-block"><legend><?= e($key) ?></legend>
<input type="hidden" name="keys[]" value="<?= e($key) ?>">
<?php foreach (['en', 'si', 'ta'] as $locale): ?>
<label><?= e($locale) ?><textarea name="<?= e($key . '_' . $locale) ?>"><?= e($locales[$locale] ?? '') ?></textarea></label>
<?php endforeach; ?>
</fieldset>
<?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
