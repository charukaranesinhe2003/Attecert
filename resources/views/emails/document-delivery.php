<p><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p>Your document for case <strong><?= e($number) ?></strong> is attached to this email.</p>
<p><strong><?= e($filename) ?></strong></p>
<?php if ($description !== ''): ?><p><?= nl2br(e($description)) ?></p><?php endif; ?>
<p>The document is also available in your account: <a href="<?= e($link) ?>">My documents</a>.</p>
