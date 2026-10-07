<div class="topbar">
    <div class="container topbar-inner">
        <p><?= e(content_block('topbar', 'Document attestation and legalization')) ?></p>
        <div class="topbar-links">
            <a href="tel:<?= e(preg_replace('/\s+/', '', (string) setting('phone', ''))) ?>"><?= icon('phone', 'icon icon-sm') ?><span><?= e(setting('phone', '')) ?></span></a>
            <a href="mailto:<?= e(setting('email', '')) ?>"><?= icon('mail', 'icon icon-sm') ?><span><?= e(setting('email', '')) ?></span></a>
        </div>
    </div>
</div>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>" aria-label="<?= e(setting('company_name', 'AtteCert')) ?>">
            <img src="<?= e(asset('brand/logo-lockup.png')) ?>" alt="<?= e(setting('company_name', 'AtteCert')) ?>" width="210" height="74">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-menu-toggle>
            <span class="sr-only"><?= e(__('nav.menu')) ?></span>
            <?= icon('menu') ?>
        </button>
        <nav class="site-nav" id="site-nav" data-nav>
            <a class="<?= ($active ?? '') === 'home' ? 'is-active' : '' ?>" href="<?= e(url('/')) ?>" <?= ($active ?? '') === 'home' ? 'aria-current="page"' : '' ?>><?= e(__('nav.home')) ?></a>
            <a class="<?= ($active ?? '') === 'services' ? 'is-active' : '' ?>" href="<?= e(url('/services')) ?>" <?= ($active ?? '') === 'services' ? 'aria-current="page"' : '' ?>><?= e(__('nav.services')) ?></a>
            <a class="<?= ($active ?? '') === 'countries' ? 'is-active' : '' ?>" href="<?= e(url('/countries')) ?>" <?= ($active ?? '') === 'countries' ? 'aria-current="page"' : '' ?>><?= e(__('nav.countries')) ?></a>
            <a class="<?= ($active ?? '') === 'track' ? 'is-active' : '' ?>" href="<?= e(url('/track')) ?>" <?= ($active ?? '') === 'track' ? 'aria-current="page"' : '' ?>><?= e(__('nav.tracking')) ?></a>
            <a class="<?= ($active ?? '') === 'guides' ? 'is-active' : '' ?>" href="<?= e(url('/guides')) ?>" <?= ($active ?? '') === 'guides' ? 'aria-current="page"' : '' ?>><?= e(__('nav.guides')) ?></a>
            <a class="<?= ($active ?? '') === 'faq' ? 'is-active' : '' ?>" href="<?= e(url('/faq')) ?>" <?= ($active ?? '') === 'faq' ? 'aria-current="page"' : '' ?>><?= e(__('nav.faq')) ?></a>
            <?php $headerCustomer = account_customer(); ?>
            <?php if ($headerCustomer): ?>
                <a class="<?= ($active ?? '') === 'support' ? 'is-active' : '' ?>" href="<?= e(url('/support')) ?>" <?= ($active ?? '') === 'support' ? 'aria-current="page"' : '' ?>><?= e(__('nav.support')) ?></a>
            <?php else: ?>
                <a class="<?= ($active ?? '') === 'contact' ? 'is-active' : '' ?>" href="<?= e(url('/contact')) ?>" <?= ($active ?? '') === 'contact' ? 'aria-current="page"' : '' ?>><?= e(__('nav.contact')) ?></a>
            <?php endif; ?>
            <div class="nav-tools">
                <label class="lang-switch">
                    <span class="sr-only"><?= e(__('nav.language')) ?></span>
                    <?= icon('language', 'icon icon-sm') ?>
                    <select data-lang aria-label="<?= e(__('nav.language')) ?>">
                        <?php foreach (config('app.locales') as $code => $label): ?>
                            <option value="<?= e(url('/language/' . $code)) ?>" <?= locale() === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($headerCustomer): ?>
                    <div class="notify" data-notify data-feed="<?= e(url('/account/notifications')) ?>" data-read="<?= e(url('/portal/notifications')) ?>">
                        <button class="notify-btn" type="button" aria-expanded="false" aria-controls="notify-panel" data-notify-toggle>
                            <span class="sr-only"><?= e(__('portal.notifications')) ?></span>
                            <?= icon('bell') ?>
                            <?php $unread = app(\App\Services\NotificationService::class)->unreadCount((int) app(\App\Services\AuthService::class)->id()); ?>
                            <span class="notify-count" data-notify-count <?= $unread > 0 ? '' : 'hidden' ?>><?= (int) $unread ?></span>
                        </button>
                        <div class="notify-panel" id="notify-panel" hidden data-notify-panel>
                            <div class="notify-head">
                                <strong><?= e(__('portal.notifications')) ?></strong>
                                <a href="<?= e(url('/portal/notifications')) ?>"><?= e(__('portal.view_all')) ?></a>
                            </div>
                            <div data-notify-list><p class="notify-empty"><?= e(__('empty.notifications')) ?></p></div>
                        </div>
                    </div>
                    <a class="text-link account-link <?= ($active ?? '') === 'account' ? 'is-active' : '' ?>" href="<?= e(url('/account')) ?>"><?= e(__('portal.my_account')) ?></a>
                <?php elseif (!empty($_SESSION['user_id'])): ?>
                    <a class="text-link" href="<?= e(url('/admin')) ?>"><?= e(__('nav.account')) ?></a>
                <?php else: ?>
                    <a class="text-link" href="<?= e(url('/login')) ?>"><?= e(__('nav.login')) ?></a>
                <?php endif; ?>
                <?php if (!empty($_SESSION['user_id'])): ?>
                    <form class="logout-form" method="post" action="<?= e(url('/logout')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-ghost btn-logout" type="submit"><?= e(__('nav.logout')) ?></button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-primary btn-quote" href="<?= e(url('/quote')) ?>"><?= e(__('nav.quote')) ?></a>
            </div>
        </nav>
    </div>
</header>
