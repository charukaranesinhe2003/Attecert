<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }
        $token = (string) ($request->input('_token') ?: $request->header('X-CSRF-TOKEN'));
        $session = (string) ($_SESSION['_csrf'] ?? '');
        if ($session === '' || $token === '' || !hash_equals($session, $token)) {
            throw new HttpException(419, 'Session expired. Please try again.');
        }

        return $next($request);
    }
}
