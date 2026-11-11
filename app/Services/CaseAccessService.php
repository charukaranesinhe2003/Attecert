<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;

/** Case ownership is checked on the server, including files and financial records. */
final class CaseAccessService
{
    public function __construct(private Database $db, private AuthService $auth)
    {
    }

    public function scope(string $caseIdColumn): string
    {
        if (!$this->auth->isRestrictedStaff()) {
            return '1 = 1';
        }

        return 'EXISTS (SELECT 1 FROM cases access_case WHERE access_case.id = ' . $caseIdColumn
            . ' AND access_case.deleted_at IS NULL AND access_case.assigned_to = ' . (int) $this->auth->id() . ')';
    }

    public function requireCase(int $id, bool $lock = false): array
    {
        $case = $this->db->fetch('SELECT id, assigned_to FROM cases WHERE id = ? AND deleted_at IS NULL'
            . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if ($case === null || ($this->auth->isRestrictedStaff() && (int) $case['assigned_to'] !== $this->auth->id())) {
            throw new HttpException(404, 'Case not found.');
        }

        return $case;
    }

    public function quoteScope(string $quoteIdColumn): string
    {
        if (!$this->auth->isRestrictedStaff()) {
            return '1 = 1';
        }
        return 'EXISTS (SELECT 1 FROM quote_requests access_quote WHERE access_quote.id = ' . $quoteIdColumn
            . ' AND access_quote.assigned_to = ' . (int) $this->auth->id() . ')';
    }

    public function requireQuote(int $id, bool $lock = false): array
    {
        $quote = $this->db->fetch('SELECT * FROM quote_requests WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if ($quote === null || ($this->auth->isRestrictedStaff() && (int) $quote['assigned_to'] !== $this->auth->id())) {
            throw new HttpException(404, 'Quote request not found.');
        }
        return $quote;
    }

    public function staff(): array
    {
        return $this->db->fetchAll("SELECT u.id, u.name, u.email FROM users u WHERE u.is_active = 1 AND u.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code <> 'customer')
            ORDER BY u.name, u.id");
    }

    public function caseOptions(): array
    {
        return $this->db->fetchAll('SELECT c.id, c.number, c.document_title, c.currency, cu.name AS customer_name
            FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
            WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL AND ' . $this->scope('c.id') . ' ORDER BY c.created_at DESC, c.id DESC');
    }

    public function requireRecord(string $table, int $id): void
    {
        if (!in_array($table, ['quotations', 'invoices', 'payments', 'documents'], true)) {
            throw new \InvalidArgumentException('Unsupported case record.');
        }
        $row = $this->db->fetch('SELECT case_id FROM ' . $table . ' WHERE id = ?', [$id]);
        if ($row === null) {
            throw new HttpException(404, 'Record not found.');
        }
        if ($this->auth->isRestrictedStaff()) {
            $this->requireCase((int) $row['case_id']);
        }
    }

    public function validateAssignee(?int $id): void
    {
        if ($id === null) {
            return;
        }
        $user = $this->db->fetch("SELECT u.id FROM users u WHERE u.id = ? AND u.is_active = 1 AND u.deleted_at IS NULL
            AND EXISTS (SELECT 1 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = u.id AND r.code <> 'customer') FOR UPDATE", [$id]);
        if ($user === null) {
            throw new ValidationException(['assigned_to' => 'Choose an active staff account.']);
        }
    }
}
