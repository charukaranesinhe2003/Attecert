<p><a class="btn btn-primary" href="<?= e(url('/admin/articles/create')) ?>"><?= e(__('admin.new_article')) ?></a></p>
<?= \App\Core\View::render('admin/partials/list-filters', ['listPath' => '/admin/articles', 'filters' => $filters, 'page' => $page, 'statusOptions' => ['draft' => __('admin_lists.draft'), 'published' => __('admin_lists.published')]]) ?>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('admin_lists.title')) ?></th><th><?= e(__('admin_lists.status')) ?></th><th><?= e(__('admin_lists.date')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $article): ?><tr>
    <td data-label="<?= e(__('admin_lists.title')) ?>"><a href="<?= e(url('/admin/articles/' . $article['id'] . '/edit')) ?>"><?= e($article['title']) ?></a></td>
    <td data-label="<?= e(__('admin_lists.status')) ?>"><?= e(__('admin_lists.' . $article['status'])) ?></td>
    <td data-label="<?= e(__('admin_lists.date')) ?>"><?= e(format_date($article['created_at'])) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/articles') ?>
<?php endif; ?>
