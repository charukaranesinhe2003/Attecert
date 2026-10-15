<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Kernel
{
    public function __construct(private Container $container, private Router $router)
    {
    }

    public function handle(Request $request): void
    {
        try {
            $this->rotateInput();
            $this->bootLocale($request);
            if ($request->isPost()) {
                $response = $this->container->get(\App\Middleware\CsrfMiddleware::class)->handle(
                    $request,
                    fn (Request $request): Response => $this->guarded($request)
                );
            } else {
                $response = $this->guarded($request);
            }
            $response->send();
        } catch (ValidationException $exception) {
            $input = $exception->input();
            unset($input['password'], $input['password_confirmation'], $input['_token']);
            $_SESSION['_errors'] = $exception->errors();
            $_SESSION['_old'] = $input;
            if ($request->wantsJson()) {
                Response::json(['message' => 'Validation failed', 'errors' => $exception->errors()], 422)->send();

                return;
            }
            flash('error', __('validation.failed'));
            $back = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
            Response::redirect($back)->send();
        } catch (HttpException $exception) {
            $this->renderError($request, $exception->status(), $exception->getMessage());
        } catch (Throwable $exception) {
            $this->logException($exception);
            if ((bool) config('app.debug')) {
                $this->renderError($request, 500, $exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine());

                return;
            }
            $this->renderError($request, 500, 'Something went wrong. Please try again.');
        }
    }

    private function rotateInput(): void
    {
        $_SESSION['_view_errors'] = $_SESSION['_errors'] ?? [];
        $_SESSION['_view_old'] = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }

    private function guarded(Request $request): Response
    {
        return $this->container->get(\App\Middleware\MaintenanceMiddleware::class)->handle(
            $request,
            fn (Request $request): Response => $this->router->dispatch($request, $this->container)
        );
    }

    private function bootLocale(Request $request): void
    {
        $locales = array_keys(config('app.locales', []));
        $query = (string) $request->query('lang', '');
        if ($query !== '' && in_array($query, $locales, true)) {
            $_SESSION['locale'] = $query;
        }
        if (empty($_SESSION['locale'])) {
            $_SESSION['locale'] = config('app.default_locale', 'en');
        }
    }

    private function renderError(Request $request, int $status, string $message): void
    {
        if ($request->wantsJson()) {
            Response::json(['message' => $message], $status)->send();

            return;
        }
        $view = match ($status) {
            403 => 'errors/403',
            404 => 'errors/404',
            419 => 'errors/419',
            default => 'errors/500',
        };
        Response::html(View::page($view, [
            'title' => (string) $status,
            'message' => $message,
            'robots' => 'noindex',
        ]), $status)->send();
    }

    private function logException(Throwable $exception): void
    {
        $dir = storage_path('logs');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s in %s:%d\n%s\n",
            date('c'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        file_put_contents($dir . '/app.log', $line, FILE_APPEND);
    }
}
