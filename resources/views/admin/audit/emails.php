<?php if (!$smtp): ?><p class="form-error"><?= e(__('admin.smtp_missing')) ?></p><?php endif; ?>
<div class="table-wrap"><table><tbody><?php foreach ($page['rows'] as $row): ?><tr><td><?= e($row['recipient']) ?></td><td><?= e($row['subject']) ?></td><td><?= e($row['status']) ?></td><td><?= e((string) $row['error_message']) ?></td><td><form method="post" action="<?= e(url('/admin/emails/' . $row['id'] . '/resend')) ?>"><?= csrf_field() ?><button><?= e(__('actions.resend')) ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?= paginator($page, '/admin/emails') ?>
