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
$passwordChangeRequired = $auth->mustChangePassword();
$can = static fn (?string $permission): bool => $permission === null || (!$passwordChangeRequired && $auth->can($permission));
$navItems = [
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
    ['password', '/admin/password', 'settings', 'staff_security.title', null],
];
$navGroups = [
    'operations' => ['cases', 'quotes', 'customers', 'contacts', 'documents', 'processing'],
    'finance' => ['quotations', 'invoices', 'payments', 'reports'],
    'website' => ['content', 'services', 'countries', 'articles', 'faqs', 'document_types', 'translations'],
    'administration' => ['users', 'audit', 'emails', 'settings', 'password'],
];
$quickActions = array_values(array_filter([
    $can('cases.create') && $can('cases.view') ? ['/admin/cases/create', __('admin.new_case')] : null,
    $can('quotations.create') && $can('cases.view') ? ['/admin/cases?status=unquoted', __('admin.new_quotation_hint')] : null,
    $can('payments.create') && $can('invoices.create') ? ['/admin/invoices?status=unpaid', __('admin.record_payment')] : null,
    $can('documents.upload') && $can('cases.view') ? ['/admin/cases', __('admin_overview.upload_to_case')] : null,
    $can('content.manage') ? ['/admin/articles/create', __('admin.new_article')] : null,
]));
?>
<a class="skip-link" href="#admin-main-content"><?= e(__('admin_overview.skip_content')) ?></a>
<div class="portal-shell admin-shell">
    <aside class="portal-side admin-side" id="portal-nav" aria-label="<?= e(__('admin.dashboard')) ?>">
        <a class="portal-brand" href="<?= e(url('/admin')) ?>">
            <img src="<?= e(asset('brand/logo-mark.png')) ?>" alt="">
            <span>AtteCert</span>
        </a>
        <button class="btn admin-nav-close" type="button" data-portal-close><?= e(__('actions.close')) ?></button>
        <nav class="admin-nav">
            <?php if (!$passwordChangeRequired): ?>
                <a class="<?= ($active ?? '') === 'dashboard' ? 'is-active' : '' ?>" href="<?= e(url('/admin')) ?>" <?= ($active ?? '') === 'dashboard' ? 'aria-current="page"' : '' ?>><?= icon('dashboard', 'icon icon-sm') ?><span><?= e(__('admin.dashboard')) ?></span></a>
            <?php endif; ?>
            <?php foreach ($navGroups as $group => $keys):
                $items = array_filter($navItems, static fn (array $item): bool => in_array($item[0], $keys, true) && $can($item[4]));
                if ($items === []) { continue; }
            ?>
            <div class="admin-nav-group">
                <p class="admin-nav-heading"><?= e(__('admin_overview.' . $group)) ?></p>
                <?php foreach ($items as [$key, $href, $icon, $label, $permission]): ?>
                <a class="<?= ($active ?? '') === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>" <?= ($active ?? '') === $key ? 'aria-current="page"' : '' ?>>
                    <?= icon($icon, 'icon icon-sm') ?>
                    <span><?= e(__($label)) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </nav>
        <form class="admin-logout" method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost admin-logout-btn" type="submit"><?= e(__('nav.logout')) ?></button>
        </form>
    </aside>
    <button class="admin-nav-backdrop" type="button" data-portal-backdrop aria-label="<?= e(__('actions.close')) ?>" tabindex="-1" hidden></button>
    <div class="portal-main admin-main">
        <header class="admin-topbar">
            <button class="btn portal-toggle" type="button" data-portal-nav aria-label="Menu" aria-controls="portal-nav" aria-expanded="false"><?= icon('menu') ?></button>
            <?php if (!$passwordChangeRequired): ?>
            <form class="admin-search" action="<?= e(url('/admin/search')) ?>" method="get" data-admin-search role="search" data-search-loading="<?= e(__('admin_overview.search_loading')) ?>" data-search-error="<?= e(__('admin_overview.search_error')) ?>" data-search-empty="<?= e(__('admin.search_empty')) ?>">
                <label class="sr-only" for="admin-search-q"><?= e(__('admin.search_everything')) ?></label>
                <input id="admin-search-q" type="search" name="q" value="<?= e((string) ($q ?? '')) ?>" placeholder="<?= e(__('admin.search_everything')) ?>" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="admin-search-results" data-admin-search-input>
                <div id="admin-search-results" class="admin-search-results" data-admin-search-results role="listbox" aria-label="<?= e(__('admin.search')) ?>" hidden></div>
                <span class="sr-only" data-admin-search-status role="status" aria-live="polite"></span>
            </form>
            <?php endif; ?>
            <?php if ($quickActions !== []): ?>
                <div class="admin-quick" data-admin-quick>
                    <button class="btn btn-primary" type="button" data-admin-quick-toggle aria-expanded="false" aria-haspopup="true"><?= e(__('admin_overview.quick_actions')) ?></button>
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
        <main class="admin-content" id="admin-main-content" tabindex="-1">
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/public.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
</body>
</html>
