<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use App\Core\ValidationException;

final class CustomerOtpService
{
    public function __construct(private Database $db, private AuthService $auth, private MailService $mail, private SmsService $sms, private AuditService $audit) {}

    public function send(string $channel): array
    {
        $user = $this->auth->user();
        if ($user === null || $this->auth->isStaff() || !$this->auth->needsVerification()) {
            throw new ValidationException(['channel' => 'Customer verification is not required for this account.']);
        }
        if (!in_array($channel, ['email', 'sms'], true)) { throw new ValidationException(['channel' => 'Choose email or SMS.']); }
        $destination = $channel === 'email' ? $user['email'] : SmslenzClient::normalizePhone($user['phone']);
        if ($destination === null) { throw new ValidationException(['channel' => 'Your account needs a valid mobile number for SMS verification. Choose email instead.']); }
        if (($channel === 'email' && !$this->mail->smtpReady()) || ($channel === 'sms' && !$this->sms->ready())) {
            throw new ValidationException(['channel' => ucfirst($channel) . ' delivery is not configured. Please contact support or choose another method.']);
        }
        $result = $this->db->transaction(function () use ($user, $channel, $destination): array {
            $current = $this->db->fetch('SELECT id FROM users WHERE id = ? AND deleted_at IS NULL AND is_active = 1 FOR UPDATE', [$user['id']]);
            if ($current === null) { throw new ValidationException(['channel' => 'Please sign in again.']); }
            $recent = $this->db->fetch('SELECT created_at FROM customer_otps WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$user['id']]);
            if ($recent !== null && strtotime($recent['created_at']) > time() - (int) config('verification.resend_seconds', 60)) {
                throw new ValidationException(['channel' => 'Wait 60 seconds before requesting another code.']);
            }
            if ((int) $this->db->fetchColumn('SELECT COUNT(*) FROM customer_otps WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)', [$user['id']]) >= (int) config('verification.hourly_limit', 5)) {
                throw new ValidationException(['channel' => 'Too many verification requests. Please try again later.']);
            }
            $code = (string) random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + (int) config('verification.ttl_minutes', 10) * 60);
            $this->db->run('UPDATE customer_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            $id = $this->db->insert('INSERT INTO customer_otps (user_id, channel, destination, code_hash, password_fingerprint, expires_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$user['id'], $channel, $destination, password_hash($code, PASSWORD_DEFAULT), hash('sha256', $user['password_hash']), $expires]);
            if ($channel === 'email') {
                $delivery = $this->mail->send($destination, 'Your AtteCert verification code', 'customer-otp',
                    ['name' => $user['name'], 'code' => $code, 'minutes' => (int) config('verification.ttl_minutes', 10)], 'user', (int) $user['id'], null, null, $expires);
            } else {
                $delivery = $this->sms->queue($destination, $user['name'], 'AtteCert verification code: ' . $code . '. Valid for 10 minutes. Do not share this code.', 'otp', $id, $expires);
            }
            if (!in_array($delivery['status'], ['queued', 'sent'], true)) {
                throw new ValidationException(['channel' => 'Verification delivery is unavailable. Please contact support.']);
            }
            $this->audit->log((int) $user['id'], 'customer_otp_requested', 'user', (int) $user['id'], ['channel' => $channel, 'delivery_status' => $delivery['status']]);
            return ['id' => $id, 'channel' => $channel, 'status' => $delivery['status']];
        });
        $_SESSION['_customer_otp'] = $result['id'];
        return $result;
    }

    public function verify(string $code): bool
    {
        $user = $this->auth->user();
        if ($user === null || $this->auth->isStaff() || !preg_match('/^[0-9]{6}$/D', $code)) { return false; }
        $verified = $this->db->transaction(function () use ($user, $code): bool {
            $current = $this->db->fetch('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND is_active = 1 FOR UPDATE', [$user['id']]);
            $otp = $this->db->fetch('SELECT * FROM customer_otps WHERE id = ? AND user_id = ? FOR UPDATE', [(int) ($_SESSION['_customer_otp'] ?? 0), $user['id']]);
            if ($current === null || $otp === null || $otp['used_at'] !== null || strtotime($otp['expires_at']) <= time()
                || (int) $otp['attempts'] >= (int) config('verification.max_attempts', 5)
                || !hash_equals($otp['password_fingerprint'], hash('sha256', $current['password_hash']))) { return false; }
            $destination = $otp['channel'] === 'email' ? $current['email'] : SmslenzClient::normalizePhone($current['phone']);
            if ($destination !== $otp['destination']) { return false; }
            $this->db->run('UPDATE customer_otps SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
            if (!password_verify($code, $otp['code_hash'])) { return false; }
            if ($otp['channel'] === 'email') {
                $this->db->run('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$user['id']]);
            } else {
                $this->db->run('UPDATE users SET phone_verified_at = NOW(), verified_phone = ? WHERE id = ?', [$destination, $user['id']]);
            }
            $this->db->run('UPDATE users SET otp_verified_at = NOW(), otp_verified_channel = ?, otp_verified_destination = ? WHERE id = ?', [$otp['channel'], $destination, $user['id']]);
            $this->db->run('UPDATE customer_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
            $this->audit->log((int) $user['id'], 'customer_verified', 'user', (int) $user['id'], ['channel' => $otp['channel']]);
            return true;
        });
        if ($verified) { unset($_SESSION['_customer_otp']); $this->auth->refreshUser(); $this->auth->renewSession(); }
        return $verified;
    }

    public static function mask(string $value): string
    {
        if (str_contains($value, '@')) { [$name, $domain] = explode('@', $value, 2); return mb_substr($name, 0, 1) . '***@' . $domain; }
        return '***' . mb_substr($value, -4);
    }
}
