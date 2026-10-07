<!DOCTYPE html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e(($title ?? 'Admin') . ' | AtteCert') ?></title>
    <link rel="icon" href="<?= e(asset('brand/favicon.png')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/portal.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="portal-body">
<div class="portal-shell">
    <aside class="portal-side" id="portal-nav">
        <a class="portal-brand" href="<?= e(url('/admin')) ?>"><img src="<?= e(asset('brand/logo-mark.png')) ?>" alt="">AtteCert</a>
        <?php
        $items = [
            'dashboard' => ['/admin', 'dashboard', 'admin.dashboard', null],
            'customers' => ['/admin/customers', 'users', 'admin.customers', 'customers.view'],
            'cases' => ['/admin/cases', 'document', 'admin.cases', 'cases.view'],
            'quotes' => ['/admin/quote-requests', 'mail', 'admin.quote_requests', 'quotes.manage'],
            'services' => ['/admin/services', 'certificate', 'admin.services', 'services.manage'],
            'countries' => ['/admin/countries', 'globe', 'admin.countries', 'countries.manage'],
            'document_types' => ['/admin/document-types', 'file', 'admin.document_types', 'content.manage'],
            'quotations' => ['/admin/quotations', 'quote', 'admin.quotations', 'quotations.create'],
            'invoices' => ['/admin/invoices', 'invoice', 'admin.invoices', 'invoices.create'],
            'payments' => ['/admin/payments', 'payment', 'admin.payments', 'payments.view'],
            'documents' => ['/admin/documents', 'file', 'admin.documents', 'documents.download'],
            'mofa' => ['/admin/mofa', 'stamp', 'admin.mofa', 'cases.edit'],
            'embassy' => ['/admin/embassy', 'building', 'admin.embassy', 'cases.edit'],
            'contacts' => ['/admin/contacts', 'mail', 'admin.contacts', 'quotes.manage'],
            'articles' => ['/admin/articles', 'file', 'admin.articles', 'content.manage'],
            'faqs' => ['/admin/faqs', 'alert', 'admin.faqs', 'content.manage'],
            'content' => ['/admin/content', 'edit', 'admin.content', 'content.manage'],
            'translations' => ['/admin/translations', 'language', 'admin.translations', 'translations.manage'],
            'users' => ['/admin/users', 'users', 'admin.users', 'users.manage'],
            'reports' => ['/admin/reports', 'chart', 'admin.reports', 'reports.view'],
            'audit' => ['/admin/audit', 'eye', 'admin.audit', 'audit.view'],
            'emails' => ['/admin/emails', 'send', 'admin.emails', 'quotations.send'],
            'settings' => ['/admin/settings', 'settings', 'admin.settings', 'settings.manage'],
        ];
        $auth = app(\App\Services\AuthService::class);
        foreach ($items as $key => [$href, $icon, $label, $permission]):
            if ($permission && !$auth->can($permission)) { continue; }
        ?>
            <a class="<?= ($active ?? '') === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>"><?= icon($icon, 'icon icon-sm') ?> <?= e(__($label)) ?></a>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit" style="width:100%;margin-top:1rem;color:white;border-color:rgba(255,255,255,.2)"><?= e(__('nav.logout')) ?></button></form>
    </aside>
    <div class="portal-main">
        <div class="portal-top">
            <button class="btn portal-toggle" type="button" data-portal-nav><?= icon('menu') ?></button>
            <h1 style="font-family:var(--font-sans);font-size:1.35rem"><?= e($title ?? '') ?></h1>
            <span><?= e($auth->user()['name'] ?? '') ?></span>
        </div>
        <?php if ($message = flash('success')): ?><p class="toast toast-success" role="status"><?= e($message) ?></p><?php endif; ?>
        <?php if ($message = flash('error')): ?><p class="toast toast-error" role="alert"><?= e($message) ?></p><?php endif; ?>
        <?= $content ?>
    </div>
</div>
<script src="<?= e(asset('js/public.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
