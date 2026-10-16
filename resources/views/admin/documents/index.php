<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<form class="filters" method="get" action="<?= e(url('/admin/documents')) ?>">
    <?php if (locale() !== 'en'): ?><input type="hidden" name="lang" value="<?= e(locale()) ?>"><?php endif; ?>
    <label><?= e(__('admin_polish.search')) ?><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(__('admin_polish.document_search')) ?>"></label>
    <label><?= e(__('admin_polish.case_id')) ?><input type="number" name="case_id" min="0" value="<?= $filters['case_id'] > 0 ? e((string) $filters['case_id']) : '' ?>"></label>
    <label><?= e(__('admin_polish.category')) ?><select name="category"><?php foreach (['all' => 'all_categories', 'customer' => 'category_customer', 'internal' => 'category_internal', 'completed' => 'category_completed'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['category'], $key) ?>><?= e(__('admin_polish.' . $label)) ?></option><?php endforeach; ?></select></label>
    <label><?= e(__('admin_polish.visibility')) ?><select name="visibility"><?php foreach (['all' => 'all_visibility', 'private' => 'private', 'visible' => 'visible'] as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['visibility'], $key) ?>><?= e(__('admin_polish.' . $label)) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url('/admin/documents')) ?>"><?= e(__('admin_polish.clear_filters')) ?></a>
</form>
<p><?= e(__('admin_polish.document_count', ['count' => (int) $page['total']])) ?> <?= e(__('admin_polish.document_help')) ?></p>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table><caption class="sr-only"><?= e(__('admin.documents')) ?></caption><thead><tr><th scope="col"><?= e(__('admin_polish.document')) ?></th><th scope="col"><?= e(__('admin_polish.case')) ?></th><th scope="col"><?= e(__('admin_polish.category')) ?></th><th scope="col"><?= e(__('admin_polish.visibility')) ?></th><th scope="col"><?= e(__('admin_polish.actions')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?>
<tr>
    <td data-label="<?= e(__('admin_polish.document')) ?>"><strong><?= e($row['original_name']) ?></strong><br><?= e((string) $row['description']) ?><br><?= e(format_datetime($row['created_at'])) ?></td>
    <td data-label="<?= e(__('admin_polish.case')) ?>"><?php if ($canViewCase && $row['case_id']): ?><a href="<?= e(url('/admin/cases/' . $row['case_id'] . '#documents')) ?>"><?= e((string) $row['case_number']) ?></a><?php else: ?><?= e((string) ($row['case_number'] ?: __('admin_polish.enquiry_attachment'))) ?><?php endif; ?></td>
    <td data-label="<?= e(__('admin_polish.category')) ?>"><?= e(__('admin_polish.category_' . $row['category'])) ?></td>
    <td data-label="<?= e(__('admin_polish.visibility')) ?>"><?= status_badge(__( (int) $row['customer_visible'] === 1 ? 'admin_polish.visible' : 'admin_polish.private'), (int) $row['customer_visible'] === 1 ? 'success' : 'warning') ?></td>
    <td data-label="<?= e(__('admin_polish.actions')) ?>">
        <a class="btn btn-ghost" href="<?= e(url('/admin/documents/' . $row['id'] . '/download')) ?>"><?= e(__('actions.download')) ?><span class="sr-only"> <?= e($row['original_name']) ?></span></a>
        <?php if ($canRelease && $row['category'] === 'completed' && $row['case_id']): $release = (int) $row['customer_visible'] !== 1; ?>
        <details><summary><?= e(__( $release ? 'admin_polish.review_release' : 'admin_polish.make_private')) ?><span class="sr-only"> <?= e($row['original_name']) ?></span></summary>
            <form class="stack" method="post" action="<?= e(url('/admin/documents/' . $row['id'] . '/visibility')) ?>">
                <?= csrf_field() ?>
                <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
                <input type="hidden" name="customer_visible" value="<?= $release ? '1' : '0' ?>">
                <p><?= e(__( $release ? 'admin_polish.release_help' : 'admin_polish.private_help')) ?></p>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> <?= e(__( $release ? 'admin_polish.release_confirm' : 'admin_polish.private_confirm')) ?><span class="sr-only"> <?= e($row['original_name']) ?></span></label>
                <button class="btn btn-primary" type="submit"><?= e(__( $release ? 'admin_polish.release' : 'admin_polish.make_private')) ?></button>
            </form>
        </details>
        <?php endif; ?>
        <?php if ($canArchive): ?>
        <details><summary><?= e(__('actions.archive')) ?><span class="sr-only"> <?= e($row['original_name']) ?></span></summary>
            <form class="stack" method="post" action="<?= e(url('/admin/documents/' . $row['id'] . '/archive')) ?>">
                <?= csrf_field() ?>
                <?php foreach ($filters as $key => $value): ?><input type="hidden" name="<?= e($key) ?>" value="<?= e((string) $value) ?>"><?php endforeach; ?>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> <?= e(__('admin_polish.archive_confirm')) ?><span class="sr-only"> <?= e($row['original_name']) ?></span></label>
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
