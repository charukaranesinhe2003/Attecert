<p>Hello <?= e($name) ?>,</p>
<h2>Verify your AtteCert account</h2>
<p>Enter this code on the verification page:</p>
<p style="font-size:32px;font-weight:800;letter-spacing:8px;background:#e7f4f1;padding:20px;text-align:center;border-radius:8px"><?= e($code) ?></p>
<p>This code expires in <?= (int) $minutes ?> minutes and can be used only once.</p>
<p>Do not share this code. If you did not request it, you can ignore this email.</p>
