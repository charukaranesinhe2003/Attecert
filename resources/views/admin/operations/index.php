<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<nav class="actions" aria-label="Assignment type">
    <a class="btn <?= !$isQuote ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/operations')) ?>"<?= !$isQuote ? ' aria-current="page"' : '' ?>>Cases</a>
    <a class="btn <?= $isQuote ? 'btn-primary' : 'btn-ghost' ?>" href="<?= e(url('/admin/operations?type=quote_requests')) ?>"<?= $isQuote ? ' aria-current="page"' : '' ?>>Quote requests</a>
</nav>
<form class="filters" method="get" action="<?= e(url('/admin/operations')) ?>">
    <?php if ($isQuote): ?><input type="hidden" name="type" value="quote_requests"><?php endif; ?>
    <label><?= $isQuote ? 'Search quote requests' : 'Search cases' ?><input type="search" name="q" value="<?= e($q) ?>" maxlength="160" placeholder="<?= $isQuote ? 'Name, email, phone' : 'Case number, document, customer' ?>"></label>
    <label>Assigned staff<select name="assignee">
        <option value="">All assignments</option>
        <option value="unassigned"<?= selected($assignee, 'unassigned') ?>>Unassigned</option>
        <?php foreach ($staff as $person): ?><option value="<?= (int) $person['id'] ?>"<?= selected($assignee, $person['id']) ?>><?= e($person['name']) ?></option><?php endforeach; ?>
    </select></label>
    <button class="btn btn-primary" type="submit">Filter</button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/operations' . ($isQuote ? '?type=quote_requests' : ''))) ?>">Clear filters</a>
</form>
<p><?= (int) $page['total'] ?> matching <?= $isQuote ? 'quote requests' : 'cases' ?></p>
<?php if ($page['rows'] === []): ?>
<p class="empty">No <?= $isQuote ? 'quote requests' : 'cases' ?> match these filters.</p>
<?php else: ?>
<div class="table-wrap"><table>
    <thead><tr><th><?= $isQuote ? 'Quote request' : 'Case' ?></th><th>Customer</th><th>Status</th><th>Assignment</th></tr></thead>
    <tbody><?php foreach ($page['rows'] as $case): ?><tr>
        <td data-label="<?= $isQuote ? 'Quote request' : 'Case' ?>"><a href="<?= e(url(($isQuote ? '/admin/quote-requests/' : '/admin/cases/') . $case['id'])) ?>"><?= e($case['number']) ?></a><small class="record-detail"><?= e($case['document_title']) ?></small></td>
        <td data-label="Customer"><?= e($case['customer_name']) ?></td>
        <td data-label="Status"><?= status_badge($case['status_name'], $case['badge_tone']) ?></td>
        <td data-label="Assignment">
            <p>Current: <?= e($case['assignee_name'] ?: 'Unassigned') ?></p>
            <form class="stack" method="post" action="<?= e(url('/admin/operations/' . ($isQuote ? 'quote-requests/' : '') . $case['id'] . '/assign')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="previous_assigned_to" value="<?= e((string) $case['assigned_to']) ?>">
                <label>Assign <?= e($case['number']) ?> to<select name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff as $person): ?><option value="<?= (int) $person['id'] ?>"<?= selected($case['assigned_to'], $person['id']) ?>><?= e($person['name'] . ' · ' . $person['email']) ?></option><?php endforeach; ?>
                </select></label>
                <button class="btn btn-primary" type="submit">Save assignment</button>
            </form>
        </td>
    </tr><?php endforeach; ?></tbody>
</table></div>
<?= paginator($page, '/admin/operations') ?>
<?php endif; ?>
