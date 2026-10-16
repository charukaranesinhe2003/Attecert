<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\CatalogService;

final class CountryController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private CatalogService $catalog)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('q', '');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, 160) : '';
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : '';
        $sql = 'SELECT c.*, COALESCE(NULLIF(t.name, \'\'), en.name, c.code) AS name FROM countries c
                LEFT JOIN country_translations t ON t.country_id = c.id AND t.locale = ?
                LEFT JOIN country_translations en ON en.country_id = c.id AND en.locale = \'en\'
                WHERE c.deleted_at IS NULL';
        $params = [locale()];
        if ($q !== '') {
            $sql .= ' AND (c.code LIKE ? OR c.slug LIKE ? OR t.name LIKE ? OR en.name LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, '%' . $q . '%'));
        }
        if ($status !== '') {
            $sql .= ' AND c.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        $sql .= ' ORDER BY c.sort_order, c.id';

        return $this->view('admin/countries/index', [
            'title' => __('admin.countries'),
            'robots' => 'noindex',
            'active' => 'countries',
            'filters' => ['q' => $q, 'status' => $status],
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
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
        $rules = [
            'code' => 'required|min:2|max:2',
            'slug' => 'nullable|max:80',
            'sort_order' => 'nullable|numeric|min:0|max:65535',
        ];
        foreach (['en', 'si', 'ta'] as $locale) {
            $rules['name_' . $locale] = ($locale === 'en' ? 'required' : 'nullable') . '|max:120';
            $rules['summary_' . $locale] = 'nullable|max:300';
            $rules['processing_' . $locale] = 'nullable|max:180';
            $rules['seo_title_' . $locale] = 'nullable|max:180';
            $rules['meta_' . $locale] = 'nullable|max:300';
        }
        $data = $this->validate($request, $rules);
        $slug = slugify((string) ($request->input('slug') ?: $request->input('name_en')));
        if (strlen($slug) > 80) {
            throw new ValidationException(['slug' => 'Keep the country slug within 80 characters.'], $request->all());
        }
        $keep = [];
        foreach ((array) $request->input('service_id', []) as $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value <= 0) {
                throw new ValidationException(['service_id' => 'Choose valid services.'], $request->all());
            }
            $keep[] = (int) $value;
        }
        $keep = array_values(array_unique($keep));
        $id = $this->db->transaction(function () use ($request, $data, $slug, $keep, $id): int {
            if ($id !== null && !$this->db->fetch('SELECT id FROM countries WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id])) {
                throw new HttpException(404);
            }
            foreach (['code' => strtoupper($data['code']), 'slug' => $slug] as $field => $value) {
                if ($this->db->fetchColumn('SELECT id FROM countries WHERE ' . $field . ' = ? AND id <> ?', [$value, $id ?? 0])) {
                    throw new ValidationException([$field => 'This country ' . $field . ' is already in use.'], $request->all());
                }
            }
            foreach ($keep as $serviceId) {
                if (!$this->db->fetchColumn('SELECT id FROM services WHERE id = ? AND deleted_at IS NULL', [$serviceId])) {
                    throw new ValidationException(['service_id' => 'Choose valid services.'], $request->all());
                }
            }
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
                    [$id, $locale, (string) $data['name_' . $locale], (string) $data['summary_' . $locale], (string) $request->input('requirements_' . $locale), (string) $request->input('documents_' . $locale), (string) $request->input('process_' . $locale), (string) $request->input('notes_' . $locale), (string) $data['processing_' . $locale], (string) $data['seo_title_' . $locale], (string) $data['meta_' . $locale]]
                );
            }
            $this->db->run('DELETE FROM country_services WHERE country_id = ?' . ($keep === [] ? '' : ' AND service_id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')'), [$id, ...$keep]);
            foreach ($keep as $serviceId) {
                $this->db->run('INSERT IGNORE INTO country_services (country_id, service_id) VALUES (?, ?)', [$id, $serviceId]);
            }

            return $id;
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/countries/' . $id . '/edit');
    }
}
