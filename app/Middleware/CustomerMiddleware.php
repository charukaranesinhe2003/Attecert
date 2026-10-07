<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class CustomerMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->customer() === null) {
            throw new HttpException(403, 'A customer account is required.');
        }

        return $next($request);
    }
}
