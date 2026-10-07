<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.verify_body')) ?></p>
<p><a href="<?= e($link) ?>"><?= e(__('mail.verify_action')) ?></a></p>
