<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\IntakeService;

final class ProfileController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private IntakeService $intake
    ) {
        parent::__construct($validator);
    }

    public function edit(): Response
    {
        return $this->redirect('/account');
    }

    public function update(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'phone' => 'nullable|max:40',
            'whatsapp' => 'nullable|max:40',
            'city' => 'nullable|max:80',
            'address' => 'nullable|max:255',
            'locale' => 'required|in:en,si,ta',
        ]);
        $customer = $this->auth->updateProfile((int) $this->auth->id(), $data['name'], (string) ($data['phone'] ?? ''), $data['locale'], [
            'whatsapp' => (string) ($data['whatsapp'] ?? ''),
            'address' => (string) ($data['address'] ?? ''),
            'city' => (string) ($data['city'] ?? ''),
        ]);
        $user = $this->auth->user();
        if ($request->wantsJson()) {
            return Response::json([
                'ok' => true,
                'message' => __('portal.profile_saved'),
                'profile' => $this->profilePayload($user ?? [], $customer),
            ]);
        }
        flash('success', __('portal.profile_saved'));

        return $this->redirect('/account');
    }

    public function password(Request $request): Response
    {
        $data = $this->validate($request, [
            'current_password' => 'required|min:8',
            'password' => 'required|min:8|max:200',
        ]);
        if ($data['password'] !== (string) $request->input('password_confirmation')) {
            throw new \App\Core\ValidationException(['password' => __('validation.confirmed')], []);
        }
        if (!$this->auth->updatePassword((int) $this->auth->id(), $data['current_password'], $data['password'])) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => __('auth.failed')], 422);
            }
            flash('error', __('auth.failed'));

            return $this->redirect('/account');
        }
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => __('portal.password_saved')]);
        }
        flash('success', __('portal.password_saved'));

        return $this->redirect('/account');
    }

    public function support(): Response
    {
        $customer = $this->auth->customer();
        $user = $this->auth->user();

        return $this->view('customer/support', [
            'title' => __('portal.support'),
            'description' => __('portal.support_lead'),
            'robots' => 'noindex',
            'active' => 'support',
            'customer' => $customer,
            'user' => $user,
        ]);
    }

    public function sendSupport(Request $request): Response
    {
        $user = $this->auth->user();
        $customer = $this->auth->customer();
        $data = $this->validate($request, ['message' => 'required|min:10|max:4000', 'subject' => 'nullable|max:180']);
        $this->intake->contact([
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $customer['phone'] ?? $user['phone'],
            'subject' => $data['subject'] ?: 'Customer support',
            'message' => $data['message'],
        ], [
            'source' => 'support',
            'user_id' => (int) $user['id'],
            'customer_id' => (int) $customer['id'],
        ]);
        flash('success', __('portal.support_sent'));

        return $this->redirect('/support');
    }

    private function profilePayload(array $user, array $customer): array
    {
        return [
            'name' => $customer['name'] ?? $user['name'] ?? '',
            'email' => $customer['email'] ?? $user['email'] ?? '',
            'phone' => (string) ($customer['phone'] ?? ''),
            'whatsapp' => (string) ($customer['whatsapp'] ?? ''),
            'city' => (string) ($customer['city'] ?? ''),
            'address' => (string) ($customer['address'] ?? ''),
            'locale' => (string) ($user['locale'] ?? locale()),
            'initials' => initials((string) ($customer['name'] ?? '')),
            'code' => $customer === [] ? '' : customer_code($customer),
        ];
    }
}
