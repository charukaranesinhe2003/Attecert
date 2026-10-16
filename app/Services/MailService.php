<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\View;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/** Business requests persist mail. Only the explicit CLI worker contacts SMTP. */
final class MailService
{
    private ?bool $queueAvailable = null;

    public function __construct(private Database $db, private SettingsService $settings, private AuditService $audit)
    {
    }

    public function send(string $to, string $subject, string $template, array $data = [], ?string $relatedType = null, ?int $relatedId = null, ?string $attachmentPath = null, ?string $attachmentName = null): array
    {
        $company = $this->company();
        $inner = View::render('emails/' . $template, array_merge($data, ['subject' => $subject, 'company' => $company]));
        $html = View::render('emails/layout', ['body' => $inner, 'company' => $company]);
        $body = $this->storeFile($html, '.html');
        $payload = $this->snapshot($body, $attachmentPath, $attachmentName);
        // The sidecar preserves exact attachments before an older deployment applies migrations.
        $this->writeFile($this->savedPath($body . '.json'), json_encode($payload, JSON_THROW_ON_ERROR));
        [$status, $error] = $this->initialStatus();

        return $this->db->transaction(function () use ($to, $subject, $template, $relatedType, $relatedId, $body, $payload, $status, $error): array {
            $id = $this->db->insert('INSERT INTO email_logs (recipient, subject, template, status, error_message, related_type, related_id, body_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$to, $subject, $template, $status, $error, $relatedType, $relatedId, $body]);
            if ($this->queueAvailable()) $this->insertOutbox($id, $payload);

            return ['id' => $id, 'status' => $status, 'error' => $error];
        });
    }

    /** Queue one saved message; do not replay a delivered or actively claimed message. */
    public function resend(int $logId, bool $confirmedUncertain = false): array
    {
        return $this->db->transaction(function () use ($logId, $confirmedUncertain): array {
            $log = $this->db->fetch('SELECT * FROM email_logs WHERE id = ? FOR UPDATE', [$logId]);
            if ($log === null) return ['id' => $logId, 'status' => 'failed', 'error' => 'Email log not found.'];
            if (in_array($log['status'], ['sent', 'sending', 'queued'], true)) {
                return ['id' => $logId, 'status' => $log['status'], 'error' => $log['status'] === 'sending' ? 'Delivery is already in progress.' : null];
            }
            if ($log['status'] === 'uncertain' && !$confirmedUncertain) {
                return ['id' => $logId, 'status' => 'uncertain', 'error' => 'Check the SMTP provider for delivery before confirming a retry; it may send a duplicate.'];
            }
            try {
                $outbox = $this->queueAvailable() ? $this->db->fetch('SELECT payload FROM mail_outbox WHERE email_log_id = ?', [$logId]) : null;
                $payload = $outbox !== null ? json_decode($outbox['payload'], true, 512, JSON_THROW_ON_ERROR) : $this->legacyPayload($log);
                $this->loadPayload($payload);
            } catch (\Throwable $exception) {
                $error = 'Saved email cannot be recovered. Re-send from the original record. ' . $this->safeError($exception);
                $this->db->run('UPDATE email_logs SET status = ?, error_message = ? WHERE id = ?', ['failed', substr($error, 0, 500), $logId]);

                return ['id' => $logId, 'status' => 'failed', 'error' => $error];
            }
            [$status, $error] = $this->initialStatus();
            if ($this->queueAvailable()) {
                if ($outbox === null) $this->insertOutbox($logId, $payload);
                else $this->db->run('UPDATE mail_outbox SET attempts = 0, available_at = NOW(), claim_token = NULL, claimed_at = NULL, finished_at = NULL WHERE email_log_id = ?', [$logId]);
            }
            $this->db->run('UPDATE email_logs SET status = ?, error_message = ? WHERE id = ?', [$status, $error, $logId]);

            return ['id' => $logId, 'status' => $status, 'error' => $error];
        });
    }

    public function smtpReady(): bool
    {
        return (string) config('mail.host') !== '' && (string) config('mail.from_address') !== '';
    }

    public function queueAvailable(): bool
    {
        return $this->queueAvailable ??= (bool) $this->db->fetchColumn("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'mail_outbox'");
    }

    /** Delivery is forbidden inside business transactions. */
    public function processQueue(int $limit = 20, ?int $onlyLogId = null): array
    {
        if ($this->db->pdo()->inTransaction()) throw new \LogicException('Mail delivery must run outside database transactions.');
        if (!$this->queueAvailable()) throw new \RuntimeException('Apply the mail outbox migration before starting the worker.');
        $this->recoverInterrupted();
        if (!$this->smtpReady()) return [];
        $results = [];
        for ($i = 0; $i < max(1, min(100, $limit)); $i++) {
            $claim = $this->claim($onlyLogId);
            if ($claim === null) break;
            $results[] = $this->deliver($claim);
        }

        return $results;
    }

    /** A lost process may have sent DATA. Never retry ambiguous delivery automatically. */
    public function recoverInterrupted(): int
    {
        if ($this->db->pdo()->inTransaction()) throw new \LogicException('Mail recovery must run outside database transactions.');
        if (!$this->queueAvailable()) return 0;
        $cutoff = date('Y-m-d H:i:s', time() - 1800);

        return $this->db->transaction(function () use ($cutoff): int {
            $rows = $this->db->fetchAll("SELECT e.id FROM email_logs e JOIN mail_outbox o ON o.email_log_id = e.id WHERE e.status = 'sending' AND o.claimed_at < ? FOR UPDATE", [$cutoff]);
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $this->db->run("UPDATE email_logs SET status = 'uncertain', error_message = ? WHERE id = ?", ['The worker stopped during delivery. Check the SMTP provider before confirming a retry.', $id]);
                $this->db->run('UPDATE mail_outbox SET claim_token = NULL, finished_at = NOW() WHERE email_log_id = ?', [$id]);
                $this->audit->log(null, 'email_delivery_uncertain', 'email_log', $id);
            }

            return count($rows);
        });
    }

    private function claim(?int $onlyLogId = null): ?array
    {
        return $this->db->transaction(function () use ($onlyLogId): ?array {
            $sql = "SELECT e.*, o.payload, o.attempts, o.max_attempts FROM email_logs e JOIN mail_outbox o ON o.email_log_id = e.id WHERE e.status = 'queued' AND o.available_at <= NOW() AND o.attempts < o.max_attempts";
            if ($onlyLogId !== null) $sql .= ' AND e.id = ?';
            $row = $this->db->fetch($sql . ' ORDER BY o.available_at, e.id LIMIT 1 FOR UPDATE', $onlyLogId === null ? [] : [$onlyLogId]);
            if ($row === null) return null;
            $row['claim_token'] = bin2hex(random_bytes(24));
            $row['attempts'] = (int) $row['attempts'] + 1;
            $this->db->run('UPDATE mail_outbox SET claim_token = ?, claimed_at = NOW(), last_attempt_at = NOW(), attempts = attempts + 1 WHERE email_log_id = ?', [$row['claim_token'], $row['id']]);
            $this->db->run("UPDATE email_logs SET status = 'sending', error_message = NULL WHERE id = ?", [$row['id']]);
            $this->audit->log(null, 'email_delivery_started', 'email_log', (int) $row['id'], ['attempt' => $row['attempts']]);

            return $row;
        });
    }

    private function deliver(array $claim): array
    {
        $transport = new class extends SMTP {
            public bool $dataStarted = false;
            public int $failureCode = 0;
            protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
            {
                // Socket errors (for example Windows 10061) are not SMTP rejection codes.
                if ((int) $smtp_code >= 400 && (int) $smtp_code <= 599) $this->failureCode = (int) $smtp_code;
                parent::setError($message, $detail, $smtp_code, $smtp_code_ex);
            }
            public function data($msg_data)
            {
                $this->dataStarted = true;
                $this->failureCode = 0;
                return parent::data($msg_data);
            }
        };
        $mailer = new PHPMailer(true);
        $status = 'failed';
        $error = null;
        try {
            $payload = json_decode($claim['payload'], true, 512, JSON_THROW_ON_ERROR);
            [$html, $attachment] = $this->loadPayload($payload);
            if (!PHPMailer::validateAddress($claim['recipient']) || !PHPMailer::validateAddress($payload['from_address'])) {
                throw new \RuntimeException('The saved sender or recipient email address is invalid.');
            }
            $mailer->isSMTP();
            $mailer->setSMTPInstance($transport);
            $mailer->Host = (string) config('mail.host');
            $mailer->Port = (int) config('mail.port');
            $mailer->SMTPAuth = (string) config('mail.username') !== '';
            $mailer->Username = (string) config('mail.username');
            $mailer->Password = (string) config('mail.password');
            $encryption = (string) config('mail.encryption');
            $mailer->SMTPSecure = in_array($encryption, ['', 'none'], true) ? '' : $encryption;
            $mailer->SMTPAutoTLS = !in_array($encryption, ['', 'none'], true);
            $mailer->Timeout = max(1, min(120, (int) config('mail.timeout', 30)));
            $transport->Timelimit = $mailer->Timeout;
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($payload['from_address'], $payload['from_name']);
            $mailer->addAddress($claim['recipient']);
            $mailer->isHTML(true);
            $mailer->Subject = $claim['subject'];
            $mailer->Body = $html;
            $mailer->AltBody = trim(html_entity_decode(strip_tags($html)));
            $mailer->MessageID = $payload['message_id'];
            if ($attachment !== null) $mailer->addStringAttachment($attachment, $payload['attachment_name']);
            $mailer->send();
            $status = 'sent';
        } catch (\Throwable $exception) {
            $code = $transport->failureCode;
            if ($transport->dataStarted && $code === 0) {
                $status = 'uncertain';
                $error = 'SMTP disconnected during delivery. Check the SMTP provider before confirming a retry.';
            } elseif ($exception instanceof \PHPMailer\PHPMailer\Exception && $code < 500 && $claim['attempts'] < $claim['max_attempts']) {
                $status = 'queued';
                $error = 'Temporary SMTP failure; a later worker run will retry. ' . $this->safeError($exception);
            } else $error = $this->safeError($exception);
        } finally {
            $mailer->smtpClose();
        }

        return $this->finish($claim, $status, $error);
    }

    private function finish(array $claim, string $status, ?string $error): array
    {
        return $this->db->transaction(function () use ($claim, $status, $error): array {
            $row = $this->db->fetch('SELECT e.status, o.claim_token FROM email_logs e JOIN mail_outbox o ON o.email_log_id = e.id WHERE e.id = ? FOR UPDATE', [$claim['id']]);
            if ($row === null || $row['status'] !== 'sending' || !hash_equals((string) $row['claim_token'], $claim['claim_token'])) {
                return ['id' => (int) $claim['id'], 'status' => 'uncertain', 'error' => 'The delivery claim changed. Check the SMTP provider before retrying.'];
            }
            $this->db->run('UPDATE email_logs SET status = ?, error_message = ?, sent_at = CASE WHEN ? = ? THEN NOW() ELSE sent_at END WHERE id = ?', [$status, $error === null ? null : substr($error, 0, 500), $status, 'sent', $claim['id']]);
            $retryAt = date('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** ($claim['attempts'] - 1))));
            $this->db->run('UPDATE mail_outbox SET claim_token = NULL, available_at = ?, finished_at = CASE WHEN ? = ? THEN NULL ELSE NOW() END WHERE email_log_id = ?', [$retryAt, $status, 'queued', $claim['id']]);
            if ($status === 'sent' && $claim['template'] === 'quotation' && $claim['related_type'] === 'quotation') $this->db->run('UPDATE quotations SET sent_at = NOW() WHERE id = ?', [$claim['related_id']]);
            if ($status === 'sent' && $claim['template'] === 'invoice' && $claim['related_type'] === 'invoice') $this->db->run("UPDATE invoices SET status = CASE WHEN status = 'issued' THEN 'sent' ELSE status END, sent_at = NOW() WHERE id = ?", [$claim['related_id']]);
            $this->audit->log(null, 'email_delivery_' . $status, 'email_log', (int) $claim['id'], ['attempt' => $claim['attempts']]);

            return ['id' => (int) $claim['id'], 'status' => $status, 'error' => $error];
        });
    }

    private function initialStatus(): array
    {
        if (!$this->queueAvailable()) return ['logged', 'Mail queue migration is required. The message is saved; apply migrations and queue a retry.'];
        if (!$this->smtpReady()) return ['logged', 'SMTP is not configured. The message was saved to the mail log.'];

        return ['queued', null];
    }

    private function insertOutbox(int $id, array $payload): void
    {
        $this->db->run('INSERT INTO mail_outbox (email_log_id, payload, max_attempts) VALUES (?, ?, ?)', [$id, json_encode($payload, JSON_THROW_ON_ERROR), max(1, min(10, (int) config('mail.max_attempts', 3)))]);
    }

    private function snapshot(string $body, ?string $attachmentPath, ?string $attachmentName): array
    {
        $from = $this->fromAddress();
        $domain = substr(strrchr($from, '@') ?: '@attecert.local', 1);
        if (!preg_match('/^[A-Za-z0-9.-]+$/D', $domain)) $domain = 'attecert.local';
        $payload = ['body_path' => $body, 'body_hash' => hash_file('sha256', $this->savedPath($body)), 'from_address' => $from, 'from_name' => $this->fromName(), 'message_id' => '<' . bin2hex(random_bytes(20)) . '@' . $domain . '>', 'attachment_path' => null, 'attachment_name' => null, 'attachment_hash' => null];
        if ($attachmentPath !== null) {
            $bytes = is_file($attachmentPath) ? file_get_contents($attachmentPath) : false;
            if ($bytes === false) throw new \RuntimeException('The email attachment is missing or unreadable.');
            $payload['attachment_path'] = $this->storeFile($bytes, '.attachment');
            $payload['attachment_name'] = $attachmentName ?: basename($attachmentPath);
            $payload['attachment_hash'] = hash('sha256', $bytes);
        }

        return $payload;
    }

    private function legacyPayload(array $log): array
    {
        $body = (string) ($log['body_path'] ?? '');
        $path = $this->savedPath($body);
        if (!is_file($path)) throw new \RuntimeException('The saved email body is missing.');
        if (is_file($path . '.json')) return json_decode((string) file_get_contents($path . '.json'), true, 512, JSON_THROW_ON_ERROR);
        // Old logs have no original attachment bytes. Reconstruct once and freeze subsequent attempts.
        $attachment = $this->resendAttachment($log);
        $payload = $this->snapshot($body, $attachment['path'] ?? null, $attachment['name'] ?? null);
        $this->writeFile($path . '.json', json_encode($payload, JSON_THROW_ON_ERROR));

        return $payload;
    }

    private function loadPayload(array $payload): array
    {
        $read = function (string $file, string $hash): string {
            $path = $this->savedPath($file);
            $bytes = is_file($path) ? file_get_contents($path) : false;
            if ($bytes === false || !hash_equals($hash, hash('sha256', $bytes))) throw new \RuntimeException('A saved email file is missing or has changed.');

            return $bytes;
        };
        $html = $read($payload['body_path'], $payload['body_hash']);
        $attachment = $payload['attachment_path'] === null ? null : $read($payload['attachment_path'], $payload['attachment_hash']);

        return [$html, $attachment];
    }

    private function savedPath(string $name): string
    {
        if ($name === '' || basename($name) !== $name || str_contains($name, '\\') || str_contains($name, ':')) throw new \RuntimeException('Invalid saved email filename.');

        return storage_path('mail/' . $name);
    }

    private function storeFile(string $bytes, string $extension): string
    {
        $name = date('Ymd') . '-' . bin2hex(random_bytes(16)) . $extension;
        $dir = storage_path('mail');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('The mail storage directory could not be created.');
        $this->writeFile($this->savedPath($name), $bytes);

        return $name;
    }

    private function writeFile(string $path, string $bytes): void
    {
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new \RuntimeException('The email could not be saved. Check private storage permissions.');
        @chmod($path, 0600);
    }

    private function safeError(\Throwable $exception): string
    {
        $message = $exception->getMessage();
        foreach (['mail.password', 'mail.username'] as $key) {
            $secret = (string) config($key);
            if ($secret !== '') $message = str_replace($secret, '[redacted]', $message);
        }

        return substr($message, 0, 350);
    }

    private function resendAttachment(array $log): ?array
    {
        $id = (int) ($log['related_id'] ?? 0);
        if ($log['template'] === 'quotation' && $log['related_type'] === 'quotation' && $id > 0) {
            $service = app(QuotationService::class);
            $bundle = $service->bundle($id);
            return ['path' => $service->pdfPath($id), 'name' => $bundle['quotation']['number'] . '.pdf'];
        }
        if ($log['template'] === 'invoice' && $log['related_type'] === 'invoice' && $id > 0) {
            $service = app(InvoiceService::class);
            $bundle = $service->bundle($id);
            return ['path' => $service->pdfPath($id), 'name' => $bundle['invoice']['number'] . '.pdf'];
        }

        return null;
    }

    private function fromAddress(): string
    {
        $configured = (string) config('mail.from_address');
        return $configured !== '' ? $configured : (string) $this->settings->get('email', 'hello@attecert.lk');
    }

    private function fromName(): string
    {
        $configured = (string) config('mail.from_name');
        return $configured !== '' ? $configured : (string) $this->settings->get('company_name', 'AtteCert');
    }

    private function company(): array
    {
        return ['name' => (string) $this->settings->get('company_name', 'AtteCert'), 'email' => (string) $this->settings->get('email', ''), 'phone' => (string) $this->settings->get('phone', ''), 'address' => (string) $this->settings->get('address', '')];
    }
}
