<form class="card-block stack" method="post" action="<?= e(url($faq ? '/admin/faqs/' . $faq['id'] : '/admin/faqs')) ?>">
<?= csrf_field() ?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<label>Category<input name="category" value="<?= e((string) old('category', $faq['category'] ?? '')) ?>" maxlength="80"></label>
<label>Sort<input type="number" name="sort_order" min="0" max="65535" value="<?= e((string) old('sort_order', $faq['sort_order'] ?? 0)) ?>"></label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<fieldset><legend><?= e(strtoupper($locale)) ?></legend>
<label>Question<input name="question_<?= e($locale) ?>" value="<?= e((string) old('question_' . $locale, $tr['question'] ?? '')) ?>" maxlength="255"<?= $locale === 'en' ? ' required' : '' ?>></label>
<label>Answer<textarea name="answer_<?= e($locale) ?>" maxlength="5000"<?= $locale === 'en' ? ' required' : '' ?>><?= e((string) old('answer_' . $locale, $tr['answer'] ?? '')) ?></textarea></label>
</fieldset>
<?php endforeach; ?>
<input type="hidden" name="is_active" value="0"><label class="consent"><input type="checkbox" name="is_active" value="1"<?= checked((int) old('is_active', $faq['is_active'] ?? 1) === 1) ?>> Active</label>
<input type="hidden" name="show_on_home" value="0"><label class="consent"><input type="checkbox" name="show_on_home" value="1"<?= checked((int) old('show_on_home', $faq['show_on_home'] ?? 0) === 1) ?>> Home</label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
