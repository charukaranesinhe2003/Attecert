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
            if ($request->wantsJson()) {
                return Response::json([
                    'ok' => false,
                    'error' => 'authentication_required',
                    'message' => __('auth.login_required'),
                    'redirect' => url('/login'),
                ], 401);
            }
            flash('error', __('auth.login_required'));

            return Response::redirect(url('/login'));
        }
        $passwordRoute = $request->path() === '/admin/password' && in_array($request->method(), ['GET', 'POST'], true);
        $logoutRoute = $request->path() === '/logout' && $request->method() === 'POST';
        if ($this->auth->mustChangePassword() && !$passwordRoute && !$logoutRoute) {
            if ($request->wantsJson()) {
                return Response::json([
                    'ok' => false,
                    'error' => 'password_change_required',
                    'message' => __('staff_security.required'),
                    'redirect' => url('/admin/password'),
                ], 403);
            }

            return Response::redirect(url('/admin/password'));
        }

        return $next($request);
    }
}
