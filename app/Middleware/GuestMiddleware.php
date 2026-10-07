<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class GuestMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect($this->auth->homePath());
        }

        return $next($request);
    }
}
