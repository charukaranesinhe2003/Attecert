<form class="card-block stack" method="post" action="<?= e(url('/admin/cases')) ?>"><?= csrf_field() ?>
<label><?= e(__('admin.customers')) ?><select name="customer_id" required><option value=""></option><?php foreach ($customers as $customer): ?><option value="<?= e((string) $customer['id']) ?>"><?= e($customer['name'] . ' · ' . $customer['email']) ?></option><?php endforeach; ?></select></label>
<label><?= e(__('fields.document_title')) ?><input name="document_title" required></label>
<label><?= e(__('fields.country_id')) ?><select name="country_id"><option value=""></option><?php foreach ($countries as $country): ?><option value="<?= e((string) $country['id']) ?>"><?= e($country['name']) ?></option><?php endforeach; ?></select></label>
<label><?= e(__('fields.document_type_id')) ?><select name="document_type_id"><option value=""></option><?php foreach ($documentTypes as $type): ?><option value="<?= e((string) $type['id']) ?>"><?= e($type['name']) ?></option><?php endforeach; ?></select></label>
<label><?= e(__('fields.summary')) ?><textarea name="summary"></textarea></label>
<fieldset><legend><?= e(__('admin.services')) ?></legend><?php foreach ($services as $service): ?><label class="consent"><input type="checkbox" name="service_id[]" value="<?= e((string) $service['id']) ?>"> <?= e($service['name']) ?></label><?php endforeach; ?></fieldset>
<button class="btn btn-primary" type="submit"><?= e(__('actions.save')) ?></button>
</form>
