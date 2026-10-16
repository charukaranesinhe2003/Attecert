<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuthService
{
    private ?array $user = null;
    private bool $loaded = false;
    private ?array $permissions = null;
    private ?array $customer = null;
    private bool $customerLoaded = false;

    public function __construct(
        private Database $db,
        private AuditService $audit,
        private MailService $mail
    ) {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $user = $this->user();

        return $user === null ? null : (int) $user['id'];
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = (int) ($_SESSION['user_id'] ?? 0);
        if ($id <= 0) {
            return $this->user = null;
        }
        $user = $this->db->fetch(
            'SELECT * FROM users WHERE id = ? AND deleted_at IS NULL AND is_active = 1',
            [$id]
        );
        if ($user === null) {
            unset($_SESSION['user_id'], $_SESSION['_password_fingerprint']);

            return $this->user = null;
        }
        $fingerprint = $_SESSION['_password_fingerprint'] ?? '';
        if (!is_string($fingerprint) || !hash_equals(hash('sha256', (string) $user['password_hash']), $fingerprint)) {
            // Legacy sessions must sign in again; accepting missing versions would revive old credentials.
            unset($_SESSION['user_id'], $_SESSION['_password_fingerprint']);

            return $this->user = null;
        }
        $user['roles'] = array_column($this->db->fetchAll(
            'SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            [$id]
        ), 'code');

        return $this->user = $user;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if (in_array('super_admin', $user['roles'], true)) {
            return true;
        }
        if ($this->permissions === null) {
            $rows = $this->db->fetchAll(
                'SELECT DISTINCT p.code
                 FROM permissions p
                 INNER JOIN role_permissions rp ON rp.permission_id = p.id
                 INNER JOIN user_roles ur ON ur.role_id = rp.role_id
                 WHERE ur.user_id = ?',
                [(int) $user['id']]
            );
            $this->permissions = array_column($rows, 'code');
        }

        return in_array($permission, $this->permissions, true);
    }

    public function isStaff(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        foreach ($user['roles'] as $role) {
            if ($role !== 'customer') {
                return true;
            }
        }

        return false;
    }

    public function customer(): ?array
    {
        if ($this->customerLoaded) {
            return $this->customer;
        }
        $this->customerLoaded = true;
        $user = $this->user();
        if ($user === null) {
            return $this->customer = null;
        }

        return $this->customer = $this->db->fetch(
            'SELECT * FROM customers WHERE user_id = ? AND deleted_at IS NULL',
            [(int) $user['id']]
        );
    }

    public function homePath(): string
    {
        return $this->isStaff() ? ($this->mustChangePassword() ? '/admin/password' : '/admin') : '/account';
    }

    public function mustChangePassword(): bool
    {
        return $this->isStaff() && (int) ($this->user()['must_change_password'] ?? 0) === 1;
    }

    public function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));
        $recent = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND successful = 0 AND created_at > (NOW() - INTERVAL 15 MINUTE)',
            [$email, $ip]
        );
        if ($recent >= 5) {
            return ['ok' => false, 'error' => __('auth.throttled')];
        }
        $user = $this->db->fetch('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        $valid = $user !== null && password_verify($password, $user['password_hash']);
        $this->db->insert(
            'INSERT INTO login_attempts (email, ip, successful) VALUES (?, ?, ?)',
            [$email, $ip, $valid ? 1 : 0]
        );
        if (!$valid) {
            $this->audit->log($user['id'] ?? null, 'login_failed', 'user', isset($user['id']) ? (int) $user['id'] : null, ['email' => $email]);

            return ['ok' => false, 'error' => __('auth.failed')];
        }
        if ((int) $user['is_active'] !== 1) {
            return ['ok' => false, 'error' => __('auth.disabled')];
        }
        if ($user['locked_until'] !== null && strtotime((string) $user['locked_until']) > time()) {
            return ['ok' => false, 'error' => __('auth.throttled')];
        }
        if ((string) setting('email_verification', '0') === '1' && $user['email_verified_at'] === null && !$this->userIsStaffId((int) $user['id'])) {
            return ['ok' => false, 'error' => __('auth.unverified')];
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['_password_fingerprint'] = hash('sha256', (string) $user['password_hash']);
        if (!empty($user['locale'])) {
            $_SESSION['locale'] = $user['locale'];
        }
        $this->loaded = false;
        $this->db->run(
            'UPDATE users SET failed_login_count = 0, last_login_at = NOW(), last_login_ip = ? WHERE id = ?',
            [$ip, $user['id']]
        );
        $this->audit->log((int) $user['id'], 'login', 'user', (int) $user['id']);

        return ['ok' => true, 'error' => null];
    }

    public function logout(): void
    {
        $id = $this->id();
        if ($id !== null) {
            $this->audit->log($id, 'logout', 'user', $id);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public function register(string $name, string $email, string $phone, string $password, string $locale): int
    {
        $email = mb_strtolower(trim($email));
        $existing = $this->db->fetch('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing) {
            throw new \App\Core\ValidationException(['email' => __('auth.email_taken')], [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]);
        }

        return $this->db->transaction(function () use ($name, $email, $phone, $password, $locale): int {
            $verify = (string) setting('email_verification', '0') === '1';
            $userId = $this->db->insert(
                'INSERT INTO users (name, email, phone, password_hash, locale, email_verified_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$name, $email, $phone !== '' ? $phone : null, \App\Helpers\Password::hash($password), $locale, $verify ? null : date('Y-m-d H:i:s')]
            );
            $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', ['customer']);
            $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleId]);
            $this->db->insert(
                'INSERT INTO customers (user_id, name, email, phone) VALUES (?, ?, ?, ?)',
                [$userId, $name, $email, $phone !== '' ? $phone : null]
            );
            if ($verify) {
                $this->sendToken($email, 'verify', '/verify-email/', 'verify', __('mail.verify_subject'));
            }
            $this->audit->log($userId, 'register', 'user', $userId);

            return $userId;
        });
    }

    public function sendPasswordReset(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $user = $this->db->fetch('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        if ($user === null) {
            return;
        }
        $this->sendToken($email, 'reset', '/reset-password/', 'password-reset', __('mail.reset_subject'));
    }

    public function resetPassword(string $token, string $password): bool
    {
        $candidate = $this->tokenRow($token, 'reset');
        if ($candidate === null) {
            return false;
        }
        $userId = $this->db->transaction(function () use ($token, $password, $candidate): ?int {
            // Match the account-then-token locking order used by signed-in password changes.
            $user = $this->db->fetch('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL FOR UPDATE', [$candidate['email']]);
            if ($user === null) {
                return null;
            }
            $row = $this->db->fetch(
                'SELECT * FROM password_resets WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [hash('sha256', $token), 'reset']
            );
            if ($row === null) {
                return null;
            }
            $id = (int) $user['id'];
            $this->db->run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [\App\Helpers\Password::hash($password), $id]);
            $this->db->run('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL', [$row['email'], 'reset']);
            $this->audit->log($id, 'password_reset', 'user', $id);

            return $id;
        });
        if ($userId === null) {
            return false;
        }
        $this->loaded = false;

        return true;
    }

    public function verifyEmail(string $token): bool
    {
        $row = $this->tokenRow($token, 'verify');
        if ($row === null) {
            return false;
        }
        $this->db->run('UPDATE users SET email_verified_at = NOW() WHERE email = ?', [$row['email']]);
        $this->db->run('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);

        return true;
    }

    public function updateProfile(int $userId, string $name, string $phone, string $locale, array $extra = []): array
    {
        $whatsapp = $extra['whatsapp'] ?? null;
        $address = $extra['address'] ?? null;
        $city = $extra['city'] ?? null;
        $this->db->run('UPDATE users SET name = ?, phone = ?, locale = ? WHERE id = ?', [$name, $phone !== '' ? $phone : null, $locale, $userId]);
        $this->db->run(
            'UPDATE customers SET name = ?, phone = ?, whatsapp = ?, address = ?, city = ? WHERE user_id = ?',
            [$name, $phone !== '' ? $phone : null, $whatsapp !== '' ? $whatsapp : null, $address !== '' ? $address : null, $city !== '' ? $city : null, $userId]
        );
        $_SESSION['locale'] = $locale;
        $this->loaded = false;
        $this->customerLoaded = false;
        $this->customer = null;
        $this->audit->log($userId, 'profile_updated', 'user', $userId);

        return $this->customer() ?? [];
    }

    public function updatePassword(int $userId, string $current, string $next): bool
    {
        $newHash = $this->db->transaction(function () use ($userId, $current, $next): ?string {
            $user = $this->db->fetch('SELECT password_hash, email FROM users WHERE id = ? AND deleted_at IS NULL AND is_active = 1 FOR UPDATE', [$userId]);
            if ($user === null || !password_verify($current, $user['password_hash'])) {
                return null;
            }
            $hash = \App\Helpers\Password::hash($next);
            $this->db->run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [$hash, $userId]);
            $this->db->run('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL', [$user['email'], 'reset']);
            $this->audit->log($userId, 'password_changed', 'user', $userId);

            return $hash;
        });
        if ($newHash !== null) {
            $this->loaded = false;
            if ((int) ($_SESSION['user_id'] ?? 0) === $userId) {
                $_SESSION['_password_fingerprint'] = hash('sha256', $newHash);
            }
        }

        return $newHash !== null;
    }

    public function renewSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        unset($_SESSION['_intended'], $_SESSION['_errors'], $_SESSION['_old'], $_SESSION['_view_errors'], $_SESSION['_view_old']);
    }

    private function sendToken(string $email, string $purpose, string $path, string $template, string $subject): void
    {
        $token = bin2hex(random_bytes(32));
        $this->db->insert(
            'INSERT INTO password_resets (email, purpose, token_hash, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))',
            [$email, $purpose, hash('sha256', $token)]
        );
        $this->mail->send($email, $subject, $template, [
            'link' => absolute_url($path . $token),
        ], 'user', null);
    }

    private function tokenRow(string $token, string $purpose): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
             ORDER BY id DESC LIMIT 1',
            [hash('sha256', $token), $purpose]
        );
    }

    private function userIsStaffId(int $userId): bool
    {
        $count = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = ? AND r.code <> ?',
            [$userId, 'customer']
        );

        return $count > 0;
    }
}
