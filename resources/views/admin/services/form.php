<form class="card-block stack" method="post" action="<?= e(url($service ? '/admin/services/' . $service['id'] : '/admin/services')) ?>">
<?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<div class="form-grid">
<label>Code<input name="code" value="<?= e((string) old('code', $service['code'] ?? '')) ?>" required maxlength="40"></label>
<label>Slug<input name="slug" value="<?= e((string) old('slug', $service['slug'] ?? '')) ?>" maxlength="120"></label>
<label>Category<input name="category" value="<?= e((string) old('category', $service['category'] ?? '')) ?>" maxlength="60"></label>
<label>Pricing<select name="pricing_type"><option value="fixed"<?= selected(old('pricing_type', $service['pricing_type'] ?? 'fixed'), 'fixed') ?>>Fixed</option><option value="custom"<?= selected(old('pricing_type', $service['pricing_type'] ?? ''), 'custom') ?>>Custom</option></select></label>
<label>Fixed price<input name="fixed_price" value="<?= e((string) old('fixed_price', $service['fixed_price'] ?? '')) ?>"></label>
<label>Days<input name="processing_days" value="<?= e((string) old('processing_days', $service['processing_days'] ?? '')) ?>" maxlength="40"></label>
<label>Icon<input name="icon" value="<?= e((string) old('icon', $service['icon'] ?? 'document')) ?>" maxlength="40"></label>
<label>Sort<input type="number" name="sort_order" min="0" max="65535" value="<?= e((string) old('sort_order', $service['sort_order'] ?? 0)) ?>"></label>
</div>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<fieldset><legend><?= e(strtoupper($locale)) ?></legend>
<label>Name<input name="name_<?= e($locale) ?>" value="<?= e((string) old('name_' . $locale, $tr['name'] ?? '')) ?>" maxlength="160"<?= $locale === 'en' ? ' required' : '' ?>></label>
<label>Summary<textarea name="summary_<?= e($locale) ?>" maxlength="300"><?= e((string) old('summary_' . $locale, $tr['summary'] ?? '')) ?></textarea></label>
<label>Description<textarea name="description_<?= e($locale) ?>"><?= e((string) old('description_' . $locale, $tr['description'] ?? '')) ?></textarea></label>
<label>Process<textarea name="process_<?= e($locale) ?>"><?= e((string) old('process_' . $locale, $tr['process_text'] ?? '')) ?></textarea></label>
<label>Documents<textarea name="documents_<?= e($locale) ?>"><?= e((string) old('documents_' . $locale, $tr['documents_required'] ?? '')) ?></textarea></label>
<label>SEO title<input name="seo_title_<?= e($locale) ?>" value="<?= e((string) old('seo_title_' . $locale, $tr['seo_title'] ?? '')) ?>" maxlength="180"></label>
<label>Meta description<textarea name="meta_<?= e($locale) ?>" maxlength="300"><?= e((string) old('meta_' . $locale, $tr['meta_description'] ?? '')) ?></textarea></label>
</fieldset>
<?php endforeach; ?>
<?php foreach (['is_active' => ['Active', 1], 'is_public' => ['Public', 1], 'show_price' => ['Show price', 0], 'featured' => ['Featured', 1]] as $key => [$label, $default]): ?>
<input type="hidden" name="<?= e($key) ?>" value="0"><label class="consent"><input type="checkbox" name="<?= e($key) ?>" value="1"<?= checked((int) old($key, $service[$key] ?? $default) === 1) ?>> <?= e($label) ?></label>
<?php endforeach; ?>
<fieldset><legend>Countries</legend><p>Existing country price overrides and notes are retained for selected countries.</p><input type="hidden" name="country_id" value="">
<?php foreach ($countries as $country): ?>
<label class="consent"><input type="checkbox" name="country_id[]" value="<?= e((string) $country['id']) ?>"<?= checked(in_array($country['id'], (array) old('country_id', $selectedCountries), false)) ?>><?= e($country['name']) ?></label>
<?php endforeach; ?>
</fieldset>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
