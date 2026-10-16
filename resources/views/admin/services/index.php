<p><a class="btn btn-primary" href="<?= e(url('/admin/services/create')) ?>"><?= e(__('admin.new_service')) ?></a></p>
<?= \App\Core\View::render('admin/partials/list-filters', ['listPath' => '/admin/services', 'filters' => $filters, 'page' => $page, 'statusOptions' => ['active' => __('admin_lists.active'), 'inactive' => __('admin_lists.inactive')]]) ?>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.name')) ?></th><th><?= e(__('admin_lists.code')) ?></th><th><?= e(__('admin_lists.pricing')) ?></th><th><?= e(__('admin_lists.status')) ?></th><th><?= e(__('admin_lists.actions')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $service): ?>
<tr><td data-label="<?= e(__('admin_lists.name')) ?>"><?= e($service['name']) ?></td><td data-label="<?= e(__('admin_lists.code')) ?>"><?= e($service['code']) ?></td><td data-label="<?= e(__('admin_lists.pricing')) ?>"><?= e(__('admin_lists.' . $service['pricing_type'])) ?> <?= $service['fixed_price'] !== null ? e(money($service['fixed_price'], $service['currency'])) : '' ?></td><td data-label="<?= e(__('admin_lists.status')) ?>"><?= e(__( (int) $service['is_active'] === 1 ? 'admin_lists.active' : 'admin_lists.inactive')) ?></td><td data-label="<?= e(__('admin_lists.actions')) ?>"><a href="<?= e(url('/admin/services/' . $service['id'] . '/edit')) ?>"><?= e(__('actions.edit')) ?><span class="sr-only"> <?= e($service['name']) ?></span></a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/services') ?>
<?php endif; ?>
