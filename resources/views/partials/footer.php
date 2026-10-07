<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a class="footer-brand" href="<?= e(url('/')) ?>">
                <img src="<?= e(asset('brand/logo-mark.png')) ?>" alt="" width="46" height="46">
                <span><?= e(setting('company_name', 'AtteCert')) ?></span>
            </a>
            <p><?= e(content_block('footer_blurb', 'AtteCert helps people in Sri Lanka prepare documents for official use at home and abroad.')) ?></p>
            <ul class="contact-list">
                <li><?= icon('pin', 'icon icon-sm') ?> <?= e(setting('address', '')) ?></li>
                <li><a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('phone', ''))) ?>"><?= icon('phone', 'icon icon-sm') ?> <?= e(setting('phone', '')) ?></a></li>
                <li><a href="mailto:<?= e(setting('email', '')) ?>"><?= icon('mail', 'icon icon-sm') ?> <?= e(setting('email', '')) ?></a></li>
            </ul>
        </div>
        <div>
            <h2><?= e(__('footer.services')) ?></h2>
            <a href="<?= e(url('/services/document-attestation')) ?>"><?= e(__('services.attestation')) ?></a>
            <a href="<?= e(url('/services/official-translation')) ?>"><?= e(__('services.translation')) ?></a>
            <a href="<?= e(url('/services/embassy-legalization')) ?>"><?= e(__('services.embassy')) ?></a>
            <a href="<?= e(url('/services/mofa-appointments')) ?>"><?= e(__('services.mofa')) ?></a>
        </div>
        <div>
            <h2><?= e(__('footer.company')) ?></h2>
            <a href="<?= e(url('/about')) ?>"><?= e(__('nav.about')) ?></a>
            <a href="<?= e(url('/contact')) ?>"><?= e(__('nav.contact')) ?></a>
            <a href="<?= e(url('/faq')) ?>"><?= e(__('nav.faq')) ?></a>
            <a href="<?= e(url('/guides')) ?>"><?= e(__('nav.guides')) ?></a>
        </div>
        <div>
            <h2><?= e(__('footer.resources')) ?></h2>
            <a href="<?= e(url('/countries')) ?>"><?= e(__('nav.countries')) ?></a>
            <a href="<?= e(url('/track')) ?>"><?= e(__('nav.tracking')) ?></a>
            <a href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
            <a href="<?= e(url('/login')) ?>"><?= e(__('nav.login')) ?></a>
        </div>
        <div>
            <h2><?= e(__('track.title')) ?></h2>
            <form class="footer-track" method="post" action="<?= e(url('/track')) ?>">
                <?= csrf_field() ?>
                <label class="sr-only" for="footer-track"><?= e(__('track.number')) ?></label>
                <input id="footer-track" name="number" placeholder="<?= e(__('track.placeholder')) ?>" required>
                <button type="submit" aria-label="<?= e(__('track.button')) ?>"><?= icon('arrow') ?></button>
            </form>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= e(setting('company_name', 'AtteCert')) ?>. <?= e(__('footer.rights')) ?></p>
        <p><?= e(setting('hours', '')) ?></p>
    </div>
</footer>
