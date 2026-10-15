<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\View;
use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function __construct(private Database $db, private SettingsService $settings)
    {
    }

    public function send(string $to, string $subject, string $template, array $data = [], ?string $relatedType = null, ?int $relatedId = null, ?string $attachmentPath = null, ?string $attachmentName = null): array
    {
        $company = $this->company();
        $inner = View::render('emails/' . $template, array_merge($data, [
            'subject' => $subject,
            'company' => $company,
        ]));
        $html = View::render('emails/layout', ['body' => $inner, 'company' => $company]);
        $stored = $this->storeBody($html);
        $configured = $this->smtpReady();
        $status = 'logged';
        $error = null;
        $sentAt = null;

        if ($configured) {
            try {
                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = (string) config('mail.host');
                $mailer->Port = (int) config('mail.port');
                $mailer->SMTPAuth = (string) config('mail.username') !== '';
                $mailer->Username = (string) config('mail.username');
                $mailer->Password = (string) config('mail.password');
                $encryption = (string) config('mail.encryption');
                if ($encryption !== '' && $encryption !== 'none') {
                    $mailer->SMTPSecure = $encryption;
                }
                $mailer->CharSet = 'UTF-8';
                $mailer->setFrom($this->fromAddress(), $this->fromName());
                $mailer->addAddress($to);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body = $html;
                $mailer->AltBody = trim(html_entity_decode(strip_tags($html)));
                if ($attachmentPath !== null && is_file($attachmentPath)) {
                    $mailer->addAttachment($attachmentPath, $attachmentName ?: basename($attachmentPath));
                }
                $mailer->send();
                $status = 'sent';
                $sentAt = date('Y-m-d H:i:s');
            } catch (MailerException $exception) {
                $status = 'failed';
                $error = substr($exception->getMessage(), 0, 500);
            } catch (\Throwable $exception) {
                $status = 'failed';
                $error = substr($exception->getMessage(), 0, 500);
            }
        } else {
            $error = 'SMTP is not configured. The message was saved to the mail log.';
        }

        $id = $this->db->insert(
            'INSERT INTO email_logs (recipient, subject, template, status, error_message, related_type, related_id, body_path, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$to, $subject, $template, $status, $error, $relatedType, $relatedId, $stored, $sentAt]
        );

        return ['id' => $id, 'status' => $status, 'error' => $error];
    }

    public function resend(int $logId): array
    {
        $log = $this->db->fetch('SELECT * FROM email_logs WHERE id = ?', [$logId]);
        if ($log === null) {
            return ['status' => 'failed', 'error' => 'Email log not found.'];
        }
        $path = storage_path('mail/' . $log['body_path']);
        $html = is_file($path) ? (string) file_get_contents($path) : '<p>' . e($log['subject']) . '</p>';
        if (!$this->smtpReady()) {
            $this->db->run(
                'UPDATE email_logs SET status = ?, error_message = ? WHERE id = ?',
                ['logged', 'SMTP is not configured. The message was saved to the mail log.', $logId]
            );

            return ['status' => 'logged', 'error' => 'SMTP is not configured. The message was saved to the mail log.'];
        }
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = (string) config('mail.host');
            $mailer->Port = (int) config('mail.port');
            $mailer->SMTPAuth = (string) config('mail.username') !== '';
            $mailer->Username = (string) config('mail.username');
            $mailer->Password = (string) config('mail.password');
            $encryption = (string) config('mail.encryption');
            if ($encryption !== '' && $encryption !== 'none') {
                $mailer->SMTPSecure = $encryption;
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($this->fromAddress(), $this->fromName());
            $mailer->addAddress($log['recipient']);
            $mailer->isHTML(true);
            $mailer->Subject = $log['subject'];
            $mailer->Body = $html;
            $mailer->send();
            $this->db->run('UPDATE email_logs SET status = ?, error_message = NULL, sent_at = NOW() WHERE id = ?', ['sent', $logId]);

            return ['status' => 'sent', 'error' => null];
        } catch (\Throwable $exception) {
            $message = substr($exception->getMessage(), 0, 500);
            $this->db->run('UPDATE email_logs SET status = ?, error_message = ? WHERE id = ?', ['failed', $message, $logId]);

            return ['status' => 'failed', 'error' => $message];
        }
    }

    public function smtpReady(): bool
    {
        return (string) config('mail.host') !== '' && (string) config('mail.from_address') !== '';
    }

    private function storeBody(string $html): string
    {
        $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.html';
        $dir = storage_path('mail');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/' . $name, $html);

        return $name;
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
        return [
            'name' => (string) $this->settings->get('company_name', 'AtteCert'),
            'email' => (string) $this->settings->get('email', ''),
            'phone' => (string) $this->settings->get('phone', ''),
            'address' => (string) $this->settings->get('address', ''),
        ];
    }
}
