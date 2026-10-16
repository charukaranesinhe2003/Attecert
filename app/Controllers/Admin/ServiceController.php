<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
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

    public function index(Request $request): Response
    {
        $raw = $request->query('q', '');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, 160) : '';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '';
        $sql = 'SELECT s.*, COALESCE(NULLIF(t.name, \'\'), en.name, s.code) AS name FROM services s
                LEFT JOIN service_translations t ON t.service_id = s.id AND t.locale = ?
                LEFT JOIN service_translations en ON en.service_id = s.id AND en.locale = \'en\'
                WHERE s.deleted_at IS NULL';
        $params = [locale()];
        if ($q !== '') {
            $sql .= ' AND (s.code LIKE ? OR s.slug LIKE ? OR t.name LIKE ? OR en.name LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, '%' . $q . '%'));
        }
        if ($status !== '') {
            $sql .= ' AND s.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        $sql .= ' ORDER BY s.sort_order, s.id';

        return $this->view('admin/services/index', [
            'title' => __('admin.services'),
            'robots' => 'noindex',
            'active' => 'services',
            'filters' => ['q' => $q, 'status' => $status],
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
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
        $rules = [
            'code' => 'required|min:2|max:40',
            'slug' => 'nullable|max:120',
            'pricing_type' => 'required|in:fixed,custom',
            'fixed_price' => 'nullable|numeric|min:0',
            'processing_days' => 'nullable|max:40',
            'category' => 'nullable|max:60',
            'sort_order' => 'nullable|numeric|min:0|max:65535',
            'icon' => 'nullable|max:40',
        ];
        foreach (['en', 'si', 'ta'] as $locale) {
            $rules['name_' . $locale] = ($locale === 'en' ? 'required' : 'nullable') . '|max:160';
            $rules['summary_' . $locale] = 'nullable|max:300';
            $rules['seo_title_' . $locale] = 'nullable|max:180';
            $rules['meta_' . $locale] = 'nullable|max:300';
        }
        $data = $this->validate($request, $rules);
        $slug = slugify((string) ($request->input('slug') ?: $request->input('name_en')));
        if (strlen($slug) > 120) {
            throw new ValidationException(['slug' => 'Keep the service slug within 120 characters.'], $request->all());
        }
        $keep = [];
        foreach ((array) $request->input('country_id', []) as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
                throw new ValidationException(['country_id' => 'Choose valid countries.'], $request->all());
            }
            $keep[] = (int) $value;
        }
        $keep = array_values(array_unique($keep));
        $id = $this->db->transaction(function () use ($request, $data, $slug, $keep, $id): int {
            $old = $id === null ? null : $this->db->fetch('SELECT * FROM services WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]);
            if ($id !== null && $old === null) {
                throw new HttpException(404);
            }
            foreach (['code' => strtoupper($data['code']), 'slug' => $slug] as $field => $value) {
                if ($this->db->fetchColumn('SELECT id FROM services WHERE ' . $field . ' = ? AND id <> ?', [$value, $id ?? 0])) {
                    throw new ValidationException([$field => 'This service ' . $field . ' is already in use.'], $request->all());
                }
            }
            foreach ($keep as $countryId) {
                if (!$this->db->fetchColumn('SELECT id FROM countries WHERE id = ? AND deleted_at IS NULL', [$countryId])) {
                    throw new ValidationException(['country_id' => 'Choose valid countries.'], $request->all());
                }
            }
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
                    [$id, $locale, (string) $data['name_' . $locale], (string) $data['summary_' . $locale], (string) $request->input('description_' . $locale), (string) $request->input('process_' . $locale), (string) $request->input('documents_' . $locale), (string) $data['seo_title_' . $locale], (string) $data['meta_' . $locale]]
                );
            }
            $this->db->run('DELETE FROM country_services WHERE service_id = ?' . ($keep === [] ? '' : ' AND country_id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')'), [$id, ...$keep]);
            foreach ($keep as $countryId) {
                $this->db->run('INSERT IGNORE INTO country_services (country_id, service_id) VALUES (?, ?)', [$countryId, $id]);
            }
            $this->audit->log($this->auth->id(), 'service_saved', 'service', $id);

            return $id;
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/services/' . $id . '/edit');
    }
}
