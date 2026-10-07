<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.quote_request_body')) ?></p>
<?php if (!empty($number)): ?><p><strong><?= e($number) ?></strong></p><?php endif; ?>
