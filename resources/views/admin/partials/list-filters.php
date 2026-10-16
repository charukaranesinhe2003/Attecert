<form class="filters" method="get" action="<?= e(url($listPath)) ?>">
    <?php if (locale() !== 'en'): ?><input type="hidden" name="lang" value="<?= e(locale()) ?>"><?php endif; ?>
    <label><span class="sr-only"><?= e(__('admin_lists.search')) ?></span><input type="search" name="q" value="<?= e($filters['q']) ?>" maxlength="160" placeholder="<?= e(__('admin_lists.search')) ?>"></label>
    <label><span class="sr-only"><?= e(__('admin_lists.status')) ?></span><select name="status">
        <option value=""><?= e(__('admin_overview.all_statuses')) ?></option>
        <?php foreach ($statusOptions as $value => $label): ?><option value="<?= e($value) ?>" <?= selected($filters['status'], $value) ?>><?= e($label) ?></option><?php endforeach; ?>
    </select></label>
    <?php if (isset($roleOptions)): ?>
        <label><span class="sr-only"><?= e(__('admin_lists.role')) ?></span><select name="role">
            <option value=""><?= e(__('admin_lists.role') . ': ' . __('admin_lists.all')) ?></option>
            <?php foreach ($roleOptions as $role): ?><option value="<?= e($role['code']) ?>" <?= selected($filters['role'], $role['code']) ?>><?= e($role['name']) ?></option><?php endforeach; ?>
        </select></label>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= e(__('actions.filter')) ?></button>
    <a class="btn btn-ghost" href="<?= e(url($listPath)) ?>"><?= e(__('admin_lists.clear')) ?></a>
</form>
<p class="overview-note"><?= e(__('admin_lists.results', ['count' => $page['total']])) ?></p>
