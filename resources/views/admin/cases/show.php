<p><strong><?= e($case['customer_name']) ?></strong> · <?= e($case['customer_email']) ?> · <?= e($case['document_title']) ?></p>
<p><?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?> <?= e($case['country_name'] ?? '') ?></p>
<div class="admin-grid">
    <section class="card-block">
        <h2><?= e(__('admin.details')) ?></h2>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'])) ?>"><?= csrf_field() ?>
            <label><?= e(__('fields.document_title')) ?><input name="document_title" value="<?= e($case['document_title']) ?>" required></label>
            <label><?= e(__('fields.country_id')) ?><select name="country_id"><option value=""></option><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>" <?= selected($case['country_id'], $country['id']) ?>><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(__('fields.document_type_id')) ?><select name="document_type_id"><option value=""></option><?php foreach ($documentTypes as $type): ?><option value="<?= e((string) $type['id']) ?>" <?= selected($case['document_type_id'], $type['id']) ?>><?= e($type['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(__('fields.summary')) ?><textarea name="summary" rows="3"><?= e((string) $case['summary']) ?></textarea></label>
            <label><?= e(__('fields.estimated_next')) ?><input name="estimated_next" value="<?= e((string) $case['estimated_next']) ?>"></label>
            <label><?= e(__('fields.internal_notes')) ?><textarea name="internal_notes" rows="3"><?= e((string) $case['internal_notes']) ?></textarea></label>
            <label><?= e(__('fields.customer_notes')) ?><textarea name="customer_notes" rows="3"><?= e((string) $case['customer_notes']) ?></textarea></label>
            <label><?= e(__('fields.assigned_to')) ?><select name="assigned_to"><option value=""></option><?php foreach ($staff as $person): ?><option value="<?= e((string) $person['id']) ?>" <?= selected($case['assigned_to'], $person['id']) ?>><?= e($person['name']) ?></option><?php endforeach; ?></select></label>
            <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
        </form>
    </section>
    <section class="card-block">
        <h2><?= e(__('admin.status')) ?></h2>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/status')) ?>"><?= csrf_field() ?>
            <label><?= e(__('fields.status')) ?><select name="status"><?php foreach ($statuses as $status): ?><option value="<?= e($status['code']) ?>" <?= selected($case['status_code'], $status['code']) ?>><?= e($status['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(__('fields.notes')) ?><textarea name="notes" rows="3"></textarea></label>
            <label class="consent"><input type="checkbox" name="customer_visible" value="1" checked> <?= e(__('admin.visible')) ?></label>
            <button class="btn btn-primary" type="submit"><?= e(__('admin.update_status')) ?></button>
        </form>
        <ol class="timeline"><?php foreach ($case['history'] as $item): ?><li><i></i><div><strong><?= e($item['name']) ?></strong> · <?= e(format_datetime($item['created_at'])) ?><?php if ($item['notes']): ?><p><?= e($item['notes']) ?></p><?php endif; ?></div></li><?php endforeach; ?></ol>
    </section>
</div>
<section class="card-block">
    <h2><?= e(__('admin.services')) ?></h2>
    <form method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/services')) ?>"><?= csrf_field() ?>
        <?php foreach ($services as $service): $selected = null; foreach ($case['services'] as $line) { if ((int) $line['service_id'] === (int) $service['id']) { $selected = $line; } } ?>
            <label class="consent"><input type="checkbox" name="service_id[]" value="<?= e((string) $service['id']) ?>" <?= $selected ? 'checked' : '' ?>> <?= e($service['name']) ?> (<?= e($service['pricing_type']) ?><?php if ($service['pricing_type'] === 'fixed'): ?> <?= e(money($service['fixed_price'], $service['currency'])) ?><?php endif; ?>)</label>
            <input type="hidden" name="quantity[<?= e((string) $service['id']) ?>]" value="<?= e((string) ($selected['quantity'] ?? 1)) ?>">
            <input type="hidden" name="unit_price[<?= e((string) $service['id']) ?>]" value="<?= e((string) ($selected['unit_price'] ?? $service['fixed_price'] ?? '')) ?>">
        <?php endforeach; ?>
        <button class="btn btn-ghost" type="submit"><?= e(__('actions.save')) ?></button>
    </form>
</section>
<section class="card-block" data-quote-builder>
    <h2><?= e(__('admin.quotation')) ?></h2>
    <form method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/quotation')) ?>"><?= csrf_field() ?>
        <?php foreach ($services as $service): $selected = null; foreach ($case['services'] as $line) { if ((int) $line['service_id'] === (int) $service['id']) { $selected = $line; } } $price = $selected['unit_price'] ?? ($service['pricing_type'] === 'fixed' ? $service['fixed_price'] : ''); ?>
            <div class="form-grid" data-line>
                <label class="consent"><input type="checkbox" name="lines[<?= e((string) $service['id']) ?>][selected]" value="1" <?= $selected ? 'checked' : '' ?>> <?= e($service['name']) ?></label>
                <input type="hidden" name="lines[<?= e((string) $service['id']) ?>][description]" value="<?= e($service['name']) ?>">
                <label><?= e(__('fields.quantity')) ?><input data-qty name="lines[<?= e((string) $service['id']) ?>][qty]" value="<?= e((string) ($selected['quantity'] ?? 1)) ?>"></label>
                <label><?= e(__('fields.price')) ?><input data-price name="lines[<?= e((string) $service['id']) ?>][price]" value="<?= e((string) $price) ?>" <?= $service['pricing_type'] === 'fixed' ? 'readonly' : '' ?>></label>
                <strong data-line-total>0.00</strong>
            </div>
        <?php endforeach; ?>
        <div class="form-grid" data-line>
            <label><?= e(__('fields.additional')) ?><input name="extra_description" placeholder="<?= e(__('fields.additional')) ?>"></label>
            <label><?= e(__('fields.quantity')) ?><input data-qty name="extra_qty" value="1"></label>
            <label><?= e(__('fields.price')) ?><input data-price name="extra_price" value="0"></label>
            <strong data-line-total>0.00</strong>
        </div>
        <div class="form-grid">
            <label><?= e(__('fields.discount')) ?><input name="discount_amount" value="0"></label>
            <label><?= e(__('fields.tax_rate')) ?><input name="tax_rate" value="0"></label>
            <label><?= e(__('fields.additional_amount')) ?><input name="additional_amount" value="0"></label>
            <label><?= e(__('fields.validity_days')) ?><input name="validity_days" value="<?= e((string) setting('quote_validity_days', 14)) ?>"></label>
        </div>
        <p><?= e(__('fields.total')) ?>: <strong data-total>0.00</strong></p>
        <label><?= e(__('fields.notes')) ?><textarea name="notes" rows="2"></textarea></label>
        <label><?= e(__('fields.terms')) ?><textarea name="terms" rows="2"><?= e((string) setting('quote_terms', '')) ?></textarea></label>
        <label class="consent"><input type="checkbox" name="issue" value="1" checked> <?= e(__('admin.issue_quote')) ?></label>
        <label class="consent"><input type="checkbox" name="email" value="1"> <?= e(__('admin.email_customer')) ?></label>
        <button class="btn btn-primary" type="submit"><?= e(__('admin.generate_quote')) ?></button>
    </form>
    <ul><?php foreach ($quotations as $quote): ?><li><a href="<?= e(url('/admin/quotations/' . $quote['id'])) ?>"><?= e($quote['number']) ?></a> <?= e($quote['status']) ?> <?= e(money($quote['total'], $quote['currency'])) ?></li><?php endforeach; ?></ul>
</section>
<section class="card-block">
    <h2><?= e(__('admin.documents')) ?></h2>
    <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/documents')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
        <label><?= e(__('fields.file')) ?><input type="file" name="document" required></label>
        <label><?= e(__('fields.category')) ?><select name="category"><option value="customer">Customer submitted</option><option value="internal">Internal</option><option value="completed">Completed</option></select></label>
        <label><?= e(__('fields.subcategory')) ?><select name="subcategory"><option value="passport">Passport</option><option value="certificate">Certificate</option><option value="translation">Translation</option><option value="mofa">MOFA</option><option value="embassy">Embassy</option><option value="other">Other</option></select></label>
        <label><?= e(__('fields.description')) ?><input name="description"></label>
        <label class="consent"><input type="checkbox" name="customer_visible" value="1"> <?= e(__('admin.visible')) ?></label>
        <button class="btn btn-primary" type="submit"><?= e(__('actions.upload')) ?></button>
    </form>
    <ul><?php foreach ($documents as $doc): ?><li><?= e($doc['original_name']) ?> · <?= e($doc['category']) ?> <?php if ((int) $doc['customer_visible'] === 1): ?><?= e(__('admin.visible')) ?><?php endif; ?> <a href="<?= e(url('/admin/documents/' . $doc['id'] . '/download')) ?>"><?= e(__('actions.download')) ?></a></li><?php endforeach; ?></ul>
</section>
<div class="admin-grid">
    <section class="card-block">
        <h2><?= e(__('admin.mofa')) ?></h2>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/mofa')) ?>"><?= csrf_field() ?>
            <label><?= e(__('fields.reference')) ?><input name="reference_no" value="<?= e((string) ($mofa['reference_no'] ?? '')) ?>"></label>
            <label><?= e(__('fields.status')) ?><select name="status"><option value="not_started">Not started</option><option value="submitted">Submitted</option><option value="processing">Processing</option><option value="completed">Completed</option></select></label>
            <label><?= e(__('fields.submitted_at')) ?><input type="date" name="submitted_at"></label>
            <label><?= e(__('fields.expected_at')) ?><input type="date" name="expected_at"></label>
            <label><?= e(__('fields.completed_at')) ?><input type="date" name="completed_at"></label>
            <label><?= e(__('fields.notes')) ?><textarea name="notes"></textarea></label>
            <label class="consent"><input type="checkbox" name="update_case" value="1" checked> <?= e(__('admin.update_case_status')) ?></label>
            <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
        </form>
    </section>
    <section class="card-block">
        <h2><?= e(__('admin.embassy')) ?></h2>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $case['id'] . '/embassy')) ?>"><?= csrf_field() ?>
            <label><?= e(__('fields.embassy')) ?><input name="embassy_name" required value="<?= e(($case['country_name'] ?? '') . ' Embassy') ?>"></label>
            <label><?= e(__('fields.country_id')) ?><select name="country_id"><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>" <?= selected($case['country_id'], $country['id']) ?>><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(__('fields.reference')) ?><input name="reference_no"></label>
            <label><?= e(__('fields.status')) ?><select name="status"><option value="not_started">Not started</option><option value="submitted">Submitted</option><option value="processing">Processing</option><option value="completed">Completed</option></select></label>
            <label><?= e(__('fields.submitted_at')) ?><input type="date" name="submitted_at"></label>
            <label><?= e(__('fields.expected_at')) ?><input type="date" name="expected_at"></label>
            <label><?= e(__('fields.completed_at')) ?><input type="date" name="completed_at"></label>
            <label><?= e(__('fields.notes')) ?><textarea name="notes"></textarea></label>
            <label class="consent"><input type="checkbox" name="update_case" value="1" checked> <?= e(__('admin.update_case_status')) ?></label>
            <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
        </form>
    </section>
</div>
<section class="card-block"><h2><?= e(__('admin.invoices')) ?></h2><ul><?php foreach ($invoices as $invoice): ?><li><a href="<?= e(url('/admin/invoices/' . $invoice['id'])) ?>"><?= e($invoice['number']) ?></a> <?= e($invoice['status']) ?> <?= e(money($invoice['total'], $invoice['currency'])) ?></li><?php endforeach; ?></ul></section>
