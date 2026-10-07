<section class="page-hero"><div class="container"><h1><?= e(__('quote.title')) ?></h1><p class="lead"><?= e(content_block('quote_lead')) ?></p></div></section>
<section class="section"><div class="container" style="max-width:860px"><form class="panel stack" method="post" action="<?= e(url('/quote')) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
<div class="form-grid">
<label><?= e(__('fields.name')) ?><input name="name" value="<?= e((string) old('name')) ?>" required></label>
<label><?= e(__('fields.phone')) ?><input name="phone" value="<?= e((string) old('phone')) ?>" required></label>
<label><?= e(__('fields.email')) ?><input type="email" name="email" value="<?= e((string) old('email')) ?>" required></label>
<label><?= e(__('fields.whatsapp')) ?><input name="whatsapp" value="<?= e((string) old('whatsapp')) ?>"></label>
<label><?= e(__('fields.document_type_id')) ?><select name="document_type_id"><option value=""><?= e(__('quote.choose')) ?></option><?php foreach ($documentTypes as $type): ?><option value="<?= e((string) $type['id']) ?>"><?= e($type['name']) ?></option><?php endforeach; ?></select></label>
<label><?= e(__('fields.country_id')) ?><select name="country_id"><option value=""><?= e(__('quote.choose')) ?></option><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>"><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
<label><?= e(__('fields.document_count')) ?><input type="number" min="1" name="document_count" value="<?= e((string) (old('document_count') ?: 1)) ?>"></label>
</div>
<fieldset><legend><?= e(__('fields.services')) ?></legend><?php foreach ($services as $service): ?><label class="consent"><input type="checkbox" name="services[]" value="<?= e((string) $service['id']) ?>"> <?= e($service['name']) ?></label><?php endforeach; ?></fieldset>
<label><?= e(__('fields.message')) ?><textarea name="message" rows="5"><?= e((string) old('message')) ?></textarea></label>
<label><?= e(__('fields.attachments')) ?><input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
<label class="consent"><input type="checkbox" name="consent" value="1" required> <?= e(__('quote.consent')) ?></label>
<?php foreach (errors() as $message): ?><p class="form-error"><?= e((string) $message) ?></p><?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?= e(__('quote.submit')) ?></button>
</form></div></section>
