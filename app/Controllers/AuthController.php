<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function loginForm(): Response
    {
        return $this->view('auth/login', ['title' => __('auth.login'), 'robots' => 'noindex']);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
        ]);
        $password = $request->input('password');
        if (!is_string($password) || mb_strlen($password) < 8 || mb_strlen($password) > 200 || str_contains($password, "\0")) {
            throw new \App\Core\ValidationException(['password' => __('auth.failed')], ['email' => $data['email']]);
        }
        $result = $this->auth->attempt($data['email'], $password, $request->ip());
        if (!$result['ok']) {
            throw new \App\Core\ValidationException(['email' => $result['error']], ['email' => $data['email']]);
        }
        $target = (string) ($_SESSION['_intended'] ?? $this->auth->homePath());
        unset($_SESSION['_intended']);
        if ($this->auth->mustChangePassword()) {
            return $this->redirect('/admin/password');
        }
        if (!preg_match('#^/(?!/)[^\\\\\x00-\x20]*$#D', $target)
            || in_array($target, ['/login', '/logout', '/register', '/forgot-password'], true)) {
            $target = $this->auth->homePath();
        }

        return $this->redirect($target);
    }

    public function registerForm(): Response
    {
        return $this->view('auth/register', ['title' => __('auth.register'), 'robots' => 'noindex']);
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'email' => 'required|email|max:190',
            'phone' => 'nullable|max:40',
            'password' => 'required|min:8|max:200',
        ]);
        if ($data['password'] !== (string) $request->input('password_confirmation')) {
            throw new \App\Core\ValidationException(['password' => __('validation.confirmed')], $request->all());
        }
        $this->auth->register($data['name'], $data['email'], (string) ($data['phone'] ?? ''), $data['password'], locale());
        flash('success', (string) setting('email_verification', '0') === '1' ? __('auth.verify_sent') : __('auth.registered'));

        return $this->redirect('/login');
    }

    public function forgotForm(): Response
    {
        return $this->view('auth/forgot', ['title' => __('auth.forgot'), 'robots' => 'noindex']);
    }

    public function forgot(Request $request): Response
    {
        $data = $this->validate($request, ['email' => 'required|email']);
        $this->auth->sendPasswordReset($data['email']);
        flash('success', __('auth.reset_sent'));

        return $this->redirect('/forgot-password');
    }

    public function resetForm(Request $request, string $token): Response
    {
        return $this->view('auth/reset', ['title' => __('auth.reset'), 'robots' => 'noindex', 'token' => $token]);
    }

    public function reset(Request $request, string $token): Response
    {
        $data = $this->validate($request, ['password' => 'required|min:8|max:200']);
        if ($data['password'] !== (string) $request->input('password_confirmation')) {
            throw new \App\Core\ValidationException(['password' => __('validation.confirmed')], []);
        }
        if (!$this->auth->resetPassword($token, $data['password'])) {
            flash('error', __('auth.reset_invalid'));

            return $this->redirect('/forgot-password');
        }
        flash('success', __('auth.reset_done'));

        return $this->redirect('/login');
    }

    public function verify(Request $request, string $token): Response
    {
        $verified = $this->auth->verifyEmail($token);
        flash($verified ? 'success' : 'error', $verified ? __('auth.verified') : __('auth.reset_invalid'));

        return $this->redirect('/login');
    }

    public function logout(): Response
    {
        $this->auth->logout();

        return Response::redirect(url('/'));
    }
}
