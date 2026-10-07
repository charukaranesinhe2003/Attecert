<form class="card-block stack" method="post" action="<?= e(url($service ? '/admin/services/' . $service['id'] : '/admin/services')) ?>">
<?= csrf_field() ?>
<label>Code<input name="code" value="<?= e($service['code'] ?? '') ?>" required></label>
<label>Slug<input name="slug" value="<?= e($service['slug'] ?? '') ?>"></label>
<label>Pricing<select name="pricing_type"><option value="fixed">Fixed</option><option value="custom" <?= selected($service['pricing_type'] ?? '', 'custom') ?>>Custom</option></select></label>
<label>Fixed price<input name="fixed_price" value="<?= e((string) ($service['fixed_price'] ?? '')) ?>"></label>
<label>Days<input name="processing_days" value="<?= e((string) ($service['processing_days'] ?? '')) ?>"></label>
<label>Icon<input name="icon" value="<?= e((string) ($service['icon'] ?? 'document')) ?>"></label>
<label>Sort<input name="sort_order" value="<?= e((string) ($service['sort_order'] ?? 0)) ?>"></label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<h2><?= e(strtoupper($locale)) ?></h2>
<label>Name<input name="name_<?= e($locale) ?>" value="<?= e($tr['name'] ?? '') ?>"></label>
<label>Summary<textarea name="summary_<?= e($locale) ?>"><?= e($tr['summary'] ?? '') ?></textarea></label>
<label>Description<textarea name="description_<?= e($locale) ?>"><?= e($tr['description'] ?? '') ?></textarea></label>
<label>Process<textarea name="process_<?= e($locale) ?>"><?= e($tr['process_text'] ?? '') ?></textarea></label>
<label>Documents<textarea name="documents_<?= e($locale) ?>"><?= e($tr['documents_required'] ?? '') ?></textarea></label>
<?php endforeach; ?>
<label class="consent"><input type="checkbox" name="is_active" value="1" <?= !isset($service) || (int) $service['is_active'] === 1 ? 'checked' : '' ?>> Active</label>
<label class="consent"><input type="checkbox" name="is_public" value="1" <?= !isset($service) || (int) $service['is_public'] === 1 ? 'checked' : '' ?>> Public</label>
<label class="consent"><input type="checkbox" name="show_price" value="1" <?= isset($service) && (int) $service['show_price'] === 1 ? 'checked' : '' ?>> Show price</label>
<label class="consent"><input type="checkbox" name="featured" value="1" <?= !isset($service) || (int) $service['featured'] === 1 ? 'checked' : '' ?>> Featured</label>
<fieldset><legend>Countries</legend>
<?php foreach ($countries as $country): ?>
<label class="consent"><input type="checkbox" name="country_id[]" value="<?= e((string) $country['id']) ?>" <?= in_array($country['id'], $selectedCountries, false) ? 'checked' : '' ?>><?= e($country['name']) ?></label>
<?php endforeach; ?>
</fieldset>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
