<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Helpers\Sanitizer;
use App\Services\AuthService;

final class ArticleController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('q', '');
        $q = is_string($raw) ? mb_substr(trim($raw), 0, 160) : '';
        $status = in_array($request->query('status'), ['draft', 'published'], true) ? $request->query('status') : '';
        $sql = 'SELECT a.*, COALESCE(NULLIF(t.title, \'\'), en.title, a.slug) AS title FROM articles a
                LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = ?
                LEFT JOIN article_translations en ON en.article_id = a.id AND en.locale = \'en\'
                WHERE a.deleted_at IS NULL';
        $params = [locale()];
        if ($q !== '') {
            $sql .= ' AND (a.slug LIKE ? OR t.title LIKE ? OR en.title LIKE ?)';
            $params = array_merge($params, array_fill(0, 3, '%' . $q . '%'));
        }
        if ($status !== '') {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

        return $this->view('admin/articles/index', [
            'title' => __('admin.articles'),
            'robots' => 'noindex',
            'active' => 'articles',
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
        $article = $this->db->fetch('SELECT * FROM articles WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if ($article === null) {
            throw new HttpException(404);
        }
        $translations = [];
        foreach ($this->db->fetchAll('SELECT * FROM article_translations WHERE article_id = ?', [(int) $id]) as $row) {
            $translations[$row['locale']] = $row;
        }

        return $this->form($article, $translations);
    }

    public function store(Request $request): Response
    {
        return $this->persist($request, null);
    }

    public function update(Request $request, string $id): Response
    {
        return $this->persist($request, (int) $id);
    }

    private function form(?array $article, array $translations): Response
    {
        return $this->view('admin/articles/form', [
            'title' => $article ? __('admin.edit_article') : __('admin.new_article'),
            'robots' => 'noindex',
            'active' => 'articles',
            'article' => $article,
            'translations' => $translations,
        ], 'layouts/admin');
    }

    private function persist(Request $request, ?int $id): Response
    {
        $rules = [
            'title_en' => 'required|max:200',
            'status' => 'required|in:draft,published',
            'slug' => 'nullable|max:160',
        ];
        foreach (['en', 'si', 'ta'] as $locale) {
            $rules['title_' . $locale] = ($locale === 'en' ? 'required' : 'nullable') . '|max:200';
            $rules['excerpt_' . $locale] = 'nullable|max:400';
            $rules['seo_title_' . $locale] = 'nullable|max:180';
            $rules['meta_' . $locale] = 'nullable|max:300';
        }
        $data = $this->validate($request, $rules);
        $slug = slugify((string) ($request->input('slug') ?: $data['title_en']));
        if (strlen($slug) > 160) {
            throw new ValidationException(['slug' => 'Keep the article slug within 160 characters.'], $request->all());
        }
        $id = $this->db->transaction(function () use ($request, $data, $slug, $id): int {
            $current = $id === null ? null : $this->db->fetch('SELECT image_path, published_at FROM articles WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$id]);
            if ($id !== null && $current === null) {
                throw new HttpException(404);
            }
            if ($this->db->fetchColumn('SELECT id FROM articles WHERE slug = ? AND id <> ?', [$slug, $id ?? 0])) {
                throw new ValidationException(['slug' => 'This article slug is already in use.'], $request->all());
            }
            $image = $this->storeImage($request);
            if ($id === null) {
                $id = $this->db->insert(
                    'INSERT INTO articles (slug, status, featured, image_path, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$slug, $data['status'], $request->input('featured') ? 1 : 0, $image, $this->auth->id(), $data['status'] === 'published' ? date('Y-m-d H:i:s') : null]
                );
            } else {
                $this->db->run(
                    'UPDATE articles SET slug = ?, status = ?, featured = ?, image_path = ?, published_at = ? WHERE id = ?',
                    [$slug, $data['status'], $request->input('featured') ? 1 : 0, $image ?: $current['image_path'], $data['status'] === 'published' ? ($current['published_at'] ?: date('Y-m-d H:i:s')) : null, $id]
                );
            }
            foreach (['en', 'si', 'ta'] as $locale) {
                $title = trim((string) $request->input('title_' . $locale));
                if ($title === '') {
                    continue;
                }
                $this->db->run(
                    'INSERT INTO article_translations (article_id, locale, title, excerpt, content, seo_title, meta_description) VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE title = VALUES(title), excerpt = VALUES(excerpt), content = VALUES(content), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description)',
                    [$id, $locale, $title, (string) $data['excerpt_' . $locale], Sanitizer::html((string) $request->input('content_' . $locale)), (string) $data['seo_title_' . $locale], (string) $data['meta_' . $locale]]
                );
            }

            return $id;
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/articles/' . $id . '/edit');
    }

    private function storeImage(Request $request): ?string
    {
        $file = $request->file('image');
        if ($file === null) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \App\Core\HttpException(422, __('documents.bad_type'));
        }
        $name = bin2hex(random_bytes(8)) . '.webp';
        $target = base_path('public/assets/images/articles');
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png' => imagecreatefrompng($file['tmp_name']),
            default => imagecreatefromwebp($file['tmp_name']),
        };
        if ($image === false) {
            throw new \App\Core\HttpException(422, __('documents.bad_type'));
        }
        imagewebp($image, $target . '/' . $name, 82);
        imagedestroy($image);

        return 'articles/' . $name;
    }
}
