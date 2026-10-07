<p><a class="btn btn-primary" href="<?= e(url('/admin/faqs/create')) ?>"><?= e(__('actions.add')) ?></a></p>
<?php foreach ($faqs as $faq): ?><p><a href="<?= e(url('/admin/faqs/' . $faq['id'] . '/edit')) ?>"><?= e($faq['question']) ?></a></p><?php endforeach; ?>
