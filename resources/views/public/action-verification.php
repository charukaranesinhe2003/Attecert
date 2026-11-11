<?php
$isQuote = $purpose === 'quote';
$sendUrl = $isQuote ? '/quote/verification/send' : '/track';
$verifyUrl = $isQuote ? '/quote/verification' : '/track/verification';
$ready = $emailReady || $smsReady;
$channel = $pending['channel'] ?? ($emailReady ? 'email' : 'sms');
?>
<section class="verification-page">
    <div class="verification-shell">
        <header class="verification-heading">
            <p class="eyebrow"><?= $isQuote ? 'Request a quotation' : 'Document tracking' ?></p>
            <h1><?= $isQuote ? 'Verify your contact' : 'Verify to view your document' ?></h1>
            <p><?= $isQuote ? 'A quick verification keeps your quotation and document updates connected to you.' : 'Enter your tracking reference. We will send a code to the customer contact registered with the case.' ?></p>
        </header>
        <ol class="verification-progress" aria-label="Verification progress"><li class="<?= $pending ? 'is-done' : 'is-active' ?>">1. <?= $isQuote ? 'Your contact' : 'Tracking reference' ?></li><li class="<?= $pending ? 'is-active' : '' ?>">2. Verify code</li><li>3. <?= $isQuote ? 'Request details' : 'View progress' ?></li></ol>
        <div class="verification-card">
            <?php foreach (errors() as $message): ?><p class="form-error" role="alert"><?= e((string) $message) ?></p><?php endforeach; ?>
            <?php if (!$ready): ?><p class="verification-notice" role="status">Verification delivery is currently unavailable. Please <a href="<?= e(url('/contact')) ?>">contact our team</a> for help.</p><?php endif; ?>
            <?php if ($pending): ?>
                <div class="verification-code-heading"><span class="verification-icon" aria-hidden="true"><?= icon('mail') ?></span><h2>Enter your verification code</h2></div>
                <p><?= $isQuote ? 'Your code is queued for ' . e((string) $pending['destination']) . '.' : 'If the reference and selected method match our records, the code will arrive at the customer’s registered contact.' ?></p>
                <?php if (!$isQuote): ?><p class="verification-reference">Reference <strong><?= e($number) ?></strong></p><?php endif; ?>
                <form class="stack verification-code-form" method="post" action="<?= e(url($verifyUrl)) ?>"><?= csrf_field() ?>
                    <label for="public-otp-code">Six-digit verification code<input id="public-otp-code" class="verification-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required aria-describedby="verification-code-help" autofocus></label>
                    <p id="verification-code-help" class="muted">The code expires in 10 minutes. Never share it with anyone.</p>
                    <button class="btn btn-primary" type="submit"><?= $isQuote ? 'Verify & continue to quote request' : 'Verify & view tracking' ?></button>
                </form>
                <details class="verification-resend"><summary>Need another code?</summary>
                    <form class="stack" method="post" action="<?= e(url($sendUrl)) ?>"><?= csrf_field() ?>
                        <?php if ($isQuote): ?><?php foreach (['name','email','phone'] as $field): ?><input type="hidden" name="<?= e($field) ?>" value="<?= e((string) ($contact[$field] ?? '')) ?>"><?php endforeach; ?><?php else: ?><input type="hidden" name="number" value="<?= e($number) ?>"><?php endif; ?>
                        <label>Delivery method<select name="channel"><option value="email"<?= selected($channel, 'email') ?><?= !$emailReady ? ' disabled' : '' ?>>Email<?= !$emailReady ? ' (unavailable)' : '' ?></option><option value="sms"<?= selected($channel, 'sms') ?><?= !$smsReady ? ' disabled' : '' ?>>SMS<?= !$smsReady ? ' (unavailable)' : '' ?></option></select></label>
                        <p class="muted">Wait 60 seconds between requests. A new code replaces the previous code.</p>
                        <button class="btn btn-ghost" type="submit"<?= !$ready ? ' disabled' : '' ?>>Send a new code</button>
                    </form>
                </details>
                <?php if ($isQuote): ?><form method="post" action="<?= e(url('/quote/verification/reset')) ?>"><?= csrf_field() ?><button class="btn btn-text" type="submit">Change contact details</button></form><?php else: ?><a class="btn btn-text" href="<?= e(url('/track')) ?>">Use another tracking reference</a><?php endif; ?>
            <?php else: ?>
                <h2><?= $isQuote ? 'Where can we reach you?' : 'Your tracking reference' ?></h2>
                <form class="stack" method="post" action="<?= e(url($sendUrl)) ?>"><?= csrf_field() ?>
                    <?php if ($isQuote): ?>
                        <label>Full name<input name="name" autocomplete="name" required minlength="2" maxlength="160" value="<?= e((string) old('name', $contact['name'] ?? '')) ?>"></label>
                        <label>Email address<input type="email" name="email" autocomplete="email" required maxlength="190" value="<?= e((string) old('email', $contact['email'] ?? '')) ?>"></label>
                        <label>Mobile number<input type="tel" name="phone" autocomplete="tel" required minlength="6" maxlength="40" placeholder="077 123 4567" value="<?= e((string) old('phone', $contact['phone'] ?? '')) ?>"></label>
                    <?php else: ?>
                        <label>Tracking / quotation reference<input name="number" required minlength="6" maxlength="24" placeholder="KASS/Q/2026111101" value="<?= e((string) old('number', $number)) ?>" autocomplete="off"></label>
                        <p class="muted">You can find this reference in your quotation or confirmation email.</p>
                    <?php endif; ?>
                    <fieldset class="verification-method"><legend>Send verification code by</legend>
                        <label><input type="radio" name="channel" value="email"<?= $channel === 'email' ? ' checked' : '' ?><?= !$emailReady ? ' disabled' : '' ?>><span>Email<small><?= $emailReady ? 'Code by email' : 'Currently unavailable' ?></small></span></label>
                        <label><input type="radio" name="channel" value="sms"<?= $channel === 'sms' ? ' checked' : '' ?><?= !$smsReady ? ' disabled' : '' ?>><span>SMS<small><?= $smsReady ? 'Code by text message' : 'Currently unavailable' ?></small></span></label>
                    </fieldset>
                    <button class="btn btn-primary" type="submit"<?= !$ready ? ' disabled' : '' ?>>Send verification code</button>
                </form>
            <?php endif; ?>
        </div>
        <p class="verification-footer"><?= $isQuote ? 'No account is required to request a quotation.' : 'Tracking information stays private until verification is complete.' ?> <a href="<?= e(url('/contact')) ?>">Need help?</a></p>
    </div>
</section>
