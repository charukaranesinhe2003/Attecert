<p class="page-back"><a class="btn btn-ghost" href="<?= e(url('/portal/applications')) ?>"><?= icon('chevron', 'icon icon-sm') ?> <?= e(__('portal.back_applications')) ?></a></p>
<p><?= e($case['document_title']) ?> · <?= e($case['country_name'] ?? '') ?></p>
<p><strong><?= e($case['number']) ?></strong> <button class="btn btn-ghost" type="button" data-copy-code="<?= e($case['number']) ?>" data-copied-label="<?= e(__('quote.copied')) ?>"><?= e(__('quote.copy_code')) ?></button></p>
<p><?= status_badge($case['status_name'] ?: $case['status_code'], $case['badge_tone']) ?></p>
<?php if ($case['estimated_next']): ?><p><strong><?= e(__('track.next')) ?>:</strong> <?= e($case['estimated_next']) ?></p><?php endif; ?>
<h2><?= e(__('portal.timeline')) ?></h2>
<ol class="timeline"><?php foreach ($case['history'] as $item): ?><li><i></i><div><strong><?= e($item['name']) ?></strong><br><?= e(format_datetime($item['created_at'])) ?><?php if ($item['notes']): ?><p><?= e($item['notes']) ?></p><?php endif; ?></div></li><?php endforeach; ?></ol>
<h2><?= e(__('portal.documents')) ?></h2>
<?php if ($documents === []): ?><p class="empty"><?= e(__('empty.documents')) ?></p><?php else: ?><ul><?php foreach ($documents as $doc): ?><li><?= e($doc['original_name']) ?> <a href="<?= e(url('/portal/documents/' . $doc['id'] . '/download')) ?>"><?= e(__('actions.download')) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
