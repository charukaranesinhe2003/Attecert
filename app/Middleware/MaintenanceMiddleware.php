<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AuthService;
use App\Services\SettingsService;

final class MaintenanceMiddleware
{
    public function __construct(private SettingsService $settings, private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $path = $request->path();
        $allowed = str_starts_with($path, '/admin') || str_starts_with($path, '/login') || str_starts_with($path, '/assets');
        if ($this->settings->get('maintenance_mode', '0') === '1' && !$allowed && !$this->auth->isStaff()) {
            return Response::html(View::page('errors/maintenance', [
                'title' => 'Maintenance',
                'robots' => 'noindex',
            ], 'layouts/public'), 503);
        }

        return $next($request);
    }
}
