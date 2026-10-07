<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\CatalogService;

final class CountryController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private CatalogService $catalog)
    {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('admin/countries/index', [
            'title' => __('admin.countries'),
            'robots' => 'noindex',
            'active' => 'countries',
            'countries' => $this->catalog->countries(false),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->form(null, []);
    }

    public function edit(Request $request, string $id): Response
    {
        $country = $this->db->fetch('SELECT * FROM countries WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if ($country === null) {
            throw new HttpException(404);
        }
        $translations = [];
        foreach ($this->db->fetchAll('SELECT * FROM country_translations WHERE country_id = ?', [(int) $id]) as $row) {
            $translations[$row['locale']] = $row;
        }

        return $this->form($country, $translations);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->persist($request, (int) $id);
    }

    private function form(?array $country, array $translations): Response
    {
        return $this->view('admin/countries/form', [
            'title' => $country ? __('admin.edit_country') : __('admin.new_country'),
            'robots' => 'noindex',
            'active' => 'countries',
            'country' => $country,
            'translations' => $translations,
            'services' => $this->catalog->services(false),
            'selected' => $country ? array_column($this->db->fetchAll('SELECT service_id FROM country_services WHERE country_id = ?', [$country['id']]), 'service_id') : [],
        ], 'layouts/admin');
    }

    private function persist(Request $request, ?int $id): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|min:2|max:2',
            'sort_order' => 'nullable|numeric',
        ]);
        $slug = slugify((string) ($request->input('slug') ?: $request->input('name_en')));
        $values = [strtoupper($data['code']), $slug, $request->input('is_active') ? 1 : 0, $request->input('featured') ? 1 : 0, (int) ($data['sort_order'] ?? 0)];
        if ($id === null) {
            $id = $this->db->insert('INSERT INTO countries (code, slug, is_active, featured, sort_order) VALUES (?, ?, ?, ?, ?)', $values);
        } else {
            $this->db->run('UPDATE countries SET code = ?, slug = ?, is_active = ?, featured = ?, sort_order = ? WHERE id = ?', [...$values, $id]);
        }
        foreach (['en', 'si', 'ta'] as $locale) {
            $this->db->run(
                'INSERT INTO country_translations (country_id, locale, name, summary, requirements, documents_required, process_text, notes, processing_info, seo_title, meta_description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), summary = VALUES(summary), requirements = VALUES(requirements), documents_required = VALUES(documents_required), process_text = VALUES(process_text), notes = VALUES(notes), processing_info = VALUES(processing_info), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description)',
                [$id, $locale, (string) $request->input('name_' . $locale), (string) $request->input('summary_' . $locale), (string) $request->input('requirements_' . $locale), (string) $request->input('documents_' . $locale), (string) $request->input('process_' . $locale), (string) $request->input('notes_' . $locale), (string) $request->input('processing_' . $locale), (string) $request->input('seo_title_' . $locale), (string) $request->input('meta_' . $locale)]
            );
        }
        $keep = array_map('intval', (array) $request->input('service_id', []));
        $this->db->run('DELETE FROM country_services WHERE country_id = ?', [$id]);
        foreach ($keep as $serviceId) {
            $this->db->run('INSERT INTO country_services (country_id, service_id) VALUES (?, ?)', [$id, $serviceId]);
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/countries/' . $id . '/edit');
    }
}
