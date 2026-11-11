<article class="card-block">
<?php if ($canUploadQuotation): ?><p><a class="btn btn-primary" href="#upload-quotation">Upload &amp; email quotation</a></p><?php endif; ?>
<p><?= e($quote['name']) ?> · <?= e($quote['email']) ?> · <?= e((string) $quote['phone']) ?></p>
<p><?= nl2br(e((string) $quote['message'])) ?></p>
<ul><?php foreach ($services as $service): ?><li><?= e($service['name']) ?></li><?php endforeach; ?></ul>
<?php if ($quote['converted_case_id'] === null): ?>
<form method="post" action="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/convert')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('admin.convert')) ?></button></form>
<?php elseif ($linkedCase !== null): ?>
<p>Converted to <a href="<?= e(url('/admin/cases/' . $linkedCase['id'])) ?>"><?= e($linkedCase['number']) ?></a>.</p>
<?php else: ?><p>This request has a case. Case access is assigned separately in Operations.</p><?php endif; ?>
<form method="post" action="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><label>Status<select name="status"><?php foreach (['new', 'contacted', 'converted', 'closed', 'spam'] as $state): ?><option value="<?= e($state) ?>"<?= selected($quote['status'], $state) ?>><?= e($state) ?></option><?php endforeach; ?></select></label><button class="btn btn-ghost"><?= e(__('actions.save')) ?></button></form>
<?php if ($files !== []): ?><h2>Submitted documents</h2><ul><?php foreach ($files as $file): ?><li><a href="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/documents/' . $file['id'] . '/download')) ?>"><?= e($file['original_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
</article>
<?php if ($quotations !== []): ?>
<section class="card-block" id="uploaded-quotations">
    <h2>Uploaded quotations</h2>
    <?php foreach ($quotations as $quotation): ?>
    <article class="process-card">
        <h3><?= e($quotation['number']) ?> · v<?= (int) $quotation['version'] ?></h3>
        <p><?= e($quotation['original_name'] ?: 'Quotation PDF') ?> · <?= e(money($quotation['total'], $quotation['currency'])) ?> · <?= e($quotation['status']) ?></p>
        <div class="actions">
            <a class="btn btn-ghost" href="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/quotations/' . $quotation['id'] . '/pdf')) ?>">Download quotation</a>
            <?php if ($canUploadQuotation && in_array($quotation['status'], ['draft', 'sent', 'viewed'], true)): ?>
            <form method="post" action="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/quotations/' . $quotation['id'] . '/send')) ?>">
                <?= csrf_field() ?><button class="btn btn-primary" type="submit">Email quotation</button>
            </form>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php if ($canUploadQuotation): ?>
    <?= \App\Core\View::render('admin/quotations/upload-form', ['quotationRequest' => $quote, 'quotationCurrency' => $quotationCurrency]) ?>
<?php endif; ?>
