<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class SecurityController extends Controller
{
    public function __construct(\App\Core\Validator $validator, private AuthService $auth)
    {
        parent::__construct($validator);
    }

    public function edit(): Response
    {
        return $this->passwordForm();
    }

    public function update(Request $request): Response
    {
        if (!$this->auth->isStaff()) {
            throw new HttpException(403, 'You do not have access to the staff area.');
        }
        // Passwords are intentionally never trimmed, flashed, or repopulated in the form.
        $data = [];
        $fieldErrors = [];
        foreach (['current_password', 'password', 'password_confirmation'] as $field) {
            $value = $request->input($field);
            if (!is_string($value)) {
                $fieldErrors[$field] = __('staff_security.invalid');
            } elseif ($value === '') {
                $fieldErrors[$field] = __('staff_security.required_field');
            } elseif (mb_strlen($value) < 8 || mb_strlen($value) > 200 || str_contains($value, "\0")) {
                $fieldErrors[$field] = __('staff_security.length');
            } else {
                $data[$field] = $value;
            }
        }
        if (isset($data['password'], $data['password_confirmation']) && !hash_equals($data['password'], $data['password_confirmation'])) {
            $fieldErrors['password_confirmation'] = __('staff_security.confirmed');
        }
        if (isset($data['password']) && password_verify($data['password'], (string) ($this->auth->user()['password_hash'] ?? ''))) {
            $fieldErrors['password'] = __('staff_security.different');
        }
        if ($fieldErrors === [] && !$this->auth->updatePassword((int) $this->auth->id(), $data['current_password'], $data['password'])) {
            $fieldErrors['current_password'] = __('staff_security.current_invalid');
        }
        if ($fieldErrors !== []) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'message' => __('validation.failed'), 'errors' => $fieldErrors], 422);
            }

            return $this->passwordForm($fieldErrors, 422);
        }
        $this->auth->renewSession();
        if ($request->wantsJson()) {
            return Response::json([
                'ok' => true,
                'message' => __('staff_security.saved'),
                'csrf_token' => csrf_token(),
                'redirect' => url('/admin'),
            ]);
        }
        flash('success', __('staff_security.saved'));

        return $this->redirect('/admin');
    }

    private function passwordForm(array $fieldErrors = [], int $status = 200): Response
    {
        if (!$this->auth->isStaff()) {
            throw new HttpException(403, 'You do not have access to the staff area.');
        }

        return $this->view('admin/security/password', [
            'title' => __('staff_security.title'),
            'pageDescription' => __('staff_security.description'),
            'active' => 'password',
            'requiredChange' => $this->auth->mustChangePassword(),
            'fieldErrors' => $fieldErrors,
        ], 'layouts/admin', $status);
    }
}
