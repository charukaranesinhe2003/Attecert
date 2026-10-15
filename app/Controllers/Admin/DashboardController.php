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
        return $this->view('admin/dashboard', [
            'title' => __('admin.dashboard'),
            'robots' => 'noindex',
            'active' => 'dashboard',
            'metrics' => $this->reports->dashboard(),
            'hidePageHeader' => true,
        ], 'layouts/admin');
    }
}
