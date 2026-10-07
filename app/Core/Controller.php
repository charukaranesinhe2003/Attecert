<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected Validator $validator)
    {
    }

    protected function view(string $view, array $data = [], string $layout = 'layouts/public', int $status = 200): Response
    {
        return Response::html(View::page($view, $data, $layout), $status);
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }

    protected function back(string $fallback = '/'): Response
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($referer !== '' && str_contains($referer, $host)) {
            return Response::redirect($referer);
        }

        return $this->redirect($fallback);
    }

    protected function validate(Request $request, array $rules): array
    {
        return $this->validator->validate($request->all(), $rules);
    }

    protected function pageQuery(Request $request): int
    {
        return max(1, (int) $request->query('page', 1));
    }
}
