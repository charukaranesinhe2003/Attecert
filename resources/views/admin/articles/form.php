<form class="card-block stack" method="post" enctype="multipart/form-data" action="<?= e(url($article ? '/admin/articles/' . $article['id'] : '/admin/articles')) ?>">
<?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<label>Status<select name="status"><option value="draft"<?= selected(old('status', $article['status'] ?? 'draft'), 'draft') ?>>Draft</option><option value="published"<?= selected(old('status', $article['status'] ?? ''), 'published') ?>>Published</option></select></label>
<label>Slug<input name="slug" value="<?= e((string) old('slug', $article['slug'] ?? '')) ?>" maxlength="160"></label>
<label>Image<input type="file" name="image" accept="image/*"></label>
<input type="hidden" name="featured" value="0"><label class="consent"><input type="checkbox" name="featured" value="1"<?= checked((int) old('featured', $article['featured'] ?? 0) === 1) ?>> Featured</label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<fieldset><legend><?= e(strtoupper($locale)) ?></legend>
<label>Title<input name="title_<?= e($locale) ?>" value="<?= e((string) old('title_' . $locale, $tr['title'] ?? '')) ?>" maxlength="200"<?= $locale === 'en' ? ' required' : '' ?>></label>
<label>Excerpt<textarea name="excerpt_<?= e($locale) ?>" maxlength="400"><?= e((string) old('excerpt_' . $locale, $tr['excerpt'] ?? '')) ?></textarea></label>
<label>Content<textarea name="content_<?= e($locale) ?>" rows="6"><?= e((string) old('content_' . $locale, $tr['content'] ?? '')) ?></textarea></label>
<label>SEO title<input name="seo_title_<?= e($locale) ?>" value="<?= e((string) old('seo_title_' . $locale, $tr['seo_title'] ?? '')) ?>" maxlength="180"></label>
<label>Meta description<textarea name="meta_<?= e($locale) ?>" maxlength="300"><?= e((string) old('meta_' . $locale, $tr['meta_description'] ?? '')) ?></textarea></label>
</fieldset>
<?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
