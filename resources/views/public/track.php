<section class="page-hero"><div class="container"><h1><?= e(__('track.title')) ?></h1><p class="lead"><?= e(__('track.lead')) ?></p></div></section>
<section class="section"><div class="container" style="max-width:760px">
    <form class="panel stack" method="post" action="<?= e(url('/track')) ?>"><?= csrf_field() ?><label><?= e(__('track.number')) ?><input name="number" value="<?= e($number) ?>" placeholder="<?= e(__('track.placeholder')) ?>" required></label><button class="btn btn-primary" type="submit"><?= e(__('track.button')) ?></button></form>
    <?php if ($searched && $result === null): ?><p class="empty" role="status"><?= e(__('track.none')) ?></p><?php endif; ?>
    <?php if ($result): ?>
        <article class="panel" style="margin-top:1rem">
            <p class="eyebrow"><?= e($result['number']) ?></p>
            <h2><?= e($result['status_name'] ?: $result['status_code']) ?></h2>
            <p><?= e($result['document_title']) ?><?php if ($result['country_name']): ?> · <?= e($result['country_name']) ?><?php endif; ?></p>
            <p><?= e(implode(', ', array_filter(array_column($result['services'], 'name')))) ?></p>
            <?php if ($result['estimated_next']): ?><p><strong><?= e(__('track.next')) ?>:</strong> <?= e($result['estimated_next']) ?></p><?php endif; ?>
            <p><?= e(__('track.updated')) ?>: <?= e(format_datetime($result['updated_at'])) ?></p>
            <ol class="timeline"><?php foreach ($result['history'] as $item): ?><li><i></i><div><strong><?= e($item['name'] ?: $item['code']) ?></strong><br><span><?= e(format_datetime($item['created_at'])) ?></span><?php if ($item['notes']): ?><p><?= e($item['notes']) ?></p><?php endif; ?></div></li><?php endforeach; ?></ol>
        </article>
    <?php endif; ?>
</div></section>
