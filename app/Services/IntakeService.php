<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class IntakeService
{
    public function __construct(
        private Database $db,
        private MailService $mail,
        private DocumentService $documents,
        private AuditService $audit
    ) {
    }

    public function quote(array $data, array $serviceIds, array $files): int
    {
        $this->throttle('quote');
        $id = $this->db->transaction(function () use ($data, $serviceIds): int {
            $requestId = $this->db->insert(
                'INSERT INTO quote_requests (name, email, phone, whatsapp, country_id, document_type_id, document_count, message, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'new\')',
                [
                    $data['name'],
                    mb_strtolower($data['email']),
                    $data['phone'] ?? null,
                    $data['whatsapp'] ?? null,
                    $data['country_id'] ?: null,
                    $data['document_type_id'] ?: null,
                    max(1, (int) ($data['document_count'] ?? 1)),
                    $data['message'] ?? null,
                ]
            );
            foreach ($serviceIds as $serviceId) {
                $this->db->run(
                    'INSERT IGNORE INTO quote_request_services (quote_request_id, service_id) VALUES (?, ?)',
                    [$requestId, (int) $serviceId]
                );
            }

            return $requestId;
        });
        foreach ($files as $file) {
            $this->documents->store(null, $id, $file, 'customer', 'enquiry', 'Quote request attachment', false, null);
        }
        $this->mail->send(
            $data['email'],
            __('mail.quote_request_subject'),
            'quote-request',
            ['name' => $data['name']],
            'quote_request',
            $id
        );
        $this->audit->log(null, 'quote_requested', 'quote_request', $id);

        return $id;
    }

    public function contact(array $data): int
    {
        $this->throttle('contact');
        $id = $this->db->insert(
            'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], mb_strtolower($data['email']), $data['phone'] ?? null, $data['subject'] ?? null, $data['message']]
        );
        $this->mail->send($data['email'], __('mail.contact_subject'), 'contact-received', ['name' => $data['name']], 'contact', $id);

        return $id;
    }

    private function throttle(string $action): void
    {
        $ip = client_ip();
        $row = $this->db->fetch('SELECT * FROM rate_limits WHERE action_key = ? AND ip = ?', [$action, $ip]);
        if ($row === null || strtotime((string) $row['window_start']) < time() - 3600) {
            $this->db->run(
                'INSERT INTO rate_limits (action_key, ip, attempts, window_start) VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE attempts = 1, window_start = NOW()',
                [$action, $ip]
            );

            return;
        }
        if ((int) $row['attempts'] >= 8) {
            throw new \App\Core\HttpException(429, __('validation.throttled'));
        }
        $this->db->run('UPDATE rate_limits SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
    }
}
