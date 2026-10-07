<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CatalogService;

final class ServiceController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CatalogService $catalog,
        private AuditService $audit,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('admin/services/index', [
            'title' => __('admin.services'),
            'robots' => 'noindex',
            'active' => 'services',
            'services' => $this->catalog->services(false),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(Request $request, string $id): Response
    {
        $service = $this->catalog->service((int) $id);
        if ($service === null) {
            throw new HttpException(404);
        }

        return $this->form($service);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->persist($request, (int) $id);
    }

    private function form(?array $service): Response
    {
        return $this->view('admin/services/form', [
            'title' => $service ? __('admin.edit_service') : __('admin.new_service'),
            'robots' => 'noindex',
            'active' => 'services',
            'service' => $service,
            'translations' => $service ? $this->catalog->serviceTranslations((int) $service['id']) : [],
            'countries' => $this->catalog->countries(false),
            'selectedCountries' => $service ? array_column($this->db->fetchAll('SELECT country_id FROM country_services WHERE service_id = ?', [$service['id']]), 'country_id') : [],
        ], 'layouts/admin');
    }

    private function persist(Request $request, ?int $id): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|min:2|max:40',
            'pricing_type' => 'required|in:fixed,custom',
            'fixed_price' => 'nullable|numeric|min:0',
            'processing_days' => 'nullable|max:40',
            'category' => 'nullable|max:60',
            'sort_order' => 'nullable|numeric',
            'icon' => 'nullable|max:40',
        ]);
        $slug = slugify((string) ($request->input('slug') ?: $request->input('name_en')));
        $payload = [
            strtoupper($data['code']),
            $slug,
            $data['pricing_type'],
            $data['pricing_type'] === 'fixed' ? $data['fixed_price'] : null,
            $data['processing_days'],
            $data['category'],
            $request->input('is_active') ? 1 : 0,
            $request->input('is_public') ? 1 : 0,
            $request->input('show_price') ? 1 : 0,
            $request->input('featured') ? 1 : 0,
            (int) ($data['sort_order'] ?? 0),
            $data['icon'] ?: 'document',
        ];
        if ($id === null) {
            $id = $this->db->insert(
                'INSERT INTO services (code, slug, pricing_type, fixed_price, currency, processing_days, category, is_active, is_public, show_price, featured, sort_order, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$payload[0], $payload[1], $payload[2], $payload[3], setting('currency', 'LKR')], array_slice($payload, 4))
            );
        } else {
            $old = $this->catalog->service($id);
            $this->db->run(
                'UPDATE services SET code = ?, slug = ?, pricing_type = ?, fixed_price = ?, processing_days = ?, category = ?, is_active = ?, is_public = ?, show_price = ?, featured = ?, sort_order = ?, icon = ? WHERE id = ?',
                [...$payload, $id]
            );
            if ($old && (string) $old['fixed_price'] !== (string) $payload[3]) {
                $this->audit->log($this->auth->id(), 'service_price_changed', 'service', $id, ['from' => $old['fixed_price'], 'to' => $payload[3]]);
            }
        }
        foreach (['en', 'si', 'ta'] as $locale) {
            $this->db->run(
                'INSERT INTO service_translations (service_id, locale, name, summary, description, process_text, documents_required, seo_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), summary = VALUES(summary), description = VALUES(description), process_text = VALUES(process_text), documents_required = VALUES(documents_required), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description)',
                [$id, $locale, (string) $request->input('name_' . $locale), (string) $request->input('summary_' . $locale), (string) $request->input('description_' . $locale), (string) $request->input('process_' . $locale), (string) $request->input('documents_' . $locale), (string) $request->input('seo_title_' . $locale), (string) $request->input('meta_' . $locale)]
            );
        }
        $this->db->run('DELETE FROM country_services WHERE service_id = ?', [$id]);
        foreach ((array) $request->input('country_id', []) as $countryId) {
            $this->db->run('INSERT INTO country_services (country_id, service_id) VALUES (?, ?)', [(int) $countryId, $id]);
        }
        $this->audit->log($this->auth->id(), 'service_saved', 'service', $id);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/services/' . $id . '/edit');
    }
}
