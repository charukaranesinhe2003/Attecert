<?php $restoreStatus = (string) old('status_form', '') === (string) $caseId; ?>
<form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/status')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="status_form" value="<?= $caseId ?>">
    <?php if (($returnTo ?? '') === 'case'): ?><input type="hidden" name="return_to" value="case"><?php endif; ?>
    <label>Status<input name="status_label" list="case-status-options" value="<?= e((string) ($restoreStatus ? old('status_label', '') : ($statusCase['status_name'] ?: $statusCase['status_code']))) ?>" required maxlength="120" placeholder="Choose a status or type your own"></label>
    <datalist id="case-status-options"><?php foreach ($statuses as $status): ?><option value="<?= e($status['name'] ?: $status['code']) ?>"></option><?php endforeach; ?></datalist>
    <p class="muted">Choose a suggested status or enter any custom status for this case.</p>
    <label>Message to customer<textarea name="notes" rows="2" maxlength="2000"><?= e((string) ($restoreStatus ? old('notes', '') : '')) ?></textarea></label>
    <p class="muted">Changing the status queues a short SMS and a formatted email. The message is emailed privately; the status is public.</p>
    <details><summary>Reopen or correct an earlier status</summary><label class="consent"><input type="checkbox" name="confirm_reversal" value="1"> Confirm this correction (include the reason in the message)</label></details>
    <button class="btn btn-primary" type="submit">Save status &amp; notify customer</button>
</form>
