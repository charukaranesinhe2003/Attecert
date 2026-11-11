<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CaseAccessService;
use App\Core\Database;

final class StaffMiddleware
{
    public function __construct(private AuthService $auth, private Database $db, private CaseAccessService $access)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->isStaff()) {
            throw new HttpException(403, 'You do not have access to the staff area.');
        }

        if (!$this->auth->isRestrictedStaff()) {
            return $next($request);
        }
        $path = $request->path();
        if (in_array($path, ['/admin/documents', '/admin/processing', '/admin/mofa', '/admin/embassy', '/admin/customers', '/admin/contacts'], true)) {
            throw new HttpException(403, 'This page is available only to administrators.');
        }
        if ($path === '/admin/quotations/upload' && $request->isPost()) {
            $caseId = $request->input('case_id');
            if ((!is_int($caseId) && !is_string($caseId)) || !preg_match('/^[1-9][0-9]*$/D', (string) $caseId)) {
                throw new \App\Core\ValidationException(['case_id' => 'Choose an assigned case.']);
            }
            return $this->db->transaction(function () use ($caseId, $request, $next): Response {
                $this->access->requireCase((int) $caseId, true);
                return $next($request);
            });
        }
        // Allow only case work and personal password changes for non-admin staff.
        if (in_array($path, ['/admin', '/admin/password', '/admin/search', '/admin/cases', '/admin/documents',
            '/admin/quotations', '/admin/invoices', '/admin/payments', '/admin/processing', '/admin/mofa', '/admin/embassy', '/admin/quote-requests', '/admin/tracking'], true)) {
            if ($request->isPost() && $path !== '/admin/password') {
                throw new HttpException(403, 'Only administrators can create or assign cases.');
            }
            return $next($request);
        }
        if (preg_match('#^/admin/quote-requests/([1-9][0-9]*)(?:/.*)?$#D', $path, $quoteMatch)) {
            $work = function () use ($request, $next, $quoteMatch): Response {
                $this->access->requireQuote((int) $quoteMatch[1], $request->isPost());
                return $next($request);
            };
            return $request->isPost() ? $this->db->transaction($work) : $work();
        }
        if (!preg_match('#^/admin/(cases|documents|quotations|invoices|payments)/([1-9][0-9]*)(?:/[^/]+)*$#D', $path, $match)) {
            throw new HttpException(403, 'Your account can only access assigned cases.');
        }
        $work = function () use ($request, $next, $match): Response {
            $id = (int) $match[2];
            if ($match[1] !== 'cases') {
                // The table name comes exclusively from the route allowlist above.
                $row = $this->db->fetch('SELECT case_id FROM ' . $match[1] . ' WHERE id = ?', [$id]);
                $id = (int) ($row['case_id'] ?? 0);
            }
            $this->access->requireCase($id, $request->isPost());
            return $next($request);
        };

        // Hold the assignment lock through writes so reassignment cannot race an edit.
        return $request->isPost() ? $this->db->transaction($work) : $work();
    }
}
