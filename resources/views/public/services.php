<section class="page-hero"><div class="container"><p class="breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a> / <?= e(__('nav.services')) ?></p><h1><?= e(content_block('services_title')) ?></h1><p class="lead"><?= e(content_block('services_lead')) ?></p></div></section>
<section class="section"><div class="container card-grid">
<?php foreach ($services as $service): ?>
    <a class="service-card" href="<?= e(url('/services/' . $service['slug'])) ?>"><span class="service-icon"><?= icon($service['icon'] ?: 'document') ?></span><h3><?= e($service['name']) ?></h3><p><?= e($service['summary']) ?></p><?php if ((int) $service['show_price'] === 1 && $service['pricing_type'] === 'fixed'): ?><strong><?= e(money($service['fixed_price'], $service['currency'])) ?></strong><?php endif; ?><span class="card-more"><?= e(__('home.learn_more')) ?></span></a>
<?php endforeach; ?>
</div></section>
