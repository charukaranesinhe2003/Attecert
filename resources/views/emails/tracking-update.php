<p><?= e(__('mail.hello', ['name' => $name])) ?></p>
<p>Here is the latest progress update for your document.</p>
<div style="padding:20px;background:#e7f4f1;border-left:4px solid #0f776e;border-radius:8px;margin:20px 0">
<p style="font-size:12px;letter-spacing:1px;text-transform:uppercase;margin-bottom:8px">Case <?= e($number) ?></p>
<h2 style="margin:0;color:#092c4c"><?= e($title) ?></h2>
<?php if ($state !== 'status'): ?><p><?= e(['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed'][$state] ?? $state) ?></p><?php endif; ?>
</div>
<?php if (!empty($documentTitle)): ?><p><strong>Document:</strong> <?= e($documentTitle) ?></p><?php endif; ?>
<?php if (!empty($stepDate)): ?><p><strong>Step date:</strong> <?= e(format_date($stepDate)) ?></p><?php endif; ?>
<?php if (!empty($expected)): ?><p><strong>Expected completion:</strong> <?= e(format_date($expected)) ?></p><?php endif; ?>
<?php if ($message !== ''): ?><p><?= nl2br(e($message)) ?></p><?php endif; ?>
<?php if (!empty($details)): ?>
<table role="presentation" style="width:100%;border-collapse:collapse;margin:20px 0"><?php foreach ($details as $label => $value): ?>
<tr><td style="padding:10px;border-bottom:1px solid #dfe7e6;color:#52667d"><?= e($label) ?></td><td style="padding:10px;border-bottom:1px solid #dfe7e6;font-weight:bold"><?= e((string) $value) ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<?php if (!empty($attachmentNames)): ?><p><strong>Attached document:</strong> <?= e(implode(', ', $attachmentNames)) ?>. Copies are also available in your account.</p><?php endif; ?>
<?php if (!empty($progressHistory)): ?>
<h3 style="color:#092c4c;margin-top:28px">Previous steps &amp; updates</h3>
<table role="presentation" style="width:100%;border-collapse:collapse"><?php foreach ($progressHistory as $event): ?>
<tr><td style="padding:12px;border-bottom:1px solid #dfe7e6;vertical-align:top"><strong><?= e($event['title']) ?></strong><br>
<span style="color:#52667d;font-size:12px"><?= e(format_datetime($event['created_at'])) ?> · <?= e(['pending' => 'Pending', 'current' => 'In progress', 'completed' => 'Completed', 'status' => 'Status updated'][$event['state']] ?? $event['state']) ?><?= $event['event_type'] === 'step_removed' ? ' · Removed from current steps' : '' ?></span>
<?php if ($event['public_description']): ?><p style="margin:8px 0 0"><?= nl2br(e($event['public_description'])) ?></p><?php endif; ?></td></tr>
<?php endforeach; ?></table>
<?php endif; ?>
<p style="margin:24px 0"><a style="background:#0f776e;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none" href="<?= e($link) ?>">View document tracking</a></p>
<p>Reply to this email if you have questions about your case.</p>
