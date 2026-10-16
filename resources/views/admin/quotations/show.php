<?php $q = $bundle['quotation']; $auth = app(\App\Services\AuthService::class); ?>
<p><?= e($q['number']) ?> · <?= e($q['status']) ?> · v<?= (int) $q['version'] ?></p>
<p><?= e($bundle['customer']['name']) ?> · <?= e($bundle['case']['number']) ?></p>
<div class="table-wrap"><table><tbody><?php foreach ($bundle['items'] as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e($item['quantity']) ?></td><td><?= e(money($item['unit_price'], $q['currency'])) ?></td><td><?= e(money($item['line_total'], $q['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p><?= e(__('fields.subtotal')) ?> <?= e(money($q['subtotal'], $q['currency'])) ?></p>
<p><?= e(__('fields.discount')) ?> <?= e(money($q['discount_amount'], $q['currency'])) ?></p>
<p><?= e(__('fields.tax')) ?> <?= e(money($q['tax_amount'], $q['currency'])) ?></p>
<p><strong><?= e(__('fields.total')) ?> <?= e(money($q['total'], $q['currency'])) ?></strong></p>
<div class="actions">
    <a class="btn btn-ghost" href="<?= e(url('/admin/quotations/' . $q['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a>
    <?php if ($auth->can('quotations.send') && ($q['status'] !== 'draft' || $auth->can('quotations.approve'))): ?>
    <form method="post" action="<?= e(url('/admin/quotations/' . $q['id'] . '/send')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('actions.email')) ?></button></form>
    <?php endif; ?>
    <?php if ($auth->can('cases.view')): ?><a class="btn btn-ghost" href="<?= e(url('/admin/cases/' . $q['case_id'])) ?>"><?= e(__('admin.cases')) ?></a><?php endif; ?>
</div>
