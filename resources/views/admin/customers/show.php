<?php
$auth = app(\App\Services\AuthService::class);
$hasLinkedLogin = !empty($customer['user_id']);
$effectiveActive = (int) $customer['is_active'] === 1 && (!$hasLinkedLogin || (int) $customer['login_active'] === 1);
$accessMismatch = $hasLinkedLogin && (int) $customer['is_active'] !== (int) $customer['login_active'];
?>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<section class="card-block">
    <h2>Customer details</h2>
    <p><?= e($customer['email']) ?> · <?= e(customer_code($customer)) ?></p>
    <p><?= status_badge($effectiveActive ? 'Active' : 'Suspended', $effectiveActive ? 'success' : 'warning') ?></p>
    <?php if ($accessMismatch): ?><p class="form-error">Customer and linked login access currently differ. Enabling Account active also enables the linked login. Leave it unchecked to save with login access disabled.</p><?php endif; ?>
    <?php if ($canEdit): ?>
    <form class="stack" method="post" action="<?= e(url('/admin/customers/' . $customer['id'])) ?>" data-confirm="Save customer details and account access?">
        <?= csrf_field() ?>
        <div class="form-grid">
            <label><?= e(__('fields.name')) ?><input name="name" value="<?= e((string) old('name', $customer['name'])) ?>" required maxlength="160"></label>
            <label><?= e(__('fields.phone')) ?><input name="phone" value="<?= e((string) old('phone', $customer['phone'])) ?>" maxlength="40"></label>
            <label><?= e(__('fields.whatsapp')) ?><input name="whatsapp" value="<?= e((string) old('whatsapp', $customer['whatsapp'])) ?>" maxlength="40"></label>
            <label><?= e(__('fields.address')) ?><input name="address" value="<?= e((string) old('address', $customer['address'])) ?>" maxlength="255"></label>
        </div>
        <label><?= e(__('fields.notes')) ?><textarea name="notes" maxlength="4000"><?= e((string) old('notes', $customer['notes'])) ?></textarea></label>
        <input type="hidden" name="is_active" value="0">
        <label class="consent"><input type="checkbox" name="is_active" value="1"<?= checked((int) old('is_active', $effectiveActive ? 1 : 0) === 1) ?>> <?= e(__('admin.active')) ?></label>
        <p>Suspending the account also disables its linked customer login.</p>
        <button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
    </form>
    <?php else: ?>
    <dl><dt><?= e(__('fields.name')) ?></dt><dd><?= e($customer['name']) ?></dd><dt><?= e(__('fields.phone')) ?></dt><dd><?= e((string) $customer['phone']) ?></dd><dt><?= e(__('fields.whatsapp')) ?></dt><dd><?= e((string) $customer['whatsapp']) ?></dd><dt><?= e(__('fields.address')) ?></dt><dd><?= e((string) $customer['address']) ?></dd><dt><?= e(__('fields.notes')) ?></dt><dd><?= nl2br(e((string) $customer['notes'])) ?></dd></dl>
    <?php endif; ?>
</section>
<?php if ($auth->can('cases.view')): ?>
<section class="card-block"><h2><?= e(__('admin.cases')) ?></h2><?php if ($cases === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?><?php foreach ($cases as $case): ?><p><a href="<?= e(url('/admin/cases/' . $case['id'])) ?>"><?= e($case['number']) ?></a> <?= e($case['status_name']) ?></p><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($auth->can('quotations.create')): ?>
<section class="card-block"><h2><?= e(__('admin.quotations')) ?></h2><?php if ($quotations === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?><?php foreach ($quotations as $row): ?><p><a href="<?= e(url('/admin/quotations/' . $row['id'])) ?>"><?= e($row['number']) ?></a> <?= e($row['status']) ?></p><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($auth->can('invoices.create')): ?>
<section class="card-block"><h2><?= e(__('admin.invoices')) ?></h2><?php if ($invoices === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?><?php foreach ($invoices as $row): ?><p><a href="<?= e(url('/admin/invoices/' . $row['id'])) ?>"><?= e($row['number']) ?></a> <?= e($row['status']) ?></p><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($auth->can('payments.view')): ?>
<section class="card-block"><h2><?= e(__('admin.payments')) ?></h2><?php if ($payments === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?><?php foreach ($payments as $row): ?><p><a href="<?= e(url('/admin/payments/' . $row['id'] . '/receipt')) ?>"><?= e($row['number']) ?></a> · <?= e(money($row['amount'])) ?></p><?php endforeach; ?></section>
<?php endif; ?>
<?php if ($auth->can('documents.download')): ?>
<section class="card-block"><h2><?= e(__('admin.documents')) ?></h2><?php if ($documents === []): ?><p class="empty"><?= e(__('empty.results')) ?></p><?php endif; ?><?php foreach ($documents as $row): ?><p><a href="<?= e(url('/admin/documents/' . $row['id'] . '/download')) ?>"><?= e($row['original_name']) ?></a> · <?= e($row['number']) ?></p><?php endforeach; ?></section>
<?php endif; ?>
