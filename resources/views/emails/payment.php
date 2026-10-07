<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.payment_body', ['number' => $number ?? '', 'amount' => $amount ?? ''])) ?></p>
<p><a href="<?= e($link) ?>"><?= e(__('mail.view_invoice')) ?></a></p>
