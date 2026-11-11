<p><?= e(__('mail.hello', ['name' => $name ?? ''])) ?></p>
<p><?= e(__('mail.case_body', ['number' => $number ?? ''])) ?></p>
<p><a href="<?= e($link) ?>"><?= !empty($contact_only) ? e(__('nav.track_document')) : e(__('mail.open_account')) ?></a></p>
