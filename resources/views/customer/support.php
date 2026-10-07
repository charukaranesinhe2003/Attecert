<section class="page-hero">
    <div class="container">
        <p class="breadcrumb"><a href="<?= e(url('/')) ?>"><?= e(__('nav.home')) ?></a> / <?= e(__('nav.support')) ?></p>
        <h1><?= e(__('portal.support')) ?></h1>
        <p class="lead"><?= e(__('portal.support_lead')) ?></p>
    </div>
</section>
<section class="section">
    <div class="container split">
        <form class="panel stack" method="post" action="<?= e(url('/support')) ?>">
            <?= csrf_field() ?>
            <div class="support-sender">
                <p class="eyebrow"><?= e(__('portal.sending_as')) ?></p>
                <div class="support-sender-main">
                    <span class="avatar" aria-hidden="true"><?= e(initials($customer['name'])) ?></span>
                    <div>
                        <strong><?= e($customer['name']) ?></strong>
                        <p><?= e($customer['email']) ?></p>
                        <?php if (!empty($customer['phone'])): ?><p><?= e($customer['phone']) ?></p><?php endif; ?>
                    </div>
                </div>
                <p class="profile-id"><?= e(__('portal.customer_id')) ?> <span><?= e(customer_code($customer)) ?></span></p>
            </div>
            <label><?= e(__('fields.subject')) ?><input name="subject" value="<?= e((string) old('subject')) ?>"></label>
            <label><?= e(__('fields.message')) ?><textarea name="message" rows="6" required><?= e((string) old('message')) ?></textarea></label>
            <?php if (error('message')): ?><p class="form-error"><?= e(error('message')) ?></p><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?= e(__('contact.send')) ?></button>
        </form>
        <aside class="panel">
            <h2><?= e(__('portal.included_details')) ?></h2>
            <dl class="support-facts">
                <div><dt><?= e(__('portal.customer_id')) ?></dt><dd><?= e(customer_code($customer)) ?></dd></div>
                <div><dt><?= e(__('fields.name')) ?></dt><dd><?= e($customer['name']) ?></dd></div>
                <div><dt><?= e(__('fields.email')) ?></dt><dd><?= e($customer['email']) ?></dd></div>
                <div><dt><?= e(__('fields.phone')) ?></dt><dd><?= e((string) ($customer['phone'] ?: '—')) ?></dd></div>
            </dl>
            <p><?= e(__('portal.support_note')) ?></p>
        </aside>
    </div>
</section>
