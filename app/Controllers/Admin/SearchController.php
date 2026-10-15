<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class SearchController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $results = $q === '' ? [] : $this->find($q);
        if ($request->wantsJson()) {
            return Response::json(['query' => $q, 'results' => $results]);
        }

        return $this->view('admin/search', [
            'title' => __('admin.search'),
            'robots' => 'noindex',
            'active' => 'search',
            'q' => $q,
            'results' => $results,
            'pageHeading' => __('admin.search'),
            'pageDescription' => __('admin.search_hint'),
        ], 'layouts/admin');
    }

    private function find(string $q): array
    {
        $like = '%' . $q . '%';
        $digits = preg_replace('/\D+/', '', $q) ?: $q;
        $groups = [];
        $canCases = $this->auth->can('cases.view');

        if ($canCases) {
        $groups[] = ['label' => 'Cases', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['number'] . ' · ' . $row['customer_name'],
            'meta' => $row['document_title'] . ' · ' . ($row['status_name'] ?: ''),
            'href' => url('/admin/cases/' . $row['id']),
        ], $this->db->fetchAll(
            'SELECT c.id, c.number, c.document_title, cu.name AS customer_name, st.name AS status_name
             FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
             LEFT JOIN workflow_status_translations st ON st.status_id = c.status_id AND st.locale = \'en\'
             WHERE c.deleted_at IS NULL AND (
                c.number LIKE ? OR c.number = ? OR cu.name LIKE ? OR cu.email LIKE ?
                OR cu.phone LIKE ? OR cu.whatsapp LIKE ? OR REPLACE(cu.phone, \' \', \'\') LIKE ?
                OR c.document_title LIKE ?
             )
             ORDER BY CASE WHEN c.number = ? THEN 0 WHEN c.number LIKE ? THEN 1 ELSE 2 END, c.created_at DESC
             LIMIT 8',
            [$like, $q, $like, $like, $like, $like, '%' . $digits . '%', $like, $q, $q . '%']
        ))];
        }

        if ($this->auth->can('customers.view')) {
        $groups[] = ['label' => 'Customers', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['name'],
            'meta' => $row['email'] . ($row['phone'] ? ' · ' . $row['phone'] : ''),
            'href' => url('/admin/customers/' . $row['id']),
        ], $this->db->fetchAll(
            'SELECT id, name, email, phone FROM customers
             WHERE deleted_at IS NULL AND (
                name LIKE ? OR email LIKE ? OR phone LIKE ? OR whatsapp LIKE ?
                OR REPLACE(phone, \' \', \'\') LIKE ? OR REPLACE(whatsapp, \' \', \'\') LIKE ?
             )
             ORDER BY name LIMIT 6',
            [$like, $like, $like, $like, '%' . $digits . '%', '%' . $digits . '%']
        ))];
        }

        if ($this->auth->can('quotations.create')) {
        $groups[] = ['label' => 'Quotations', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['number'],
            'meta' => $row['customer_name'] . ' · ' . $row['status'],
            'href' => url('/admin/quotations/' . $row['id']),
        ], $this->db->fetchAll(
            'SELECT q.id, q.number, q.status, q.case_id, cu.name AS customer_name
             FROM quotations q INNER JOIN customers cu ON cu.id = q.customer_id
             INNER JOIN cases c ON c.id = q.case_id
             WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL AND q.number LIKE ? ORDER BY q.created_at DESC LIMIT 5',
            [$like]
        ))];
        }

        if ($this->auth->can('invoices.create')) {
        $groups[] = ['label' => 'Invoices', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['number'],
            'meta' => $row['customer_name'] . ' · ' . $row['status'],
            'href' => url('/admin/invoices/' . $row['id']),
        ], $this->db->fetchAll(
            'SELECT i.id, i.number, i.status, i.case_id, cu.name AS customer_name
             FROM invoices i INNER JOIN customers cu ON cu.id = i.customer_id
             INNER JOIN cases c ON c.id = i.case_id
             WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL AND i.number LIKE ? ORDER BY i.created_at DESC LIMIT 5',
            [$like]
        ))];
        }

        if ($this->auth->can('payments.view')) {
        $groups[] = ['label' => 'Payments', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['number'],
            'meta' => ($row['reference'] ?: $row['method']) . ' · ' . $row['customer_name'],
            'href' => $canCases ? url('/admin/cases/' . $row['case_id'] . '#finance') : url('/admin/payments/' . $row['id'] . '/receipt'),
        ], $this->db->fetchAll(
            'SELECT p.id, p.number, p.method, p.reference, p.case_id, cu.name AS customer_name
             FROM payments p INNER JOIN customers cu ON cu.id = p.customer_id
             INNER JOIN cases c ON c.id = p.case_id
             WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL AND (p.number LIKE ? OR p.reference LIKE ?) ORDER BY p.created_at DESC LIMIT 5',
            [$like, $like]
        ))];
        }

        if ($this->auth->can('quotes.manage')) {
        $groups[] = ['label' => 'Quote requests', 'items' => array_map(static fn (array $row): array => [
            'title' => $row['name'],
            'meta' => $row['email'] . ' · ' . $row['status'],
            'href' => url('/admin/quote-requests/' . $row['id']),
        ], $this->db->fetchAll(
            'SELECT id, name, email, status FROM quote_requests
             WHERE name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 5',
            [$like, $like, $like]
        ))];
        }

        return array_values(array_filter($groups, static fn (array $group): bool => $group['items'] !== []));
    }
}
