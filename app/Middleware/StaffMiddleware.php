<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class StaffMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->isStaff()) {
            throw new HttpException(403, 'You do not have access to the staff area.');
        }

        return $next($request);
    }
}
