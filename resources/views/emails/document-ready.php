<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.document_body', ['number' => $number ?? ''])) ?></p>
<p><a href="<?= e($link) ?>"><?= e(__('mail.download_document')) ?></a></p>
