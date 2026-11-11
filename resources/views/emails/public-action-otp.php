<p><?= e(__('mail.hello', ['name' => $name])) ?></p>
<h2 style="color:#092c4c"><?= $purpose === 'track' ? 'Verify document tracking' : 'Verify your quote request' ?></h2>
<p>Enter this code on the AtteCert website to <?= $purpose === 'track' ? 'view your document progress' : 'continue your quotation request' ?>.</p>
<p style="font-size:32px;letter-spacing:8px;font-weight:bold;background:#e7f4f1;padding:20px;text-align:center;border-radius:12px"><?= e($code) ?></p>
<p>This code expires in <?= (int) $minutes ?> minutes. Do not share it with anyone.</p>
<p>If you did not request this code, you can ignore this email.</p>
