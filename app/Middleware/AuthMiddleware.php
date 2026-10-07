<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthMiddleware
{
    public function __construct(private AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->auth->check()) {
            $_SESSION['_intended'] = $request->path();
            flash('error', __('auth.login_required'));

            return Response::redirect(url('/login'));
        }

        return $next($request);
    }
}
