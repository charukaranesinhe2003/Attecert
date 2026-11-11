<section class="progress-history" aria-label="Previous steps and updates">
    <h3>Previous steps &amp; updates</h3>
    <?php if ($progressHistory === []): ?><p class="muted">Progress updates will appear here.</p><?php else: ?>
    <ol class="timeline"><?php foreach ($progressHistory as $event): ?><li><i aria-hidden="true"></i><div>
        <strong><?= e($event['title']) ?></strong>
        <p class="muted"><?= e(format_datetime($event['created_at'])) ?> · <?= e(['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed', 'status' => 'Status updated'][$event['state']] ?? $event['state']) ?><?= $event['event_type'] === 'step_removed' ? ' · Removed from current steps' : '' ?><?= $event['event_type'] === 'document_added' ? ' · Document added' : '' ?></p>
        <?php if ($event['step_date']): ?><p>Step date: <?= e(format_date($event['step_date'])) ?></p><?php endif; ?>
        <?php if ($event['public_description']): ?><p><?= nl2br(e($event['public_description'])) ?></p><?php endif; ?>
    </div></li><?php endforeach; ?></ol>
    <?php endif; ?>
</section>
