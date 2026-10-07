<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.status_body', ['number' => $number ?? '', 'status' => $status ?? ''])) ?></p>
<?php if (!empty($notes)): ?><p><?= e($notes) ?></p><?php endif; ?>
<p><a href="<?= e($link) ?>"><?= e(__('mail.view_case')) ?></a></p>
