<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

final class SmsService
{
    public function __construct(private Database $db, private SmslenzClient $client) {}

    public function ready(): bool { return $this->client->ready(); }

    public function queue(?string $phone, string $name, string $message, string $type, int $id, ?string $expires = null): array
    {
        $recipient = SmslenzClient::normalizePhone($phone);
        $status = $recipient === null ? 'invalid' : ($this->ready() ? 'queued' : 'blocked');
        $error = $recipient === null ? 'invalid_phone' : ($status === 'blocked' ? 'sms_not_configured' : null);
        $messageId = $this->db->insert('INSERT INTO sms_messages (recipient, contact_name, message, status, error_code, related_type, related_id, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$recipient, mb_substr($name, 0, 160), mb_substr($message, 0, 1500), $status, $error, $type, $id, $expires]);
        return ['id' => $messageId, 'status' => $status, 'error_code' => $error];
    }

    public function processQueue(int $limit = 20): array
    {
        if ($this->db->pdo()->inTransaction()) { throw new \LogicException('SMS delivery must run outside business transactions.'); }
        if (!$this->ready()) { return []; }
        $this->db->run("UPDATE sms_messages SET status = 'uncertain', error_code = 'interrupted_delivery', claim_token = NULL
            WHERE status = 'sending' AND claimed_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $results = [];
        for ($i = 0; $i < max(1, min(100, $limit)); $i++) {
            $row = $this->db->transaction(function (): ?array {
                $row = $this->db->fetch("SELECT * FROM sms_messages WHERE status = 'queued' ORDER BY id LIMIT 1 FOR UPDATE");
                if ($row === null) { return null; }
                $row['claim_token'] = bin2hex(random_bytes(16));
                $this->db->run("UPDATE sms_messages SET status = 'sending', attempts = attempts + 1, claim_token = ?, claimed_at = NOW() WHERE id = ?", [$row['claim_token'], $row['id']]);
                return $row;
            });
            if ($row === null) { break; }
            $result = $row['expires_at'] !== null && strtotime($row['expires_at']) <= time()
                ? ['status' => 'expired', 'error_code' => 'expired_message'] : $this->client->send($row);
            $this->db->run('UPDATE sms_messages SET status = ?, error_code = ?, campaign_id = ?, sent_at = ?, claim_token = NULL WHERE id = ? AND claim_token = ?',
                [$result['status'], $result['error_code'], $result['campaign_id'] ?? null, $result['status'] === 'sent' ? date('Y-m-d H:i:s') : null, $row['id'], $row['claim_token']]);
            $results[] = ['id' => (int) $row['id']] + $result;
        }
        return $results;
    }
}
