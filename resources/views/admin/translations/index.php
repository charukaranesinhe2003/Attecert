<form class="filters" method="get"><label><?= e(__('admin_lists.search')) ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="190"></label><button class="btn btn-primary"><?= e(__('actions.search')) ?></button><a class="btn btn-ghost" href="<?= e(url('/admin/translations')) ?>"><?= e(__('admin_lists.clear')) ?></a></form>
<p><?= e(__('admin_lists.results', ['count' => (string) $page['total']])) ?></p>
<p><?= e(__('admin_lists.translation_hint')) ?></p>
<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<?php if ($keys === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php endif; ?>
<?php foreach ($keys as $key => $english): ?>
<section class="card-block stack">
    <h2><?= e($key) ?></h2>
    <form class="stack" method="post" action="<?= e(url('/admin/translations')) ?>"><?= csrf_field() ?><input type="hidden" name="trans_key" value="<?= e($key) ?>">
    <?php foreach (config('app.locales') as $language => $name):
        $fieldId = 'translation-' . md5($key . $language);
        $value = $overrides[$key][$language] ?? $defaults[$key][$language];
        if (old('trans_key') === $key && is_string(old('value_' . $language, null))) {
            $value = old('value_' . $language);
        }
    ?>
        <label for="<?= e($fieldId) ?>"><?= e($name) ?></label><textarea id="<?= e($fieldId) ?>" name="value_<?= e($language) ?>" maxlength="10000"><?= e($value) ?></textarea>
        <details><summary><?= e(__('admin_lists.default')) ?>: <?= e($name) ?></summary><p><?= e($defaults[$key][$language]) ?></p></details>
    <?php endforeach; ?>
    <button class="btn btn-ghost"><?= e(__('actions.save')) ?></button>
    </form>
    <details><summary><?= e(__('admin_lists.reset')) ?></summary>
        <?php foreach (config('app.locales') as $language => $name): if (!isset($overrides[$key][$language])) continue; ?>
        <form class="stack" method="post" action="<?= e(url('/admin/translations')) ?>"><?= csrf_field() ?><input type="hidden" name="trans_key" value="<?= e($key) ?>"><input type="hidden" name="reset_locale" value="<?= e($language) ?>">
            <label class="consent"><input type="checkbox" name="confirm_reset" value="1" required> <?= e(__('admin_lists.reset_confirm')) ?> (<?= e($name) ?>)</label>
            <button class="btn btn-ghost" type="submit"><?= e(__('admin_lists.reset')) ?>: <?= e($name) ?></button>
        </form>
        <?php endforeach; ?>
    </details>
</section>
<?php endforeach; ?>
<?= paginator($page, '/admin/translations') ?>
