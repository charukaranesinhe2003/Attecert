<div class="table-wrap"><table><tbody>
<?php foreach ($page['rows'] as $row): ?>
<tr><td><?= e($row['name']) ?><br><?= e($row['email']) ?></td><td><?= e($row['message']) ?></td><td>
<form method="post" action="<?= e(url('/admin/contacts/' . $row['id'] . '/status')) ?>"><?= csrf_field() ?>
<select name="status"><?php foreach (['new','contacted','closed','spam'] as $state): ?><option value="<?= e($state) ?>" <?= selected($row['status'], $state) ?>><?= e($state) ?></option><?php endforeach; ?></select>
<button><?= e(__('actions.save')) ?></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/contacts') ?>
