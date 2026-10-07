<?php if ($rows === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?>
<?php foreach ($rows as $row): ?><p><a href="<?= e(url('/admin/cases/' . $row['case_id'])) ?>"><?= e($row['number']) ?></a> <?= e($row['customer_name']) ?> · <?= e($row['status']) ?></p><?php endforeach; ?>
