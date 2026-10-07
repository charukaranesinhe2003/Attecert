<?php $q = $bundle['quotation']; ?>
<p><?= e(__('fields.status')) ?>: <?= e($q['status']) ?> · <?= e(__('fields.case')) ?> <?= e($bundle['case']['number']) ?></p>
<div class="table-wrap"><table><thead><tr><th><?= e(__('fields.description')) ?></th><th><?= e(__('fields.quantity')) ?></th><th><?= e(__('fields.price')) ?></th><th><?= e(__('fields.total')) ?></th></tr></thead><tbody><?php foreach ($bundle['items'] as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e(money($item['unit_price'], $q['currency'])) ?></td><td><?= e(money($item['line_total'], $q['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p><strong><?= e(__('fields.total')) ?>: <?= e(money($q['total'], $q['currency'])) ?></strong></p>
<p><a class="btn btn-ghost" href="<?= e(url('/portal/quotations/' . $q['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a></p>
<?php if (in_array($q['status'], ['sent', 'viewed'], true)): ?>
<form method="post" action="<?= e(url('/portal/quotations/' . $q['id'] . '/accept')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('quotations.accept')) ?></button></form>
<form method="post" action="<?= e(url('/portal/quotations/' . $q['id'] . '/reject')) ?>" style="margin-top:.6rem"><?= csrf_field() ?><label><?= e(__('quotations.reject_reason')) ?><input name="reason"></label><button class="btn btn-ghost" type="submit"><?= e(__('quotations.reject')) ?></button></form>
<?php endif; ?>
