<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class DocumentTypeController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db)
    {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('admin/document-types/index', [
            'title' => __('admin.document_types'),
            'robots' => 'noindex',
            'active' => 'document_types',
            'types' => $this->db->fetchAll('SELECT d.*, en.name AS name_en, si.name AS name_si, ta.name AS name_ta FROM document_types d LEFT JOIN document_type_translations en ON en.document_type_id = d.id AND en.locale = \'en\' LEFT JOIN document_type_translations si ON si.document_type_id = d.id AND si.locale = \'si\' LEFT JOIN document_type_translations ta ON ta.document_type_id = d.id AND ta.locale = \'ta\' ORDER BY d.sort_order'),
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'code' => 'required|min:2|max:40',
            'name_en' => 'required|max:160',
            'name_si' => 'nullable|max:160',
            'name_ta' => 'nullable|max:160',
            'sort_order' => 'nullable|numeric',
        ]);
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            $this->db->run('UPDATE document_types SET code = ?, is_active = ?, sort_order = ? WHERE id = ?', [strtoupper($data['code']), $request->input('is_active') ? 1 : 0, (int) ($data['sort_order'] ?? 0), $id]);
        } else {
            $id = $this->db->insert('INSERT INTO document_types (code, is_active, sort_order) VALUES (?, 1, ?)', [strtoupper($data['code']), (int) ($data['sort_order'] ?? 0)]);
        }
        foreach (['en', 'si', 'ta'] as $locale) {
            $name = (string) ($data['name_' . $locale] ?? '');
            if ($name === '') {
                continue;
            }
            $this->db->run(
                'INSERT INTO document_type_translations (document_type_id, locale, name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)',
                [$id, $locale, $name]
            );
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/document-types');
    }
}
