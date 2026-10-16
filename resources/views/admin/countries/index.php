<p><a class="btn btn-primary" href="<?= e(url('/admin/countries/create')) ?>"><?= e(__('admin.new_country')) ?></a></p>
<?= \App\Core\View::render('admin/partials/list-filters', ['listPath' => '/admin/countries', 'filters' => $filters, 'page' => $page, 'statusOptions' => ['active' => __('admin_lists.active'), 'inactive' => __('admin_lists.inactive')]]) ?>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.name')) ?></th><th><?= e(__('admin_lists.code')) ?></th><th><?= e(__('admin_lists.status')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $country): ?><tr>
    <td data-label="<?= e(__('admin_lists.name')) ?>"><a href="<?= e(url('/admin/countries/' . $country['id'] . '/edit')) ?>"><?= e($country['name']) ?></a></td>
    <td data-label="<?= e(__('admin_lists.code')) ?>"><?= e($country['code']) ?></td>
    <td data-label="<?= e(__('admin_lists.status')) ?>"><?= e(__( (int) $country['is_active'] === 1 ? 'admin_lists.active' : 'admin_lists.inactive')) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/countries') ?>
<?php endif; ?>
