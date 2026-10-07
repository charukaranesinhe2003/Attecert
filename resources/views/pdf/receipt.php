<style>body{font-family:sans-serif;color:#10243c} h1{color:#092c4c}</style>
<h1><?= e($company['name']) ?></h1>
<p>Receipt <?= e($payment['number']) ?></p>
<p><?= e($customer['name']) ?><br>Invoice <?= e($invoice['number']) ?></p>
<p>Amount <?= e(number_format((float) $payment['amount'], 2)) ?> <?= e($payment['currency']) ?></p>
<p>Method <?= e($payment['method']) ?> · <?= e($payment['paid_at']) ?></p>
<p><?= e((string) $payment['reference']) ?></p>
