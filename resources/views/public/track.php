<?php
$steps = $result['steps'] ?? [];
$expected = $result && $result['tracking_expected_on'] ? format_date($result['tracking_expected_on']) : 'To be confirmed';
if ($result && in_array($result['status_code'], ['ready_for_collection', 'ready_for_delivery'], true)) { $expected = 'Ready now'; }
if ($result && $result['status_code'] === 'completed') { $expected = 'Completed'; }
$notice = $result ? trim((string) $result['tracking_message']) : '';
if ($notice === '' && $result && $result['status_code'] === 'ready_for_collection') { $notice = 'Your completed document is ready. Please contact us to arrange collection.'; }
if ($notice === '' && $result && $result['status_code'] === 'ready_for_delivery') { $notice = 'Your completed document is ready. Please contact us to arrange delivery.'; }
if ($notice === '' && $result) { $notice = (string) $result['estimated_next']; }
?>
<section class="tracking-center">
    <div class="tracking-container">
        <header class="tracking-heading">
            <p class="eyebrow">Tracking</p>
            <h1>Document Status Tracking Center</h1>
            <p>Enter the case number from your quotation or confirmation to see where your document is right now.</p>
        </header>
        <form class="tracking-search" method="post" action="<?= e(url('/track')) ?>">
            <?= csrf_field() ?>
            <label for="tracking-number"><?= e(__('track.number')) ?></label>
            <div class="tracking-search-row">
                <input id="tracking-number" name="number" value="<?= e($number) ?>" placeholder="KASS/Q/2026111101" required minlength="6" maxlength="24" autocomplete="off" aria-describedby="tracking-number-help">
                <button class="btn btn-primary" type="submit"><?= e(__('track.button')) ?></button>
            </div>
            <p id="tracking-number-help">Your case number is in your confirmation or quotation email.</p>
            <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
        </form>
        <?php if ($searched && $result === null): ?><p class="tracking-empty" role="status"><?= e(__('track.none')) ?></p><?php endif; ?>
        <?php if ($result): ?>
        <article class="tracking-result" aria-label="Tracking result for <?= e($result['number']) ?>">
            <header class="tracking-result-heading">
                <div><h2><?= e($result['number']) ?></h2><p><?= e($result['document_title']) ?></p></div>
                <?= status_badge($result['status_name'] ?: $result['status_code'], $result['badge_tone']) ?>
            </header>
            <dl class="tracking-meta">
                <div><dt>Destination</dt><dd><?= e($result['country_name'] ?: 'To be confirmed') ?></dd></div>
                <div><dt>Started</dt><dd><?= e(format_date($result['created_at'])) ?></dd></div>
                <div><dt>Updated</dt><dd><?= e(format_date($result['updated_at'])) ?></dd></div>
                <div><dt>Expected</dt><dd><?= e($expected) ?></dd></div>
            </dl>
            <?php if ($notice !== ''): ?><p class="tracking-notice"><?= nl2br(e($notice)) ?></p><?php endif; ?>
            <ol class="tracking-timeline" aria-label="Document progress">
                <?php foreach ($steps as $index => $step): ?>
                <li class="tracking-step is-<?= e($step['state']) ?>"<?= $step['state'] === 'current' ? ' aria-current="step"' : '' ?>>
                    <span class="tracking-step-marker" aria-hidden="true"><?= $step['state'] === 'completed' ? '&#10003;' : ($step['state'] === 'current' ? '&#8226;' : (int) $index + 1) ?></span>
                    <div class="tracking-step-body">
                        <h3><?= e($step['title']) ?></h3>
                        <p class="tracking-step-date"><?= e($step['step_date'] ? format_date($step['step_date']) : 'Date to be confirmed') ?><?php if ($step['state'] === 'current'): ?> <span>· In progress</span><?php elseif ($step['state'] === 'pending'): ?> <span>· Pending</span><?php else: ?> <span class="sr-only"> · Completed</span><?php endif; ?></p>
                        <?php if ($step['public_description']): ?><p class="tracking-step-description"><?= nl2br(e($step['public_description'])) ?></p><?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <?= \App\Core\View::render('partials/progress-history', ['progressHistory' => $result['progress_history']]) ?>
            <a class="btn btn-ghost tracking-support" href="<?= e(url('/contact')) ?>">Contact support</a>
        </article>
        <?php endif; ?>
    </div>
</section>
