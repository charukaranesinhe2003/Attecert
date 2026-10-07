<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ReportService;

final class DashboardController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private ReportService $reports)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        return $this->view('admin/dashboard', [
            'title' => __('admin.dashboard'),
            'robots' => 'noindex',
            'active' => 'dashboard',
            'from' => $from,
            'to' => $to,
            'metrics' => $this->reports->dashboard($from, $to),
            'hidePageHeader' => true,
        ], 'layouts/admin');
    }
}
