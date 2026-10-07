<div class="metric-grid">
    <article class="metric"><span><?= e(__('portal.applications')) ?></span><strong><?= count($cases) ?></strong></article>
    <article class="metric"><span><?= e(__('portal.quotations')) ?></span><strong><?= count($quotations) ?></strong></article>
    <article class="metric"><span><?= e(__('portal.invoices')) ?></span><strong><?= count($invoices) ?></strong></article>
    <article class="metric"><span><?= e(__('portal.documents')) ?></span><strong><?= (int) $documents ?></strong></article>
</div>
<div class="dash-grid" style="margin-top:1rem">
    <section class="card-block"><h2><?= e(__('portal.applications')) ?></h2><?php if ($cases === []): ?><p class="empty"><?= e(__('empty.cases')) ?></p><?php else: ?><ul><?php foreach ($cases as $case): ?><li><a href="<?= e(url('/portal/applications/' . $case['id'])) ?>"><?= e($case['number']) ?></a> · <?= e($case['status_name']) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
    <section class="card-block"><h2><?= e(__('portal.notifications')) ?></h2><p><?= e(__('portal.unread', ['count' => (string) $unread])) ?></p><a href="<?= e(url('/portal/notifications')) ?>"><?= e(__('portal.view_all')) ?></a></section>
</div>
