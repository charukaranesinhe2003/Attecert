<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\TranslationService;

final class TranslationController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private TranslationService $translations, private Database $db)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $keys = $this->translations->fileKeys();
        if ($q !== '') {
            $keys = array_filter($keys, static fn ($value, $key): bool => str_contains($key, $q) || str_contains(mb_strtolower((string) $value), mb_strtolower($q)), ARRAY_FILTER_USE_BOTH);
        }
        $keys = array_slice($keys, 0, 80, true);
        $overrides = [];
        if ($keys !== []) {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $rows = $this->db->fetchAll('SELECT locale, trans_key, value FROM ui_translations WHERE trans_key IN (' . $placeholders . ')', array_keys($keys));
            foreach ($rows as $row) {
                $overrides[$row['trans_key']][$row['locale']] = $row['value'];
            }
        }

        return $this->view('admin/translations/index', [
            'title' => __('admin.translations'),
            'robots' => 'noindex',
            'active' => 'translations',
            'q' => $q,
            'keys' => $keys,
            'overrides' => $overrides,
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $key = (string) $request->input('trans_key');
        foreach (['en', 'si', 'ta'] as $locale) {
            $value = trim((string) $request->input('value_' . $locale));
            if ($value !== '') {
                $this->translations->set($locale, $key, $value);
            }
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/translations?q=' . rawurlencode($key));
    }
}
