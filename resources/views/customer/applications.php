<p class="page-back"><a class="btn btn-ghost" href="<?= e(url('/account')) ?>"><?= icon('chevron', 'icon icon-sm') ?> <?= e(__('portal.back_account')) ?></a></p>
<?php if ($cases === []): ?>
    <p class="empty"><?= e(__('empty.cases')) ?></p>
<?php else: ?>
    <div class="app-list">
        <?php foreach ($cases as $case): ?>
            <article class="app-card">
                <div>
                    <p class="eyebrow"><?= e(__('portal.application_id')) ?></p>
                    <h2><?= e($case['number']) ?></h2>
                    <p><?= e($case['document_title']) ?><?php if (!empty($case['country_name'])): ?> · <?= e($case['country_name']) ?><?php endif; ?></p>
                    <p><?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?></p>
                </div>
                <div class="app-actions">
                    <button class="btn btn-ghost" type="button" data-copy-code="<?= e($case['number']) ?>" data-copied-label="<?= e(__('quote.copied')) ?>"><?= e(__('quote.copy_code')) ?></button>
                    <a class="btn btn-ghost" href="<?= e(url('/track?number=' . rawurlencode($case['number']))) ?>"><?= e(__('track.button')) ?></a>
                    <a class="btn btn-primary" href="<?= e(url('/portal/applications/' . $case['id'])) ?>"><?= e(__('actions.open')) ?></a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
