<?php $invoice = $bundle['invoice']; ?>
<p><?= e($invoice['status']) ?> · <?= e($bundle['case']['number']) ?></p>
<div class="table-wrap"><table><tbody><?php foreach ($bundle['items'] as $item): ?><tr><td><?= e($item['description']) ?></td><td><?= e(money($item['line_total'], $invoice['currency'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p><?= e(__('fields.total')) ?>: <?= e(money($invoice['total'], $invoice['currency'])) ?></p>
<p><?= e(__('fields.paid')) ?>: <?= e(money($invoice['amount_paid'], $invoice['currency'])) ?></p>
<p><?= e(__('fields.due')) ?>: <?= e(money($invoice['amount_due'], $invoice['currency'])) ?></p>
<a class="btn btn-primary" href="<?= e(url('/portal/invoices/' . $invoice['id'] . '/pdf')) ?>"><?= e(__('actions.download')) ?></a>
