<div style="background:#f0f9ff;border:1px solid #bae6fd;padding:1rem;border-radius:8px;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;">
    <div>
        <strong style="color:#0369a1;">Making a bank transfer or deposit?</strong>
        <p style="margin:0.25rem 0 0 0;color:#0284c7;font-size:0.9rem;">View our official Bank of Ceylon, HNB, and Commercial Bank account numbers.</p>
    </div>
    <a href="<?= e(url('/bank-details')) ?>" class="btn btn-primary" target="_blank">View Bank Details</a>
</div>

<?php if ($payments === []): ?><p class="empty"><?= e(__('empty.payments')) ?></p><?php else: ?>
<div class="table-wrap"><table><thead><tr><th><?= e(__('fields.number')) ?></th><th><?= e(__('fields.amount')) ?></th><th><?= e(__('fields.method')) ?></th><th><?= e(__('fields.date')) ?></th><th><?= e(__('fields.status')) ?></th></tr></thead><tbody>
<?php foreach ($payments as $row): ?><tr><td><?= e($row['number']) ?></td><td><?= e(money($row['amount'], $row['currency'])) ?></td><td><?= e(ucwords(str_replace('_', ' ', $row['method']))) ?></td><td><?= e(format_date($row['paid_at'])) ?></td><td><?= status_badge(['pending' => 'Awaiting confirmation', 'completed' => 'Received', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded'][$row['status']] ?? $row['status'], $row['status'] === 'completed' ? 'success' : 'neutral') ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
