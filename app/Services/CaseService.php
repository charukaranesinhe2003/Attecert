<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class CaseService
{
    public function __construct(
        private Database $db,
        private NumberService $numbers,
        private WorkflowService $workflow,
        private AuditService $audit,
        private MailService $mail,
        private CatalogService $catalog
    ) {
    }

    public function create(array $data, array $lines, ?int $staffId): int
    {
        return $this->db->transaction(function () use ($data, $lines, $staffId): int {
            $number = $this->numbers->next('case', 'ATT');
            $statusId = $this->workflow->idFor('new_request');
            $caseId = $this->db->insert(
                'INSERT INTO cases (number, customer_id, country_id, document_type_id, document_title, summary, status_id, assigned_to, source, quote_request_id, currency, created_by, customer_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $number,
                    $data['customer_id'],
                    $data['country_id'] ?: null,
                    $data['document_type_id'] ?: null,
                    $data['document_title'],
                    $data['summary'] ?? null,
                    $statusId,
                    ($data['assigned_to'] ?? null) ?: null,
                    $data['source'] ?? 'admin',
                    $data['quote_request_id'] ?? null,
                    $data['currency'] ?? setting('currency', 'LKR'),
                    $staffId,
                    $data['customer_notes'] ?? null,
                ]
            );
            foreach ($lines as $line) {
                $service = $this->catalog->service((int) $line['service_id']);
                if ($service === null) {
                    continue;
                }
                $price = $this->catalog->priceFor($service, $data['country_id'] ? (int) $data['country_id'] : null);
                $this->db->insert(
                    'INSERT INTO case_services (case_id, service_id, quantity, pricing_type, unit_price, currency) VALUES (?, ?, ?, ?, ?, ?)',
                    [$caseId, $service['id'], $line['quantity'] ?? 1, $service['pricing_type'], $price, $service['currency']]
                );
            }
            $this->db->insert(
                'INSERT INTO case_status_history (case_id, status_id, staff_id, notes, customer_visible) VALUES (?, ?, ?, ?, 1)',
                [$caseId, $statusId, $staffId, 'Case opened']
            );
            $this->audit->log($staffId, 'case_created', 'case', $caseId, ['number' => $number]);

            return $caseId;
        });
    }

    public function createFromQuote(int $quoteRequestId, ?int $staffId): int
    {
        $request = $this->db->fetch('SELECT * FROM quote_requests WHERE id = ?', [$quoteRequestId]);
        if ($request === null) {
            throw new \RuntimeException('Quote request not found.');
        }
        if ($request['converted_case_id'] !== null) {
            return (int) $request['converted_case_id'];
        }
        $customerId = $this->ensureCustomer($request, $staffId);
        $services = $this->db->fetchAll('SELECT service_id FROM quote_request_services WHERE quote_request_id = ?', [$quoteRequestId]);
        $lines = array_map(static fn (array $row): array => ['service_id' => $row['service_id'], 'quantity' => $request['document_count'] ?: 1], $services);
        $documentTitle = 'Document request';
        if (!empty($request['document_type_id'])) {
            $typeName = $this->db->fetchColumn(
                'SELECT name FROM document_type_translations WHERE document_type_id = ? AND locale = \'en\'',
                [(int) $request['document_type_id']]
            );
            if (is_string($typeName) && $typeName !== '') {
                $documentTitle = $typeName;
            }
        }
        $caseId = $this->create([
            'customer_id' => $customerId,
            'country_id' => $request['country_id'],
            'document_type_id' => $request['document_type_id'],
            'document_title' => $documentTitle,
            'summary' => $request['message'],
            'source' => 'quote_request',
            'quote_request_id' => $quoteRequestId,
            'customer_notes' => $request['message'],
        ], $lines, $staffId);
        $this->db->run(
            'UPDATE quote_requests SET status = ?, converted_case_id = ? WHERE id = ?',
            ['converted', $caseId, $quoteRequestId]
        );
        $case = $this->find($caseId);
        $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ?', [$customerId]);
        if ($customer !== null) {
            $this->mail->send(
                $customer['email'],
                __('mail.case_subject', ['number' => $case['number']]),
                'case-opened',
                ['name' => $customer['name'], 'number' => $case['number'], 'link' => absolute_url('/login')],
                'case',
                $caseId
            );
        }

        return $caseId;
    }

    public function ensureCustomer(array $request, ?int $staffId): int
    {
        $email = mb_strtolower(trim((string) $request['email']));
        $existing = $this->db->fetch('SELECT * FROM customers WHERE email = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$email]);
        if ($existing) {
            return (int) $existing['id'];
        }
        $user = $this->db->fetch('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        if ($user === null) {
            $userId = $this->db->insert(
                'INSERT INTO users (name, email, phone, password_hash, must_change_password, email_verified_at) VALUES (?, ?, ?, ?, 1, NOW())',
                [$request['name'], $email, $request['phone'] ?? null, password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT)]
            );
            $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', ['customer']);
            $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
            $this->mail->send($email, __('mail.welcome_subject'), 'welcome', [
                'name' => $request['name'],
                'link' => absolute_url('/forgot-password'),
            ], 'user', $userId);
        } else {
            $userId = (int) $user['id'];
        }
        $customerId = $this->db->insert(
            'INSERT INTO customers (user_id, name, email, phone, whatsapp) VALUES (?, ?, ?, ?, ?)',
            [$userId, $request['name'], $email, $request['phone'] ?? null, $request['whatsapp'] ?? null]
        );
        $this->audit->log($staffId, 'customer_created', 'customer', $customerId);

        return $customerId;
    }

    public function find(int $id): ?array
    {
        $case = $this->db->fetch(
            'SELECT c.*, cu.name AS customer_name, cu.email AS customer_email, cu.phone AS customer_phone,
                    cu.whatsapp AS customer_whatsapp, cu.user_id,
                    co.slug AS country_slug, ct.name AS country_name, dt.code AS document_code,
                    dtt.name AS document_type_name, s.code AS status_code, s.badge_tone, st.name AS status_name,
                    au.name AS assignee_name
             FROM cases c
             INNER JOIN customers cu ON cu.id = c.customer_id
             LEFT JOIN countries co ON co.id = c.country_id
             LEFT JOIN country_translations ct ON ct.country_id = co.id AND ct.locale = ?
             LEFT JOIN document_types dt ON dt.id = c.document_type_id
             LEFT JOIN document_type_translations dtt ON dtt.document_type_id = dt.id AND dtt.locale = ?
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
             LEFT JOIN users au ON au.id = c.assigned_to
             WHERE c.id = ? AND c.deleted_at IS NULL',
            [locale(), locale(), locale(), $id]
        );
        if ($case === null) {
            return null;
        }
        $case['services'] = $this->db->fetchAll(
            'SELECT cs.*, s.code, s.category, s.pricing_type AS master_pricing, s.fixed_price, st.name
             FROM case_services cs
             INNER JOIN services s ON s.id = cs.service_id
             LEFT JOIN service_translations st ON st.service_id = s.id AND st.locale = ?
             WHERE cs.case_id = ?',
            [locale(), $id]
        );
        $case['history'] = $this->workflow->history($id, false);

        return $case;
    }

    public function forCustomer(int $id, int $customerId): ?array
    {
        $case = $this->find($id);
        if ($case === null || (int) $case['customer_id'] !== $customerId) {
            return null;
        }
        $case['history'] = $this->workflow->history($id, true);

        return $case;
    }

    public function search(array $filters, int $page): array
    {
        $sql = 'SELECT c.*, cu.name AS customer_name, cu.email AS customer_email, ct.name AS country_name,
                       st.name AS status_name, s.code AS status_code, s.badge_tone
                FROM cases c
                INNER JOIN customers cu ON cu.id = c.customer_id
                LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = ?
                INNER JOIN workflow_statuses s ON s.id = c.status_id
                LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
                WHERE c.deleted_at IS NULL';
        $params = [locale(), locale()];
        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND (c.number LIKE ? OR cu.name LIKE ? OR cu.email LIKE ? OR cu.phone LIKE ? OR c.document_title LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (($filters['status'] ?? '') === 'active') {
            $sql .= ' AND s.is_terminal = 0';
        } elseif (($filters['status'] ?? '') === 'unquoted') {
            $sql .= ' AND s.code IN (\'new_request\', \'under_review\')
                      AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.case_id = c.id)';
        } elseif (($filters['status'] ?? '') !== '') {
            $sql .= ' AND s.code = ?';
            $params[] = $filters['status'];
        }
        if (($filters['country_id'] ?? '') !== '') {
            $sql .= ' AND c.country_id = ?';
            $params[] = (int) $filters['country_id'];
        }
        $sql .= ' ORDER BY c.created_at DESC';

        return $this->db->paginate($sql, $params, $page);
    }

    public function forCustomerList(int $customerId): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, ct.name AS country_name, st.name AS status_name, s.code AS status_code, s.badge_tone
             FROM cases c
             LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = ?
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
             WHERE c.customer_id = ? AND c.deleted_at IS NULL
             ORDER BY c.created_at DESC',
            [locale(), locale(), $customerId]
        );
    }

    public function track(string $number): ?array
    {
        $number = strtoupper(trim($number));
        $case = $this->db->fetch(
            'SELECT c.id, c.number, c.document_title, c.estimated_next, c.updated_at, ct.name AS country_name,
                    st.name AS status_name, s.code AS status_code, s.badge_tone
             FROM cases c
             LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = ?
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
             WHERE c.number = ? AND c.deleted_at IS NULL',
            [locale(), locale(), $number]
        );
        if ($case === null) {
            return null;
        }
        $case['services'] = $this->db->fetchAll(
            'SELECT st.name FROM case_services cs
             LEFT JOIN service_translations st ON st.service_id = cs.service_id AND st.locale = ?
             WHERE cs.case_id = ?',
            [locale(), $case['id']]
        );
        $case['history'] = $this->workflow->history((int) $case['id'], true, true);

        return $case;
    }

    public function updateDetails(int $id, array $data, int $staffId): void
    {
        $this->db->run(
            'UPDATE cases SET country_id = ?, document_type_id = ?, document_title = ?, summary = ?, assigned_to = ?, estimated_next = ?, internal_notes = ?, customer_notes = ? WHERE id = ?',
            [
                $data['country_id'] ?: null,
                $data['document_type_id'] ?: null,
                $data['document_title'],
                $data['summary'] ?? null,
                $data['assigned_to'] ?: null,
                $data['estimated_next'] ?? null,
                $data['internal_notes'] ?? null,
                $data['customer_notes'] ?? null,
                $id,
            ]
        );
        $this->audit->log($staffId, 'case_updated', 'case', $id);
    }

    public function syncServices(int $caseId, array $lines, ?int $staffId = null): void
    {
        $this->db->transaction(function () use ($caseId, $lines, $staffId): void {
        $case = $this->db->fetch('SELECT * FROM cases WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$caseId]);
        if ($case === null) {
            throw new \App\Core\HttpException(404, 'Case not found.');
        }
        $existing = [];
        foreach ($this->db->fetchAll('SELECT service_id, unit_price FROM case_services WHERE case_id = ?', [$caseId]) as $item) {
            $existing[(int) $item['service_id']] = $item['unit_price'];
        }
        $prepared = [];
        foreach ($lines as $line) {
            if (empty($line['service_id'])) {
                continue;
            }
            $service = $this->catalog->service((int) $line['service_id']);
            if ($service === null) {
                throw new \App\Core\ValidationException(['service_id' => 'A selected service is no longer available.']);
            }
            $quantity = \App\Helpers\Decimal::quantity($line['quantity'] ?? '1');
            if (\App\Helpers\Money::cmp($quantity, '0') <= 0) {
                throw new \App\Core\ValidationException(['quantity' => 'Service quantities must be greater than zero.']);
            }
            $price = ($service['pricing_type'] === 'fixed')
                ? $this->catalog->priceFor($service, $case['country_id'] ? (int) $case['country_id'] : null)
                : (($line['unit_price'] ?? '') !== '' ? \App\Helpers\Decimal::amount($line['unit_price'], 'unit_price') : ($existing[(int) $service['id']] ?? null));
            if ($price !== null && \App\Helpers\Money::cmp($price, '0') < 0) {
                throw new \App\Core\ValidationException(['unit_price' => 'Service prices cannot be negative.']);
            }
            $prepared[(int) $service['id']] = [$caseId, $service['id'], $quantity, $service['pricing_type'], $price, $service['currency']];
        }
        $this->db->run('DELETE FROM case_services WHERE case_id = ?', [$caseId]);
        foreach ($prepared as $values) {
            $this->db->insert(
                'INSERT INTO case_services (case_id, service_id, quantity, pricing_type, unit_price, currency) VALUES (?, ?, ?, ?, ?, ?)',
                $values
            );
        }
        $this->audit->log($staffId, 'case_services_updated', 'case', $caseId, ['service_ids' => array_keys($prepared)]);
        });
    }
}
