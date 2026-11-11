<?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
<form class="card-block filters" method="get" action="<?= e(url('/admin/tracking')) ?>">
    <label>Select a case<select name="case_id" required><option value="">Choose a case</option><?php foreach ($caseOptions as $option): ?><option value="<?= (int) $option['id'] ?>"<?= selected($selectedCase['id'] ?? '', $option['id']) ?>><?= e($option['number'] . ' · ' . $option['customer_name'] . ' · ' . $option['document_title']) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary" type="submit">Manage tracking</button>
</form>
<?php if ($selectedCase === null): ?>
<p class="empty"><?= $caseOptions === [] ? 'No cases are assigned to you yet.' : 'Select a case to manage its tracking steps, status, and customer documents.' ?></p>
<?php else: $caseId = (int) $selectedCase['id']; ?>
<section class="card-block">
    <h2><?= e($selectedCase['number']) ?></h2>
    <p><?= e($selectedCase['document_title']) ?> · <?= e($selectedCase['customer_name']) ?> · <?= status_badge($selectedCase['status_name'] ?: $selectedCase['status_code'], $selectedCase['badge_tone']) ?></p>
    <div class="actions">
        <a class="btn btn-ghost" target="_blank" rel="noopener" href="<?= e(url('/track?number=' . rawurlencode($selectedCase['number']))) ?>">View public tracking</a>
        <a class="btn btn-ghost" href="<?= e(url('/admin/cases/' . $caseId)) ?>">Open case</a>
    </div>
</section>
<div class="admin-grid">
    <section class="card-block">
        <h2>Public tracking summary</h2>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking')) ?>">
            <?= csrf_field() ?>
            <label>Expected completion date<input type="date" name="tracking_expected_on" value="<?= e((string) $selectedCase['tracking_expected_on']) ?>"></label>
            <label>Public notice<textarea name="tracking_message" rows="3" maxlength="600"><?= e((string) $selectedCase['tracking_message']) ?></textarea></label>
            <p class="muted">Shown above the timeline. For example, “Your document is ready for collection.”</p>
            <button class="btn btn-primary" type="submit">Save tracking summary</button>
        </form>
    </section>
    <section class="card-block">
        <h2>Update case status</h2>
        <?= \App\Core\View::render('admin/tracking/status-form', ['caseId' => $caseId, 'statusCase' => $selectedCase, 'statuses' => $statuses]) ?>
    </section>
</div>
<section class="card-block">
    <h2>Tracking steps</h2>
    <p>Steps appear in display order. Only one step can be in progress at a time. A document is optional on every step, including completed steps. Previous steps and status updates remain visible in tracking history.</p>
    <?php if ($steps === []): ?><p class="empty">No custom tracking steps yet.</p><?php endif; ?>
    <?php foreach ($steps as $step): ?>
    <article class="process-card">
        <h3><?= (int) $step['sort_order'] ?>. <?= e($step['title']) ?></h3>
        <p><?= status_badge(['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed'][$step['state']], $step['state'] === 'completed' ? 'success' : 'neutral') ?> · <?= e($step['step_date'] ? format_date($step['step_date']) : 'Date to be confirmed') ?></p>
        <?php if ($step['public_description']): ?><p><?= nl2br(e($step['public_description'])) ?></p><?php endif; ?>
        <?= \App\Core\View::render('admin/tracking/step-documents', ['step' => $step, 'documents' => $documents]) ?>
        <details><summary>Edit step / add optional document: <?= e($step['title']) ?></summary>
            <?= \App\Core\View::render('admin/tracking/step-form', ['caseId' => $caseId, 'step' => $step]) ?>
        </details>
        <details><summary>Delete step: <?= e($step['title']) ?></summary>
            <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/steps/' . $step['id'] . '/delete')) ?>">
                <?= csrf_field() ?>
                <label class="consent"><input type="checkbox" name="confirmed" value="1" required> Remove from current steps and retain previous updates in history</label>
                <button class="btn btn-ghost" type="submit">Delete step</button>
            </form>
        </details>
    </article>
    <?php endforeach; ?>
    <h3>Add a step</h3>
    <?= \App\Core\View::render('admin/tracking/step-form', ['caseId' => $caseId, 'step' => [], 'nextOrder' => min(1000, $steps === [] ? 1 : max(array_column($steps, 'sort_order')) + 1)]) ?>
</section>
<section class="card-block"><?= \App\Core\View::render('partials/progress-history', ['progressHistory' => $selectedCase['progress_history']]) ?></section>
<div class="admin-grid">
    <section class="card-block">
        <h2>Upload &amp; email a document</h2>
        <form class="stack" method="post" enctype="multipart/form-data" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/documents')) ?>">
            <?= csrf_field() ?>
            <label>Document<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
            <label>Description<input name="description" maxlength="255"></label>
            <p class="muted">PDF or image, up to <?= (int) setting('upload_max_mb', 10) ?> MB. The original file is attached to the email and added to the customer's documents.</p>
            <button class="btn btn-primary" type="submit">Upload &amp; email document</button>
        </form>
    </section>
    <section class="card-block">
        <h2>Email an existing document</h2>
        <?php if ($documents === []): ?><p class="empty">No customer documents are available for this case.</p><?php else: ?>
        <form class="stack" method="post" action="<?= e(url('/admin/cases/' . $caseId . '/tracking/documents/send')) ?>">
            <?= csrf_field() ?>
            <label>Document<select name="document_id" required><?php foreach ($documents as $doc): ?><option value="<?= (int) $doc['id'] ?>"><?= e($doc['original_name']) ?> · <?= (int) $doc['customer_visible'] === 1 ? 'In customer account' : 'Private' ?></option><?php endforeach; ?></select></label>
            <p class="muted">Sending makes this document available in the customer's account.</p>
            <button class="btn btn-primary" type="submit">Email document</button>
        </form>
        <?php endif; ?>
    </section>
</div>
<?php endif; ?>
