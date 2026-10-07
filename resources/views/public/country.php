<section class="page-hero"><div class="container"><p class="breadcrumb"><a href="<?= e(url('/countries')) ?>"><?= e(__('nav.countries')) ?></a> / <?= e($country['name']) ?></p><h1><?= e($country['name']) ?></h1><p class="lead"><?= e($country['summary']) ?></p></div></section>
<section class="section"><div class="container split">
    <article class="prose">
        <h2><?= e(__('countries.requirements')) ?></h2><p><?= nl2br(e((string) $country['requirements'])) ?></p>
        <h2><?= e(__('services.documents')) ?></h2><p><?= nl2br(e((string) $country['documents_required'])) ?></p>
        <h2><?= e(__('services.process')) ?></h2><p><?= nl2br(e((string) $country['process_text'])) ?></p>
        <h2><?= e(__('countries.notes')) ?></h2><p><?= nl2br(e((string) $country['notes'])) ?></p>
        <?php if ($country['processing_info']): ?><p><strong><?= e(__('services.time')) ?>:</strong> <?= e($country['processing_info']) ?></p><?php endif; ?>
        <a class="btn btn-primary" href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
    </article>
    <aside class="panel"><h2><?= e(__('countries.available')) ?></h2><div class="stack"><?php foreach ($country['services'] as $service): ?><a href="<?= e(url('/services/' . $service['slug'])) ?>"><?= e($service['name']) ?></a><?php endforeach; ?></div></aside>
</div></section>
<section class="section faq-section"><div class="container"><h2><?= e(__('nav.faq')) ?></h2><div class="accordion" data-accordion><?php foreach (array_slice($country['faqs'], 0, 4) as $faq): ?><div class="accordion-item"><h3><button type="button" aria-expanded="false" data-accordion-button><?= e($faq['question']) ?> <?= icon('chevron-down', 'icon icon-sm') ?></button></h3><div class="accordion-panel" hidden><p><?= e($faq['answer']) ?></p></div></div><?php endforeach; ?></div></div></section>
