<p><a class="btn btn-primary" href="<?= e(url('/admin/services/create')) ?>"><?= e(__('admin.new_service')) ?></a></p>
<div class="table-wrap"><table><tbody>
<?php foreach ($services as $service): ?>
<tr><td><?= e($service['name'] ?? $service['code']) ?></td><td><?= e($service['pricing_type']) ?> <?= $service['fixed_price'] !== null ? e(money($service['fixed_price'], $service['currency'])) : '' ?></td><td><a href="<?= e(url('/admin/services/' . $service['id'] . '/edit')) ?>"><?= e(__('actions.edit')) ?></a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
