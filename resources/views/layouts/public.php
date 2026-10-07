<?php
$locale = locale();
$pageTitle = trim(($title ?? setting('company_name', 'AtteCert')) . ' | ' . setting('company_name', 'AtteCert'));
if (($title ?? '') === '' || ($title ?? '') === setting('company_name', 'AtteCert')) {
    $pageTitle = setting('company_name', 'AtteCert') . ' | ' . __('meta.tagline');
}
$description = $description ?? content_block('hero_lead');
$canonical = absolute_url(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$robots = $robots ?? 'index,follow';
?>
<!DOCTYPE html>
<html lang="<?= e($locale === 'si' ? 'si' : ($locale === 'ta' ? 'ta' : 'en')) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php foreach (array_keys(config('app.locales')) as $code): ?>
        <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(rtrim((string) config('app.url'), '/') . (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') . ($code === 'en' ? '' : '?lang=' . $code)) ?>">
    <?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= e($canonical) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e(absolute_url('/assets/brand/logo-mark.png')) ?>">
    <link rel="icon" href="<?= e(asset('brand/favicon.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('brand/apple-touch.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Newsreader:opsz,wght@6..72,500;6..72,600;6..72,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/variables.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/public.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/account.css')) ?>">
    <script type="application/ld+json">
        <?= json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => setting('company_name', 'AtteCert'),
            'url' => config('app.url'),
            'email' => setting('email', ''),
            'telephone' => setting('phone', ''),
            'address' => setting('address', ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
    </script>
</head>
<body>
<a class="skip-link" href="#main"><?= e(__('a11y.skip')) ?></a>
<?php require base_path('resources/views/partials/header.php'); ?>
<main id="main">
<?php if (!empty($accountFrame)): ?>
    <section class="page-hero account-sub">
        <div class="container">
            <p class="breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a> / <a href="<?= e(url('/account')) ?>"><?= e(__('portal.my_account')) ?></a></p>
            <h1><?= e($title ?? '') ?></h1>
        </div>
    </section>
    <section class="section"><div class="container account-sub-body"><?= $content ?></div></section>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>
</main>
<?php require base_path('resources/views/partials/footer.php'); ?>
<?php $wa = preg_replace('/\D+/', '', (string) setting('whatsapp', '')); ?>
<?php if ($wa): ?>
    <a class="whatsapp-float" href="https://wa.me/<?= e($wa) ?>?text=<?= rawurlencode(__('whatsapp.prefill')) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e(__('whatsapp.label')) ?>"><?= icon('whatsapp') ?></a>
<?php endif; ?>
<?php $trackPopup = $_SESSION['_track_popup'] ?? null; unset($_SESSION['_track_popup']); ?>
<?php if (is_string($trackPopup) && $trackPopup !== ''): ?>
<dialog class="profile-dialog track-dialog" data-track-dialog aria-labelledby="track-dialog-title">
    <h2 id="track-dialog-title"><?= e(__('quote.track_ready')) ?></h2>
    <p><?= e(__('quote.track_ready_lead')) ?></p>
    <p class="track-code" id="track-code-value"><?= e($trackPopup) ?></p>
    <div class="dialog-actions">
        <button class="btn btn-primary" type="button" data-copy-track data-copy-label="<?= e(__('quote.copy_code')) ?>" data-copied-label="<?= e(__('quote.copied')) ?>"><?= e(__('quote.copy_code')) ?></button>
        <a class="btn btn-ghost" href="<?= e(url('/track?number=' . rawurlencode($trackPopup))) ?>"><?= e(__('track.button')) ?></a>
        <button class="btn btn-ghost" type="button" data-track-close><?= e(__('actions.close')) ?></button>
    </div>
</dialog>
<?php endif; ?>
<div class="toast-stack" id="toasts" aria-live="polite">
    <?php if ($message = flash('success')): ?><div class="toast toast-success" role="status"><?= e($message) ?></div><?php endif; ?>
    <?php if ($message = flash('error')): ?><div class="toast toast-error" role="alert"><?= e($message) ?></div><?php endif; ?>
</div>
<script src="<?= e(asset('js/public.js')) ?>" defer></script>
</body>
</html>
