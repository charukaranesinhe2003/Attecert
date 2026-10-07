<div class="admin-grid">
    <section class="card-block">
        <div class="card-head">
            <h2><?= e(__('admin.mofa')) ?></h2>
            <a href="<?= e(url('/admin/mofa')) ?>"><?= e(__('actions.view')) ?></a>
        </div>
        <?php if (($mofa ?? []) === []): ?>
            <p class="empty"><?= e(__('admin.processing_empty_mofa')) ?></p>
        <?php else: ?>
            <?php foreach ($mofa as $row): ?>
                <p>
                    <a href="<?= e(url('/admin/cases/' . $row['case_id'] . '#processing')) ?>"><?= e($row['number']) ?></a>
                    · <?= e($row['customer_name']) ?>
                    · <?= e($row['status']) ?>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
    <section class="card-block">
        <div class="card-head">
            <h2><?= e(__('admin.embassy')) ?></h2>
            <a href="<?= e(url('/admin/embassy')) ?>"><?= e(__('actions.view')) ?></a>
        </div>
        <?php if (($embassy ?? []) === []): ?>
            <p class="empty"><?= e(__('admin.processing_empty_embassy')) ?></p>
        <?php else: ?>
            <?php foreach ($embassy as $row): ?>
                <p>
                    <a href="<?= e(url('/admin/cases/' . $row['case_id'] . '#processing')) ?>"><?= e($row['number']) ?></a>
                    · <?= e($row['customer_name']) ?>
                    · <?= e($row['status']) ?>
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
