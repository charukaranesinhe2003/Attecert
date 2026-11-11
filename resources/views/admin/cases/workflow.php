<?php
$auth = app(\App\Services\AuthService::class);
$caseId = (int) $case['id'];
$confirmedTotal = '0.00';
$pendingTotal = '0.00';
foreach ($payments as $payment) {
    if (in_array($payment['status'], ['completed', 'refunded'], true)) { $confirmedTotal = \App\Helpers\Money::add($confirmedTotal, $payment['amount']); }
    if ($payment['status'] === 'pending') { $pendingTotal = \App\Helpers\Money::add($pendingTotal, $payment['amount']); }
}
?>
<article class="case-workflow">
    <header class="card-block workflow-hero">
        <p class="muted">Tracking / quotation no.</p>
        <h1><?= e($case['number']) ?></h1>
        <p><?= e($case['document_title']) ?> · <strong><?= e($case['customer_name']) ?></strong> <?= status_badge($case['status_name'] ?: $case['status_code'], $case['custom_status'] ? 'neutral' : $case['badge_tone']) ?></p>
        <p class="muted"><?= e($case['customer_email']) ?><?= $case['customer_phone'] ? ' · ' . e($case['customer_phone']) : '' ?></p>
        <p class="muted">Update steps and payments independently. Documents are optional and can be added at any step.</p>
        <div class="actions">
            <a class="btn btn-ghost" href="<?= e(url('/track?number=' . rawurlencode($case['number']))) ?>" target="_blank" rel="noopener">View tracking</a>
            <?php if ($case['quote_request_id'] && $auth->can('quote_requests.manage') && (!$auth->isRestrictedStaff() || (int) app(\App\Core\Database::class)->fetchColumn('SELECT assigned_to FROM quote_requests WHERE id = ?', [$case['quote_request_id']]) === $auth->id())): ?><a class="btn btn-ghost" href="<?= e(url('/admin/quote-requests/' . $case['quote_request_id'])) ?>">Quote request</a><?php endif; ?>
            <a class="btn btn-ghost" href="<?= e(url('/admin/cases/' . $caseId . '/details')) ?>">Case details</a>
        </div>
        <?php if ($quotations !== []): ?><details id="quote"><summary>Quotation history</summary><ul><?php foreach ($quotations as $quotation): ?><li><a href="<?= e(url('/admin/quotations/' . $quotation['id'])) ?>"><?= e($quotation['number']) ?></a> · <?= e($quotation['status']) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
    </header>
    <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
    <nav class="workflow-nav" aria-label="Case workflow">
        <a href="#overview">Steps &amp; status</a>
        <?php if ($auth->can('documents.download') || $auth->can('documents.upload')): ?><a href="#documents">Documents (optional)</a><?php endif; ?>
        <?php if ($auth->can('payments.view')): ?><a href="#finance">Payments</a><?php endif; ?>
    </nav>
    <section id="overview" class="workflow-section" aria-labelledby="workflow-steps-title">
        <h2 id="workflow-steps-title">Steps &amp; status</h2>
        <div class="admin-grid">
            <section class="card-block">
                <h3>Document steps</h3>
                <p class="muted">Add your steps, then mark each one in progress or completed. Changes appear on public tracking and notify the customer.</p>
                <?php if ($steps === []): ?><p class="empty">Add the first step to begin tracking.</p><?php endif; ?>
                <ol class="workflow-step-list">
                <?php foreach ($steps as $step): ?>
                    <li class="workflow-step <?= e($step['state']) ?>">
                        <h4><?= e($step['title']) ?></h4>
                        <p><?= status_badge(['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed'][$step['state']], $step['state'] === 'completed' ? 'success' : 'neutral') ?><?php if ($step['step_date']): ?> · <?= e(format_date($step['step_date'])) ?><?php endif; ?></p>
                        <?php if ($step['public_description']): ?><p><?= nl2br(e($step['public_description'])) ?></p><?php endif; ?>
                        <?php if ($auth->can('cases.edit')): ?>
                        <details><summary>Edit step / add optional document</summary><?= \App\Core\View::render('admin/tracking/step-form', ['caseId' => $caseId, 'step' => $step, 'returnTo' => 'case']) ?></details>
                        <details><summary>Remove step</summary><form method="post" class="stack" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/steps/' . $step['id'] . '/delete')) ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="case"><label class="consent"><input type="checkbox" name="confirmed" value="1" required> Remove from current steps and keep previous updates in history</label><button class="btn btn-ghost" type="submit">Remove step</button></form></details>
                        <?php endif; ?>
                        <?php if ($auth->can('documents.download')): ?><?= \App\Core\View::render('admin/tracking/step-documents', ['step' => $step, 'documents' => $documents]) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ol>
                <?php if ($auth->can('cases.edit')): ?>
                <details<?= $steps === [] ? ' open' : '' ?>><summary>Add a custom step</summary><?= \App\Core\View::render('admin/tracking/step-form', ['caseId' => $caseId, 'step' => [], 'returnTo' => 'case', 'nextOrder' => min(1000, $steps === [] ? 1 : max(array_column($steps, 'sort_order')) + 1)]) ?></details>
                <?php endif; ?>
            </section>
            <section class="card-block">
                <h3>Current status</h3>
                <?php if ($auth->can('cases.status_update')): ?><?= \App\Core\View::render('admin/tracking/status-form', ['caseId' => $caseId, 'statusCase' => $case, 'statuses' => $statuses, 'returnTo' => 'case']) ?><?php endif; ?>
                <?php if ($auth->can('cases.edit')): ?>
                <details class="workflow-summary"><summary>Expected date &amp; public notice</summary>
                    <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking')) ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="case">
                        <label>Expected completion<input type="date" name="tracking_expected_on" value="<?= e((string) $case['tracking_expected_on']) ?>"></label>
                        <label>Public notice<textarea name="tracking_message" rows="2" maxlength="600"><?= e((string) $case['tracking_message']) ?></textarea></label>
                        <button class="btn btn-ghost" type="submit">Save tracking summary</button>
                    </form>
                </details>
                <?php endif; ?>
            </section>
        </div>
        <div class="card-block"><?= \App\Core\View::render('partials/progress-history', ['progressHistory' => $case['progress_history']]) ?></div>
    </section>
    <?php if ($auth->can('documents.download') || $auth->can('documents.upload')): ?>
    <section id="documents" class="card-block workflow-section" aria-labelledby="workflow-documents-title">
        <h2 id="workflow-documents-title">Documents (optional)</h2>
        <?php if ($auth->can('documents.upload')): ?>
        <details><summary>Upload &amp; email a document</summary>
            <form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/documents')) ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="case">
                <label>Document<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                <label>Description<input name="description" maxlength="255"></label>
                <p class="muted">PDF or image, up to <?= (int) setting('upload_max_mb', 10) ?> MB. The original is emailed and added to the customer's account.</p>
                <button class="btn btn-primary" type="submit">Upload &amp; email document</button>
            </form>
        </details>
        <?php endif; ?>
        <?php if ($documents === []): ?><p class="empty">No documents added yet.</p><?php else: ?>
        <ul class="workflow-documents"><?php foreach ($documents as $document): ?><li>
            <div><strong><?= e($document['original_name']) ?></strong><p class="muted"><?= e(ucfirst($document['category'])) ?> · <?= $document['customer_visible'] ? 'In customer account' : 'Private' ?></p></div>
            <div class="actions">
                <?php if ($auth->can('documents.download')): ?><a class="btn btn-ghost" href="<?= e(url('/admin/documents/' . $document['id'] . '/download')) ?>">Download</a><?php endif; ?>
                <?php if ($auth->can('documents.upload') && in_array($document['category'], ['customer', 'completed'], true)): ?><form method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/documents/send')) ?>"><?= csrf_field() ?><input type="hidden" name="return_to" value="case"><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="btn btn-ghost" type="submit">Email customer</button></form><?php endif; ?>
            </div>
        </li><?php endforeach; ?></ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php if ($auth->can('payments.view')): ?>
    <section id="finance" class="card-block workflow-section" aria-labelledby="workflow-payments-title">
        <h2 id="workflow-payments-title">Payments</h2>
        <div class="workflow-totals"><p>Confirmed received <strong><?= e(money($confirmedTotal, $case['currency'])) ?></strong></p><p>Awaiting confirmation <strong><?= e(money($pendingTotal, $case['currency'])) ?></strong></p></div>
        <?php if ($auth->can('payments.create')): ?>
        <details<?= $payments === [] ? ' open' : '' ?>><summary>Add a payment manually</summary>
            <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/payments')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="request_key" value="<?= e((string) old('request_key', bin2hex(random_bytes(16)))) ?>">
                <div class="form-grid">
                    <label>Amount (<?= e($case['currency']) ?>)<input type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" required value="<?= e((string) old('amount', '')) ?>"></label>
                    <label>Payment date<input type="date" name="paid_at" required max="<?= e(date('Y-m-d')) ?>" value="<?= e((string) old('paid_at', date('Y-m-d'))) ?>"></label>
                    <label>Payment method<select name="method"><?php foreach (['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'card' => 'Card', 'online' => 'Online', 'other' => 'Other'] as $code => $label): ?><option value="<?= e($code) ?>"<?= selected(old('method', 'bank_transfer'), $code) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label>Bank / transaction reference<input name="reference" maxlength="80" value="<?= e((string) old('reference', '')) ?>"></label>
                </div>
                <?php if ($invoices !== []): ?><label>Apply to invoice (optional)<select name="invoice_id"><option value="">Case payment — no invoice</option><?php foreach ($invoices as $invoice): ?><?php if (!in_array($invoice['status'], ['cancelled', 'paid', 'refunded'], true)): ?><option value="<?= (int) $invoice['id'] ?>"<?= selected(old('invoice_id', ''), $invoice['id']) ?>><?= e($invoice['number'] . ' · Due ' . money($invoice['amount_due'], $invoice['currency'])) ?></option><?php endif; ?><?php endforeach; ?></select></label><?php endif; ?>
                <label>Internal note (optional)<textarea name="notes" rows="2" maxlength="1000"><?= e((string) old('notes', '')) ?></textarea></label>
                <p class="muted">The payment stays pending until you confirm that the funds have arrived. Confirmation sends the customer an SMS and a formatted email receipt summary.</p>
                <button class="btn btn-primary" type="submit">Add pending payment</button>
            </form>
        </details>
        <?php endif; ?>
        <?php foreach ($payments as $payment): ?>
        <article class="workflow-payment">
            <div><h3><?= e(money($payment['amount'], $payment['currency'])) ?> <span class="muted"><?= e(ucfirst($payment['status'])) ?></span></h3><p><?= e($payment['number']) ?> · <?= e(ucwords(str_replace('_', ' ', $payment['method']))) ?> · <?= e(format_date($payment['paid_at'])) ?></p><?php if ($payment['reference']): ?><p>Reference: <?= e($payment['reference']) ?></p><?php endif; ?><?php if ($payment['confirmed_at']): ?><p class="muted">Confirmed <?= e(format_datetime($payment['confirmed_at'])) ?></p><?php endif; ?></div>
            <?php if ($payment['status'] === 'pending' && $auth->can('payments.create')): ?>
            <form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/cases/' . $caseId . '/payments/' . $payment['id'] . '/confirm')) ?>"><?= csrf_field() ?>
                <?php if ($auth->can('documents.upload')): ?><label>Payment confirmation PDF (optional)<input type="file" name="receipt_document" accept=".pdf,application/pdf"></label><?php endif; ?>
                <p class="muted">Confirm with or without a document. An uploaded PDF is saved in the customer's account and attached to the confirmation email.</p>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> I have checked and received these funds</label><button class="btn btn-primary" type="submit">Confirm received &amp; notify</button>
            </form>
            <details><summary>Cancel this pending entry</summary><form method="post" class="stack" action="<?= e(url('/admin/cases/' . $caseId . '/payments/' . $payment['id'] . '/cancel')) ?>"><?= csrf_field() ?><label class="consent"><input type="checkbox" name="confirmed" value="1" required> Cancel this unconfirmed payment</label><button class="btn btn-ghost" type="submit">Cancel pending payment</button></form></details>
            <?php elseif (in_array($payment['status'], ['completed', 'refunded'], true)): ?>
                <a class="btn btn-ghost" href="<?= e(url('/admin/payments/' . $payment['id'] . '/receipt')) ?>"><?= $payment['receipt_document_id'] !== null ? 'Download confirmation PDF' : 'Download receipt' ?></a>
                <?php if ($payment['status'] === 'completed' && $payment['receipt_document_id'] === null && $auth->can('payments.create') && $auth->can('documents.upload')): ?>
                <details><summary>Add a confirmation PDF (optional)</summary><form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/cases/' . $caseId . '/payments/' . $payment['id'] . '/confirm')) ?>"><?= csrf_field() ?><input type="hidden" name="confirmed" value="1"><label>Payment confirmation PDF<input type="file" name="receipt_document" accept=".pdf,application/pdf"></label><p class="muted">The payment stays confirmed. The PDF is saved and emailed to the customer.</p><button class="btn btn-ghost" type="submit">Add PDF &amp; notify customer</button></form></details>
                <?php endif; ?>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
        <?php if ($invoices !== [] && $auth->can('invoices.create')): ?><details><summary>Existing invoices</summary><ul><?php foreach ($invoices as $invoice): ?><li><a href="<?= e(url('/admin/invoices/' . $invoice['id'])) ?>"><?= e($invoice['number']) ?></a> · <?= e($invoice['status']) ?> · Due <?= e(money($invoice['amount_due'], $invoice['currency'])) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
    </section>
    <?php endif; ?>
</article>
