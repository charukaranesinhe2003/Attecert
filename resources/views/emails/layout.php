<?php
$company = $company ?? ['name' => 'AtteCert', 'email' => '', 'phone' => '', 'address' => ''];
?>
<!DOCTYPE html>
<html>
<body style="margin:0;background:#f5f7f6;font-family:Arial,sans-serif;color:#10243c">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="background:#092c4c;color:#fff;padding:20px 24px;font-size:20px;font-weight:700"><?= e($company['name']) ?></td></tr>
<tr><td style="padding:24px">
<?= $body ?>
</td></tr>
<tr><td style="padding:16px 24px;color:#6d7e90;font-size:13px"><?= e($company['address']) ?><br><?= e($company['phone']) ?> · <?= e($company['email']) ?></td></tr>
</table>
</td></tr></table>
</body>
</html>
