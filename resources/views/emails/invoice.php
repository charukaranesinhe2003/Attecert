<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.invoice_body', ['number' => $number ?? '', 'total' => $total ?? '', 'due' => $due ?? ''])) ?></p>
<p><a href="<?= e($link) ?>"><?= e(__('mail.view_invoice')) ?></a></p>
