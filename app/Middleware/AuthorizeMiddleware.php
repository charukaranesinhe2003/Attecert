<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthorizeMiddleware
{
    private string $permission = '';

    public function __construct(private AuthService $auth)
    {
    }

    public function setPermission(string $permission): void
    {
        $this->permission = $permission;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->can($this->permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
