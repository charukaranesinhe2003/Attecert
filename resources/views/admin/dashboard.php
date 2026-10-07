<form class="filters" method="get"><input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button></form>
<div class="metric-grid">
    <article class="metric"><span><?= e(__('admin.new_cases')) ?></span><strong><?= (int) $metrics['new_cases'] ?></strong></article>
    <article class="metric"><span><?= e(__('admin.active_cases')) ?></span><strong><?= (int) $metrics['active'] ?></strong></article>
    <article class="metric"><span><?= e(__('admin.completed')) ?></span><strong><?= (int) $metrics['completed'] ?></strong></article>
    <article class="metric"><span><?= e(__('admin.revenue')) ?></span><strong><?= e(money($metrics['revenue'])) ?></strong></article>
    <article class="metric"><span><?= e(__('admin.outstanding')) ?></span><strong><?= e(money($metrics['outstanding'])) ?></strong></article>
    <article class="metric"><span><?= e(__('admin.quote_requests')) ?></span><strong><?= (int) $metrics['quote_requests'] ?></strong></article>
    <article class="metric"><span><?= e(__('status.awaiting_documents')) ?></span><strong><?= (int) ($metrics['by_status']['awaiting_documents'] ?? 0) ?></strong></article>
    <article class="metric"><span><?= e(__('status.mofa_processing')) ?></span><strong><?= (int) ($metrics['by_status']['mofa_processing'] ?? 0) ?></strong></article>
</div>
<div class="admin-grid" style="margin-top:1rem">
    <section class="card-block"><h2><?= e(__('admin.recent_cases')) ?></h2><?php foreach ($metrics['recent_cases'] as $case): ?><p><a href="<?= e(url('/admin/cases/' . $case['id'])) ?>"><?= e($case['number']) ?></a> <?= e($case['customer_name']) ?> · <?= e($case['status_name']) ?></p><?php endforeach; ?></section>
    <section class="card-block"><h2><?= e(__('admin.quote_requests')) ?></h2><?php foreach ($metrics['recent_quotes'] as $quote): ?><p><a href="<?= e(url('/admin/quote-requests/' . $quote['id'])) ?>"><?= e($quote['name']) ?></a> · <?= e($quote['status']) ?></p><?php endforeach; ?></section>
</div>
