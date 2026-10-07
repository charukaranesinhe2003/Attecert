<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

final class ReportController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private ReportService $reports)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        return $this->view('admin/reports/index', [
            'title' => __('admin.reports'),
            'robots' => 'noindex',
            'active' => 'reports',
            'from' => $from,
            'to' => $to,
            'report' => $this->reports->summary($from, $to),
        ], 'layouts/admin');
    }

    public function export(Request $request): Response
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        $report = $this->reports->summary($from, $to);
        $csv = "section,name,total,amount\n";
        foreach ($report['by_status'] as $row) {
            $csv .= 'status,' . $this->csv($row['name']) . ',' . $row['total'] . ",\n";
        }
        foreach ($report['by_country'] as $row) {
            $csv .= 'country,' . $this->csv($row['name']) . ',' . $row['total'] . ",\n";
        }
        foreach ($report['payments'] as $row) {
            $csv .= 'payment,' . $this->csv($row['method']) . ',' . $row['total'] . ',' . $row['amount'] . "\n";
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attecert-report.csv"',
        ]);
    }

    private function csv(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
