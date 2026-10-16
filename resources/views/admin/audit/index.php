<form class="filters" method="get"><label><?= e(__('admin_lists.search')) ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="190"></label><button class="btn btn-primary"><?= e(__('actions.search')) ?></button><a class="btn btn-ghost" href="<?= e(url('/admin/audit')) ?>"><?= e(__('admin_lists.clear')) ?></a></form>
<p><?= e(__('admin_lists.results', ['count' => (string) $page['total']])) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('admin_lists.empty')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><?php foreach (['date','actor','event','record'] as $key): ?><th><?= e(__('admin_lists.' . $key)) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?><tr>
<td data-label="<?= e(__('admin_lists.date')) ?>"><?= e(format_datetime($row['created_at'])) ?></td>
<td data-label="<?= e(__('admin_lists.actor')) ?>"><?= e((string) ($row['user_name'] ?: '—')) ?></td>
<td data-label="<?= e(__('admin_lists.event')) ?>"><?= e($row['action']) ?></td>
<td data-label="<?= e(__('admin_lists.record')) ?>"><?= e((string) $row['entity']) ?> <?= e((string) $row['entity_id']) ?></td>
</tr><?php endforeach; ?></tbody></table></div><?= paginator($page, '/admin/audit') ?>
<?php endif; ?>
