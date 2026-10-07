<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class FaqController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db)
    {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('admin/faqs/index', [
            'title' => __('admin.faqs'),
            'robots' => 'noindex',
            'active' => 'faqs',
            'faqs' => $this->db->fetchAll('SELECT f.*, t.question FROM faqs f LEFT JOIN faq_translations t ON t.faq_id = f.id AND t.locale = \'en\' ORDER BY f.sort_order'),
        ], 'layouts/admin');
    }

    public function create(): Response
    {
        return $this->form(null, []);
    }

    public function edit(Request $request, string $id): Response
    {
        $faq = $this->db->fetch('SELECT * FROM faqs WHERE id = ?', [(int) $id]);
        if ($faq === null) {
            throw new HttpException(404);
        }
        $translations = [];
        foreach ($this->db->fetchAll('SELECT * FROM faq_translations WHERE faq_id = ?', [(int) $id]) as $row) {
            $translations[$row['locale']] = $row;
        }

        return $this->form($faq, $translations);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->persist($request, (int) $id);
    }

    private function form(?array $faq, array $translations): Response
    {
        return $this->view('admin/faqs/form', [
            'title' => __('admin.faqs'),
            'robots' => 'noindex',
            'active' => 'faqs',
            'faq' => $faq,
            'translations' => $translations,
        ], 'layouts/admin');
    }

    private function persist(Request $request, ?int $id): Response
    {
        $data = $this->validate($request, [
            'question_en' => 'required|max:255',
            'answer_en' => 'required|max:5000',
            'category' => 'nullable|max:80',
            'sort_order' => 'nullable|numeric',
        ]);
        $flags = [$data['category'], (int) ($data['sort_order'] ?? 0), $request->input('is_active') ? 1 : 0, $request->input('show_on_home') ? 1 : 0];
        if ($id === null) {
            $id = $this->db->insert('INSERT INTO faqs (category, sort_order, is_active, show_on_home) VALUES (?, ?, ?, ?)', $flags);
        } else {
            $this->db->run('UPDATE faqs SET category = ?, sort_order = ?, is_active = ?, show_on_home = ? WHERE id = ?', [...$flags, $id]);
        }
        foreach (['en', 'si', 'ta'] as $locale) {
            $question = trim((string) $request->input('question_' . $locale));
            if ($question === '') {
                continue;
            }
            $this->db->run(
                'INSERT INTO faq_translations (faq_id, locale, question, answer) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE question = VALUES(question), answer = VALUES(answer)',
                [$id, $locale, $question, (string) $request->input('answer_' . $locale)]
            );
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/faqs/' . $id . '/edit');
    }
}
