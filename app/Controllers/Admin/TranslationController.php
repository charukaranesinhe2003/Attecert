<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\TranslationService;
use App\Core\HttpException;
use App\Services\AuditService;
use App\Services\AuthService;

final class TranslationController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private TranslationService $translations, private Database $db, private AuditService $audit, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $keys = $this->translations->fileKeys();
        if ($q !== '') {
            $customMatches = array_flip(array_column($this->db->fetchAll('SELECT DISTINCT trans_key FROM ui_translations WHERE value LIKE ?', ['%' . $q . '%']), 'trans_key'));
            $keys = array_filter($keys, static fn ($value, $key): bool => isset($customMatches[$key]) || str_contains(mb_strtolower($key), mb_strtolower($q)) || str_contains(mb_strtolower((string) $value), mb_strtolower($q)), ARRAY_FILTER_USE_BOTH);
        }
        $total = count($keys);
        $pages = max(1, (int) ceil($total / 20));
        $number = min($pages, $this->pageQuery($request));
        $keys = array_slice($keys, ($number - 1) * 20, 20, true);
        $defaults = [];
        foreach ($keys as $key => $value) { $defaults[$key] = $this->translations->defaults($key); }
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
            'defaults' => $defaults,
            'page' => ['page' => $number, 'pages' => $pages, 'total' => $total],
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $key = is_string($request->input('trans_key')) ? $request->input('trans_key') : '';
        if (!array_key_exists($key, $this->translations->fileKeys())) {
            throw new HttpException(422, __('admin_lists.translation_invalid'));
        }
        $locales = array_keys(config('app.locales'));
        $reset = is_string($request->input('reset_locale')) ? $request->input('reset_locale') : '';
        if ($request->input('reset_locale') !== null && !is_string($request->input('reset_locale'))) {
            throw new HttpException(422, __('admin_lists.translation_invalid'));
        }
        if ($reset !== '' && !in_array($reset, $locales, true)) {
            throw new HttpException(422, __('admin_lists.translation_invalid'));
        }
        if ($reset !== '' && $request->input('confirm_reset') !== '1') {
            throw new HttpException(422, __('admin_lists.reset_confirm'));
        }
        $rules = [];
        foreach ($locales as $locale) { $rules['value_' . $locale] = 'nullable|max:10000'; }
        $values = $this->validate($request, $rules);
        $defaults = $this->translations->defaults($key);
        $this->db->transaction(function () use ($key, $reset, $values, $locales, $defaults): void {
            if ($reset !== '') {
                $this->translations->reset($reset, $key);
            } else {
                foreach ($locales as $locale) {
                    if (($values['value_' . $locale] ?? '') !== '' && $values['value_' . $locale] !== null) {
                        if ($values['value_' . $locale] === $defaults[$locale]) {
                            $this->translations->reset($locale, $key);
                        } else {
                            $this->translations->set($locale, $key, $values['value_' . $locale]);
                        }
                    }
                }
            }
            $this->audit->log($this->auth->id(), $reset !== '' ? 'translation_reset' : 'translation_updated', 'translation', null, ['key' => $key, 'locale' => $reset ?: 'all']);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/translations?q=' . rawurlencode($key));
    }
}
