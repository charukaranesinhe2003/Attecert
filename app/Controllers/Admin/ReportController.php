<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
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

        $rangeErrors = [];
        $report = null;
        try {
            $this->validateRange($from, $to);
            $report = $this->reports->summary($from, $to);
        } catch (ValidationException $exception) {
            $rangeErrors = $exception->errors();
        }

        return $this->view('admin/reports/index', [
            'title' => __('admin.reports'),
            'robots' => 'noindex',
            'active' => 'reports',
            'from' => $from,
            'to' => $to,
            'report' => $report,
            'rangeErrors' => $rangeErrors,
        ], 'layouts/admin', $rangeErrors === [] ? 200 : 422);
    }

    public function export(Request $request): Response
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));
        try {
            $this->validateRange($from, $to);
        } catch (ValidationException) {
            throw new HttpException(422, 'Choose valid report dates, with the start on or before the end.');
        }
        $report = $this->reports->summary($from, $to);
        $stream = fopen('php://temp', 'r+');
        $write = static function (array $cells) use ($stream): void {
            $cells = array_map(static function (mixed $value): string {
                $text = (string) $value;

                return preg_match('/^[=+@\t\r\n]/', $text) ? "'" . $text : $text;
            }, $cells);
            fputcsv($stream, $cells);
        };
        $write(['section', 'name', 'total', 'amount', 'currency']);
        foreach (['by_status' => 'status', 'by_country' => 'country', 'by_service' => 'service'] as $key => $section) {
            foreach ($report[$key] as $row) {
                $write([$section, $row['name'], $row['total'], '', '']);
            }
        }
        foreach ($report['payments'] as $row) {
            $write(['payment', $row['method'], $row['total'], $row['amount'], $row['currency']]);
        }
        foreach ($report['outstanding'] as $row) {
            $write(['outstanding', $row['number'], '', $row['amount_due'], $row['currency']]);
        }
        $write(['summary', 'New customers', $report['customers'], '', '']);
        $write(['summary', 'Quotations created', $report['quotations']['total'] ?? 0, '', '']);
        $write(['summary', 'Accepted or converted quotations', $report['quotations']['accepted'] ?? 0, '', '']);
        $write(['summary', 'Average completion days', $report['turnaround'] ?? '', '', '']);
        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attecert-report.csv"',
        ]);
    }

    private function validateRange(string $from, string $to): void
    {
        $this->validator->validate(['from' => $from, 'to' => $to], ['from' => 'required|date', 'to' => 'required|date']);
        if ($from > $to) {
            throw new ValidationException(['to' => 'The end date must be on or after the start date.'], ['from' => $from, 'to' => $to]);
        }
    }
}
