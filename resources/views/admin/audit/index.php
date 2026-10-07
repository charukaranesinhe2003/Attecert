<form class="filters" method="get"><input name="q" value="<?= e($q) ?>"><button class="btn btn-primary"><?= e(__('actions.search')) ?></button></form>
<div class="table-wrap"><table><tbody><?php foreach ($page['rows'] as $row): ?><tr><td><?= e(format_datetime($row['created_at'])) ?></td><td><?= e((string) $row['user_name']) ?></td><td><?= e($row['action']) ?></td><td><?= e((string) $row['entity']) ?> <?= e((string) $row['entity_id']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= paginator($page, '/admin/audit') ?>
