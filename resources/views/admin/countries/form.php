<form class="card-block stack" method="post" action="<?= e(url($country ? '/admin/countries/' . $country['id'] : '/admin/countries')) ?>">
<?= csrf_field() ?>
<label>Code<input name="code" maxlength="2" value="<?= e($country['code'] ?? '') ?>" required></label>
<label>Slug<input name="slug" value="<?= e($country['slug'] ?? '') ?>"></label>
<label>Sort<input name="sort_order" value="<?= e((string) ($country['sort_order'] ?? 0)) ?>"></label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<h2><?= e(strtoupper($locale)) ?></h2>
<label>Name<input name="name_<?= e($locale) ?>" value="<?= e($tr['name'] ?? '') ?>"></label>
<label>Summary<textarea name="summary_<?= e($locale) ?>"><?= e($tr['summary'] ?? '') ?></textarea></label>
<label>Requirements<textarea name="requirements_<?= e($locale) ?>"><?= e($tr['requirements'] ?? '') ?></textarea></label>
<label>Documents<textarea name="documents_<?= e($locale) ?>"><?= e($tr['documents_required'] ?? '') ?></textarea></label>
<label>Process<textarea name="process_<?= e($locale) ?>"><?= e($tr['process_text'] ?? '') ?></textarea></label>
<label>Notes<textarea name="notes_<?= e($locale) ?>"><?= e($tr['notes'] ?? '') ?></textarea></label>
<label>Processing<input name="processing_<?= e($locale) ?>" value="<?= e($tr['processing_info'] ?? '') ?>"></label>
<?php endforeach; ?>
<label class="consent"><input type="checkbox" name="is_active" value="1" checked> Active</label>
<label class="consent"><input type="checkbox" name="featured" value="1" <?= isset($country) && (int) $country['featured'] === 1 ? 'checked' : '' ?>> Featured</label>
<fieldset><?php foreach ($services as $service): ?><label class="consent"><input type="checkbox" name="service_id[]" value="<?= e((string) $service['id']) ?>" <?= in_array($service['id'], $selected, false) ? 'checked' : '' ?>><?= e($service['name'] ?? $service['code']) ?></label><?php endforeach; ?></fieldset>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
