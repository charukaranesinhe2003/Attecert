<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\SettingsService;

final class SettingsController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private SettingsService $settings,
        private AuditService $audit,
        private AuthService $auth,
        private Database $db
    ) {
        parent::__construct($validator);
    }

    public function edit(): Response
    {
        return $this->view('admin/settings/edit', [
            'title' => __('admin.settings'),
            'robots' => 'noindex',
            'active' => 'settings',
            'settings' => $this->settings->all(),
            'smtp' => app(\App\Services\MailService::class)->smtpReady(),
            'uploadLimit' => $this->uploadLimit(),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $rules = [
            'company_name' => 'required|max:160', 'email' => 'required|email|max:190',
            'phone' => 'nullable|max:40', 'whatsapp' => 'nullable|max:40', 'address' => 'nullable|max:255',
            'hours' => 'nullable|max:255', 'registration' => 'nullable|max:160',
            'map_url' => 'nullable|http_url|max:2048', 'currency' => 'required|currency',
            'quote_validity_days' => 'required|integer|min:1|max:3650',
            'invoice_due_days' => 'required|integer|min:1|max:3650',
            'upload_max_mb' => 'required|integer|min:1|max:' . $this->uploadLimit(),
            'quote_terms' => 'nullable|max:10000', 'invoice_terms' => 'nullable|max:10000',
            'facebook' => 'nullable|http_url|max:2048', 'instagram' => 'nullable|http_url|max:2048', 'linkedin' => 'nullable|http_url|max:2048',
            'maintenance_mode' => 'required|in:0,1', 'email_verification' => 'required|in:0,1',
        ];
        $input = array_replace($this->settings->all(), $request->all());
        if (is_string($input['currency'] ?? null)) {
            $input['currency'] = strtoupper(trim($input['currency']));
        }
        $values = $this->validator->validate($input, $rules);
        $this->db->transaction(function () use ($values): void {
            $this->settings->setMany($values);
            $this->audit->log($this->auth->id(), 'settings_updated', 'settings', null, ['keys' => array_keys($values)]);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/settings');
    }

    private function uploadLimit(): int
    {
        $limits = [1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $key) {
            $value = trim((string) ini_get($key));
            if (preg_match('/^(\d+)([KMG]?)$/i', $value, $matches) && (int) $matches[1] > 0) {
                $bytes = (int) $matches[1] * match (strtoupper($matches[2])) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
                $limits[] = max(1, (int) floor($bytes / 1048576));
            }
        }

        return min($limits);
    }
}
