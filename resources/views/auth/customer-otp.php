<section class="verification-page"><div class="verification-shell">
    <header class="verification-heading"><p class="eyebrow">Customer account</p><h1>Verify your account</h1>
    <p>Verify your email address or mobile number to open your customer account.</p></header>
    <div class="verification-card stack">
    <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
    <?php if ($success = flash('success')): ?>
        <div style="background:#ecfdf5;color:#065f46;padding:12px 16px;border-radius:6px;border:1px solid #a7f3d0;font-size:0.95em;line-height:1.4;">
            <strong>&check; <?= e($success) ?></strong>
        </div>
    <?php endif; ?>
    <?php if (!$emailReady && !$smsReady): ?>
        <p class="verification-notice">Verification delivery has not been configured. Please contact support. Your account cannot be opened until verification is complete.</p>
    <?php endif; ?>

    <form class="stack" method="post" action="<?= e(url('/verify-customer')) ?>">
        <?= csrf_field() ?>
        <label style="font-weight:600;font-size:1.05rem;">Enter 6-digit code
            <input class="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" placeholder="123456" autofocus required style="font-size:1.5rem;letter-spacing:6px;text-align:center;padding:10px;">
        </label>
        <button class="btn btn-primary" type="submit" style="font-size:1rem;padding:10px;">Verify and open account</button>
    </form>

    <hr style="border:none;border-top:1px solid #e2e8f0;margin:1rem 0;">
    <details style="font-size:0.95em;color:#475569;" open>
        <summary style="cursor:pointer;font-weight:500;color:#2563eb;">Didn't receive the code? Resend or change delivery method</summary>
        <form class="stack" method="post" action="<?= e(url('/verify-customer/send')) ?>" style="margin-top:0.75rem;">
            <?= csrf_field() ?>
            <label>Send a new code to:
                <select name="channel">
                    <option value="email"<?= !$emailReady ? ' disabled' : '' ?>>Email: <?= e($email) ?><?= !$emailReady ? ' (unavailable)' : '' ?></option>
                    <option value="sms"<?= !$emailReady && $smsReady ? ' selected' : '' ?><?= !$smsReady ? ' disabled' : '' ?>>SMS: <?= e($phone) ?><?= !$smsReady ? ' (requires SMS gateway)' : '' ?></option>
                </select>
            </label>
            <button class="btn btn-ghost" type="submit"<?= !$emailReady && !$smsReady ? ' disabled' : '' ?>>Resend verification code</button>
            <p class="muted" style="font-size:0.85em;">Wait 60 seconds before requesting another code. A new code replaces the previous code.</p>
        </form>
    </details>

    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Sign out</button></form>
</div></div></section>
