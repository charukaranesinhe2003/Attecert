<form class="filters" method="get"><input name="q" value="<?= e($q) ?>" placeholder="key"><button class="btn btn-primary"><?= e(__('actions.search')) ?></button></form>
<?php foreach ($keys as $key => $english): ?>
<form class="card-block stack" method="post" action="<?= e(url('/admin/translations')) ?>"><?= csrf_field() ?>
<input type="hidden" name="trans_key" value="<?= e($key) ?>">
<strong><?= e($key) ?></strong>
<label>en<textarea name="value_en"><?= e($overrides[$key]['en'] ?? $english) ?></textarea></label>
<label>si<textarea name="value_si"><?= e($overrides[$key]['si'] ?? '') ?></textarea></label>
<label>ta<textarea name="value_ta"><?= e($overrides[$key]['ta'] ?? '') ?></textarea></label>
<button class="btn btn-ghost"><?= e(__('actions.save')) ?></button>
</form>
<?php endforeach; ?>
