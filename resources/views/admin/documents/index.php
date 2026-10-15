<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<form class="filters" method="get" action="<?= e(url('/admin/documents')) ?>">
    <label>Search<input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="File name or case number"></label>
    <label>Case ID<input type="number" name="case_id" min="0" value="<?= $filters['case_id'] > 0 ? e((string) $filters['case_id']) : '' ?>"></label>
    <label>Category<select name="category"><?php foreach (['all' => 'All categories', 'customer' => 'Customer documents', 'internal' => 'Processing documents', 'completed' => 'Completed documents'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['category'], $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <label>Visibility<select name="visibility"><?php foreach (['all' => 'All visibility', 'private' => 'Private', 'visible' => 'Visible to customer'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['visibility'], $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/documents')) ?>">Clear filters</a>
</form>
<p><?= e((string) $page['total']) ?> matching document<?= $page['total'] === 1 ? '' : 's' ?>. Release completed files after reviewing their content. Making a file private stops future portal downloads.</p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Document</th><th>Case</th><th>Category</th><th>Visibility</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?>
<tr>
    <td data-label="Document"><strong><?= e($row['original_name']) ?></strong><br><?= e((string) $row['description']) ?><br><?= e(format_datetime($row['created_at'])) ?></td>
    <td data-label="Case"><?php if ($canViewCase && $row['case_id']): ?><a href="<?= e(url('/admin/cases/' . $row['case_id'] . '#documents')) ?>"><?= e((string) $row['case_number']) ?></a><?php else: ?><?= e((string) ($row['case_number'] ?: 'Enquiry attachment')) ?><?php endif; ?></td>
    <td data-label="Category"><?= e($row['category']) ?></td>
    <td data-label="Visibility"><?= status_badge((int) $row['customer_visible'] === 1 ? 'Visible to customer' : 'Private', (int) $row['customer_visible'] === 1 ? 'success' : 'warning') ?></td>
    <td data-label="Actions">
        <a class="btn btn-ghost" href="<?= e(url('/admin/documents/' . $row['id'] . '/download')) ?>"><?= e(__('actions.download')) ?></a>
        <?php if ($canRelease && $row['category'] === 'completed' && $row['case_id']): $release = (int) $row['customer_visible'] !== 1; ?>
        <details><summary><?= $release ? 'Review & release' : 'Make private' ?></summary>
            <form class="stack" method="post" action="<?= e(url('/admin/documents/' . $row['id'] . '/visibility')) ?>">
                <?= csrf_field() ?>
                <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
                <input type="hidden" name="customer_visible" value="<?= $release ? '1' : '0' ?>">
                <p><?= $release ? 'The customer will be able to download this file and will receive the usual document notification and email.' : 'The customer will no longer be able to download this file from the portal.' ?></p>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> <?= $release ? 'I reviewed this file and confirm it is ready for the customer.' : 'I confirm this file should be private.' ?></label>
                <button class="btn btn-primary" type="submit"><?= $release ? 'Release to customer' : 'Make private' ?></button>
            </form>
        </details>
        <?php endif; ?>
        <?php if ($canArchive): ?>
        <details><summary><?= e(__('actions.archive')) ?></summary>
            <form class="stack" method="post" action="<?= e(url('/admin/documents/' . $row['id'] . '/archive')) ?>">
                <?= csrf_field() ?>
                <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> Archive this document and remove portal access. The stored file will be retained.</label>
                <button type="submit"><?= e(__('actions.archive')) ?></button>
            </form>
        </details>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= paginator($page, '/admin/documents') ?>
<?php endif; ?>
