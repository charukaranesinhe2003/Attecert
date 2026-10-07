<article class="card-block">
<p><?= e($quote['name']) ?> · <?= e($quote['email']) ?> · <?= e((string) $quote['phone']) ?></p>
<p><?= nl2br(e((string) $quote['message'])) ?></p>
<ul><?php foreach ($services as $service): ?><li><?= e($service['name']) ?></li><?php endforeach; ?></ul>
<form method="post" action="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/convert')) ?>"><?= csrf_field() ?><button class="btn btn-primary" type="submit"><?= e(__('admin.convert')) ?></button></form>
<form method="post" action="<?= e(url('/admin/quote-requests/' . $quote['id'] . '/status')) ?>"><?= csrf_field() ?><select name="status"><option value="new">new</option><option value="contacted">contacted</option><option value="closed">closed</option><option value="spam">spam</option></select><button class="btn btn-ghost"><?= e(__('actions.save')) ?></button></form>
</article>
