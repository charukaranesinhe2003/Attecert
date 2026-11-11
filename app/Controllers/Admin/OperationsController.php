<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CaseAccessService;

final class OperationsController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db,
        private CaseAccessService $access, private AuthService $auth, private AuditService $audit)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $isQuote = $request->query('type') === 'quote_requests';
        $q = $request->query('q', '');
        $q = is_string($q) ? mb_substr(trim($q), 0, 160) : '';
        $assignee = $request->query('assignee', '');
        $assignee = is_string($assignee) && preg_match('/^(unassigned|[1-9][0-9]*)$/D', $assignee) ? $assignee : '';
        $sql = 'SELECT c.id, c.number, c.document_title, c.assigned_to, cu.name AS customer_name,
                       u.name AS assignee_name, COALESCE(st.name, s.code) AS status_name, s.badge_tone
                FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
                INNER JOIN workflow_statuses s ON s.id = c.status_id
                LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
                LEFT JOIN users u ON u.id = c.assigned_to WHERE c.deleted_at IS NULL';
        $params = [locale()];
        if ($isQuote) {
            $sql = "SELECT c.id, CONCAT('Request #', c.id) AS number, c.email AS document_title, c.assigned_to,
                    c.name AS customer_name, u.name AS assignee_name, c.status AS status_name, 'neutral' AS badge_tone
                    FROM quote_requests c LEFT JOIN users u ON u.id = c.assigned_to WHERE 1 = 1";
            $params = [];
        }
        if ($q !== '') {
            $sql .= $isQuote ? ' AND (c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)' : ' AND (c.number LIKE ? OR c.document_title LIKE ? OR cu.name LIKE ?)';
            array_push($params, ...array_fill(0, 3, '%' . $q . '%'));
        }
        if ($assignee === 'unassigned') {
            $sql .= ' AND c.assigned_to IS NULL';
        } elseif ($assignee !== '') {
            $sql .= ' AND c.assigned_to = ?';
            $params[] = (int) $assignee;
        }
        $sql .= ' ORDER BY (c.assigned_to IS NULL) DESC, c.created_at DESC, c.id DESC';
        return $this->view('admin/operations/index', [
            'title' => 'Assign staff', 'pageHeading' => 'Assign staff', 'active' => 'operations',
            'pageDescription' => 'Assign cases and quote requests to staff. Staff can see and manage only their assigned work. Reassigning a record removes the previous staff member’s access.',
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
            'staff' => $this->access->staff(), 'q' => $q, 'assignee' => $assignee,
            'isQuote' => $isQuote,
        ], 'layouts/admin');
    }

    public function assign(Request $request, string $id): Response
    {
        return $this->saveAssignment($request, $id, false);
    }

    public function assignQuote(Request $request, string $id): Response
    {
        return $this->saveAssignment($request, $id, true);
    }

    private function saveAssignment(Request $request, string $id, bool $isQuote): Response
    {
        $data = $this->validate($request, ['assigned_to' => 'nullable|integer|min:1', 'previous_assigned_to' => 'nullable|integer|min:1']);
        $next = empty($data['assigned_to']) ? null : (int) $data['assigned_to'];
        $previous = empty($data['previous_assigned_to']) ? null : (int) $data['previous_assigned_to'];
        $this->db->transaction(function () use ($id, $next, $previous, $isQuote): void {
            $case = $isQuote ? $this->access->requireQuote((int) $id, true) : $this->access->requireCase((int) $id, true);
            $current = $case['assigned_to'] === null ? null : (int) $case['assigned_to'];
            if ($current !== $previous) {
                throw new ValidationException(['assigned_to' => 'This assignment changed while the page was open. Refresh Operations and try again.']);
            }
            $this->access->validateAssignee($next);
            if ($next !== $current) {
                $this->db->run('UPDATE ' . ($isQuote ? 'quote_requests' : 'cases') . ' SET assigned_to = ? WHERE id = ?', [$next, (int) $id]);
                $this->audit->log($this->auth->id(), $isQuote ? 'quote_request_assigned' : 'case_assigned', $isQuote ? 'quote_request' : 'case', (int) $id, ['previous_assigned_to' => $current, 'assigned_to' => $next]);
            }
        });
        $label = $isQuote ? 'Quote request' : 'Case';
        flash('success', $next === null ? $label . ' is now unassigned.' : $label . ' assignment saved.');
        return $this->redirect('/admin/operations' . ($isQuote ? '?type=quote_requests' : ''));
    }
}
