<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
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
        private AuthService $auth
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
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $keys = [
            'company_name', 'email', 'phone', 'whatsapp', 'address', 'hours', 'map_url',
            'registration', 'currency', 'quote_validity_days', 'invoice_due_days',
            'quote_terms', 'invoice_terms', 'facebook', 'instagram', 'linkedin',
            'maintenance_mode', 'email_verification', 'upload_max_mb',
        ];
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = (string) $request->input($key, '');
        }
        $values['maintenance_mode'] = $request->input('maintenance_mode') ? '1' : '0';
        $values['email_verification'] = $request->input('email_verification') ? '1' : '0';
        $this->settings->setMany($values);
        $this->audit->log($this->auth->id(), 'settings_updated', 'settings', null, ['keys' => $keys]);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/settings');
    }
}
