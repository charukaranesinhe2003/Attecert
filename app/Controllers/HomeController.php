<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CatalogService;

final class HomeController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private CatalogService $catalog,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        return $this->view('public/home', [
            'title' => content_block('hero_title', 'Attecert: Fast, Reliable Document Attestation in Sri Lanka'),
            'description' => content_block('hero_lead'),
            'active' => 'home',
            'services' => $this->catalog->services(true, true),
            'countries' => array_values(array_filter($this->catalog->countries(true), static fn (array $c): bool => (int) $c['featured'] === 1)),
            'formCountries' => $this->catalog->countries(true),
            'faqs' => $this->catalog->faqs(true),
            'articles' => $this->catalog->articles(true, 3),
            'documentTypes' => $this->catalog->documentTypes(),
        ]);
    }

    public function language(Request $request, string $locale): Response
    {
        $locales = config('app.locales', []);
        if (isset($locales[$locale])) {
            $_SESSION['locale'] = $locale;
            if ($this->auth->check()) {
                app(\App\Core\Database::class)->run('UPDATE users SET locale = ? WHERE id = ?', [$locale, $this->auth->id()]);
            }
        }
        $back = (string) ($_SERVER['HTTP_REFERER'] ?? '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($back === '' || !str_contains($back, $host)) {
            $back = url('/');
        }

        return Response::redirect($back);
    }
}
