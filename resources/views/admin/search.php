<section class="card-block">
    <form class="filters" method="get" action="<?= e(url('/admin/search')) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('admin.search_everything')) ?>" autofocus>
        <button class="btn btn-primary" type="submit"><?= e(__('actions.search')) ?></button>
    </form>
    <?php if ($q === ''): ?>
        <p class="empty"><?= e(__('admin.search_hint')) ?></p>
    <?php elseif ($results === []): ?>
        <p class="empty"><?= e(__('admin.search_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($results as $group): ?>
            <h2 style="margin-top:1rem"><?= e($group['label']) ?></h2>
            <div class="doc-list">
                <?php foreach ($group['items'] as $item): ?>
                    <a class="doc-row" href="<?= e($item['href']) ?>" style="text-decoration:none;color:inherit">
                        <div>
                            <strong><?= e($item['title']) ?></strong>
                            <span><?= e($item['meta'] ?? '') ?></span>
                        </div>
                        <span class="btn btn-ghost"><?= e(__('actions.open')) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
