<p><a class="btn btn-primary" href="<?= e(url('/admin/countries/create')) ?>"><?= e(__('admin.new_country')) ?></a></p>
<?php foreach ($countries as $country): ?>
<p><a href="<?= e(url('/admin/countries/' . $country['id'] . '/edit')) ?>"><?= e($country['name']) ?></a></p>
<?php endforeach; ?>
