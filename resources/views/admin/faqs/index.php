<p><a class="btn btn-primary" href="<?= e(url('/admin/faqs/create')) ?>"><?= e(__('actions.add')) ?></a></p>
<?= \App\Core\View::render('admin/partials/list-filters', ['listPath' => '/admin/faqs', 'filters' => $filters, 'page' => $page, 'statusOptions' => ['active' => __('admin_lists.active'), 'inactive' => __('admin_lists.inactive')]]) ?>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.question')) ?></th><th><?= e(__('admin_lists.category')) ?></th><th><?= e(__('admin_lists.status')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $faq): ?><tr>
    <td data-label="<?= e(__('admin_lists.question')) ?>"><a href="<?= e(url('/admin/faqs/' . $faq['id'] . '/edit')) ?>"><?= e($faq['question']) ?></a></td>
    <td data-label="<?= e(__('admin_lists.category')) ?>"><?= e((string) ($faq['category'] ?? '—')) ?></td>
    <td data-label="<?= e(__('admin_lists.status')) ?>"><?= e(__( (int) $faq['is_active'] === 1 ? 'admin_lists.active' : 'admin_lists.inactive')) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/faqs') ?>
<?php endif; ?>
