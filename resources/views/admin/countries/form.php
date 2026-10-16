<form class="card-block stack" method="post" action="<?= e(url($country ? '/admin/countries/' . $country['id'] : '/admin/countries')) ?>">
<?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<div class="form-grid">
<label>Code<input name="code" maxlength="2" value="<?= e((string) old('code', $country['code'] ?? '')) ?>" required></label>
<label>Slug<input name="slug" value="<?= e((string) old('slug', $country['slug'] ?? '')) ?>" maxlength="80"></label>
<label>Sort<input type="number" name="sort_order" min="0" max="65535" value="<?= e((string) old('sort_order', $country['sort_order'] ?? 0)) ?>"></label>
</div>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<fieldset><legend><?= e(strtoupper($locale)) ?></legend>
<label>Name<input name="name_<?= e($locale) ?>" value="<?= e((string) old('name_' . $locale, $tr['name'] ?? '')) ?>" maxlength="120"<?= $locale === 'en' ? ' required' : '' ?>></label>
<label>Summary<textarea name="summary_<?= e($locale) ?>" maxlength="300"><?= e((string) old('summary_' . $locale, $tr['summary'] ?? '')) ?></textarea></label>
<label>Requirements<textarea name="requirements_<?= e($locale) ?>"><?= e((string) old('requirements_' . $locale, $tr['requirements'] ?? '')) ?></textarea></label>
<label>Documents<textarea name="documents_<?= e($locale) ?>"><?= e((string) old('documents_' . $locale, $tr['documents_required'] ?? '')) ?></textarea></label>
<label>Process<textarea name="process_<?= e($locale) ?>"><?= e((string) old('process_' . $locale, $tr['process_text'] ?? '')) ?></textarea></label>
<label>Notes<textarea name="notes_<?= e($locale) ?>"><?= e((string) old('notes_' . $locale, $tr['notes'] ?? '')) ?></textarea></label>
<label>Processing<input name="processing_<?= e($locale) ?>" value="<?= e((string) old('processing_' . $locale, $tr['processing_info'] ?? '')) ?>" maxlength="180"></label>
<label>SEO title<input name="seo_title_<?= e($locale) ?>" value="<?= e((string) old('seo_title_' . $locale, $tr['seo_title'] ?? '')) ?>" maxlength="180"></label>
<label>Meta description<textarea name="meta_<?= e($locale) ?>" maxlength="300"><?= e((string) old('meta_' . $locale, $tr['meta_description'] ?? '')) ?></textarea></label>
</fieldset>
<?php endforeach; ?>
<input type="hidden" name="is_active" value="0"><label class="consent"><input type="checkbox" name="is_active" value="1"<?= checked((int) old('is_active', $country['is_active'] ?? 1) === 1) ?>> Active</label>
<input type="hidden" name="featured" value="0"><label class="consent"><input type="checkbox" name="featured" value="1"<?= checked((int) old('featured', $country['featured'] ?? 0) === 1) ?>> Featured</label>
<fieldset><legend>Services</legend><p>Existing price overrides and notes are retained for selected services.</p><input type="hidden" name="service_id" value=""><?php foreach ($services as $service): ?><label class="consent"><input type="checkbox" name="service_id[]" value="<?= e((string) $service['id']) ?>"<?= checked(in_array($service['id'], (array) old('service_id', $selected), false)) ?>><?= e($service['name'] ?? $service['code']) ?></label><?php endforeach; ?></fieldset>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
