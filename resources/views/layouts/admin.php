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
<body class="portal-body admin-body">
<?php
$auth = app(\App\Services\AuthService::class);
$user = $auth->user() ?? [];
$can = static fn (?string $permission): bool => $permission === null || $auth->can($permission);
$navItems = [
    ['dashboard', '/admin', 'dashboard', 'admin.dashboard', null],
    ['cases', '/admin/cases', 'document', 'admin.cases', 'cases.view'],
    ['quotes', '/admin/quote-requests', 'mail', 'admin.quote_requests', 'quotes.manage'],
    ['documents', '/admin/documents', 'file', 'admin.documents', 'documents.download'],
    ['processing', '/admin/processing', 'stamp', 'admin.processing', 'cases.edit'],
    ['customers', '/admin/customers', 'users', 'admin.customers', 'customers.view'],
    ['contacts', '/admin/contacts', 'mail', 'admin.contacts', 'quotes.manage'],
    ['quotations', '/admin/quotations', 'quote', 'admin.quotations', 'quotations.create'],
    ['invoices', '/admin/invoices', 'invoice', 'admin.invoices', 'invoices.create'],
    ['payments', '/admin/payments', 'payment', 'admin.payments', 'payments.view'],
    ['content', '/admin/content', 'edit', 'admin.content', 'content.manage'],
    ['services', '/admin/services', 'certificate', 'admin.services', 'services.manage'],
    ['countries', '/admin/countries', 'globe', 'admin.countries', 'countries.manage'],
    ['articles', '/admin/articles', 'file', 'admin.articles', 'content.manage'],
    ['faqs', '/admin/faqs', 'alert', 'admin.faqs', 'content.manage'],
    ['document_types', '/admin/document-types', 'file', 'admin.document_types', 'content.manage'],
    ['translations', '/admin/translations', 'language', 'admin.translations', 'translations.manage'],
    ['reports', '/admin/reports', 'chart', 'admin.reports', 'reports.view'],
    ['users', '/admin/users', 'users', 'admin.users', 'users.manage'],
    ['audit', '/admin/audit', 'eye', 'admin.audit', 'audit.view'],
    ['emails', '/admin/emails', 'send', 'admin.emails', 'quotations.send'],
    ['settings', '/admin/settings', 'settings', 'admin.settings', 'settings.manage'],
];
$quickActions = array_values(array_filter([
    $can('cases.create') ? ['/admin/cases/create', __('admin.new_case')] : null,
    $can('customers.edit') ? ['/admin/customers', __('admin.new_customer')] : null,
    $can('quotations.create') ? ['/admin/cases', __('admin.new_quotation_hint')] : null,
    $can('payments.create') ? ['/admin/invoices', __('admin.record_payment')] : null,
    $can('documents.upload') ? ['/admin/documents', __('admin.upload_document')] : null,
    $can('content.manage') ? ['/admin/articles/create', __('admin.new_article')] : null,
]));
?>
<div class="portal-shell admin-shell">
    <aside class="portal-side admin-side" id="portal-nav" aria-label="<?= e(__('admin.dashboard')) ?>">
        <a class="portal-brand" href="<?= e(url('/admin')) ?>">
            <img src="<?= e(asset('brand/logo-mark.png')) ?>" alt="">
            <span>AtteCert</span>
        </a>
        <nav class="admin-nav">
            <?php foreach ($navItems as [$key, $href, $icon, $label, $permission]):
                if (!$can($permission)) {
                    continue;
                }
            ?>
                <a class="<?= ($active ?? '') === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>">
                    <?= icon($icon, 'icon icon-sm') ?>
                    <span><?= e(__($label)) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <form class="admin-logout" method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost admin-logout-btn" type="submit"><?= e(__('nav.logout')) ?></button>
        </form>
    </aside>
    <div class="portal-main admin-main">
        <header class="admin-topbar">
            <button class="btn portal-toggle" type="button" data-portal-nav aria-label="Menu"><?= icon('menu') ?></button>
            <form class="admin-search" action="<?= e(url('/admin/search')) ?>" method="get" data-admin-search role="search">
                <label class="sr-only" for="admin-search-q"><?= e(__('admin.search_everything')) ?></label>
                <input id="admin-search-q" type="search" name="q" value="<?= e((string) ($q ?? '')) ?>" placeholder="<?= e(__('admin.search_everything')) ?>" autocomplete="off" data-admin-search-input>
                <div class="admin-search-results" data-admin-search-results hidden></div>
            </form>
            <?php if ($quickActions !== []): ?>
                <div class="admin-quick" data-admin-quick>
                    <button class="btn btn-primary" type="button" data-admin-quick-toggle aria-expanded="false" aria-haspopup="true">+ <?= e(__('admin.new')) ?></button>
                    <div class="admin-quick-menu" data-admin-quick-menu hidden>
                        <?php foreach ($quickActions as [$href, $label]): ?>
                            <a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="admin-user">
                <span class="admin-user-name"><?= e((string) ($user['name'] ?? '')) ?></span>
            </div>
        </header>
        <?php if (empty($hidePageHeader)): ?>
            <?php if (!empty($pageHeading)): ?>
                <div class="admin-page-header">
                    <div>
                        <h1><?= e($pageHeading) ?></h1>
                        <?php if (!empty($pageDescription)): ?><p><?= e($pageDescription) ?></p><?php endif; ?>
                    </div>
                    <?php if (!empty($pageAction)): ?>
                        <div class="admin-page-actions"><?= $pageAction ?></div>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($title) && ($active ?? '') !== 'dashboard'): ?>
                <div class="admin-page-header">
                    <div><h1><?= e($title) ?></h1></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($message = flash('success')): ?><p class="toast toast-success" role="status"><?= e($message) ?></p><?php endif; ?>
        <?php if ($message = flash('error')): ?><p class="toast toast-error" role="alert"><?= e($message) ?></p><?php endif; ?>
        <div class="admin-content">
            <?= $content ?>
        </div>
    </div>
</div>
<script>
window.AtteCertAdmin = {
    searchUrl: <?= json_encode(url('/admin/search'), JSON_UNESCAPED_SLASHES) ?>,
    csrf: <?= json_encode(csrf_token()) ?>
};
</script>
<script src="<?= e(asset('js/public.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
