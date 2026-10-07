<form class="card-block stack" method="post" enctype="multipart/form-data" action="<?= e(url($article ? '/admin/articles/' . $article['id'] : '/admin/articles')) ?>">
<?= csrf_field() ?>
<label>Status<select name="status"><option value="draft">Draft</option><option value="published" <?= selected($article['status'] ?? '', 'published') ?>>Published</option></select></label>
<label>Slug<input name="slug" value="<?= e($article['slug'] ?? '') ?>"></label>
<label>Image<input type="file" name="image" accept="image/*"></label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<h2><?= e($locale) ?></h2>
<label>Title<input name="title_<?= e($locale) ?>" value="<?= e($tr['title'] ?? '') ?>"></label>
<label>Excerpt<textarea name="excerpt_<?= e($locale) ?>"><?= e($tr['excerpt'] ?? '') ?></textarea></label>
<label>Content<textarea name="content_<?= e($locale) ?>" rows="6"><?= e($tr['content'] ?? '') ?></textarea></label>
<label>SEO<input name="seo_title_<?= e($locale) ?>" value="<?= e($tr['seo_title'] ?? '') ?>"></label>
<label>Meta<textarea name="meta_<?= e($locale) ?>"><?= e($tr['meta_description'] ?? '') ?></textarea></label>
<?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
