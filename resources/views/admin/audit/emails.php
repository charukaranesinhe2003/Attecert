<?php if (!$smtp): ?><p class="form-error" role="status"><?= e(__('admin.smtp_missing')) ?></p><?php endif; ?>
<?php if (!$queueAvailable): ?><p class="form-error" role="status"><?= e(__('admin.email_migration_required')) ?></p><?php endif; ?>
<p class="muted"><?= e(__('admin.email_worker_help')) ?></p>
<form class="filters" method="get">
    <label><?= e(__('actions.search')) ?><input name="q" value="<?= e($q) ?>" maxlength="190" placeholder="<?= e(__('admin.email_search')) ?>"></label>
    <label><?= e(__('fields.status')) ?><select name="status"><option value=""><?= e(__('admin.email_all_statuses')) ?></option><?php foreach ($statuses as $option): ?><option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(__('admin.email_status_' . $option)) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.search')) ?></button>
</form>
<?php if ($page['rows'] === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('fields.email')) ?></th><th><?= e(__('fields.subject')) ?></th><th><?= e(__('fields.status')) ?></th><th><?= e(__('admin.email_attempts')) ?></th><th><?= e(__('admin.email_delivery_detail')) ?></th><th><?= e(__('admin.email_recovery')) ?></th></tr></thead><tbody>
<?php foreach ($page['rows'] as $row): ?>
    <tr>
        <td data-label="<?= e(__('fields.email')) ?>"><?= e($row['recipient']) ?><br><small><?= e(format_datetime($row['created_at'])) ?></small></td>
        <td data-label="<?= e(__('fields.subject')) ?>"><?= e($row['subject']) ?></td>
        <td data-label="<?= e(__('fields.status')) ?>"><?= status_badge(__('admin.email_status_' . $row['status']), match ($row['status']) { 'sent' => 'success', 'failed', 'uncertain' => 'danger', 'logged' => 'warning', default => 'neutral' }) ?><?php if ($row['sent_at']): ?><br><small><?= e(format_datetime($row['sent_at'])) ?></small><?php endif; ?></td>
        <td data-label="<?= e(__('admin.email_attempts')) ?>"><?= $row['attempts'] === null ? '—' : e($row['attempts'] . ' / ' . $row['max_attempts']) ?></td>
        <td data-label="<?= e(__('admin.email_delivery_detail')) ?>"><?= e((string) $row['error_message']) ?><?php if ($row['status'] === 'queued' && $row['available_at']): ?><br><small><?= e(__('admin.email_next_attempt')) ?> <?= e(format_datetime($row['available_at'])) ?></small><?php endif; ?></td>
        <td data-label="<?= e(__('admin.email_recovery')) ?>">
            <?php if ($row['can_retry'] && in_array($row['status'], ['failed', 'logged', 'uncertain'], true)): ?>
            <form class="stack" method="post" action="<?= e(url('/admin/emails/' . $row['id'] . '/resend')) ?>">
                <?= csrf_field() ?>
                <?php if ($row['status'] === 'uncertain'): ?><label><input type="checkbox" name="confirmed" value="1" required> <?= e(__('admin.email_uncertain_confirm')) ?></label><?php endif; ?>
                <button class="btn btn-ghost" type="submit"><?= e(__('admin.email_queue_retry')) ?></button>
            </form>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<?= paginator($page, '/admin/emails') ?>
