<section class="verification-page"><div class="verification-shell">
    <header class="verification-heading"><p class="eyebrow">Customer account</p><h1>Verify your account</h1>
    <p>Verify your email address or mobile number to open your customer account.</p></header>
    <div class="verification-card stack">
    <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
    <?php if (!$emailReady && !$smsReady): ?><p class="verification-notice">Verification delivery has not been configured. Please contact support. Your account cannot be opened until verification is complete.</p><?php endif; ?>
    <form class="stack" method="post" action="<?= e(url('/verify-customer/send')) ?>">
        <?= csrf_field() ?>
        <label>Send a code by<select name="channel"><option value="email"<?= !$emailReady ? ' disabled' : '' ?>>Email <?= e($email) ?><?= !$emailReady ? ' (unavailable)' : '' ?></option><option value="sms"<?= !$emailReady && $smsReady ? ' selected' : '' ?><?= !$smsReady ? ' disabled' : '' ?>>SMS <?= e($phone) ?><?= !$smsReady ? ' (unavailable)' : '' ?></option></select></label>
        <button class="btn btn-primary" type="submit"<?= !$emailReady && !$smsReady ? ' disabled' : '' ?>>Send verification code</button>
        <p class="muted">Wait 60 seconds before requesting another code. A new code replaces the previous code.</p>
    </form>
    <form class="stack" method="post" action="<?= e(url('/verify-customer')) ?>">
        <?= csrf_field() ?>
        <label>Six-digit code<input class="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label>
        <button class="btn btn-primary" type="submit">Verify account</button>
    </form>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Sign out</button></form>
</div></div></section>
