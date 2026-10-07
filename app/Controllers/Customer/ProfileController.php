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
        return $this->view('customer/profile', [
            'title' => __('portal.profile'),
            'robots' => 'noindex',
            'active' => 'profile',
            'user' => $this->auth->user(),
        ], 'layouts/customer');
    }

    public function update(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'phone' => 'nullable|max:40',
            'locale' => 'required|in:en,si,ta',
        ]);
        $this->auth->updateProfile((int) $this->auth->id(), $data['name'], (string) ($data['phone'] ?? ''), $data['locale']);
        flash('success', __('portal.profile_saved'));

        return $this->redirect('/portal/profile');
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
            flash('error', __('auth.failed'));

            return $this->redirect('/portal/profile');
        }
        flash('success', __('portal.password_saved'));

        return $this->redirect('/portal/profile');
    }

    public function support(): Response
    {
        return $this->view('customer/support', [
            'title' => __('portal.support'),
            'robots' => 'noindex',
            'active' => 'support',
        ], 'layouts/customer');
    }

    public function sendSupport(Request $request): Response
    {
        $user = $this->auth->user();
        $data = $this->validate($request, ['message' => 'required|min:10|max:4000', 'subject' => 'nullable|max:180']);
        $this->intake->contact([
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'subject' => $data['subject'] ?: 'Customer support',
            'message' => $data['message'],
        ]);
        flash('success', __('contact.sent'));

        return $this->redirect('/portal/support');
    }
}
