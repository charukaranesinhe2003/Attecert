<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Sanitizer;
use App\Services\AuthService;

final class ArticleController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private Database $db, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('admin/articles/index', [
            'title' => __('admin.articles'),
            'robots' => 'noindex',
            'active' => 'articles',
            'articles' => $this->db->fetchAll('SELECT a.*, t.title FROM articles a LEFT JOIN article_translations t ON t.article_id = a.id AND t.locale = \'en\' WHERE a.deleted_at IS NULL ORDER BY a.created_at DESC'),
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
        $data = $this->validate($request, [
            'title_en' => 'required|max:200',
            'status' => 'required|in:draft,published',
        ]);
        $slug = slugify((string) ($request->input('slug') ?: $data['title_en']));
        $image = $this->storeImage($request);
        if ($id === null) {
            $id = $this->db->insert(
                'INSERT INTO articles (slug, status, featured, image_path, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$slug, $data['status'], $request->input('featured') ? 1 : 0, $image, $this->auth->id(), $data['status'] === 'published' ? date('Y-m-d H:i:s') : null]
            );
        } else {
            $current = $this->db->fetch('SELECT image_path, published_at FROM articles WHERE id = ?', [$id]);
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
                [$id, $locale, $title, (string) $request->input('excerpt_' . $locale), Sanitizer::html((string) $request->input('content_' . $locale)), (string) $request->input('seo_title_' . $locale), (string) $request->input('meta_' . $locale)]
            );
        }
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
