<section class="card-block" id="upload-quotation">
    <h2>Upload &amp; email quotation</h2>
    <p>Upload the quotation PDF you prepared. The original document will be attached to the customer's email and available in their account.</p>
    <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
    <form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url(!empty($quotationRequest) ? '/admin/quote-requests/' . $quotationRequest['id'] . '/quotation' : '/admin/quotations/upload')) ?>">
        <?= csrf_field() ?>
        <?php if (!empty($quotationRequest)): ?>
            <p><strong>Request #<?= (int) $quotationRequest['id'] ?></strong> · <?= e($quotationRequest['name']) ?> · <?= e($quotationRequest['email']) ?></p>
            <p class="muted">This quotation is linked to this request's case.<?= $quotationRequest['converted_case_id'] === null ? ' A case will be created when you upload.' : '' ?> Currency: <?= e((string) $quotationCurrency) ?>.</p>
        <?php elseif (!empty($quotationCase)): ?>
            <input type="hidden" name="case_id" value="<?= (int) $quotationCase['id'] ?>">
            <p><?= e($quotationCase['number']) ?> · <?= e($quotationCase['customer_name']) ?> · <?= e($quotationCase['currency']) ?></p>
        <?php else: ?>
            <label>Case<select name="case_id" required><option value="">Choose a case</option><?php foreach ($quotationCases as $option): ?><option value="<?= (int) $option['id'] ?>"<?= selected(old('case_id'), $option['id']) ?>><?= e($option['number'] . ' · ' . $option['customer_name'] . ' · ' . $option['currency']) ?></option><?php endforeach; ?></select></label>
        <?php endif; ?>
        <label>Quotation PDF<input type="file" name="quotation_document" accept="application/pdf,.pdf" required></label>
        <p class="muted">PDF only, up to <?= (int) setting('upload_max_mb', 10) ?> MB. Enter the total exactly as it appears on the document, in the case's currency.</p>
        <label>Quoted total<input type="number" name="total" min="0.01" step="0.01" value="<?= e((string) old('total')) ?>" required></label>
        <label>Valid for (days)<input type="number" name="validity_days" min="1" max="3650" value="<?= e((string) old('validity_days', setting('quote_validity_days', 14))) ?>" required></label>
        <label>Notes<textarea name="notes" rows="2" maxlength="4000"><?= e((string) old('notes')) ?></textarea></label>
        <button class="btn btn-primary" type="submit">Upload &amp; email quotation</button>
    </form>
</section>
