<?php
declare(strict_types=1);
namespace App\Services;

final class SmslenzClient
{
    public static function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/[\s().-]+/', '', trim((string) $phone));
        if (str_starts_with($phone, '00')) { $phone = '+' . substr($phone, 2); }
        if (preg_match('/^07[0-9]{8}$/D', $phone)) { $phone = '+94' . substr($phone, 1); }
        if (preg_match('/^947[0-9]{8}$/D', $phone)) { $phone = '+' . $phone; }
        return preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) ? $phone : null;
    }

    public function ready(): bool
    {
        return (string) config('sms.user_id') !== '' && (string) config('sms.api_key') !== ''
            && (string) config('sms.sender_id') !== '' && $this->endpoint() !== null && function_exists('curl_init');
    }

    private function endpoint(): ?string
    {
        $base = rtrim((string) config('sms.base_url'), '/');
        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) { return null; }
        $local = in_array(config('app.env'), ['local', 'test'], true) && in_array($parts['host'], ['127.0.0.1', 'localhost'], true);
        if (($parts['scheme'] ?? '') !== 'https' && !(($parts['scheme'] ?? '') === 'http' && $local)) { return null; }
        return $base . '/send-sms';
    }

    public function send(array $message): array
    {
        if (!$this->ready()) { return ['status' => 'blocked', 'error_code' => 'sms_not_configured']; }
        $curl = curl_init($this->endpoint());
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => max(2, min(60, (int) config('sms.timeout', 15))),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['user_id' => config('sms.user_id'), 'api_key' => config('sms.api_key'),
                'sender_id' => config('sms.sender_id'), 'contact' => $message['recipient'],
                'message' => $message['message'], 'contact_name' => $message['contact_name']], JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_errno($curl);
        curl_close($curl);
        if ($body === false || $error !== 0) { return ['status' => 'uncertain', 'error_code' => 'sms_transport_uncertain']; }
        $data = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($data) && ($data['success'] ?? false) === true && ($data['data']['status'] ?? '') === 'success') {
            $campaign = $data['data']['campaign_id'] ?? '';
            return ['status' => 'sent', 'error_code' => null, 'campaign_id' => is_scalar($campaign) ? mb_substr((string) $campaign, 0, 80) : ''];
        }
        // Never log provider bodies or credentials. Ambiguous responses must not be retried automatically.
        return ['status' => ($status >= 500 || ($status >= 200 && $status < 300 && !is_array($data))) ? 'uncertain' : 'failed', 'error_code' => 'sms_provider_rejected'];
    }
}
