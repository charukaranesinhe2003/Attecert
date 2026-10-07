<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.quotation_body', ['number' => $number ?? '', 'total' => $total ?? ''])) ?></p>
<p><a href="<?= e($link) ?>"><?= e(__('mail.view_quotation')) ?></a></p>
