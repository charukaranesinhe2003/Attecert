<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(($title ?? __('portal.dashboard')) . ' | ' . setting('company_name', 'AtteCert')) ?></title>
    <link rel="icon" href="<?= e(asset('brand/favicon.png')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Newsreader:opsz,wght@6..72,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
</head>
<body class="portal-body">
<div class="portal-shell">
    <aside class="portal-side" id="portal-nav">
        <a class="portal-brand" href="<?= e(url('/portal')) ?>"><img src="<?= e(asset('brand/logo-mark.png')) ?>" alt=""><?= e(setting('company_name', 'AtteCert')) ?></a>
        <?php
        $links = [
            'dashboard' => ['/portal', 'dashboard', 'portal.dashboard'],
            'applications' => ['/portal/applications', 'document', 'portal.applications'],
            'tracking' => ['/portal/tracking', 'search', 'portal.tracking'],
            'quotations' => ['/portal/quotations', 'quote', 'portal.quotations'],
            'invoices' => ['/portal/invoices', 'invoice', 'portal.invoices'],
            'payments' => ['/portal/payments', 'payment', 'portal.payments'],
            'documents' => ['/portal/documents', 'file', 'portal.documents'],
            'notifications' => ['/portal/notifications', 'bell', 'portal.notifications'],
            'profile' => ['/portal/profile', 'user', 'portal.profile'],
            'support' => ['/portal/support', 'support', 'portal.support'],
        ];
        foreach ($links as $key => [$href, $icon, $label]): ?>
            <a class="<?= ($active ?? '') === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"><?= icon($icon, 'icon icon-sm') ?> <?= e(__($label)) ?></a>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit" style="width:100%;margin-top:1rem;color:white;border-color:rgba(255,255,255,.2)"><?= e(__('nav.logout')) ?></button></form>
    </aside>
    <div class="portal-main">
        <div class="portal-top">
            <button class="btn portal-toggle" type="button" data-portal-nav><?= icon('menu') ?></button>
            <h1 style="font-family:var(--font-sans);font-size:1.4rem"><?= e($title ?? '') ?></h1>
            <a href="<?= e(url('/')) ?>"><?= e(__('nav.website')) ?></a>
        </div>
        <?php if ($message = flash('success')): ?><p class="toast toast-success" role="status"><?= e($message) ?></p><?php endif; ?>
        <?php if ($message = flash('error')): ?><p class="toast toast-error" role="alert"><?= e($message) ?></p><?php endif; ?>
        <?= $content ?>
    </div>
</div>
<script src="<?= e(asset('js/public.js')) ?>" defer></script>
<script>
document.querySelector('[data-portal-nav]')?.addEventListener('click', () => document.getElementById('portal-nav').classList.toggle('is-open'));
</script>
</body>
</html>
