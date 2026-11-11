<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Core\Validator;

final class PublicOtpService
{
    public function __construct(private Database $db, private Validator $validator, private MailService $mail, private SmsService $sms) {}

    public function required(): bool { return (bool) config('verification.public_required', true); }

    private function sessionHash(): string
    {
        if (!isset($_SESSION['_public_otp_key'])) { $_SESSION['_public_otp_key'] = bin2hex(random_bytes(32)); }
        return hash('sha256', $_SESSION['_public_otp_key']);
    }

    public function contact(array $input): array
    {
        foreach (['name', 'email', 'phone'] as $field) {
            if (isset($input[$field]) && !is_string($input[$field])) { throw new ValidationException([$field => 'Enter valid contact details.']); }
        }
        $data = $this->validator->validate($input, ['name' => 'required|min:2|max:160', 'email' => 'required|email|max:190', 'phone' => 'required|min:6|max:40']);
        $data['email'] = mb_strtolower($data['email']);
        $data['phone'] = SmslenzClient::normalizePhone($data['phone']) ?? $data['phone'];
        return $data;
    }

    public function sendQuote(array $input): void
    {
        $contact = $this->contact($input);
        $channel = $this->validator->validate($input, ['channel' => 'required|in:email,sms'])['channel'];
        $destination = $channel === 'email' ? $contact['email'] : SmslenzClient::normalizePhone($contact['phone']);
        if ($destination === null) { throw new ValidationException(['phone' => 'Enter a valid mobile number for SMS, or choose email.'], $input); }
        $id = $this->send('quote', $this->subject($contact), $channel, $destination, $contact['name']);
        $_SESSION['_public_quote_contact'] = $contact;
        $_SESSION['_public_otp']['quote'] = $id;
    }

    public function sendTracking(array $input): void
    {
        $data = $this->validator->validate($input, ['number' => 'required|min:6|max:24', 'channel' => 'required|in:email,sms']);
        $number = strtoupper((string) $data['number']);
        $contact = $this->trackingContact($number);
        $destination = $contact === null ? null : ($data['channel'] === 'email' ? $contact['email'] : SmslenzClient::normalizePhone($contact['phone']));
        $id = $this->send('track', $this->trackingSubject($number, $contact), $data['channel'], $destination, $contact['name'] ?? 'Customer');
        $_SESSION['_public_tracking_number'] = $number;
        $_SESSION['_public_otp']['track'] = $id;
    }

    private function send(string $purpose, string $subject, string $channel, ?string $destination, string $name): int
    {
        if (($channel === 'email' && !$this->mail->smtpReady()) || ($channel === 'sms' && !$this->sms->ready())) {
            throw new ValidationException(['channel' => ucfirst($channel) . ' verification is unavailable. Choose another method or contact support.']);
        }
        $session = $this->sessionHash();
        return $this->db->transaction(function () use ($purpose, $subject, $channel, $destination, $name, $session): int {
            // Stable lock order and counters also limit requests across new browser sessions.
            $this->rate('ip:' . hash('sha256', client_ip()), 20);
            $this->rate('destination:' . hash('sha256', $destination ?? $subject), 5);
            $this->rate('session:' . $session . ':' . $purpose, (int) config('verification.hourly_limit', 5));
            $latest = $this->db->fetch('SELECT created_at FROM public_action_otps WHERE session_hash = ? AND purpose = ? ORDER BY id DESC LIMIT 1', [$session, $purpose]);
            if ($latest !== null && strtotime($latest['created_at']) > time() - (int) config('verification.resend_seconds', 60)) {
                throw new ValidationException(['channel' => 'Wait 60 seconds before requesting another code.']);
            }
            $code = (string) random_int(100000, 999999);
            $expiry = date('Y-m-d H:i:s', time() + (int) config('verification.ttl_minutes', 10) * 60);
            $this->db->run('UPDATE public_action_otps SET used_at = COALESCE(used_at, NOW()), grant_expires_at = NULL WHERE session_hash = ? AND purpose = ?', [$session, $purpose]);
            $id = $this->db->insert('INSERT INTO public_action_otps (session_hash, purpose, subject_hash, channel, destination, code_hash, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$session, $purpose, $subject, $channel, $destination ?? '', password_hash($code, PASSWORD_DEFAULT), $expiry]);
            // Unknown references produce the same response and a non-verifiable challenge. No contact data is revealed.
            if ($destination !== null && $destination !== '') {
                $delivery = $channel === 'email'
                    ? $this->mail->send($destination, 'Your AtteCert ' . ($purpose === 'track' ? 'tracking' : 'quote request') . ' verification code', 'public-action-otp',
                        ['name' => $name, 'code' => $code, 'purpose' => $purpose, 'minutes' => (int) config('verification.ttl_minutes', 10)], 'public_otp', $id, null, null, $expiry)
                    : $this->sms->queue($destination, $name, 'AtteCert ' . ($purpose === 'track' ? 'tracking' : 'quote request') . ' code: ' . $code . '. Valid for 10 minutes. Do not share this code.', 'public_otp', $id, $expiry);
                if (!in_array($delivery['status'], ['queued', 'sent'], true)) { throw new ValidationException(['channel' => 'Verification delivery is unavailable. Please try another method.']); }
            }
            return $id;
        });
    }

    private function rate(string $key, int $limit): void
    {
        $key = 'public-otp:' . $key;
        $this->db->run("INSERT INTO rate_limits (action_key, ip, attempts, window_start) VALUES (?, 'otp', 0, NOW()) ON DUPLICATE KEY UPDATE id = id", [$key]);
        $row = $this->db->fetch("SELECT * FROM rate_limits WHERE action_key = ? AND ip = 'otp' FOR UPDATE", [$key]);
        if (strtotime($row['window_start']) <= time() - 3600) {
            $this->db->run('UPDATE rate_limits SET attempts = 0, window_start = NOW() WHERE id = ?', [$row['id']]); $row['attempts'] = 0;
        }
        if ((int) $row['attempts'] >= $limit) { throw new ValidationException(['channel' => 'Too many verification requests. Please try again later.']); }
        $this->db->run('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
    }

    public function verify(string $purpose, string $code): bool
    {
        if (!in_array($purpose, ['quote', 'track'], true) || !preg_match('/^[0-9]{6}$/D', $code)) { return false; }
        $valid = $this->db->transaction(function () use ($purpose, $code): bool {
            $otp = $this->challenge($purpose, true);
            if ($otp === null || $otp['used_at'] !== null || $otp['destination'] === '' || strtotime($otp['expires_at']) <= time()
                || (int) $otp['attempts'] >= (int) config('verification.max_attempts', 5)) { return false; }
            $this->db->run('UPDATE public_action_otps SET attempts = attempts + 1 WHERE id = ?', [$otp['id']]);
            if (!hash_equals($otp['subject_hash'], $this->currentSubject($purpose)) || !password_verify($code, $otp['code_hash'])) { return false; }
            $this->db->run('UPDATE public_action_otps SET used_at = NOW(), verified_at = NOW(), grant_expires_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s', time() + (int) config('verification.public_grant_minutes', 30) * 60), $otp['id']]);
            return true;
        });
        if ($valid && session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); }
        return $valid;
    }

    public function quoteContact(): ?array
    {
        return $this->granted('quote') ? ($_SESSION['_public_quote_contact'] ?? null) : null;
    }

    public function trackingAllowed(string $number): bool
    {
        if (!$this->required()) { return true; }
        return strtoupper(trim($number)) === ($_SESSION['_public_tracking_number'] ?? null) && $this->granted('track');
    }

    public function withQuote(array $input, callable $submit): mixed
    {
        if (!$this->required()) { return $submit(); }
        return $this->db->transaction(function () use ($input, $submit): mixed {
            $otp = $this->challenge('quote', true);
            if (!$this->granted('quote', $otp) || !hash_equals($otp['subject_hash'], $this->subject($this->contact($input)))) {
                throw new ValidationException(['verification' => 'Verify your contact details before requesting a quote.'], $input);
            }
            $result = $submit();
            $this->db->run('UPDATE public_action_otps SET consumed_at = NOW() WHERE id = ?', [$otp['id']]);
            return $result;
        });
    }

    private function granted(string $purpose, ?array $otp = null): bool
    {
        $otp ??= $this->challenge($purpose);
        return $otp !== null && $otp['verified_at'] !== null && $otp['consumed_at'] === null
            && $otp['grant_expires_at'] !== null && strtotime($otp['grant_expires_at']) > time()
            && hash_equals($otp['subject_hash'], $this->currentSubject($purpose));
    }

    public function pending(string $purpose): ?array
    {
        $otp = $this->challenge($purpose);
        if ($otp === null || $otp['verified_at'] !== null || $otp['used_at'] !== null) { return null; }
        return ['channel' => $otp['channel'], 'destination' => $purpose === 'quote' ? CustomerOtpService::mask($otp['destination']) : null,
            'expires_at' => $otp['expires_at']];
    }

    public function resetQuote(): void { unset($_SESSION['_public_otp']['quote'], $_SESSION['_public_quote_contact']); }

    private function challenge(string $purpose, bool $lock = false): ?array
    {
        return $this->db->fetch('SELECT * FROM public_action_otps WHERE id = ? AND session_hash = ? AND purpose = ?' . ($lock ? ' FOR UPDATE' : ''),
            [(int) ($_SESSION['_public_otp'][$purpose] ?? 0), $this->sessionHash(), $purpose]);
    }

    private function subject(array $contact): string { return hash('sha256', json_encode($contact, JSON_THROW_ON_ERROR)); }
    private function currentSubject(string $purpose): string
    {
        if ($purpose === 'quote') { return $this->subject($_SESSION['_public_quote_contact'] ?? []); }
        $number = $_SESSION['_public_tracking_number'] ?? '';
        return $this->trackingSubject($number, $this->trackingContact($number));
    }
    private function trackingSubject(string $number, ?array $contact): string { return $this->subject(['number' => $number, 'contact' => $contact]); }
    private function trackingContact(string $number): ?array
    {
        return $this->db->fetch("SELECT c.id, cu.id AS customer_id, cu.name, LOWER(cu.email) AS email, COALESCE(NULLIF(cu.phone, ''), u.phone) AS phone
            FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id LEFT JOIN users u ON u.id = cu.user_id
            WHERE c.number = ? AND c.deleted_at IS NULL AND cu.deleted_at IS NULL", [$number]);
    }
}
