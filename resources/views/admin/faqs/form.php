<form class="card-block stack" method="post" action="<?= e(url($faq ? '/admin/faqs/' . $faq['id'] : '/admin/faqs')) ?>">
<?= csrf_field() ?>
<label>Category<input name="category" value="<?= e($faq['category'] ?? '') ?>"></label>
<label>Sort<input name="sort_order" value="<?= e((string) ($faq['sort_order'] ?? 0)) ?>"></label>
<?php foreach (['en', 'si', 'ta'] as $locale): $tr = $translations[$locale] ?? []; ?>
<label>Question <?= e($locale) ?><input name="question_<?= e($locale) ?>" value="<?= e($tr['question'] ?? '') ?>"></label>
<label>Answer <?= e($locale) ?><textarea name="answer_<?= e($locale) ?>"><?= e($tr['answer'] ?? '') ?></textarea></label>
<?php endforeach; ?>
<label class="consent"><input type="checkbox" name="is_active" value="1" checked> Active</label>
<label class="consent"><input type="checkbox" name="show_on_home" value="1" <?= isset($faq) && (int) $faq['show_on_home'] === 1 ? 'checked' : '' ?>> Home</label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
