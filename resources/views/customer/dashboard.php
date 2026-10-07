<?php
$first = trim((string) strtok((string) $customer['name'], ' '));
$code = customer_code($customer);
?>
<section class="account-page">
    <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a> <span>/</span> <?= e(__('portal.my_account')) ?></p>
        <div class="account-hero">
            <div>
                <h1><?= e(__('portal.my_account')) ?></h1>
                <p class="account-welcome" data-profile-welcome data-welcome-template="<?= e(__('portal.welcome', ['name' => '__NAME__'])) ?>"><?= e(__('portal.welcome', ['name' => $first])) ?></p>
                <p class="lead"><?= e(__('portal.welcome_lead')) ?></p>
            </div>
            <article class="profile-card">
                <div class="profile-card-main">
                    <span class="avatar" data-profile-initials><?= e(initials($customer['name'])) ?></span>
                    <div>
                        <strong data-profile-name><?= e($customer['name']) ?></strong>
                        <p data-profile-email><?= e($customer['email']) ?></p>
                        <p data-profile-phone><?= e((string) ($customer['phone'] ?: '—')) ?></p>
                        <p class="profile-id"><?= e(__('portal.customer_id')) ?> <span><?= e($code) ?></span></p>
                    </div>
                </div>
                <button class="btn btn-primary" type="button" data-profile-open><?= e(__('portal.edit_profile')) ?></button>
            </article>
        </div>

        <div class="account-stats">
            <?php
            $stats = [
                ['applications', 'document', 'portal.applications', 'portal.applications_hint'],
                ['tracking', 'search', 'portal.tracking', 'portal.tracking_hint'],
                ['quotations', 'quote', 'portal.quotations', 'portal.quotations_hint'],
                ['invoices', 'invoice', 'portal.invoices', 'portal.invoices_hint'],
                ['payments', 'payment', 'portal.payments', 'portal.payments_hint'],
                ['documents', 'file', 'portal.documents', 'portal.documents_hint'],
            ];
            foreach ($stats as [$key, $icon, $label, $hint]): ?>
                <a class="stat-card" href="#<?= e($key) ?>">
                    <span class="stat-top"><strong><?= (int) $counts[$key] ?></strong><?= icon('chevron', 'icon icon-sm') ?></span>
                    <span class="stat-icon"><?= icon($icon) ?></span>
                    <span class="stat-label"><?= e(__($label)) ?></span>
                    <span class="stat-hint"><?= e(__($hint)) ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <section class="account-panel" id="applications">
            <div class="account-panel-head">
                <div><span class="service-icon"><?= icon('document') ?></span><div><h2><?= e(__('portal.recent_applications')) ?></h2><p><?= e(__('portal.recent_applications_lead')) ?></p></div></div>
                <a href="<?= e(url('/portal/applications')) ?>"><?= e(__('portal.view_all')) ?> <?= icon('arrow', 'icon icon-sm') ?></a>
            </div>
            <?php if ($cases === []): ?>
                <p class="empty"><?= e(__('empty.cases')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th><?= e(__('portal.application_id')) ?></th><th><?= e(__('fields.document_type_id')) ?></th><th><?= e(__('fields.country_id')) ?></th><th><?= e(__('fields.status')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($cases, 0, 5) as $case): ?>
                            <tr>
                                <td data-label="<?= e(__('portal.application_id')) ?>"><a href="<?= e(url('/portal/applications/' . $case['id'])) ?>"><?= e($case['number']) ?></a></td>
                                <td data-label="<?= e(__('fields.document_type_id')) ?>"><?= e($case['service_name'] ?: $case['document_title']) ?></td>
                                <td data-label="<?= e(__('fields.country_id')) ?>">
                                    <?php if (!empty($case['country_code'])): ?><img class="flag" src="<?= e(asset('images/flags/' . strtolower($case['country_code']) . '.svg')) ?>" alt="" width="22" height="15"><?php endif; ?>
                                    <?= e($case['country_name'] ?? '') ?>
                                </td>
                                <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?></td>
                                <td><a class="text-link" href="<?= e(url('/portal/applications/' . $case['id'])) ?>"><?= e(__('actions.open')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <div class="account-split">
            <section class="account-panel" id="quotations">
                <div class="account-panel-head">
                    <div><span class="service-icon"><?= icon('quote') ?></span><div><h2><?= e(__('portal.quotes_invoices')) ?></h2><p><?= e(__('portal.quotes_invoices_lead')) ?></p></div></div>
                </div>
                <?php if ($quotations === [] && $invoices === []): ?>
                    <p class="empty"><?= e(__('empty.quotations')) ?></p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th><?= e(__('fields.document')) ?></th><th><?= e(__('fields.number')) ?></th><th><?= e(__('fields.description')) ?></th><th><?= e(__('fields.total')) ?></th><th><?= e(__('fields.status')) ?></th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($quotations as $row): ?>
                            <tr>
                                <td data-label="Type"><?= e(__('portal.quotation')) ?></td>
                                <td data-label="<?= e(__('fields.number')) ?>"><a href="<?= e(url('/portal/quotations/' . $row['id'])) ?>"><?= e($row['number']) ?></a></td>
                                <td data-label="<?= e(__('fields.description')) ?>"><?= e((string) $row['item_name']) ?></td>
                                <td data-label="<?= e(__('fields.total')) ?>"><?= e(money($row['total'], $row['currency'])) ?></td>
                                <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status'], in_array($row['status'], ['accepted', 'converted'], true) ? 'success' : 'neutral') ?></td>
                                <td><a href="<?= e(url('/portal/quotations/' . $row['id'])) ?>"><?= e(__('actions.open')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php foreach ($invoices as $row): ?>
                            <tr id="invoices">
                                <td data-label="Type"><?= e(__('portal.invoice')) ?></td>
                                <td data-label="<?= e(__('fields.number')) ?>"><a href="<?= e(url('/portal/invoices/' . $row['id'])) ?>"><?= e($row['number']) ?></a></td>
                                <td data-label="<?= e(__('fields.description')) ?>"><?= e((string) $row['item_name']) ?></td>
                                <td data-label="<?= e(__('fields.total')) ?>"><?= e(money($row['total'], $row['currency'])) ?></td>
                                <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status'], $row['status'] === 'paid' ? 'success' : ($row['status'] === 'overdue' ? 'danger' : 'warning')) ?></td>
                                <td><a href="<?= e(url('/portal/invoices/' . $row['id'])) ?>"><?= e(__('actions.open')) ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>

            <section class="account-panel" id="payments">
                <div class="account-panel-head">
                    <div><span class="service-icon"><?= icon('payment') ?></span><div><h2><?= e(__('portal.payments')) ?></h2><p><?= e(__('portal.payments_lead')) ?></p></div></div>
                    <a href="<?= e(url('/portal/payments')) ?>"><?= e(__('portal.view_all')) ?></a>
                </div>
                <?php if ($payments === []): ?>
                    <p class="empty"><?= e(__('empty.payments')) ?></p>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th><?= e(__('fields.date')) ?></th><th><?= e(__('fields.number')) ?></th><th><?= e(__('fields.amount')) ?></th><th><?= e(__('fields.status')) ?></th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $row): ?>
                            <tr>
                                <td data-label="<?= e(__('fields.date')) ?>"><?= e(format_date($row['paid_at'])) ?></td>
                                <td data-label="<?= e(__('fields.number')) ?>"><?= e($row['number']) ?></td>
                                <td data-label="<?= e(__('fields.amount')) ?>"><?= e(money($row['amount'], $row['currency'])) ?></td>
                                <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge($row['status'] === 'completed' ? __('portal.paid') : $row['status'], $row['status'] === 'completed' ? 'success' : 'warning') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </section>
        </div>

        <section class="account-panel" id="tracking">
            <div class="account-panel-head">
                <div><span class="service-icon"><?= icon('search') ?></span><div><h2><?= e(__('portal.tracking')) ?></h2><p><?= e(__('portal.tracking_hint')) ?></p></div></div>
            </div>
            <?php $open = array_values(array_filter($cases, static fn (array $case): bool => (int) $case['is_terminal'] === 0)); ?>
            <?php if ($open === []): ?>
                <p class="empty"><?= e(__('empty.tracking')) ?></p>
            <?php else: ?>
                <ul class="track-list">
                    <?php foreach (array_slice($open, 0, 4) as $case): ?>
                        <li>
                            <div><strong><?= e($case['number']) ?></strong><span><?= e($case['document_title']) ?></span></div>
                            <?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?>
                            <a href="<?= e(url('/track?number=' . rawurlencode($case['number']))) ?>"><?= e(__('track.button')) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="account-panel" id="documents">
            <div class="account-panel-head">
                <div><span class="service-icon"><?= icon('file') ?></span><div><h2><?= e(__('portal.documents')) ?></h2><p><?= e(__('portal.documents_lead')) ?></p></div></div>
                <a href="<?= e(url('/portal/documents')) ?>"><?= e(__('portal.manage_documents')) ?></a>
            </div>
            <?php if ($documents === []): ?>
                <p class="empty"><?= e(__('empty.documents')) ?></p>
            <?php else: ?>
                <div class="doc-grid">
                    <?php foreach ($documents as $doc): ?>
                        <article class="doc-card">
                            <span class="doc-icon"><?= icon(str_contains((string) $doc['mime'], 'pdf') ? 'file' : 'document') ?></span>
                            <div>
                                <strong><?= e($doc['original_name']) ?></strong>
                                <span><?= e(__('portal.uploaded_on', ['date' => format_date($doc['created_at'])])) ?></span>
                            </div>
                            <a href="<?= e(url('/portal/documents/' . $doc['id'] . '/download')) ?>" aria-label="<?= e(__('actions.download')) ?>"><?= icon('download') ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

<dialog class="profile-dialog" id="profile-dialog" aria-labelledby="profile-dialog-title">
    <form method="post" action="<?= e(url('/portal/profile')) ?>" data-profile-form>
        <?= csrf_field() ?>
        <div class="dialog-head">
            <h2 id="profile-dialog-title"><?= e(__('portal.edit_profile')) ?></h2>
            <button class="icon-btn" type="button" data-profile-close aria-label="<?= e(__('actions.close')) ?>"><?= icon('close') ?></button>
        </div>
        <p class="form-error" data-profile-error hidden></p>
        <div class="form-grid">
            <label><?= e(__('fields.name')) ?><input name="name" value="<?= e($customer['name']) ?>" required data-field="name"></label>
            <label><?= e(__('fields.email')) ?><input value="<?= e($customer['email']) ?>" disabled></label>
            <label><?= e(__('fields.phone')) ?><input name="phone" value="<?= e((string) $customer['phone']) ?>" data-field="phone"></label>
            <label><?= e(__('fields.whatsapp')) ?><input name="whatsapp" value="<?= e((string) $customer['whatsapp']) ?>" data-field="whatsapp"></label>
            <label><?= e(__('fields.city')) ?><input name="city" value="<?= e((string) $customer['city']) ?>" data-field="city"></label>
            <label><?= e(__('fields.locale')) ?>
                <select name="locale">
                    <?php foreach (config('app.locales') as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= selected($user['locale'] ?? locale(), $code) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="span-2"><?= e(__('fields.address')) ?><input name="address" value="<?= e((string) $customer['address']) ?>" data-field="address"></label>
        </div>
        <div class="dialog-actions">
            <button class="btn btn-ghost" type="button" data-profile-close><?= e(__('actions.cancel')) ?></button>
            <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
        </div>
    </form>
    <form method="post" action="<?= e(url('/portal/profile/password')) ?>" data-password-form>
        <?= csrf_field() ?>
        <h3><?= e(__('portal.password')) ?></h3>
        <p class="form-error" data-password-error hidden></p>
        <div class="form-grid">
            <label><?= e(__('fields.current_password')) ?><input type="password" name="current_password" required autocomplete="current-password"></label>
            <label><?= e(__('fields.password')) ?><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label class="span-2"><?= e(__('fields.password_confirmation')) ?><input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
        </div>
        <div class="dialog-actions">
            <button class="btn btn-primary" type="submit"><?= e(__('portal.password_saved_action')) ?></button>
        </div>
    </form>
</dialog>
