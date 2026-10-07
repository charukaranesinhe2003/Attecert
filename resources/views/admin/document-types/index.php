<form class="card-block stack" method="post" action="<?= e(url('/admin/document-types')) ?>"><?= csrf_field() ?>
<label>Code<input name="code" required></label>
<label>English<input name="name_en" required></label>
<label>Sinhala<input name="name_si"></label>
<label>Tamil<input name="name_ta"></label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
<div class="table-wrap"><table><tbody><?php foreach ($types as $type): ?><tr><td><?= e($type['code']) ?></td><td><?= e((string) $type['name_en']) ?></td></tr><?php endforeach; ?></tbody></table></div>
