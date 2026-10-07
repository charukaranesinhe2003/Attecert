<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\ContentService;

final class ContentController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private ContentService $content)
    {
        parent::__construct($validator);
    }

    public function edit(): Response
    {
        return $this->view('admin/content/edit', [
            'title' => __('admin.content'),
            'robots' => 'noindex',
            'active' => 'content',
            'blocks' => $this->content->all(),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $keys = (array) $request->input('keys', []);
        foreach ($keys as $key) {
            $this->content->save((string) $key, 'home', [
                'en' => (string) $request->input($key . '_en', ''),
                'si' => (string) $request->input($key . '_si', ''),
                'ta' => (string) $request->input($key . '_ta', ''),
            ]);
        }
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/content');
    }
}
