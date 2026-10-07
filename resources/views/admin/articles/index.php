<p><a class="btn btn-primary" href="<?= e(url('/admin/articles/create')) ?>"><?= e(__('admin.new_article')) ?></a></p>
<?php foreach ($articles as $article): ?><p><a href="<?= e(url('/admin/articles/' . $article['id'] . '/edit')) ?>"><?= e($article['title']) ?></a> <?= e($article['status']) ?></p><?php endforeach; ?>
