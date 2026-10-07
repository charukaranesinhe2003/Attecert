<section class="page-hero"><div class="container"><p class="breadcrumb"><a href="<?= e(url('/services')) ?>"><?= e(__('nav.services')) ?></a> / <?= e($service['name']) ?></p><h1><?= e($service['name']) ?></h1><p class="lead"><?= e($service['summary']) ?></p></div></section>
<section class="section"><div class="container split">
    <article class="prose">
        <p><?= nl2br(e((string) $service['description'])) ?></p>
        <h2><?= e(__('services.process')) ?></h2>
        <p><?= nl2br(e((string) $service['process_text'])) ?></p>
        <h2><?= e(__('services.documents')) ?></h2>
        <p><?= nl2br(e((string) $service['documents_required'])) ?></p>
        <?php if ($service['processing_days']): ?><p><strong><?= e(__('services.time')) ?>:</strong> <?= e($service['processing_days']) ?></p><?php endif; ?>
        <?php if ((int) $service['show_price'] === 1 && $service['pricing_type'] === 'fixed'): ?><p><strong><?= e(__('services.fee')) ?>:</strong> <?= e(money($service['fixed_price'], $service['currency'])) ?></p><?php else: ?><p><?= e(__('services.custom_price')) ?></p><?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
    </article>
    <aside class="panel"><h2><?= e(__('services.related')) ?></h2><div class="stack"><?php foreach (array_slice($countries, 0, 6) as $country): ?><a href="<?= e(url('/countries/' . $country['slug'])) ?>"><?= e($country['name']) ?></a><?php endforeach; ?></div></aside>
</div></section>
