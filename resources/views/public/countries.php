<section class="page-hero"><div class="container"><p class="breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a> / <?= e(__('nav.countries')) ?></p><h1><?= e(content_block('countries_title')) ?></h1><p class="lead"><?= e(content_block('countries_lead')) ?></p></div></section>
<section class="section"><div class="container country-grid">
<?php foreach ($countries as $country): ?>
    <a class="country-card" href="<?= e(url('/countries/' . $country['slug'])) ?>"><img src="<?= e(asset('images/flags/' . strtolower($country['code']) . '.svg')) ?>" alt="" width="36" height="24"><h3><?= e($country['name']) ?></h3><p><?= e($country['summary']) ?></p></a>
<?php endforeach; ?>
</div></section>
