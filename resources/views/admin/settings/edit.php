<?php if (!$smtp): ?><p class="form-error"><?= e(__('admin.smtp_missing')) ?></p><?php endif; ?>
<form class="card-block stack" method="post" action="<?= e(url('/admin/settings')) ?>"><?= csrf_field() ?>
<?php foreach (['company_name','email','phone','whatsapp','address','hours','map_url','registration','currency','quote_validity_days','invoice_due_days','upload_max_mb','facebook','instagram','linkedin'] as $key): ?>
<label><?= e($key) ?><input name="<?= e($key) ?>" value="<?= e((string) ($settings[$key] ?? '')) ?>"></label>
<?php endforeach; ?>
<label>Quote terms<textarea name="quote_terms"><?= e((string) ($settings['quote_terms'] ?? '')) ?></textarea></label>
<label>Invoice terms<textarea name="invoice_terms"><?= e((string) ($settings['invoice_terms'] ?? '')) ?></textarea></label>
<label class="consent"><input type="checkbox" name="email_verification" value="1" <?= ($settings['email_verification'] ?? '0') === '1' ? 'checked' : '' ?>> Email verification</label>
<label class="consent"><input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Maintenance mode</label>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
