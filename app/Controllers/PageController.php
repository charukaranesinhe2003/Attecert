<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;
use App\Services\IntakeService;

final class PageController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private CatalogService $catalog,
        private IntakeService $intake
    ) {
        parent::__construct($validator);
    }

    public function services(): Response
    {
        return $this->view('public/services', [
            'title' => __('pages.services'),
            'description' => content_block('services_lead'),
            'active' => 'services',
            'services' => $this->catalog->services(true),
        ]);
    }

    public function service(Request $request, string $slug): Response
    {
        $service = $this->catalog->serviceBySlug($slug);
        if ($service === null || (int) $service['is_public'] !== 1) {
            throw new HttpException(404);
        }

        return $this->view('public/service', [
            'title' => $service['seo_title'] ?: $service['name'],
            'description' => $service['meta_description'] ?: $service['summary'],
            'active' => 'services',
            'service' => $service,
            'countries' => $this->catalog->countries(true),
        ]);
    }

    public function countries(): Response
    {
        return $this->view('public/countries', [
            'title' => __('pages.countries'),
            'description' => content_block('countries_lead'),
            'active' => 'countries',
            'countries' => $this->catalog->countries(true),
        ]);
    }

    public function country(Request $request, string $slug): Response
    {
        $country = $this->catalog->countryBySlug($slug);
        if ($country === null || (int) $country['is_active'] !== 1) {
            throw new HttpException(404);
        }

        return $this->view('public/country', [
            'title' => $country['seo_title'] ?: $country['name'],
            'description' => $country['meta_description'] ?: $country['summary'],
            'active' => 'countries',
            'country' => $country,
        ]);
    }

    public function about(): Response
    {
        return $this->view('public/about', [
            'title' => __('pages.about'),
            'description' => content_block('about_lead'),
            'active' => 'about',
        ]);
    }

    public function faq(): Response
    {
        return $this->view('public/faq', [
            'title' => __('pages.faq'),
            'description' => content_block('faq_lead'),
            'active' => 'faq',
            'faqs' => $this->catalog->faqs(false),
        ]);
    }

    public function guides(): Response
    {
        return $this->view('public/guides', [
            'title' => __('pages.guides'),
            'description' => content_block('guides_lead'),
            'active' => 'guides',
            'articles' => $this->catalog->articles(true),
        ]);
    }

    public function guide(Request $request, string $slug): Response
    {
        $article = $this->catalog->articleBySlug($slug);
        if ($article === null) {
            throw new HttpException(404);
        }

        return $this->view('public/guide', [
            'title' => $article['seo_title'] ?: $article['title'],
            'description' => $article['meta_description'] ?: $article['excerpt'],
            'active' => 'guides',
            'article' => $article,
        ]);
    }

    public function contact(): Response
    {
        if (account_customer() !== null) {
            return $this->redirect('/support');
        }

        return $this->view('public/contact', [
            'title' => __('pages.contact'),
            'description' => __('pages.contact_lead'),
            'active' => 'contact',
        ]);
    }

    public function sendContact(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|max:40',
            'subject' => 'nullable|max:180',
            'message' => 'required|min:10|max:4000',
        ]);
        if (account_customer() !== null) {
            return $this->redirect('/support');
        }
        $this->intake->contact($data);
        flash('success', __('contact.sent'));

        return $this->redirect('/contact');
    }

    public function sitemap(): Response
    {
        $urls = ['/', '/services', '/countries', '/track', '/quote', '/about', '/faq', '/guides', '/contact'];
        foreach ($this->catalog->services(true) as $service) {
            $urls[] = '/services/' . $service['slug'];
        }
        foreach ($this->catalog->countries(true) as $country) {
            $urls[] = '/countries/' . $country['slug'];
        }
        foreach ($this->catalog->articles(true) as $article) {
            $urls[] = '/guides/' . $article['slug'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $path) {
            $xml .= '<url><loc>' . htmlspecialchars(absolute_url($path), ENT_XML1) . '</loc></url>';
        }
        $xml .= '</urlset>';

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $body = "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /portal\nDisallow: /login\nDisallow: /register\nSitemap: " . absolute_url('/sitemap.xml') . "\n";

        return new Response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
