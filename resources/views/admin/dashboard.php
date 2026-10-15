<?php
$hour = (int) date('G');
$greeting = $hour < 12 ? __('admin.good_morning') : ($hour < 17 ? __('admin.good_afternoon') : __('admin.good_evening'));
$auth = app(\App\Services\AuthService::class);
$user = $auth->user();
$definitions = [
    'overdue' => ['overdue', '/admin/invoices?status=overdue', 'danger', 0, 'due'],
    'ready' => ['ready', '/admin/documents?category=completed&visibility=private', 'success', 1, 'uploaded'],
    'unquoted' => ['unquoted', '/admin/cases?status=unquoted', 'info', 2, 'created'],
    'draft_quotes' => ['draft_quotes', '/admin/quotations?status=draft', 'info', 2, 'created'],
    'waiting_documents' => ['waiting_documents', '/admin/cases?status=awaiting_documents', '', 3, 'created'],
    'mofa' => ['mofa', '/admin/mofa?status=active', 'info', 3, 'updated'],
    'embassy' => ['embassy', '/admin/embassy?status=active', 'info', 3, 'updated'],
];
$attentionRows = [];
$queueTotal = 0;
foreach ($metrics['attention'] as $key => $queue) {
    [$label, $path, $tone, $priority, $dateLabel] = $definitions[$key];
    $queueTotal += $queue['total'];
    foreach ($queue['rows'] as $row) {
        $isProcess = in_array($key, ['mofa', 'embassy'], true);
        $expected = $isProcess ? ($row['expected_at'] ?? null) : null;
        $pastExpected = $expected && $expected < date('Y-m-d');
        $eventAt = $expected ?: $row['event_at'];
        $href = match ($key) {
            'overdue' => '/admin/invoices/' . $row['id'],
            'draft_quotes' => '/admin/quotations/' . $row['id'],
            'ready' => '/admin/documents?category=completed&visibility=private&case_id=' . $row['case_id'],
            'unquoted' => '/admin/cases/' . $row['id'] . '#quote',
            'waiting_documents' => '/admin/cases/' . $row['id'] . '#documents',
            default => '/admin/cases/' . $row['id'] . '#processing',
        };
        $attentionRows[] = [
            'number' => $row['case_number'] ?? $row['number'],
            'detail' => $key === 'ready' ? $row['original_name'] : (isset($row['case_number']) ? $row['number'] : ''),
            'customer' => $row['customer_name'],
            'reason' => __('admin_overview.' . $label),
            'tone' => $pastExpected ? 'danger' : $tone,
            'priority' => $pastExpected ? 0 : $priority,
            'event_at' => $eventAt,
            'date_label' => __('admin_overview.' . ($expected ? 'expected' : $dateLabel)),
            'href' => url($href),
        ];
    }
}
usort($attentionRows, static fn (array $a, array $b): int => [$a['priority'], $a['event_at'], $a['number']] <=> [$b['priority'], $b['event_at'], $b['number']]);
$attentionRows = array_slice($attentionRows, 0, 12);
$cards = [
    ['active', 'admin.active_cases', '/admin/cases?status=active', 'active_hint'],
    ['unquoted', 'admin_overview.unquoted', '/admin/cases?status=unquoted', 'unquoted_hint'],
    ['awaiting_payment', 'admin_overview.unpaid', '/admin/invoices?status=unpaid', 'unpaid_hint'],
    ['ready_docs', 'admin_overview.ready', '/admin/documents?category=completed&visibility=private', 'ready_hint'],
];
$workspace = array_values(array_filter([
    $auth->can('content.manage') ? ['/admin/content', 'admin.content'] : null,
    $auth->can('content.manage') ? ['/admin/articles', 'admin.articles'] : null,
    $auth->can('countries.manage') ? ['/admin/countries', 'admin.countries'] : null,
    $auth->can('services.manage') ? ['/admin/services', 'admin.services'] : null,
    $auth->can('translations.manage') ? ['/admin/translations', 'admin.translations'] : null,
    $auth->can('reports.view') ? ['/admin/reports', 'admin.reports'] : null,
    $auth->can('users.manage') ? ['/admin/users', 'admin.users'] : null,
    $auth->can('settings.manage') ? ['/admin/settings', 'admin.settings'] : null,
]));
?>
<section class="dash-hello">
    <div>
        <h1><?= e($greeting) ?>, <?= e((string) ($user['name'] ?? 'Admin')) ?></h1>
        <p><?= e(__('admin_overview.scope')) ?></p>
    </div>
    <?php if ($auth->can('reports.view')): ?><a class="btn btn-ghost" href="<?= e(url('/admin/reports')) ?>"><?= e(__('admin.reports')) ?></a><?php endif; ?>
</section>

<div class="metric-grid overview-metrics">
    <?php foreach ($cards as [$key, $label, $path, $hint]): if ($metrics[$key] === null) { continue; } ?>
    <a class="metric" href="<?= e(url($path)) ?>">
        <span><?= e(__($label)) ?></span>
        <strong><?= (int) $metrics[$key] ?></strong>
        <small><?= e(__('admin_overview.' . $hint)) ?></small>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($metrics['attention'] !== []): ?>
<section class="card-block">
    <div class="card-head"><h2><?= e(__('admin.needs_attention')) ?></h2><span class="badge"><?= (int) $queueTotal ?> <?= e(__('admin_overview.tasks')) ?></span></div>
    <p class="overview-note"><?= e(__('admin_overview.queue_scope', ['shown' => count($attentionRows), 'total' => $queueTotal])) ?></p>
    <div class="queue-links">
        <?php foreach ($metrics['attention'] as $key => $queue): ?>
        <a href="<?= e(url($definitions[$key][1])) ?>"><?= e(__('admin_overview.' . $definitions[$key][0])) ?><strong><?= (int) $queue['total'] ?></strong></a>
        <?php endforeach; ?>
    </div>
    <?php if ($attentionRows === []): ?>
        <p class="empty"><?= e(__('admin_overview.queue_clear')) ?></p>
    <?php else: ?>
    <div class="table-wrap"><table class="attention-table">
        <thead><tr><th><?= e(__('admin.cases')) ?></th><th><?= e(__('admin.customers')) ?></th><th><?= e(__('admin.reason')) ?></th><th><?= e(__('fields.date')) ?></th><th><span class="sr-only"><?= e(__('actions.open')) ?></span></th></tr></thead>
        <tbody>
        <?php foreach ($attentionRows as $row): ?>
            <tr>
                <td data-label="<?= e(__('admin.cases')) ?>"><a href="<?= e($row['href']) ?>"><?= e($row['number']) ?></a><?php if ($row['detail']): ?><small class="record-detail"><?= e($row['detail']) ?></small><?php endif; ?></td>
                <td data-label="<?= e(__('admin.customers')) ?>"><?= e($row['customer']) ?></td>
                <td data-label="<?= e(__('admin.reason')) ?>"><span class="reason-pill <?= e($row['tone'] ? 'is-' . $row['tone'] : '') ?>"><?= e($row['reason']) ?></span></td>
                <td data-label="<?= e(__('fields.date')) ?>"><?= e(format_date($row['event_at'])) ?><small class="record-detail"><?= e($row['date_label']) ?></small></td>
                <td><a class="btn btn-ghost" href="<?= e($row['href']) ?>"><?= e(__('actions.open')) ?><span class="sr-only"> <?= e($row['number']) ?></span></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="admin-grid overview-recent">
    <?php if ($metrics['recent_cases'] !== null): ?>
    <section class="card-block">
        <div class="card-head"><h2><?= e(__('admin.recent_cases')) ?></h2><a href="<?= e(url('/admin/cases')) ?>"><?= e(__('actions.view')) ?></a></div>
        <?php if ($metrics['recent_cases'] === []): ?><p class="empty"><?= e(__('empty.cases')) ?></p><?php endif; ?>
        <?php foreach ($metrics['recent_cases'] as $case): ?>
        <div class="overview-record"><a href="<?= e(url('/admin/cases/' . $case['id'])) ?>"><strong><?= e($case['number']) ?></strong><small><?= e($case['customer_name']) ?> · <?= e(format_date($case['created_at'])) ?></small></a><?= status_badge($case['status_name'], $case['badge_tone']) ?></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if ($metrics['recent_quotes'] !== null): ?>
    <section class="card-block">
        <div class="card-head"><h2><?= e(__('admin.quote_requests')) ?></h2><a href="<?= e(url('/admin/quote-requests')) ?>"><?= e(__('actions.view')) ?></a></div>
        <?php if ($metrics['recent_quotes'] === []): ?><p class="empty"><?= e(__('empty.quotations')) ?></p><?php endif; ?>
        <?php foreach ($metrics['recent_quotes'] as $quote): ?>
        <div class="overview-record"><a href="<?= e(url('/admin/quote-requests/' . $quote['id'])) ?>"><strong><?= e($quote['name']) ?></strong><small><?= e(format_datetime($quote['created_at'])) ?></small></a><span class="badge"><?= e($quote['status']) ?></span></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>

<?php if ($workspace !== []): ?>
<section class="card-block">
    <div class="card-head"><h2><?= e(__('admin_overview.workspace')) ?></h2></div>
    <div class="actions"><?php foreach ($workspace as [$path, $label]): ?><a class="btn btn-ghost" href="<?= e(url($path)) ?>"><?= e(__($label)) ?></a><?php endforeach; ?></div>
</section>
<?php endif; ?>

