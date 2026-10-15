<?php
use App\Helpers\NextStep;

$auth = app(\App\Services\AuthService::class);
$canQuote = $auth->can('quotations.create');
$canFinance = $auth->can('invoices.create') || $auth->can('payments.view');
$canDocs = $auth->can('documents.download') || $auth->can('documents.upload');
$next = $nextStep ?? NextStep::forCase($case, $quotations, $invoices, $mofa, $embassy);
$statusCode = (string) $case['status_code'];
$progress = [
    ['key' => 'received', 'label' => __('admin.step_received'), 'done' => true],
    ['key' => 'documents', 'label' => __('admin.step_documents'), 'done' => !in_array($statusCode, ['new_request', 'under_review', 'awaiting_documents'], true)],
    ['key' => 'quotation', 'label' => __('admin.step_quotation'), 'done' => $quotations !== [] && !in_array((string) ($quotations[0]['status'] ?? ''), ['draft', 'sent', 'viewed', 'rejected', 'expired'], true)],
    ['key' => 'payment', 'label' => __('admin.step_payment'), 'done' => in_array((string) (($invoices[0]['status'] ?? '')), ['paid'], true) || in_array($statusCode, ['payment_received', 'verification', 'mofa_processing', 'submitted_to_mofa', 'mofa_completed', 'embassy_processing', 'submitted_to_embassy', 'embassy_completed', 'ready_for_collection', 'ready_for_delivery', 'completed'], true)],
    ['key' => 'mofa', 'label' => __('admin.step_mofa'), 'done' => in_array(($mofa['status'] ?? ''), ['completed'], true) || in_array($statusCode, ['mofa_completed', 'embassy_processing', 'submitted_to_embassy', 'embassy_completed', 'ready_for_collection', 'ready_for_delivery', 'completed'], true)],
    ['key' => 'embassy', 'label' => __('admin.step_embassy'), 'done' => in_array(($embassy['status'] ?? ''), ['completed'], true) || in_array($statusCode, ['embassy_completed', 'ready_for_collection', 'ready_for_delivery', 'completed'], true)],
    ['key' => 'completed', 'label' => __('admin.step_completed'), 'done' => $statusCode === 'completed'],
];
$currentFound = false;
foreach ($progress as $i => $step) {
    if (!$step['done'] && !$currentFound) {
        $progress[$i]['current'] = true;
        $currentFound = true;
    } else {
        $progress[$i]['current'] = false;
    }
}
if (!$currentFound && $statusCode === 'completed') {
    $progress[count($progress) - 1]['current'] = true;
}
$docsByCategory = ['customer' => [], 'internal' => [], 'completed' => []];
foreach ($documents as $doc) {
    $cat = (string) ($doc['category'] ?? 'internal');
    if (!isset($docsByCategory[$cat])) {
        $cat = 'internal';
    }
    $docsByCategory[$cat][] = $doc;
}
$latestQuote = $quotations[0] ?? null;
$latestInvoice = $invoices[0] ?? null;
$restoringQuote = (string) old('quotation_form', '') === (string) $case['id'];
$quoteValue = static fn (string $field, mixed $default = ''): mixed => $restoringQuote ? old($field, $default) : $default;
$builderLines = [];
$customLines = [];
foreach ($latestQuote ? ($quotationItems ?? []) : $case['services'] as $line) {
    if (!empty($line['service_id'])) {
        $builderLines[(int) $line['service_id']] = [
            'selected' => 1,
            'qty' => $line['quantity'],
            'price' => $line['unit_price'],
            'description' => $line['description'] ?? '',
        ];
    } else {
        $customLines[] = $line;
    }
}
if ($restoringQuote) {
    $builderLines = (array) old('lines', []);
    $customLines = (array) old('custom_lines', []);
}
$validityDays = (int) setting('quote_validity_days', 14);
if ($latestQuote && $latestQuote['valid_until']) {
    $validityDays = max(1, (int) round((strtotime($latestQuote['valid_until']) - strtotime(substr((string) ($latestQuote['issued_at'] ?: $latestQuote['created_at']), 0, 10))) / 86400));
}
$outstanding = 0.0;
foreach ($invoices as $invoice) {
    if (!in_array($invoice['status'], ['cancelled', 'paid'], true)) {
        $outstanding += (float) $invoice['amount_due'];
    }
}
?>
<article class="case-workspace">
    <header class="case-hero">
        <div class="case-hero-top">
            <div>
                <h1><?= e($case['number']) ?></h1>
                <div class="case-hero-meta">
                    <span><?= e((string) ($case['country_name'] ?? '—')) ?> · <?= e($case['document_title']) ?></span>
                    <span><?= e(__('admin.customers')) ?>: <strong><?= e($case['customer_name']) ?></strong></span>
                    <span><?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?></span>
                </div>
            </div>
            <div class="actions">
                <?php if ($auth->can('cases.status_update')): ?><a class="btn btn-primary" href="#overview" data-jump-tab="overview"><?= e(__('admin.update_status')) ?></a><?php endif; ?>
                <?php if ($auth->can('documents.upload')): ?><a class="btn btn-ghost" href="#documents" data-jump-tab="documents"><?= e(__('admin.upload_document')) ?></a><?php endif; ?>
                <?php if ($auth->can('customers.view')): ?><a class="btn btn-ghost" href="<?= e(url('/admin/customers/' . $case['customer_id'])) ?>"><?= e(__('admin.customers')) ?></a><?php endif; ?>
            </div>
        </div>
        <div class="case-next">
            <div>
                <strong><?= e(__('admin.next_action')) ?></strong>
                <p><?= e($next['label']) ?></p>
            </div>
            <a class="btn btn-primary" href="#<?= e($next['tab']) ?>" data-jump-tab="<?= e($next['tab']) ?>"><?= e(__('admin.open_next')) ?></a>
        </div>
    </header>

    <nav class="case-tabs" data-case-tabs aria-label="Case sections">
        <button type="button" data-tab="overview" class="is-active"><?= e(__('admin.tab_overview')) ?></button>
        <?php if ($canQuote || $auth->can('cases.edit')): ?><button type="button" data-tab="quote"><?= e(__('admin.tab_quote')) ?></button><?php endif; ?>
        <?php if ($canDocs): ?><button type="button" data-tab="documents"><?= e(__('admin.tab_documents')) ?></button><?php endif; ?>
        <button type="button" data-tab="processing"><?= e(__('admin.tab_processing')) ?></button>
        <?php if ($canFinance): ?><button type="button" data-tab="finance"><?= e(__('admin.tab_finance')) ?></button><?php endif; ?>
        <button type="button" data-tab="activity"><?= e(__('admin.tab_activity')) ?></button>
    </nav>

    <section class="case-panel" data-panel="overview">
        <div class="case-summary-grid">
            <section class="card-block">
                <h2><?= e(__('admin.customers')) ?></h2>
                <dl class="kv">
                    <div><dt><?= e(__('fields.name')) ?></dt><dd><?= e($case['customer_name']) ?></dd></div>
                    <div><dt><?= e(__('fields.email')) ?></dt><dd><?= e($case['customer_email']) ?></dd></div>
                    <div><dt><?= e(__('fields.phone')) ?></dt><dd><?= e((string) ($case['customer_phone'] ?: '—')) ?></dd></div>
                    <div><dt><?= e(__('fields.whatsapp')) ?></dt><dd><?= e((string) ($case['customer_whatsapp'] ?: '—')) ?></dd></div>
                </dl>
            </section>
            <section class="card-block">
                <h2><?= e(__('admin.cases')) ?></h2>
                <dl class="kv">
                    <div><dt><?= e(__('fields.country_id')) ?></dt><dd><?= e((string) ($case['country_name'] ?: '—')) ?></dd></div>
                    <div><dt><?= e(__('fields.document_type_id')) ?></dt><dd><?= e((string) ($case['document_type_name'] ?: $case['document_title'])) ?></dd></div>
                    <div><dt><?= e(__('fields.date')) ?></dt><dd><?= e(format_datetime($case['created_at'])) ?></dd></div>
                    <div><dt><?= e(__('fields.assigned_to')) ?></dt><dd><?= e((string) ($case['assignee_name'] ?: __('admin.unassigned'))) ?></dd></div>
                    <div><dt><?= e(__('fields.priority')) ?></dt><dd><?= e((string) $case['priority']) ?></dd></div>
                </dl>
            </section>
            <section class="card-block">
                <h2><?= e(__('admin.current_progress')) ?></h2>
                <ol class="progress-track">
                    <?php foreach ($progress as $step): ?>
                        <li class="<?= $step['done'] ? 'is-done' : '' ?> <?= !empty($step['current']) ? 'is-current' : '' ?>">
                            <i></i>
                            <span><?= e($step['label']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>

        <div class="admin-grid">
            <section class="card-block">
                <h2><?= e(__('admin.details')) ?></h2>
                <?php if ($auth->can('cases.edit')): ?>
                <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'])) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <div class="form-grid">
                        <label><?= e(__('fields.document_title')) ?><input name="document_title" value="<?= e($case['document_title']) ?>" required></label>
                        <label><?= e(__('fields.country_id')) ?><select name="country_id"><option value=""></option><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>" <?= selected($case['country_id'], $country['id']) ?>><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
                        <label><?= e(__('fields.document_type_id')) ?><select name="document_type_id"><option value=""></option><?php foreach ($documentTypes as $type): ?><option value="<?= e((string) $type['id']) ?>" <?= selected($case['document_type_id'], $type['id']) ?>><?= e($type['name']) ?></option><?php endforeach; ?></select></label>
                        <label><?= e(__('fields.assigned_to')) ?><select name="assigned_to"><option value=""></option><?php foreach ($staff as $person): ?><option value="<?= e((string) $person['id']) ?>" <?= selected($case['assigned_to'], $person['id']) ?>><?= e($person['name']) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label><?= e(__('fields.summary')) ?><textarea name="summary" rows="3"><?= e((string) $case['summary']) ?></textarea></label>
                    <label><?= e(__('fields.estimated_next')) ?><input name="estimated_next" value="<?= e((string) $case['estimated_next']) ?>"></label>
                    <label><?= e(__('fields.internal_notes')) ?><textarea name="internal_notes" rows="3"><?= e((string) $case['internal_notes']) ?></textarea></label>
                    <label><?= e(__('fields.customer_notes')) ?><textarea name="customer_notes" rows="3"><?= e((string) $case['customer_notes']) ?></textarea></label>
                    <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
                </form>
                <?php else: ?><p><?= e((string) $case['summary']) ?></p><p><?= e((string) $case['estimated_next']) ?></p><p><?= e((string) $case['customer_notes']) ?></p><?php endif; ?>
            </section>
            <section class="card-block">
                <h2><?= e(__('admin.update_status')) ?></h2>
                <?php if ($auth->can('cases.status_update')): ?>
                <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/status')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <label><?= e(__('fields.status')) ?><select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status['code']) ?>" <?= selected($case['status_code'], $status['code']) ?>><?= e($status['name']) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(__('fields.notes')) ?><textarea name="notes" rows="3"></textarea></label>
                    <label class="consent"><input type="checkbox" name="customer_visible" value="1" checked> <?= e(__('admin.visible')) ?></label>
                    <button class="btn btn-primary" type="submit"><?= e(__('admin.update_status')) ?></button>
                </form>
                <?php else: ?><p class="muted">Your role can review this case. Status changes require an authorized colleague.</p><?php endif; ?>
                <?php if (trim((string) $case['internal_notes']) !== ''): ?>
                    <div class="process-card" style="margin-top:1rem">
                        <h3><?= e(__('admin.important_notes')) ?></h3>
                        <p><?= e((string) $case['internal_notes']) ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <?php if ($canQuote || $auth->can('cases.edit')): ?>
    <section class="case-panel" data-panel="quote" hidden>
        <?php if ($auth->can('cases.edit')): ?>
        <section class="card-block">
            <div class="card-head"><h2><?= e(__('admin.services')) ?></h2></div>
            <form method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/services')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                <?php foreach ($services as $service):
                    $selected = null;
                    foreach ($case['services'] as $line) {
                        if ((int) $line['service_id'] === (int) $service['id']) {
                            $selected = $line;
                        }
                    }
                ?>
                    <label class="consent">
                        <input type="checkbox" name="service_id[]" value="<?= e((string) $service['id']) ?>" <?= $selected ? 'checked' : '' ?>>
                        <?= e($service['name']) ?>
                        <?php if ($service['pricing_type'] === 'fixed'): ?> · <?= e(money($service['fixed_price'], $service['currency'])) ?><?php else: ?> · <?= e(__('fields.custom')) ?><?php endif; ?>
                    </label>
                    <input type="hidden" name="quantity[<?= e((string) $service['id']) ?>]" value="<?= e((string) ($selected['quantity'] ?? 1)) ?>">
                    <?php if ($canQuote): ?><input type="hidden" name="unit_price[<?= e((string) $service['id']) ?>]" value="<?= e((string) ($selected['unit_price'] ?? $service['fixed_price'] ?? '')) ?>"><?php endif; ?>
                <?php endforeach; ?>
                <div class="actions" style="margin-top:.75rem"><button class="btn btn-ghost" type="submit"><?= e(__('actions.save')) ?></button></div>
            </form>
        </section>
        <?php endif; ?>
        <?php if ($canQuote): ?>
        <section class="card-block" data-quote-builder>
            <div class="card-head"><h2><?= e(__('admin.quotation')) ?></h2></div>
            <?php if ($latestQuote): ?>
                <div class="finance-row" style="margin-bottom:1rem">
                    <div>
                        <strong><?= e($latestQuote['number']) ?></strong>
                        <div><?= e($latestQuote['status']) ?><?php if (!empty($latestQuote['version'])): ?> · v<?= (int) $latestQuote['version'] ?><?php endif; ?></div>
                    </div>
                    <div>
                        <strong><?= e(money($latestQuote['total'], $latestQuote['currency'])) ?></strong>
                        <div class="actions" style="margin-top:.35rem">
                            <a class="btn btn-ghost" href="<?= e(url('/admin/quotations/' . $latestQuote['id'])) ?>"><?= e(__('actions.view')) ?></a>
                            <a class="btn btn-ghost" href="<?= e(url('/admin/quotations/' . $latestQuote['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a>
                            <?php if ($auth->can('quotations.send') && in_array($latestQuote['status'], ['draft', 'sent', 'viewed'], true)): ?>
                                <form method="post" action="<?= e(url('/admin/quotations/' . $latestQuote['id'] . '/send')) ?>" data-busy="<?= e(__('admin.sending')) ?>"><?= csrf_field() ?><input type="hidden" name="return_case" value="<?= e((string) $case['id']) ?>"><button class="btn btn-primary" type="submit"><?= e(__('actions.email')) ?></button></form>
                            <?php endif; ?>
                            <?php if ($auth->can('invoices.create') && in_array($latestQuote['status'], ['accepted'], true)): ?>
                                <form method="post" action="<?= e(url('/admin/quotations/' . $latestQuote['id'] . '/invoice')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('admin.create_invoice')) ?></button></form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/quotation')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                <input type="hidden" name="quotation_form" value="<?= e((string) $case['id']) ?>">
                <?php if ($latestQuote): ?>
                    <input type="hidden" name="quotation_id" value="<?= e((string) $latestQuote['id']) ?>">
                <?php endif; ?>
                <div class="table-wrap"><table class="quote-table">
                    <thead><tr><th><?= e(__('admin.services')) ?></th><th><?= e(__('fields.quantity')) ?></th><th><?= e(__('fields.price')) ?></th><th><?= e(__('fields.total')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($services as $service):
                        $line = $builderLines[(int) $service['id']] ?? [];
                        $selected = !empty($line['selected']);
                        $price = $line['price'] ?? ($service['pricing_type'] === 'fixed' ? $service['fixed_price'] : '');
                    ?>
                        <tr data-line>
                            <td>
                                <label class="consent">
                                    <input data-selected type="checkbox" name="lines[<?= e((string) $service['id']) ?>][selected]" value="1" <?= $selected ? 'checked' : '' ?>>
                                    <?= e($service['name']) ?>
                                </label>
                                <input type="hidden" name="lines[<?= e((string) $service['id']) ?>][description]" value="<?= e((string) (($line['description'] ?? '') ?: $service['name'])) ?>">
                            </td>
                            <td><input data-qty name="lines[<?= e((string) $service['id']) ?>][qty]" value="<?= e((string) ($line['qty'] ?? 1)) ?>"></td>
                            <td><input data-price name="lines[<?= e((string) $service['id']) ?>][price]" value="<?= e((string) $price) ?>" <?= $service['pricing_type'] === 'fixed' ? 'readonly' : '' ?>></td>
                            <td><strong data-line-total>0.00</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($customLines as $index => $line): ?>
                        <tr data-line>
                            <td><input name="custom_lines[<?= (int) $index ?>][description]" aria-label="<?= e(__('fields.description')) ?>" value="<?= e((string) ($line['description'] ?? '')) ?>"></td>
                            <td><input data-qty name="custom_lines[<?= (int) $index ?>][quantity]" aria-label="<?= e(__('fields.quantity')) ?>" value="<?= e((string) ($line['quantity'] ?? 1)) ?>"></td>
                            <td><input data-price name="custom_lines[<?= (int) $index ?>][unit_price]" aria-label="<?= e(__('fields.price')) ?>" value="<?= e((string) ($line['unit_price'] ?? 0)) ?>"></td>
                            <td><strong data-line-total>0.00</strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-line>
                        <td><input name="extra_description" placeholder="<?= e(__('fields.additional')) ?>" value="<?= e((string) $quoteValue('extra_description')) ?>"></td>
                        <td><input data-qty name="extra_qty" value="<?= e((string) $quoteValue('extra_qty', 1)) ?>"></td>
                        <td><input data-price name="extra_price" value="<?= e((string) $quoteValue('extra_price', 0)) ?>"></td>
                        <td><strong data-line-total>0.00</strong></td>
                    </tr>
                    </tbody>
                </table></div>
                <div class="form-grid">
                    <label><?= e(__('fields.discount')) ?><input name="discount_amount" value="<?= e((string) $quoteValue('discount_amount', $latestQuote['discount_amount'] ?? 0)) ?>"></label>
                    <label><?= e(__('fields.tax_rate')) ?><input name="tax_rate" value="<?= e((string) $quoteValue('tax_rate', $latestQuote['tax_rate'] ?? 0)) ?>"></label>
                    <label><?= e(__('fields.additional_amount')) ?><input name="additional_amount" value="<?= e((string) $quoteValue('additional_amount', $latestQuote['additional_amount'] ?? 0)) ?>"></label>
                    <label><?= e(__('fields.validity_days')) ?><input name="validity_days" value="<?= e((string) $quoteValue('validity_days', $validityDays)) ?>"></label>
                </div>
                <div class="quote-total"><span><?= e(__('fields.total')) ?></span><strong data-total>0.00</strong></div>
                <label><?= e(__('fields.notes')) ?><textarea name="notes" rows="2"><?= e((string) $quoteValue('notes', $latestQuote['notes'] ?? '')) ?></textarea></label>
                <label><?= e(__('fields.terms')) ?><textarea name="terms" rows="2"><?= e((string) $quoteValue('terms', $latestQuote['terms'] ?? setting('quote_terms', ''))) ?></textarea></label>
                <?php if ($auth->can('quotations.approve')): ?><label class="consent"><input type="checkbox" name="issue" value="1" <?= (!$restoringQuote || old('issue', false)) ? 'checked' : '' ?>> <?= e(__('admin.issue_quote')) ?></label><?php endif; ?>
                <?php if ($auth->can('quotations.send')): ?><label class="consent"><input type="checkbox" name="email" value="1" <?= $restoringQuote && old('email', false) ? 'checked' : '' ?>> <?= e(__('admin.email_customer')) ?></label><?php endif; ?>
                <div class="actions">
                    <button class="btn btn-primary" type="submit"><?= $latestQuote && $latestQuote['status'] !== 'draft' ? e(__('admin.create_revision')) : e(__('admin.generate_quote')) ?></button>
                </div>
            </form>
            <?php if (count($quotations) > 1): ?>
                <h3 style="margin-top:1rem"><?= e(__('admin.quote_history')) ?></h3>
                <ul><?php foreach ($quotations as $quote): ?><li><a href="<?= e(url('/admin/quotations/' . $quote['id'])) ?>"><?= e($quote['number']) ?></a> · <?= e($quote['status']) ?> · <?= e(money($quote['total'], $quote['currency'])) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($canDocs): ?>
    <section class="case-panel" data-panel="documents" hidden>
        <?php if ($auth->can('documents.upload')): ?>
        <section class="card-block">
            <div class="card-head"><h2><?= e(__('admin.upload_document')) ?></h2></div>
            <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/documents')) ?>" enctype="multipart/form-data" data-busy="<?= e(__('admin.uploading')) ?>"><?= csrf_field() ?>
                <div class="form-grid">
                    <label><?= e(__('fields.file')) ?><input type="file" name="document" required></label>
                    <label><?= e(__('fields.category')) ?>
                        <select name="category" data-doc-category>
                            <option value="customer"><?= e(__('admin.doc_customer')) ?></option>
                            <option value="internal"><?= e(__('admin.doc_internal')) ?></option>
                            <option value="completed"><?= e(__('admin.doc_completed')) ?></option>
                        </select>
                    </label>
                    <label><?= e(__('fields.subcategory')) ?>
                        <select name="subcategory">
                            <option value="passport">Passport</option>
                            <option value="certificate">Certificate</option>
                            <option value="translation">Translation</option>
                            <option value="mofa">MOFA</option>
                            <option value="embassy">Embassy</option>
                            <option value="other">Other</option>
                        </select>
                    </label>
                    <label><?= e(__('fields.description')) ?><input name="description" placeholder="<?= e(__('fields.display_name')) ?>"></label>
                </div>
                <label class="consent"><input type="checkbox" name="customer_visible" value="1" data-doc-visible> <?= e(__('admin.visible')) ?></label>
                <button class="btn btn-primary" type="submit"><?= e(__('actions.upload')) ?></button>
            </form>
        </section>
        <?php endif; ?>
        <?php if ($auth->can('documents.download')): ?>
        <p><a href="<?= e(url('/admin/documents?case_id=' . $case['id'])) ?>">Manage this case's documents and release completed files</a></p>
        <?php
        $groups = [
            'customer' => __('admin.doc_customer'),
            'internal' => __('admin.doc_internal'),
            'completed' => __('admin.doc_completed'),
        ];
        foreach ($groups as $key => $label):
            $rows = $docsByCategory[$key] ?? [];
        ?>
            <section class="card-block doc-group">
                <h3><?= e($label) ?></h3>
                <?php if ($rows === []): ?>
                    <p class="empty"><?= e(__('empty.documents')) ?></p>
                <?php else: ?>
                    <div class="doc-list">
                        <?php foreach ($rows as $doc): ?>
                            <div class="doc-row">
                                <div>
                                    <strong><?= e($doc['original_name']) ?></strong>
                                    <span><?= e($doc['subcategory'] ?? '') ?> · <?= e(format_datetime($doc['created_at'])) ?></span>
                                    <span class="vis-tag <?= (int) $doc['customer_visible'] === 1 ? 'is-public' : '' ?>"><?= (int) $doc['customer_visible'] === 1 ? e(__('admin.visible')) : e(__('admin.private')) ?></span>
                                </div>
                                <a class="btn btn-ghost" href="<?= e(url('/admin/documents/' . $doc['id'] . '/download')) ?>"><?= e(__('actions.download')) ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="case-panel" data-panel="processing" hidden>
        <div class="admin-grid">
            <section class="card-block">
                <h2><?= e(__('admin.mofa')) ?></h2>
                <div class="process-card">
                    <h3><?= e(ucfirst((string) ($mofa['status'] ?? 'not_started'))) ?></h3>
                    <p><?= e(__('fields.reference')) ?>: <?= e((string) ($mofa['reference_no'] ?? '—')) ?></p>
                    <p><?= e(__('fields.submitted_at')) ?>: <?= e((string) ($mofa['submitted_at'] ?? '—')) ?></p>
                    <p><?= e(__('fields.expected_at')) ?>: <?= e((string) ($mofa['expected_at'] ?? '—')) ?></p>
                </div>
                <?php if ($auth->can('cases.edit')): ?>
                <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/mofa')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <div class="form-grid">
                        <label><?= e(__('fields.reference')) ?><input name="reference_no" value="<?= e((string) ($mofa['reference_no'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.status')) ?>
                            <select name="status">
                                <?php foreach (['not_started', 'submitted', 'processing', 'completed'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= selected($mofa['status'] ?? 'not_started', $st) ?>><?= e(ucfirst(str_replace('_', ' ', $st))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= e(__('fields.submitted_at')) ?><input type="date" name="submitted_at" value="<?= e((string) ($mofa['submitted_at'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.expected_at')) ?><input type="date" name="expected_at" value="<?= e((string) ($mofa['expected_at'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.completed_at')) ?><input type="date" name="completed_at" value="<?= e((string) ($mofa['completed_at'] ?? '')) ?>"></label>
                    </div>
                    <label><?= e(__('fields.notes')) ?><textarea name="notes"><?= e((string) ($mofa['notes'] ?? '')) ?></textarea></label>
                    <label class="consent"><input type="checkbox" name="update_case" value="1" checked> <?= e(__('admin.update_case_status')) ?></label>
                    <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
                </form>
                <?php endif; ?>
            </section>
            <section class="card-block">
                <h2><?= e(__('admin.embassy')) ?></h2>
                <div class="process-card">
                    <h3><?= e(ucfirst((string) ($embassy['status'] ?? 'not_started'))) ?></h3>
                    <p><?= e(__('fields.reference')) ?>: <?= e((string) ($embassy['reference_no'] ?? '—')) ?></p>
                    <p><?= e(__('fields.submitted_at')) ?>: <?= e((string) ($embassy['submitted_at'] ?? '—')) ?></p>
                    <p><?= e(__('fields.expected_at')) ?>: <?= e((string) ($embassy['expected_at'] ?? '—')) ?></p>
                </div>
                <?php if ($auth->can('cases.edit')): ?>
                <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/embassy')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <div class="form-grid">
                        <label><?= e(__('fields.embassy')) ?><input name="embassy_name" required value="<?= e((string) ($embassy['embassy_name'] ?? (($case['country_name'] ?? '') . ' Embassy'))) ?>"></label>
                        <label><?= e(__('fields.country_id')) ?><select name="country_id"><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>" <?= selected($case['country_id'], $country['id']) ?>><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
                        <label><?= e(__('fields.reference')) ?><input name="reference_no" value="<?= e((string) ($embassy['reference_no'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.status')) ?>
                            <select name="status">
                                <?php foreach (['not_started', 'submitted', 'processing', 'completed'] as $st): ?>
                                    <option value="<?= e($st) ?>" <?= selected($embassy['status'] ?? 'not_started', $st) ?>><?= e(ucfirst(str_replace('_', ' ', $st))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><?= e(__('fields.submitted_at')) ?><input type="date" name="submitted_at" value="<?= e((string) ($embassy['submitted_at'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.expected_at')) ?><input type="date" name="expected_at" value="<?= e((string) ($embassy['expected_at'] ?? '')) ?>"></label>
                        <label><?= e(__('fields.completed_at')) ?><input type="date" name="completed_at" value="<?= e((string) ($embassy['completed_at'] ?? '')) ?>"></label>
                    </div>
                    <label><?= e(__('fields.notes')) ?><textarea name="notes"><?= e((string) ($embassy['notes'] ?? '')) ?></textarea></label>
                    <label class="consent"><input type="checkbox" name="update_case" value="1" checked> <?= e(__('admin.update_case_status')) ?></label>
                    <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
                </form>
                <?php endif; ?>
            </section>
        </div>
    </section>

    <?php if ($canFinance): ?>
    <section class="case-panel" data-panel="finance" hidden>
        <section class="card-block">
            <div class="card-head"><h2><?= e(__('admin.tab_finance')) ?></h2></div>
            <div class="finance-stack">
                <div class="finance-row">
                    <div>
                        <strong><?= e(__('admin.quotation')) ?></strong>
                        <?php if ($latestQuote): ?>
                            <div><a href="<?= e(url('/admin/quotations/' . $latestQuote['id'])) ?>"><?= e($latestQuote['number']) ?></a> · <?= e($latestQuote['status']) ?></div>
                        <?php else: ?>
                            <div><?= e(__('admin.none_yet')) ?></div>
                        <?php endif; ?>
                    </div>
                    <strong><?= $latestQuote ? e(money($latestQuote['total'], $latestQuote['currency'])) : '—' ?></strong>
                </div>
                <div class="finance-row">
                    <div>
                        <strong><?= e(__('admin.invoices')) ?></strong>
                        <?php if ($latestInvoice): ?>
                            <div><a href="<?= e(url('/admin/invoices/' . $latestInvoice['id'])) ?>"><?= e($latestInvoice['number']) ?></a> · <?= e($latestInvoice['status']) ?></div>
                        <?php else: ?>
                            <div><?= e(__('admin.none_yet')) ?></div>
                        <?php endif; ?>
                    </div>
                    <strong><?= $latestInvoice ? e(money($latestInvoice['total'], $latestInvoice['currency'])) : '—' ?></strong>
                </div>
                <div class="finance-row">
                    <div>
                        <strong><?= e(__('admin.outstanding')) ?></strong>
                        <div><?= e(__('admin.balance_due')) ?></div>
                    </div>
                    <strong><?= e(money($outstanding, $case['currency'] ?? 'LKR')) ?></strong>
                </div>
            </div>
            <?php if ($auth->can('payments.create') && $latestInvoice && !in_array($latestInvoice['status'], ['cancelled', 'paid'], true)): ?>
                <form class="stack" style="margin-top:1rem" method="post" action="<?= e(url('/admin/invoices/' . $latestInvoice['id'] . '/payments')) ?>" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <h3><?= e(__('admin.record_payment')) ?></h3>
                    <div class="form-grid">
                        <label><?= e(__('fields.amount')) ?><input name="amount" required value="<?= e((string) $latestInvoice['amount_due']) ?>"></label>
                        <label><?= e(__('fields.method')) ?><select name="method"><option value="bank_transfer">Bank transfer</option><option value="cash">Cash</option><option value="card">Card</option><option value="online">Online</option><option value="other">Other</option></select></label>
                        <label><?= e(__('fields.date')) ?><input type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>" required></label>
                        <label><?= e(__('fields.reference')) ?><input name="reference"></label>
                    </div>
                    <label><?= e(__('fields.notes')) ?><textarea name="notes"></textarea></label>
                    <button class="btn btn-primary" type="submit"><?= e(__('admin.record_payment')) ?></button>
                </form>
            <?php endif; ?>
            <?php if ($auth->can('payments.create') && $latestInvoice && $latestInvoice['status'] !== 'cancelled' && \App\Helpers\Money::cmp($latestInvoice['amount_paid'], '0') > 0): ?>
                <form class="stack" style="margin-top:1rem" method="post" action="<?= e(url('/admin/invoices/' . $latestInvoice['id'] . '/payments')) ?>" data-confirm="Record this refund?" data-busy="<?= e(__('admin.saving')) ?>"><?= csrf_field() ?>
                    <h3>Record refund</h3>
                    <p>Available to refund: <?= e(money($latestInvoice['amount_paid'], $latestInvoice['currency'])) ?></p>
                    <input type="hidden" name="method" value="refund">
                    <div class="form-grid">
                        <label><?= e(__('fields.amount')) ?><input name="amount" type="number" min="0.01" step="0.01" max="<?= e((string) $latestInvoice['amount_paid']) ?>" required></label>
                        <label><?= e(__('fields.date')) ?><input type="date" name="paid_at" value="<?= e(date('Y-m-d')) ?>" required></label>
                        <label><?= e(__('fields.reference')) ?><input name="reference" maxlength="80"></label>
                    </div>
                    <label><?= e(__('fields.notes')) ?><textarea name="notes" maxlength="1000"></textarea></label>
                    <button class="btn btn-ghost" type="submit">Record refund</button>
                </form>
            <?php endif; ?>
            <?php if ($payments !== []): ?>
                <h3 style="margin-top:1rem"><?= e(__('admin.payments')) ?></h3>
                <ul>
                    <?php foreach ($payments as $payment): ?>
                        <li><?= e($payment['number']) ?> · <?= e($payment['method']) ?> · <?= e(money($payment['amount'], $payment['currency'] ?? $case['currency'])) ?> · <?= e(format_datetime($payment['paid_at'] ?? $payment['created_at'])) ?>
                            <a href="<?= e(url('/admin/payments/' . $payment['id'] . '/receipt')) ?>"><?= e(__('actions.download')) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </section>
    <?php endif; ?>

    <section class="case-panel" data-panel="activity" hidden>
        <section class="card-block">
            <div class="card-head"><h2><?= e(__('admin.tab_activity')) ?></h2></div>
            <?php if (($activity ?? []) === []): ?>
                <p class="empty"><?= e(__('admin.no_activity')) ?></p>
            <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($activity as $item): ?>
                        <article class="activity-item">
                            <time><?= e(format_datetime($item['at'])) ?></time>
                            <div>
                                <strong><?= e($item['title']) ?></strong>
                                <?php if (!empty($item['meta'])): ?><p><?= e($item['meta']) ?></p><?php endif; ?>
                                <?php if (!empty($item['by'])): ?><span><?= e(__('admin.by')) ?> <?= e($item['by']) ?></span><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </section>
</article>
