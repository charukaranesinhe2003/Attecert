<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? __('admin.good_morning') : ($hour < 17 ? __('admin.good_afternoon') : __('admin.good_evening'));
$attentionRows = [];
foreach (($metrics['attention']['draft_quotes'] ?? []) as $row) {
    $attentionRows[] = [
        'case' => $row['number'],
        'customer' => $row['customer_name'],
        'reason' => __('admin.attention_quote_draft'),
        'tone' => 'info',
        'age' => format_datetime($row['created_at']),
        'href' => url('/admin/cases/' . $row['case_id'] . '#quote'),
        'action' => __('admin.prepare_quotation'),
    ];
}
foreach (($metrics['attention']['overdue'] ?? []) as $row) {
    $attentionRows[] = [
        'case' => $row['number'],
        'customer' => $row['customer_name'],
        'reason' => __('admin.attention_invoice_overdue'),
        'tone' => 'danger',
        'age' => format_datetime($row['due_at']),
        'href' => url('/admin/cases/' . $row['case_id'] . '#finance'),
        'action' => __('admin.record_payment'),
    ];
}
foreach (($metrics['attention']['waiting_documents'] ?? []) as $row) {
    $attentionRows[] = [
        'case' => $row['number'],
        'customer' => $row['customer_name'],
        'reason' => __('admin.attention_waiting_docs'),
        'tone' => '',
        'age' => format_datetime($row['created_at']),
        'href' => url('/admin/cases/' . $row['id'] . '#documents'),
        'action' => __('admin.open_case'),
    ];
}
foreach (($metrics['attention']['mofa'] ?? []) as $row) {
    $attentionRows[] = [
        'case' => $row['number'],
        'customer' => $row['customer_name'],
        'reason' => __('admin.attention_mofa'),
        'tone' => 'info',
        'age' => format_datetime($row['updated_at']),
        'href' => url('/admin/cases/' . $row['id'] . '#processing'),
        'action' => __('admin.update_status'),
    ];
}
foreach (($metrics['attention']['ready'] ?? []) as $row) {
    $attentionRows[] = [
        'case' => $row['case_number'],
        'customer' => $row['customer_name'],
        'reason' => __('admin.attention_ready_release'),
        'tone' => 'success',
        'age' => $row['original_name'],
        'href' => url('/admin/cases/' . $row['case_id'] . '#documents'),
        'action' => __('admin.release_document'),
    ];
}
$user = app(\App\Services\AuthService::class)->user();
?>
<section class="dash-hello">
    <div>
        <h1><?= e($greeting) ?>, <?= e((string) ($user['name'] ?? 'Admin')) ?></h1>
        <p><?= e(__('admin.dashboard_hint')) ?></p>
    </div>
</section>

<div class="metric-grid">
    <a class="metric" href="<?= e(url('/admin/cases?status=active')) ?>">
        <span><?= e(__('admin.active_cases')) ?></span>
        <strong><?= (int) $metrics['active'] ?></strong>
    </a>
    <a class="metric" href="<?= e(url('/admin/quotations?status=draft')) ?>">
        <span><?= e(__('admin.awaiting_quotation')) ?></span>
        <strong><?= (int) $metrics['awaiting_quote'] ?></strong>
    </a>
    <a class="metric" href="<?= e(url('/admin/invoices?status=sent')) ?>">
        <span><?= e(__('admin.awaiting_payment')) ?></span>
        <strong><?= (int) $metrics['awaiting_payment'] ?></strong>
    </a>
    <a class="metric" href="<?= e(url('/admin/documents')) ?>">
        <span><?= e(__('admin.ready_completed')) ?></span>
        <strong><?= (int) $metrics['ready_docs'] ?></strong>
    </a>
</div>

<section class="card-block">
    <div class="card-head">
        <h2><?= e(__('admin.needs_attention')) ?></h2>
    </div>
    <?php if ($attentionRows === []): ?>
        <p class="empty"><?= e(__('admin.attention_clear')) ?></p>
    <?php else: ?>
        <div class="table-wrap"><table class="attention-table">
            <thead>
                <tr>
                    <th><?= e(__('admin.cases')) ?></th>
                    <th><?= e(__('admin.customers')) ?></th>
                    <th><?= e(__('admin.reason')) ?></th>
                    <th><?= e(__('fields.date')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($attentionRows as $row): ?>
                <tr>
                    <td data-label="<?= e(__('admin.cases')) ?>"><a href="<?= e($row['href']) ?>"><?= e($row['case']) ?></a></td>
                    <td data-label="<?= e(__('admin.customers')) ?>"><?= e($row['customer']) ?></td>
                    <td data-label="<?= e(__('admin.reason')) ?>"><span class="reason-pill <?= e($row['tone'] ? 'is-' . $row['tone'] : '') ?>"><?= e($row['reason']) ?></span></td>
                    <td data-label="<?= e(__('fields.date')) ?>"><?= e($row['age']) ?></td>
                    <td data-label=""><a class="btn btn-ghost" href="<?= e($row['href']) ?>"><?= e($row['action']) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<div class="admin-grid">
    <section class="card-block">
        <div class="card-head"><h2><?= e(__('admin.recent_cases')) ?></h2><a href="<?= e(url('/admin/cases')) ?>"><?= e(__('actions.view')) ?></a></div>
        <?php if (($metrics['recent_cases'] ?? []) === []): ?>
            <p class="empty"><?= e(__('empty.cases')) ?></p>
        <?php else: ?>
            <?php foreach ($metrics['recent_cases'] as $case): ?>
                <p><a href="<?= e(url('/admin/cases/' . $case['id'])) ?>"><?= e($case['number']) ?></a> · <?= e($case['customer_name']) ?> · <?= e($case['status_name']) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <section class="card-block">
        <div class="card-head"><h2><?= e(__('admin.quote_requests')) ?></h2><a href="<?= e(url('/admin/quote-requests')) ?>"><?= e(__('actions.view')) ?></a></div>
        <?php if (($metrics['recent_quotes'] ?? []) === []): ?>
            <p class="empty"><?= e(__('empty.quotations')) ?></p>
        <?php else: ?>
            <?php foreach ($metrics['recent_quotes'] as $quote): ?>
                <p><a href="<?= e(url('/admin/quote-requests/' . $quote['id'])) ?>"><?= e($quote['name']) ?></a> · <?= e($quote['status']) ?> · <?= e(format_datetime($quote['created_at'])) ?></p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<section class="card-block">
    <div class="card-head"><h2><?= e(__('admin.recent_documents')) ?></h2></div>
    <?php if (($metrics['recent_documents'] ?? []) === []): ?>
        <p class="empty"><?= e(__('empty.documents')) ?></p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th><?= e(__('fields.document')) ?></th><th><?= e(__('admin.customers')) ?></th><th><?= e(__('fields.number')) ?></th><th><?= e(__('fields.date')) ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($metrics['recent_documents'] as $doc): ?>
                <tr>
                    <td data-label="<?= e(__('fields.document')) ?>"><?= e($doc['original_name']) ?></td>
                    <td data-label="<?= e(__('admin.customers')) ?>"><?= e((string) $doc['customer_name']) ?></td>
                    <td data-label="<?= e(__('fields.number')) ?>"><?php if ($doc['case_id']): ?><a href="<?= e(url('/admin/cases/' . $doc['case_id'])) ?>"><?= e((string) $doc['case_number']) ?></a><?php endif; ?></td>
                    <td data-label="<?= e(__('fields.date')) ?>"><?= e(format_datetime($doc['created_at'])) ?></td>
                    <td data-label=""><a href="<?= e(url('/admin/documents/' . $doc['id'] . '/download')) ?>"><?= e(__('actions.download')) ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
