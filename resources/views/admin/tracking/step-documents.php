<?php $stepDocuments = array_filter($documents, static fn (array $document): bool => (int) ($document['tracking_step_id'] ?? 0) === (int) $step['id']); ?>
<?php if ($stepDocuments !== []): ?>
<ul class="step-documents" aria-label="Documents for this step"><?php foreach ($stepDocuments as $document): ?><li><a href="<?= e(url('/admin/documents/' . $document['id'] . '/download')) ?>"><?= e($document['original_name']) ?></a></li><?php endforeach; ?></ul>
<?php endif; ?>
