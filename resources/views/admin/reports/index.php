<form class="filters" method="get">
<input type="date" name="from" value="<?= e($from) ?>">
<input type="date" name="to" value="<?= e($to) ?>">
<button class="btn btn-primary"><?= e(__('actions.filter')) ?></button>
<a class="btn btn-ghost" href="<?= e(url('/admin/reports/export?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>"><?= e(__('actions.export')) ?></a>
</form>
<div class="admin-grid">
<section class="card-block"><h2>Status</h2><?php foreach ($report['by_status'] as $row): ?><p><?= e($row['name']) ?>: <?= (int) $row['total'] ?></p><?php endforeach; ?></section>
<section class="card-block"><h2>Country</h2><?php foreach ($report['by_country'] as $row): ?><p><?= e($row['name']) ?>: <?= (int) $row['total'] ?></p><?php endforeach; ?></section>
<section class="card-block"><h2>Services</h2><?php foreach ($report['by_service'] as $row): ?><p><?= e($row['name']) ?>: <?= (int) $row['total'] ?></p><?php endforeach; ?></section>
<section class="card-block"><h2>Payments</h2><?php foreach ($report['payments'] as $row): ?><p><?= e($row['method']) ?>: <?= e(money($row['amount'])) ?></p><?php endforeach; ?>
<p>Turnaround days: <?= e((string) $report['turnaround']) ?></p>
<p>New customers: <?= (int) $report['customers'] ?></p>
<p>Quotations: <?= (int) ($report['quotations']['total'] ?? 0) ?> / accepted <?= (int) ($report['quotations']['accepted'] ?? 0) ?></p>
<h3>Outstanding</h3><?php foreach ($report['outstanding'] as $row): ?><p><?= e($row['number']) ?> <?= e(money($row['amount_due'])) ?></p><?php endforeach; ?>
</section>
</div>
